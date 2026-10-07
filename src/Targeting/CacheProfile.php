<?php
/**
 * Cache sensitivity of a targeting definition.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Targeting;

defined( 'ABSPATH' ) || exit;

/**
 * The set of request dimensions a definition depends on, with the strongest cache hazard among them.
 * Classification only: M3 implements no cache bypass, no AJAX and no client-side targeting.
 */
final class CacheProfile {

	/**
	 * Dimension names (for example "device", "taxonomy:category", "query_param:utm_source"), sorted and unique.
	 *
	 * @var list<string>
	 */
	public readonly array $dimensions;

	/**
	 * Strongest sensitivity among the dimensions (PageStable when there are none).
	 *
	 * @var CacheSensitivity
	 */
	public readonly CacheSensitivity $sensitivity;

	/**
	 * Constructor.
	 *
	 * @param array<string, CacheSensitivity> $by_dimension Sensitivity per dimension.
	 */
	private function __construct( array $by_dimension ) {
		ksort( $by_dimension );
		$strongest = CacheSensitivity::PageStable;
		foreach ( $by_dimension as $sensitivity ) {
			if ( $sensitivity->rank() > $strongest->rank() ) {
				$strongest = $sensitivity;
			}
		}
		$this->dimensions  = array_map( 'strval', array_keys( $by_dimension ) );
		$this->sensitivity = $strongest;
	}

	/**
	 * A profile with no dimensions: page stable.
	 */
	public static function none(): self {
		return new self( array() );
	}

	/**
	 * Builds a profile from rules.
	 *
	 * @param array<Rule> $rules Rules.
	 */
	public static function fromRules( array $rules ): self {
		$by = array();
		foreach ( $rules as $rule ) {
			$by[ $rule->dimension() ] = $rule->sensitivity();
		}
		return new self( $by );
	}

	/**
	 * Union of several profiles.
	 *
	 * @param CacheProfile ...$profiles Profiles.
	 */
	public static function merge( CacheProfile ...$profiles ): self {
		$by = array();
		foreach ( $profiles as $profile ) {
			foreach ( $profile->dimensions as $dimension ) {
				$by[ $dimension ] = self::sensitivityOf( $dimension );
			}
		}
		return new self( $by );
	}

	/**
	 * Whether every dimension is page stable (a cached page's outcome is the same for every visitor of the URL).
	 */
	public function isPageStable(): bool {
		return CacheSensitivity::PageStable === $this->sensitivity;
	}

	/**
	 * Whether the outcome can differ between visitors or requests for the same cached URL.
	 */
	public function requiresVariation(): bool {
		return ! $this->isPageStable();
	}

	/**
	 * Sensitivity of a dimension name.
	 *
	 * @param string $dimension Dimension name.
	 */
	private static function sensitivityOf( string $dimension ): CacheSensitivity {
		if ( in_array( $dimension, array( 'user_state', 'user_role' ), true ) ) {
			return CacheSensitivity::VisitorVariant;
		}
		if ( 'device' === $dimension || 'referrer' === $dimension || str_starts_with( $dimension, 'query_param:' ) ) {
			return CacheSensitivity::RequestVariant;
		}
		return CacheSensitivity::PageStable;
	}
}
