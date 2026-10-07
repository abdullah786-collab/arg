<?php
/**
 * A validated inclusive UTC date range.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Analytics;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable, at most 366 days, start on or before end. Built only through the factories, which return null for
 * anything invalid so callers never query with an unbounded or malformed range.
 */
final class DateRange {

	public const MAX_DAYS = 366;

	/**
	 * Constructor (use the factories).
	 *
	 * @param string $from First day.
	 * @param string $to   Last day.
	 */
	private function __construct( public readonly string $from, public readonly string $to ) {
	}

	/**
	 * Builds a range from two day keys.
	 *
	 * @param string $from First day.
	 * @param string $to   Last day.
	 */
	public static function of( string $from, string $to ): ?self {
		if ( ! DayKey::isValid( $from ) || ! DayKey::isValid( $to ) || $from > $to ) {
			return null;
		}
		$range = new self( $from, $to );
		return $range->length() > self::MAX_DAYS ? null : $range;
	}

	/**
	 * The last N days ending today (UTC), N within 1..366.
	 *
	 * @param DateTimeImmutable $now  Current instant.
	 * @param int               $days Number of days including today.
	 */
	public static function last( DateTimeImmutable $now, int $days ): ?self {
		if ( $days < 1 || $days > self::MAX_DAYS ) {
			return null;
		}
		$to = DayKey::fromInstant( $now );
		return self::of( DayKey::shift( $to, 1 - $days ), $to );
	}

	/**
	 * Number of days in the range.
	 */
	public function length(): int {
		$from = new DateTimeImmutable( $this->from );
		$to   = new DateTimeImmutable( $this->to );
		return (int) $from->diff( $to )->days + 1;
	}

	/**
	 * Every day key in the range, in order.
	 *
	 * @return array<string>
	 */
	public function days(): array {
		$days = array();
		$day  = $this->from;
		while ( $day <= $this->to ) {
			$days[] = $day;
			$day    = DayKey::shift( $day, 1 );
		}
		return $days;
	}
}
