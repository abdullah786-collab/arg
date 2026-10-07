<?php
/**
 * Read-side access to the statistics.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Bounded reads. No entry point of its own: the caller (M9) must check `rgp_view_analytics`.
 */
final class StatsReader {

	public const MAX_PAGE = 100;

	/**
	 * Constructor.
	 *
	 * @param StatsStoreInterface $store Aggregate storage.
	 */
	public function __construct( private readonly StatsStoreInterface $store ) {
	}

	/**
	 * Total impressions in the range.
	 *
	 * @param DateRange $range Range.
	 */
	public function total( DateRange $range ): int {
		return $this->store->total( $range->from, $range->to );
	}

	/**
	 * Top ads by impressions.
	 *
	 * @param DateRange $range  Range.
	 * @param int       $limit  Page size, clamped to 1..100.
	 * @param int       $offset Offset, at least 0.
	 * @return array<int,array{ad_id:int,impressions:int}>
	 */
	public function topAds( DateRange $range, int $limit = 10, int $offset = 0 ): array {
		return $this->store->totalsByAd( $range->from, $range->to, max( 1, min( self::MAX_PAGE, $limit ) ), max( 0, $offset ) );
	}

	/**
	 * Daily series, zero-filled for every day in the range.
	 *
	 * @param DateRange $range Range.
	 * @param int|null  $ad_id One ad, or null for all.
	 * @return array<string,int>
	 */
	public function daily( DateRange $range, ?int $ad_id = null ): array {
		$found  = $this->store->dailyTotals( $range->from, $range->to, null === $ad_id || $ad_id < 1 ? null : $ad_id );
		$series = array();
		foreach ( $range->days() as $day ) {
			$series[ $day ] = $found[ $day ] ?? 0;
		}
		return $series;
	}
}
