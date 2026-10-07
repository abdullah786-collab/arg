<?php
/**
 * A single typed, bounded targeting rule.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Targeting;

defined( 'ABSPATH' ) || exit;

use RevGuardPro\Domain\Exception\InvalidEntityException;

/**
 * One rule: a closed type, an operator allowed for that type, normalized bounded values, and a negate flag.
 * Built only through the typed factories or fromArray(); every path validates and normalizes. Never a free-form blob.
 */
final class Rule {

	public const MAX_VALUES = 50;

	/**
	 * Normalized, de-duplicated values in the order given.
	 *
	 * @var list<string>
	 */
	public readonly array $values;

	/**
	 * Constructor. Use the factories.
	 *
	 * @param RuleType     $type     Rule type.
	 * @param string|null  $key      Taxonomy slug or query parameter name, when the type has one.
	 * @param Operator     $operator Operator.
	 * @param array<mixed> $values  Raw values.
	 * @param bool         $negate   Flip the result.
	 * @throws InvalidEntityException When anything is invalid.
	 */
	private function __construct(
		public readonly RuleType $type,
		public readonly ?string $key,
		public readonly Operator $operator,
		array $values,
		public readonly bool $negate
	) {
		if ( ! in_array( $operator, self::operatorsFor( $type ), true ) ) {
			throw new InvalidEntityException( 'rule_operator', InvalidEntityException::REASON_INVALID_VALUE );
		}
		$needs_key = in_array( $type, array( RuleType::Taxonomy, RuleType::QueryParam ), true );
		if ( $needs_key && ( null === $key || ! self::validKey( $type, $key ) ) ) {
			throw new InvalidEntityException( 'rule_key', InvalidEntityException::REASON_INVALID_VALUE );
		}
		if ( ! $needs_key && null !== $key ) {
			throw new InvalidEntityException( 'rule_key', InvalidEntityException::REASON_UNKNOWN_KEY );
		}

		$takes_values = ! in_array( $operator, array( Operator::Exists, Operator::Direct, Operator::Internal ), true );
		if ( ! $takes_values ) {
			if ( array() !== $values ) {
				throw new InvalidEntityException( 'rule_values', InvalidEntityException::REASON_INVALID_VALUE );
			}
			$this->values = array();
			return;
		}
		if ( array() === $values ) {
			throw new InvalidEntityException( 'rule_values', InvalidEntityException::REASON_EMPTY );
		}
		if ( count( $values ) > self::MAX_VALUES ) {
			throw new InvalidEntityException( 'rule_values', InvalidEntityException::REASON_TOO_MANY );
		}

		$clean = array();
		foreach ( $values as $value ) {
			if ( ! is_string( $value ) ) {
				throw new InvalidEntityException( 'rule_values', InvalidEntityException::REASON_INVALID_VALUE );
			}
			$normalized = self::normalizeValue( $type, $operator, $value );
			if ( null === $normalized ) {
				throw new InvalidEntityException( 'rule_values', InvalidEntityException::REASON_INVALID_VALUE );
			}
			$clean[ $normalized ] = $normalized;
		}
		$this->values = array_values( $clean );
	}

	/**
	 * Device is one of desktop, tablet, mobile.
	 *
	 * @param array<mixed> $devices Device names.
	 * @param bool         $negate  Flip the result.
	 */
	public static function device( array $devices, bool $negate = false ): self {
		return new self( RuleType::Device, null, Operator::In, $devices, $negate );
	}

	/**
	 * The current post type is one of the given slugs.
	 *
	 * @param array<mixed> $post_types Post type slugs.
	 * @param bool         $negate     Flip the result.
	 */
	public static function postType( array $post_types, bool $negate = false ): self {
		return new self( RuleType::PostType, null, Operator::In, $post_types, $negate );
	}

