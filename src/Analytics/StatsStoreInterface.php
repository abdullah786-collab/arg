<?php
/**
 * Storage of daily impression aggregates.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * The only way analytics data is read or written. It shares nothing with the domain repositories (ADR 001/007).
 */
interface StatsStoreInterface {

	/**
	 * Adds counts to a day, atomically and additively (no read-modify-write).
	 *
	 * @param string         $day    Valid day key.
	 * @param array<int,int> $counts Ad id to impressions to add (each >= 1).
	 * @return bool Whether the write succeeded.
	 */
	public function increment( string $day, array $counts ): bool;

	/**
	 * Deletes up to $limit rows older than a day, oldest first.
	 *
	 * @param string $day   Valid day key; rows with a strictly earlier day are removed.
	 * @param int    $limit Maximum rows to delete.
	 * @return int Rows deleted.
	 */
	public function deleteBefore( string $day, int $limit ): int;

	/**
	 * Total impressions in an inclusive day range.
	 *
	 * @param string $from First day.
	 * @param string $to   Last day.
	 */
	public function total( string $from, string $to ): int;

	/**
	 * Impressions per ad in a range, highest first (ties by ad id ascending).
	 *
	 * @param string $from   First day.
	 * @param string $to     Last day.
	 * @param int    $limit  Page size.
	 * @param int    $offset Offset.
	 * @return array<int,array{ad_id:int,impressions:int}>
	 */
	public function totalsByAd( string $from, string $to, int $limit, int $offset ): array;

	/**
	 * Impressions per day in a range, optionally for one ad. Days with no row are absent.
	 *
	 * @param string   $from  First day.
	 * @param string   $to    Last day.
	 * @param int|null $ad_id Ad id, or null for all ads.
	 * @return array<string,int>
	 */
	public function dailyTotals( string $from, string $to, ?int $ad_id ): array;
}
