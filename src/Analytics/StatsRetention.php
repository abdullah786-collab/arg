<?php
/**
 * Bounded purge of old daily statistics.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Analytics;

use Closure;
use RevGuardPro\Support\ClockInterface;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Deletes rows older than the configured number of days, in bounded batches, so one cron run is always short.
 */
final class StatsRetention {

	public const CRON_HOOK    = 'revguard_pro_purge_stats';
	public const DEFAULT_DAYS = 90;
	public const MIN_DAYS     = 7;
	public const MAX_DAYS     = 3650;
	public const BATCH        = 1000;
	public const MAX_BATCHES  = 20;

	/**
	 * Constructor.
	 *
	 * @param StatsStoreInterface $store Aggregate storage.
	 * @param ClockInterface      $clock Clock.
	 * @param Closure             $days  Closure(): int, the configured retention in days (untrusted).
	 */
	public function __construct(
		private readonly StatsStoreInterface $store,
		private readonly ClockInterface $clock,
		private readonly Closure $days
	) {
	}

	/**
	 * A safe retention: invalid (<= 0) falls back to the default, otherwise clamped to the allowed range.
	 *
	 * @param int $days Configured value.
	 */
	public static function clamp( int $days ): int {
		if ( $days <= 0 ) {
			return self::DEFAULT_DAYS;
		}
		return max( self::MIN_DAYS, min( self::MAX_DAYS, $days ) );
	}

	/**
	 * Purges rows older than the retention window. Returns the number of rows deleted; never throws.
	 */
	public function purge(): int {
		try {
			$cutoff = DayKey::shift( DayKey::fromInstant( $this->clock->now() ), -self::clamp( (int) ( $this->days )() ) );
			$total  = 0;
			for ( $batch = 0; $batch < self::MAX_BATCHES; $batch++ ) {
				$deleted = $this->store->deleteBefore( $cutoff, self::BATCH );
				$total  += $deleted;
				if ( $deleted < self::BATCH ) {
					break;
				}
			}
			return $total;
		} catch ( Throwable ) {
			return 0;
		}
	}
}