	/**
	 * The current page has one of the given terms in a taxonomy.
	 *
	 * @param string       $taxonomy Taxonomy slug.
	 * @param array<mixed> $terms    Term slugs.
	 * @param bool         $negate   Flip the result.
	 */
	public static function taxonomy( string $taxonomy, array $terms, bool $negate = false ): self {
		return new self( RuleType::Taxonomy, $taxonomy, Operator::In, $terms, $negate );
	}

	/**
	 * Visitor state is logged_in or logged_out.
	 *
	 * @param array<mixed> $states States.
	 * @param bool         $negate Flip the result.
	 */
	public static function userState( array $states, bool $negate = false ): self {
		return new self( RuleType::UserState, null, Operator::In, $states, $negate );
	}

	/**
	 * The visitor has one of the given roles on this site.
	 *
	 * @param array<mixed> $roles  Role slugs.
	 * @param bool         $negate Flip the result.
	 */
	public static function userRole( array $roles, bool $negate = false ): self {
		return new self( RuleType::UserRole, null, Operator::In, $roles, $negate );
	}

	/**
	 * The normalized request path matches.
	 *
	 * @param Operator     $operator Equals, StartsWith (segment aware), EndsWith or Contains.
	 * @param array<mixed> $values   Paths or substrings.
	 * @param bool         $negate   Flip the result.
	 */
	public static function url( Operator $operator, array $values, bool $negate = false ): self {
		return new self( RuleType::Url, null, $operator, $values, $negate );
	}

	/**
	 * The referrer host matches.
	 *
	 * @param Operator     $operator HostIn (with domains), Direct or Internal (without values).
	 * @param array<mixed> $domains  Domains for HostIn.
	 * @param bool         $negate   Flip the result.
	 */
	public static function referrer( Operator $operator, array $domains = array(), bool $negate = false ): self {
		return new self( RuleType::Referrer, null, $operator, $domains, $negate );
	}

	/**
	 * A query parameter exists or equals one of the values.
	 *
	 * @param string       $name     Parameter name.
	 * @param Operator     $operator Exists (without values) or Equals (with values).
	 * @param array<mixed> $values   Values for Equals.
	 * @param bool         $negate   Flip the result.
	 */
	public static function queryParam( string $name, Operator $operator, array $values = array(), bool $negate = false ): self {
		return new self( RuleType::QueryParam, $name, $operator, $values, $negate );
	}

	/**
	 * Rebuilds a rule from its stored array form. Strict: unknown keys are rejected.
	 *
	 * @param array<mixed> $data Stored rule.
	 * @throws InvalidEntityException When the shape or any value is invalid.
	 */
	public static function fromArray( array $data ): self {
		$allowed = array( 'type', 'key', 'op', 'values', 'negate' );
		if ( array() !== array_diff( array_keys( $data ), $allowed ) ) {
			throw new InvalidEntityException( 'rule', InvalidEntityException::REASON_UNKNOWN_KEY );
		}
		$type     = isset( $data['type'] ) && is_string( $data['type'] ) ? RuleType::tryFrom( $data['type'] ) : null;
		$operator = isset( $data['op'] ) && is_string( $data['op'] ) ? Operator::tryFrom( $data['op'] ) : null;
		$values   = $data['values'] ?? array();
		$key      = $data['key'] ?? null;
		$negate   = $data['negate'] ?? false;
		if ( null === $type || null === $operator || ! is_array( $values ) || ! is_bool( $negate ) || ( null !== $key && ! is_string( $key ) ) ) {
			throw new InvalidEntityException( 'rule', InvalidEntityException::REASON_INVALID_VALUE );
		}
		return new self( $type, $key, $operator, array_values( $values ), $negate );
	}

	/**
	 * Canonical array form, with a fixed key order.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		$out = array( 'type' => $this->type->value );
		if ( null !== $this->key ) {
			$out['key'] = $this->key;
		}
		$out['op']     = $this->operator->value;
		$out['values'] = $this->values;
		$out['negate'] = $this->negate;
		return $out;
	}

	/**
	 * The cache dimension this rule depends on.
	 */
	public function dimension(): string {
		return match ( $this->type ) {
			RuleType::Taxonomy   => 'taxonomy:' . $this->key,
			RuleType::QueryParam => 'query_param:' . $this->key,
			default              => $this->type->value,
		};
	}

