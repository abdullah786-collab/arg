<?php
/**
 * Typed readers for untrusted request values.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Support;

use BackedEnum;

defined( 'ABSPATH' ) || exit;

/**
 * Pure and strict: a value is accepted only in its exact expected shape, otherwise the reader returns the neutral value
 * (null, '', empty list). Nothing here trusts a type, a length or a range.
 */
final class Input {

	public const MAX_ID   = 2147483647;
	public const MAX_BULK = 50;

	/**
	 * A positive id from an int or a plain digit string.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function id( mixed $value ): ?int {
		if ( is_int( $value ) ) {
			return $value >= 1 && $value <= self::MAX_ID ? $value : null;
		}
		if ( is_string( $value ) && 1 === preg_match( '/^[0-9]{1,10}$/D', $value ) ) {
			$id = (int) $value;
			return $id >= 1 && $id <= self::MAX_ID ? $id : null;
		}
		return null;
	}

	/**
	 * An integer in a range from an int or an optionally signed digit string; null when absent or out of range.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min   Minimum.
	 * @param int   $max   Maximum.
	 */
	public static function int( mixed $value, int $min, int $max ): ?int {
		if ( is_string( $value ) && 1 === preg_match( '/^-?[0-9]{1,10}$/D', $value ) ) {
			$value = (int) $value;
		}
		if ( ! is_int( $value ) ) {
			return null;
		}
		return $value >= $min && $value <= $max ? $value : null;
	}

	/**
	 * Single-line text: strings only, control characters removed, trimmed, capped at $max characters.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Maximum characters.
	 */
	public static function text( mixed $value, int $max ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$clean = preg_replace( '/[\x00-\x1F\x7F]/u', '', $value );
		if ( ! is_string( $clean ) ) {
			return '';
		}
		$clean = trim( $clean );
		return mb_strlen( $clean, 'UTF-8' ) > $max ? mb_substr( $clean, 0, $max, 'UTF-8' ) : $clean;
	}

	/**
	 * Multi-line text kept as typed (newlines and tabs allowed, other control characters removed). Used for the creative,
	 * which is handed to the storage policy unchanged otherwise.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function block( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$clean = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value );
		return is_string( $clean ) ? $clean : '';
	}

	/**
	 * A backed-enum case from its exact value, or null.
	 *
	 * @template T of BackedEnum
	 * @phpstan-param class-string<T> $enum_class
	 * @phpstan-return T|null
	 *
	 * @param mixed  $value      Raw value.
	 * @param string $enum_class Enum class name.
	 */
	public static function enumCase( mixed $value, string $enum_class ): ?BackedEnum {
		if ( ! is_string( $value ) && ! is_int( $value ) ) {
			return null;
		}
		return $enum_class::tryFrom( $value );
	}

	/**
	 * A bounded, de-duplicated list of ids from an array of values; anything else is ignored.
	 *
	 * @param mixed $values Raw value.
	 * @return array<int,int>
	 */
	public static function ids( mixed $values ): array {
		if ( ! is_array( $values ) ) {
			return array();
		}
		$ids = array();
		foreach ( $values as $value ) {
			$id = self::id( $value );
			if ( null !== $id ) {
				$ids[ $id ] = $id;
			}
			if ( count( $ids ) >= self::MAX_BULK ) {
				break;
			}
		}
		return array_values( $ids );
	}
}
