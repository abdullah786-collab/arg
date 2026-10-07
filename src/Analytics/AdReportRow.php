<?php
/**
 * One row of the top-ads report.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Impressions for an ad id, with its current name; `deleted` when the ad no longer exists.
 */
final class AdReportRow {

	/**
	 * Constructor.
	 *
	 * @param int         $ad_id       Ad id.
	 * @param int         $impressions Impressions in the range.
	 * @param string|null $name        Current ad name, null when deleted.
	 * @param bool        $deleted     Whether the ad no longer exists.
	 */
	public function __construct(
		public readonly int $ad_id,
		public readonly int $impressions,
		public readonly ?string $name,
		public readonly bool $deleted
	) {
	}
}