	/**
	 * How this rule behaves under full-page caching.
	 */
	public function sensitivity(): CacheSensitivity {
		return match ( $this->type ) {
			RuleType::PostType, RuleType::Taxonomy, RuleType::Url => CacheSensitivity::PageStable,
			RuleType::UserState, RuleType::UserRole               => CacheSensitivity::VisitorVariant,
			RuleType::Device, RuleType::Referrer, RuleType::QueryParam => CacheSensitivity::RequestVariant,
		};
	}

	/**
	 * Operators a rule type accepts.
	 *
	 * @param RuleType $type Rule type.
	 * @return list<Operator>
	 */
	private static function operatorsFor( RuleType $type ): array {
		return match ( $type ) {
			RuleType::Url        => array( Operator::Equals, Operator::StartsWith, Operator::EndsWith, Operator::Contains ),
			RuleType::Referrer   => array( Operator::HostIn, Operator::Direct, Operator::Internal ),
			RuleType::QueryParam => array( Operator::Exists, Operator::Equals ),
			default              => array( Operator::In ),
		};
	}

	/**
	 * Validates a taxonomy slug or query parameter name.
	 *
	 * @param RuleType $type Rule type.
	 * @param string   $key  Candidate key.
	 */
	private static function validKey( RuleType $type, string $key ): bool {
		return RuleType::Taxonomy === $type
			? 1 === preg_match( '/^[a-z0-9_-]{1,32}$/D', $key )
			: 1 === preg_match( '/^[A-Za-z0-9_-]{1,40}$/D', $key );
	}

	/**
	 * Normalizes one value for the type and operator, or null when invalid.
	 *
	 * @param RuleType $type     Rule type.
	 * @param Operator $operator Operator.
	 * @param string   $value    Raw value.
	 */
	private static function normalizeValue( RuleType $type, Operator $operator, string $value ): ?string {
		return match ( $type ) {
			RuleType::Device     => DeviceType::tryFrom( $value )?->value,
			RuleType::PostType   => ( 1 === preg_match( '/^[a-z0-9_-]{1,20}$/D', $value ) ? $value : null ),
			RuleType::Taxonomy   => ( 1 === preg_match( '/^[a-z0-9%._-]{1,200}$/D', $value ) ? $value : null ),
			RuleType::UserState  => ( in_array( $value, array( 'logged_in', 'logged_out' ), true ) ? $value : null ),
			RuleType::UserRole   => ( 1 === preg_match( '/^[a-z0-9_-]{1,64}$/D', $value ) ? $value : null ),
			RuleType::Url        => ( in_array( $operator, array( Operator::Equals, Operator::StartsWith ), true ) ? UrlPath::normalizePathValue( $value ) : UrlPath::normalizeSubstringValue( $value ) ),
			RuleType::Referrer   => self::normalizeDomain( $value ),
			RuleType::QueryParam => self::normalizeParamValue( $value ),
		};
	}

	/**
	 * A lowercase domain name made of valid labels.
	 *
	 * @param string $value Candidate.
	 */
	private static function normalizeDomain( string $value ): ?string {
		if ( strlen( $value ) > 253 ) {
			return null;
		}
		$value = strtolower( $value );
		return 1 === preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $value ) ? $value : null;
	}

	/**
	 * A query parameter value: 1 to 100 characters, valid UTF-8, no control characters.
	 *
	 * @param string $value Candidate.
	 */
	private static function normalizeParamValue( string $value ): ?string {
		if ( '' === $value || mb_strlen( $value, 'UTF-8' ) > 100 || ! mb_check_encoding( $value, 'UTF-8' ) || 1 === preg_match( '/[\x00-\x1F\x7F]/', $value ) ) {
			return null;
		}
		return $value;
	}
}
