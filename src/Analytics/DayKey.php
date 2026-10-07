<?php
/**
 * UTC day keys.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Analytics;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Statistics are bucketed by UTC calendar day, written as `YYYY-MM-DD`.
 */
final class DayKey {

	/**
	 * The UTC day containing an instant.
	 *
	 * @param DateTimeImmutable $at Instant.
	 */
	public static function fromInstant( DateTimeImmutable $at ): string {
		return $at->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d' );
	}

	/**
	 * Whether a string is a real calendar day in canonical form.
	 *
	 * @param string $day Candidate.
	 */
	public static function isValid( string $day ): bool {
		if ( 1 !== preg_match( '/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $day, $match ) ) {
			return false;
		}
		return checkdate( (int) $match[2], (int) $match[3], (int) $match[1] );
	}

	/**
	 * A day shifted by a number of days (negative = earlier).
	 *
	 * @param string $day  Valid day key.
	 * @param int    $days Offset in days.
	 */
	public static function shift( string $day, int $days ): string {
		$base = new DateTimeImmutable( $day . ' 00:00:00', new DateTimeZone( 'UTC' ) );
		return $base->modify( sprintf( '%+d days', $days ) )->format( 'Y-m-d' );
	}
}
