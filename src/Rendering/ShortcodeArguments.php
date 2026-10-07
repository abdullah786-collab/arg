<?php
/**
 * Parsed and validated shortcode attributes.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rendering;

use RevGuardPro\Domain\TargetType;

defined( 'ABSPATH' ) || exit;

/**
 * Pure value object produced from a shortcode's attribute array. Null from fromAtts() means the
 * attributes are invalid (both or neither of ad/group given, or a non-positive or non-integer value).
 */
final class ShortcodeArguments {

	/**
	 * Constructor.
	 *
	 * @param TargetType $type  Target type (Ad or Group).
	 * @param int        $id    Target id (>= 1).
	 * @param string     $class Extra CSS class tokens (space-separated, already validated).
	 */
	public function __construct(
		public readonly TargetType $type,
		public readonly int $id,
		public readonly string $class // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.classFound -- Public property name of a value object.
	) {
	}

	/**
	 * Builds a ShortcodeArguments from a shortcode attribute array, or returns null when invalid.
	 * Reads only the keys "ad", "group", and "class"; everything else is ignored.
	 * Exactly one of "ad"/"group" must be a non-empty string matching ^[0-9]{1,10}$ with integer value 1..2147483647.
	 * "class" tokens are split on whitespace; up to 3 matching ^[A-Za-z0-9_-]{1,40}$ are kept.
	 *
	 * @param array<mixed> $atts Attribute array (already passed through shortcode_atts).
	 */
	public static function fromAtts( array $atts ): ?self {
		$raw_ad    = $atts['ad'] ?? '';
		$raw_group = $atts['group'] ?? '';
		$raw_class = $atts['class'] ?? '';

		// Values must be strings; arrays or other types are rejected.
		if ( ! is_string( $raw_ad ) || ! is_string( $raw_group ) ) {
			return null;
		}

		$has_ad    = '' !== $raw_ad;
		$has_group = '' !== $raw_group;

		// Exactly one of ad/group must be set.
		if ( $has_ad === $has_group ) {
			return null;
		}

		if ( $has_ad ) {
			$id = self::parseId( $raw_ad );
			if ( null === $id ) {
				return null;
			}
			$type = TargetType::Ad;
		} else {
			$id = self::parseId( $raw_group );
			if ( null === $id ) {
				return null;
			}
			$type = TargetType::Group;
		}

		$class = self::parseClass( $raw_class );

		return new self( $type, $id, $class );
	}

	/**
	 * Validates and parses an id string. Returns the integer value or null when invalid.
	 * Accepts only strings matching ^[0-9]{1,10}$ (trimmed) with value in 1..2147483647.
	 *
	 * @param string $raw Raw value from the attribute.
	 */
	private static function parseId( string $raw ): ?int {
		$trimmed = trim( $raw );
		if ( 1 !== preg_match( '/^[0-9]{1,10}$/D', $trimmed ) ) {
			return null;
		}
		$value = (int) $trimmed;
		if ( $value < 1 || $value > 2147483647 ) {
			return null;
		}
		return $value;
	}

	/**
	 * Parses the class attribute. Splits on whitespace, keeps up to 3 tokens matching ^[A-Za-z0-9_-]{1,40}$;
	 * invalid tokens are silently dropped. Returns a space-joined string (empty when none survive).
	 *
	 * @param mixed $raw Raw class value (any type; non-string returns '').
	 */
	private static function parseClass( mixed $raw ): string {
		if ( ! is_string( $raw ) || '' === $raw ) {
			return '';
		}
		$parts  = preg_split( '/\s+/', trim( $raw ) );
		$tokens = array();
		if ( false !== $parts ) {
			foreach ( $parts as $token ) {
				if ( '' === $token ) {
					continue;
				}
				if ( 1 === preg_match( '/^[A-Za-z0-9_-]{1,40}$/D', $token ) ) {
					$tokens[] = $token;
					if ( 3 === count( $tokens ) ) {
						break;
					}
				}
			}
		}
		return implode( ' ', $tokens );
	}
}
