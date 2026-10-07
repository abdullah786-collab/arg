<?php
/**
 * Typed access to raw database rows.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Persistence\Mapper;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use RevGuardPro\Domain\EntityKind;
use RevGuardPro\Domain\Exception\CorruptRecordException;

defined( 'ABSPATH' ) || exit;

/**
 * Reads columns from `$wpdb` rows (all values arrive as strings or null) and raises
 * CorruptRecordException when a column is missing or malformed.
 */
final class Row {

	/**
	 * Required string column.
	 *
	 * @param array<string, mixed> $row  Row.
	 * @param string               $key  Column.
	 * @param EntityKind           $kind Entity kind, for errors.
	 * @throws CorruptRecordException When the column is missing or not a string.
	 */
	public static function string( array $row, string $key, EntityKind $kind ): string {
		if ( ! isset( $row[ $key ] ) || ! is_string( $row[ $key ] ) ) {
			throw new CorruptRecordException( $kind, self::idOf( $row ), 'column_' . $key );
		}
		return $row[ $key ];
	}

	/**
	 * Required integer column.
	 *
	 * @param array<string, mixed> $row  Row.
	 * @param string               $key  Column.
	 * @param EntityKind           $kind Entity kind, for errors.
	 * @throws CorruptRecordException When the column is missing or not numeric.
	 */
	public static function int( array $row, string $key, EntityKind $kind ): int {
		if ( ! isset( $row[ $key ] ) || ! is_numeric( $row[ $key ] ) ) {
			throw new CorruptRecordException( $kind, self::idOf( $row ), 'column_' . $key );
		}
		return (int) $row[ $key ];
	}

	/**
	 * Nullable integer column.
	 *
	 * @param array<string, mixed> $row  Row.
	 * @param string               $key  Column.
	 * @param EntityKind           $kind Entity kind, for errors.
	 */
	public static function nullableInt( array $row, string $key, EntityKind $kind ): ?int {
		if ( ! array_key_exists( $key, $row ) || null === $row[ $key ] ) {
			return null;
		}
		return self::int( $row, $key, $kind );
	}

	/**
	 * Nullable string column.
	 *
	 * @param array<string, mixed> $row  Row.
	 * @param string               $key  Column.
	 * @param EntityKind           $kind Entity kind, for errors.
	 */
	public static function nullableString( array $row, string $key, EntityKind $kind ): ?string {
		if ( ! array_key_exists( $key, $row ) || null === $row[ $key ] ) {
			return null;
		}
		return self::string( $row, $key, $kind );
	}

	/**
	 * Required UTC datetime column.
	 *
	 * @param array<string, mixed> $row  Row.
	 * @param string               $key  Column.
	 * @param EntityKind           $kind Entity kind, for errors.
	 * @throws CorruptRecordException When the column is missing or not a datetime.
	 */
	public static function date( array $row, string $key, EntityKind $kind ): DateTimeImmutable {
		$value = self::string( $row, $key, $kind );
		try {
			return new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
		} catch ( Exception ) {
			throw new CorruptRecordException( $kind, self::idOf( $row ), 'column_' . $key );
		}
	}

	/**
	 * Nullable UTC datetime column.
	 *
	 * @param array<string, mixed> $row  Row.
	 * @param string               $key  Column.
	 * @param EntityKind           $kind Entity kind, for errors.
	 * @throws CorruptRecordException When present but not a datetime.
	 */
	public static function nullableDate( array $row, string $key, EntityKind $kind ): ?DateTimeImmutable {
		if ( ! array_key_exists( $key, $row ) || null === $row[ $key ] ) {
			return null;
		}
		return self::date( $row, $key, $kind );
	}

	/**
	 * Best-effort row id for error reporting.
	 *
	 * @param array<string, mixed> $row Row.
	 */
	public static function idOf( array $row ): int {
		return isset( $row['id'] ) && is_numeric( $row['id'] ) ? (int) $row['id'] : 0;
	}
}
