<?php
/**
 * A validated, bounded targeting definition.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Targeting;

defined( 'ABSPATH' ) || exit;

use JsonException;
use RevGuardPro\Domain\Exception\InvalidEntityException;

/**
 * A mode plus up to 20 rules. No rules means unrestricted. A *corrupt* definition (unreadable stored data) is a
 * distinct state that matches nothing and can never be stored (fail closed; see docs/adr/003-targeting-engine.md).
 */
final class TargetingDefinition {

	public const VERSION        = 1;
	public const MAX_RULES      = 20;
	public const MAX_JSON_BYTES = 16384;
	private const JSON_DEPTH    = 6;

	/**
	 * Rules in order.
	 *
	 * @var list<Rule>
	 */
	public readonly array $rules;

	/**
	 * Constructor. Use none(), all(), any(), fromArray() or fromStored().
	 *
	 * @param Mode         $mode    Combination mode.
	 * @param array<mixed> $rules   Rules.
	 * @param bool         $corrupt Whether this stands for unreadable stored data.
	 * @throws InvalidEntityException When there are too many rules or an element is not a Rule.
	 */
	private function __construct( public readonly Mode $mode, array $rules, private readonly bool $corrupt = false ) {
		if ( count( $rules ) > self::MAX_RULES ) {
			throw new InvalidEntityException( 'targeting', InvalidEntityException::REASON_TOO_MANY );
		}
		$list = array();
		foreach ( $rules as $rule ) {
			if ( ! $rule instanceof Rule ) {
				throw new InvalidEntityException( 'targeting', InvalidEntityException::REASON_INVALID_VALUE );
			}
			$list[] = $rule;
		}
		$this->rules = $list;
	}

	/**
	 * Unrestricted: matches every request.
	 */
	public static function none(): self {
		return new self( Mode::All, array() );
	}

	/**
	 * Every rule must hold.
	 *
	 * @param array<mixed> $rules Rules.
	 */
	public static function all( array $rules ): self {
		return new self( Mode::All, $rules );
	}

	/**
	 * At least one rule must hold.
	 *
	 * @param array<mixed> $rules Rules.
	 */
	public static function any( array $rules ): self {
		return new self( Mode::Any, $rules );
	}

	/**
	 * Stands for unreadable stored targeting: matches nothing and cannot be stored.
	 */
	public static function corrupt(): self {
		return new self( Mode::All, array(), true );
	}

	/**
	 * Strictly rebuilds a definition from its stored array form.
	 *
	 * @param array<mixed> $data Stored form: v, mode, rules.
	 * @throws InvalidEntityException When anything is not exactly as specified.
	 */
	public static function fromArray( array $data ): self {
		if ( array() !== array_diff( array_keys( $data ), array( 'v', 'mode', 'rules' ) ) ) {
			throw new InvalidEntityException( 'targeting', InvalidEntityException::REASON_UNKNOWN_KEY );
		}
		if ( ( $data['v'] ?? null ) !== self::VERSION ) {
			throw new InvalidEntityException( 'targeting', InvalidEntityException::REASON_INVALID_VALUE );
		}
		$mode  = isset( $data['mode'] ) && is_string( $data['mode'] ) ? Mode::tryFrom( $data['mode'] ) : null;
		$rules = $data['rules'] ?? null;
		if ( null === $mode || ! is_array( $rules ) || ! array_is_list( $rules ) ) {
			throw new InvalidEntityException( 'targeting', InvalidEntityException::REASON_INVALID_VALUE );
		}
		$built = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				throw new InvalidEntityException( 'targeting', InvalidEntityException::REASON_INVALID_VALUE );
			}
			$built[] = Rule::fromArray( $rule );
		}
		return new self( $mode, $built );
	}

	/**
	 * Reads stored targeting. Never throws: null or empty means unrestricted, anything unreadable is corrupt.
	 *
	 * @param string|null $stored Stored JSON or null.
	 */
	public static function fromStored( ?string $stored ): self {
		if ( null === $stored || '' === $stored ) {
			return self::none();
		}
		if ( strlen( $stored ) > self::MAX_JSON_BYTES ) {
			return self::corrupt();
		}
		try {
			$decoded = json_decode( $stored, true, self::JSON_DEPTH, JSON_THROW_ON_ERROR );
			if ( ! is_array( $decoded ) ) {
				return self::corrupt();
			}
			return self::fromArray( $decoded );
		} catch ( JsonException | InvalidEntityException ) {
			return self::corrupt();
		}
	}

	/**
	 * Whether the definition has no rules (and is not corrupt).
	 */
	public function isUnrestricted(): bool {
		return ! $this->corrupt && array() === $this->rules;
	}

	/**
	 * Whether this stands for unreadable stored data.
	 */
	public function isCorrupt(): bool {
		return $this->corrupt;
	}

	/**
	 * Throws when the definition may not be stored (corrupt).
	 *
	 * @throws InvalidEntityException When corrupt.
	 */
	public function assertStorable(): void {
		if ( $this->corrupt ) {
			throw new InvalidEntityException( 'targeting', InvalidEntityException::REASON_INVALID_VALUE );
		}
	}

	/**
	 * Canonical JSON for storage, or null for an unrestricted definition.
	 *
	 * @throws InvalidEntityException When corrupt or too large.
	 */
	public function toJson(): ?string {
		$this->assertStorable();
		if ( array() === $this->rules ) {
			return null;
		}
		$json = json_encode( // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure layer: no WordPress functions; values are validated strings and bools.
			array(
				'v'     => self::VERSION,
				'mode'  => $this->mode->value,
				'rules' => array_map( static fn( Rule $rule ): array => $rule->toArray(), $this->rules ),
			),
			JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
		if ( strlen( $json ) > self::MAX_JSON_BYTES ) {
			throw new InvalidEntityException( 'targeting', InvalidEntityException::REASON_TOO_LONG );
		}
		return $json;
	}

	/**
	 * What request data the rules need.
	 */
	public function requirements(): TargetingRequirements {
		return TargetingRequirements::fromRules( $this->rules );
	}

	/**
	 * Cache sensitivity of the rules.
	 */
	public function cacheProfile(): CacheProfile {
		return CacheProfile::fromRules( $this->rules );
	}
}
