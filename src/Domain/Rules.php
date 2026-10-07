<?php
/**
 * Shared, pure validation rules for domain entities.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain;

use DateTimeImmutable;
use RevGuardPro\Domain\Exception\InvalidEntityException;

defined( 'ABSPATH' ) || exit;

/**
 * Stateless validation helpers. They normalize (trim) but never escape: escaping is an output concern.
 */
final class Rules {

	public const NAME_MAX_CHARS = 190;

	/**
	 * A single-line display name: trimmed, valid UTF-8, no control characters, 1 to 190 characters.
	 *
	 * @param string $value Raw name.
	 * @param string $field Field code for errors.
	 * @throws InvalidEntityException When the name is not acceptable.
	 */
	public static function name( string $value, string $field = 'name' ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			throw new InvalidEntityException( $field, InvalidEntityException::REASON_EMPTY );
		}
		if ( ! mb_check_encoding( $value, 'UTF-8' ) ) {
			throw new InvalidEntityException( $field, InvalidEntityException::REASON_INVALID_ENCODING );
		}
		if ( mb_strlen( $value, 'UTF-8' ) > self::NAME_MAX_CHARS ) {
			throw new InvalidEntityException( $field, InvalidEntityException::REASON_TOO_LONG );
		}
		if ( 1 === preg_match( '/[\x00-\x1F\x7F]/', $value ) ) {
			throw new InvalidEntityException( $field, InvalidEntityException::REASON_INVALID_VALUE );
		}
		return $value;
	}

	/**
	 * Opaque text payload (for example ad code): valid UTF-8, no NUL byte, bounded size. Content is not altered.
	 *
	 * @param string $value     Raw payload.
	 * @param int    $max_bytes Maximum size in bytes.
	 * @param string $field     Field code for errors.
	 * @throws InvalidEntityException When the payload is not acceptable.
	 */
	public static function payload( string $value, int $max_bytes, string $field ): string {
		if ( strlen( $value ) > $max_bytes ) {
			throw new InvalidEntityException( $field, InvalidEntityException::REASON_TOO_LONG );
		}
		if ( str_contains( $value, "\0" ) ) {
			throw new InvalidEntityException( $field, InvalidEntityException::REASON_INVALID_VALUE );
		}
		if ( ! mb_check_encoding( $value, 'UTF-8' ) ) {
			throw new InvalidEntityException( $field, InvalidEntityException::REASON_INVALID_ENCODING );
		}
		return $value;
	}

	/**
	 * A positive integer id.
	 *
	 * @param int    $value Candidate id.
	 * @param string $field Field code for errors.
	 * @throws InvalidEntityException When below 1.
	 */
	public static function id( int $value, string $field ): int {
		if ( $value < 1 ) {
			throw new InvalidEntityException( $field, InvalidEntityException::REASON_OUT_OF_RANGE );
		}
		return $value;
	}

	/**
	 * An integer within an inclusive range.
	 *
	 * @param int    $value Candidate.
	 * @param int    $min   Lower bound.
	 * @param int    $max   Upper bound.
	 * @param string $field Field code for errors.
	 * @throws InvalidEntityException When out of range.
	 */
	public static function range( int $value, int $min, int $max, string $field ): int {
		if ( $value < $min || $value > $max ) {
			throw new InvalidEntityException( $field, InvalidEntityException::REASON_OUT_OF_RANGE );
		}
		return $value;
	}

	/**
	 * Persisted-state consistency: id and both timestamps are all present (stored) or all absent (new).
	 *
	 * @param int|null               $id         Entity id.
	 * @param DateTimeImmutable|null $created_at Creation time.
	 * @param DateTimeImmutable|null $updated_at Update time.
	 * @throws InvalidEntityException When the combination is inconsistent.
	 */
	public static function persistedState( ?int $id, ?DateTimeImmutable $created_at, ?DateTimeImmutable $updated_at ): void {
		if ( null !== $id ) {
			self::id( $id, 'id' );
		}
		$present = ( null !== $id ) + ( null !== $created_at ) + ( null !== $updated_at );
		if ( 0 !== $present && 3 !== $present ) {
			throw new InvalidEntityException( 'persisted_state', InvalidEntityException::REASON_INVALID_VALUE );
		}
	}
}
