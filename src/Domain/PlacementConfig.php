<?php
/**
 * Typed placement configuration.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain;

use JsonException;
use RevGuardPro\Domain\Exception\InvalidEntityException;

defined( 'ABSPATH' ) || exit;

/**
 * The configuration of one placement type. Not a free-form blob: each type has a fixed set of integer
 * keys with ranges, unknown keys are rejected, and defaults are filled so the stored form is complete.
 *
 * Insertion behaviour is M4; this class only guarantees that what is stored is well-formed.
 */
final class PlacementConfig {

	private const JSON_DEPTH = 8;

	/**
	 * Validated values, keyed by name.
	 *
	 * @var array<string, int>
	 */
	private array $values;

	/**
	 * Constructor. Use of().
	 *
	 * @param PlacementType $type   Placement type.
	 * @param array<mixed>  $values Raw values.
	 * @throws InvalidEntityException When a key is unknown or a value is invalid.
	 */
	private function __construct( public readonly PlacementType $type, array $values ) {
		$schema = self::schema( $type );
		foreach ( array_keys( $values ) as $key ) {
			if ( ! is_string( $key ) || ! isset( $schema[ $key ] ) ) {
				throw new InvalidEntityException( 'config', InvalidEntityException::REASON_UNKNOWN_KEY );
			}
		}

		$clean = array();
		foreach ( $schema as $key => $rule ) {
			list( $min, $max, $default ) = $rule;
			if ( ! array_key_exists( $key, $values ) ) {
				if ( null === $default ) {
					throw new InvalidEntityException( 'config', InvalidEntityException::REASON_MISSING );
				}
				$clean[ $key ] = $default;
				continue;
			}
			if ( ! is_int( $values[ $key ] ) ) {
				throw new InvalidEntityException( 'config', InvalidEntityException::REASON_INVALID_VALUE );
			}
			$clean[ $key ] = Rules::range( $values[ $key ], $min, $max, 'config' );
		}
		$this->values = $clean;
	}

	/**
	 * Builds and validates a configuration.
	 *
	 * @param PlacementType $type   Placement type.
	 * @param array<mixed>  $values Values; omitted optional keys take their default.
	 */
	public static function of( PlacementType $type, array $values = array() ): self {
		return new self( $type, $values );
	}

	/**
	 * Decodes and validates stored JSON. Never uses unserialize.
	 *
	 * @param PlacementType $type Placement type.
	 * @param string        $json Stored JSON object.
	 * @throws InvalidEntityException When the JSON is malformed or invalid for the type.
	 */
	public static function fromJson( PlacementType $type, string $json ): self {
		try {
			$decoded = json_decode( $json, true, self::JSON_DEPTH, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new InvalidEntityException( 'config', InvalidEntityException::REASON_INVALID_VALUE );
		}
		if ( ! is_array( $decoded ) ) {
			throw new InvalidEntityException( 'config', InvalidEntityException::REASON_INVALID_VALUE );
		}
		return new self( $type, $decoded );
	}

	/**
	 * A configuration value.
	 *
	 * @param string $key Key defined for the type.
	 * @throws InvalidEntityException When the key is not defined for this type.
	 */
	public function value( string $key ): int {
		if ( ! isset( $this->values[ $key ] ) ) {
			throw new InvalidEntityException( 'config', InvalidEntityException::REASON_UNKNOWN_KEY );
		}
		return $this->values[ $key ];
	}

	/**
	 * All values, defaults included.
	 *
	 * @return array<string, int>
	 */
	public function toArray(): array {
		return $this->values;
	}

	/**
	 * Canonical JSON for storage (an object, even when empty).
	 */
	public function toJson(): string {
		// Integer-only array: encoding cannot fail, and the domain layer deliberately stays WordPress-free.
		return array() === $this->values ? '{}' : json_encode( $this->values, JSON_THROW_ON_ERROR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}

	/**
	 * Allowed keys per type as [min, max, default]; a null default marks a required key.
	 *
	 * @param PlacementType $type Placement type.
	 * @return array<string, array{int, int, int|null}>
	 */
	private static function schema( PlacementType $type ): array {
		return match ( $type ) {
			PlacementType::AfterParagraph => array( 'paragraph' => array( 1, 100, null ) ),
			PlacementType::AfterHeading   => array(
				'heading_level' => array( 2, 6, 2 ),
				'occurrence'    => array( 1, 20, 1 ),
			),
			PlacementType::Percentage     => array( 'percent' => array( 1, 99, null ) ),
			PlacementType::BeforeContent,
			PlacementType::AfterContent,
			PlacementType::Manual         => array(),
		};
	}
}
