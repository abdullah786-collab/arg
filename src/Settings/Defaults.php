<?php
/**
 * Default plugin settings. The type of each default defines the type the key is coerced to.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Default plugin settings. The type of each default defines the type the key is coerced to.
 */
final class Defaults {

	/**
	 * All known settings with their defaults.
	 *
	 * @return array<string, bool|int|string>
	 */
	public static function all(): array {
		return array(
			// Data is kept on uninstall unless the site owner explicitly opts in.
			'delete_data_on_uninstall'        => false,
			// Render html creatives verbatim (trusted-administrator mode). Off: they are filtered with an ad allowlist.
			'allow_unfiltered_html_creatives' => false,
			// Insert ads into post content automatically from the configured placements.
			'automatic_insertion'             => true,
			// Count server-side rendered impressions per ad per day (no personal data is stored).
			'analytics_enabled'               => true,
			// Days of daily statistics to keep; clamped to 7..3650 when used.
			'analytics_retention_days'        => 90,
		);
	}
}
