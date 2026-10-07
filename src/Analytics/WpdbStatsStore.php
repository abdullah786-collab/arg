<?php
/**
 * The `$wpdb` implementation of the statistics store.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Analytics;

use RevGuardPro\Persistence\Schema;
use Throwable;
use wpdb;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- This class is the database boundary for statistics.
// phpcs:disable WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders -- Table name is an allow-listed Schema constant plus the prefix; placeholders are generated from %s/%d only.

/**
 * Table name comes from the Schema allow-list plus the prefix read at call time (multisite-safe). Every value is
 * bound through `prepare()`. Writes never throw: a failure returns false / 0.
 */
final class WpdbStatsStore implements StatsStoreInterface {

	public const ROWS_PER_STATEMENT = 100;

	/**
	 * Constructor.
	 *
	 * @param wpdb $wpdb WordPress database object.
	 */
	public function __construct( private readonly wpdb $wpdb ) {
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string         $day    Day key.
	 * @param array<int,int> $counts Ad id to count.
	 */
	public function increment( string $day, array $counts ): bool {
		if ( ! DayKey::isValid( $day ) ) {
			return false;
		}
		$rows = array();
		foreach ( $counts as $ad_id => $count ) {
			if ( is_int( $ad_id ) && $ad_id >= 1 && is_int( $count ) && $count >= 1 ) {
				$rows[ $ad_id ] = $count;
			}
		}
		if ( array() === $rows ) {
			return true;
		}

		$table = Schema::table( $this->wpdb, Schema::STATS );
		$ok    = true;
		$prev  = $this->wpdb->suppress_errors( true );
		try {
			foreach ( array_chunk( $rows, self::ROWS_PER_STATEMENT, true ) as $chunk ) {
				$holders = array();
				$args    = array();
				foreach ( $chunk as $ad_id => $count ) {
					$holders[] = '(%s, %d, %d)';
					$args[]    = $day;
					$args[]    = $ad_id;
					$args[]    = $count;
				}
				$sql    = $this->wpdb->prepare( "INSERT INTO {$table} (day, ad_id, impressions) VALUES " . implode( ', ', $holders ) . ' ON DUPLICATE KEY UPDATE impressions = impressions + VALUES(impressions)', $args );
				$result = $this->wpdb->query( $sql );
				$ok     = $ok && false !== $result;
			}
		} catch ( Throwable ) {
			$ok = false;
		} finally {
			$this->wpdb->suppress_errors( $prev );
		}
		return $ok;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $day   Day key.
	 * @param int    $limit Maximum rows.
	 */
	public function deleteBefore( string $day, int $limit ): int {
		if ( ! DayKey::isValid( $day ) || $limit < 1 ) {
			return 0;
		}
		$table = Schema::table( $this->wpdb, Schema::STATS );
		$prev  = $this->wpdb->suppress_errors( true );
		try {
			$deleted = $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$table} WHERE day < %s ORDER BY day ASC LIMIT %d", $day, $limit ) );
		} catch ( Throwable ) {
			$deleted = false;
		} finally {
			$this->wpdb->suppress_errors( $prev );
		}
		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $from First day.
	 * @param string $to   Last day.
	 */
	public function total( string $from, string $to ): int {
		$table = Schema::table( $this->wpdb, Schema::STATS );
		$value = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COALESCE(SUM(impressions), 0) FROM {$table} WHERE day BETWEEN %s AND %s", $from, $to ) );
		return (int) $value;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $from   First day.
	 * @param string $to     Last day.
	 * @param int    $limit  Page size.
	 * @param int    $offset Offset.
	 */
	public function totalsByAd( string $from, string $to, int $limit, int $offset ): array {
		$table = Schema::table( $this->wpdb, Schema::STATS );
		$rows  = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT ad_id, SUM(impressions) AS impressions FROM {$table} WHERE day BETWEEN %s AND %s GROUP BY ad_id ORDER BY impressions DESC, ad_id ASC LIMIT %d OFFSET %d", $from, $to, max( 1, $limit ), max( 0, $offset ) ), ARRAY_A );
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'ad_id'       => (int) $row['ad_id'],
				'impressions' => (int) $row['impressions'],
			);
		}
		return $out;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string   $from  First day.
	 * @param string   $to    Last day.
	 * @param int|null $ad_id Ad id.
	 */
	public function dailyTotals( string $from, string $to, ?int $ad_id ): array {
		$table = Schema::table( $this->wpdb, Schema::STATS );
		if ( null === $ad_id ) {
			$sql = $this->wpdb->prepare( "SELECT day, SUM(impressions) AS impressions FROM {$table} WHERE day BETWEEN %s AND %s GROUP BY day ORDER BY day ASC", $from, $to );
		} else {
			$sql = $this->wpdb->prepare( "SELECT day, SUM(impressions) AS impressions FROM {$table} WHERE day BETWEEN %s AND %s AND ad_id = %d GROUP BY day ORDER BY day ASC", $from, $to, $ad_id );
		}
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['day'] ] = (int) $row['impressions'];
		}
		return $out;
	}
}
