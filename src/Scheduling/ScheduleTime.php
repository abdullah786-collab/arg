<?php
/**
 * Wall-clock conversion at the schedule boundary.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Scheduling;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RevGuardPro\Domain\Exception\InvalidEntityException;

defined( 'ABSPATH' ) || exit;

/**
 * Converts human wall-clock times to absolute instants and back, using a zone the caller supplies
 * (`wp_timezone()` in WordPress, which handles both named zones and fixed offsets). It never reads the
 * server or PHP default timezone. Times that do not exist or occur twice in the zone are rejected, not guessed.
 */
final class ScheduleTime {

	private const DAY = 86400;

	/**
	 * Converts "YYYY-MM-DD HH:MM[:SS]" (a space or "T" separator) in the given zone to a UTC instant.
	 *
	 * @param string       $wall_clock Local wall-clock time.
	 * @param DateTimeZone $zone       Zone the wall clock is expressed in.
	 * @param string       $field      Field code for errors.
	 * @throws InvalidEntityException When malformed, nonexistent (DST gap) or ambiguous (DST overlap).
	 */
	public static function fromLocal( string $wall_clock, DateTimeZone $zone, string $field = 'schedule' ): DateTimeImmutable {
		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/D', $wall_clock, $m ) ) {
			throw new InvalidEntityException( $field, InvalidEntityException::REASON_INVALID_VALUE );
		}
		$year   = (int) $m[1];
		$month  = (int) $m[2];
		$day    = (int) $m[3];
		$hour   = (int) $m[4];
		$minute = (int) $m[5];
		$second = isset( $m[6] ) ? (int) $m[6] : 0;
		if ( ! checkdate( $month, $day, $year ) || $hour > 23 || $minute > 59 || $second > 59 ) {
			throw new InvalidEntityException( $field, InvalidEntityException::REASON_INVALID_VALUE );
		}

		// The wall clock read as if it were UTC, then shifted by each offset the zone uses around that day.
		$as_utc = gmmktime( $hour, $minute, $second, $month, $day, $year );
		if ( false === $as_utc ) {
			throw new InvalidEntityException( $field, InvalidEntityException::REASON_INVALID_VALUE );
		}
		$offsets = array();
		foreach ( array( $as_utc - self::DAY, $as_utc, $as_utc + self::DAY ) as $probe ) {
			$offsets[ self::offsetAt( $zone, $probe ) ] = true;
		}

		$valid = array();
		foreach ( array_keys( $offsets ) as $offset ) {
			$candidate = $as_utc - $offset;
			if ( self::offsetAt( $zone, $candidate ) === $offset ) {
				$valid[ $candidate ] = true;
			}
		}

		if ( array() === $valid ) {
			throw new InvalidEntityException( $field, InvalidEntityException::REASON_NONEXISTENT_LOCAL );
		}
		if ( count( $valid ) > 1 ) {
			throw new InvalidEntityException( $field, InvalidEntityException::REASON_AMBIGUOUS_LOCAL );
		}
		$timestamp = (int) array_key_first( $valid );
		return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Formats an instant as local wall-clock "Y-m-d H:i:s" in the given zone (for display).
	 *
	 * @param DateTimeInterface $instant Instant.
	 * @param DateTimeZone      $zone    Zone to express it in.
	 */
	public static function toLocal( DateTimeInterface $instant, DateTimeZone $zone ): string {
		return ( new DateTimeImmutable( '@' . $instant->getTimestamp() ) )->setTimezone( $zone )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * UTC offset in seconds that the zone uses at the given Unix timestamp.
	 *
	 * @param DateTimeZone $zone      Zone.
	 * @param int          $timestamp Unix timestamp.
	 */
	private static function offsetAt( DateTimeZone $zone, int $timestamp ): int {
		return $zone->getOffset( ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( new DateTimeZone( 'UTC' ) ) );
	}
}
