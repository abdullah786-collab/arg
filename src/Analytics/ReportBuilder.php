<?php
/**
 * Joins statistics with ad names for reporting.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Analytics;

use RevGuardPro\Domain\Repository\AdRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * One ad lookup per page of results (never per row). Statistics for deleted ads stay readable: they report
 * `name = null, deleted = true` instead of failing (ADR 001/007: no dependency between the two stores).
 */
final class ReportBuilder {

	/**
	 * Constructor.
	 *
	 * @param StatsReader           $reader Statistics reader.
	 * @param AdRepositoryInterface $ads    Ad repository.
	 */
	public function __construct(
		private readonly StatsReader $reader,
		private readonly AdRepositoryInterface $ads
	) {
	}

	/**
	 * Top ads with names.
	 *
	 * @param DateRange $range  Range.
	 * @param int       $limit  Page size.
	 * @param int       $offset Offset.
	 * @return array<int,AdReportRow>
	 */
	public function topAds( DateRange $range, int $limit = 10, int $offset = 0 ): array {
		$rows = $this->reader->topAds( $range, $limit, $offset );
		if ( array() === $rows ) {
			return array();
		}
		$known = $this->ads->findMany( array_column( $rows, 'ad_id' ) );
		$out   = array();
		foreach ( $rows as $row ) {
			$ad    = $known[ $row['ad_id'] ] ?? null;
			$out[] = new AdReportRow( $row['ad_id'], $row['impressions'], null === $ad ? null : $ad->name, null === $ad );
		}
		return $out;
	}
}
