<?php
/**
 * Deactivation hook handler. Stops background work; never deletes data.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Lifecycle;

use RevGuardPro\Analytics\StatsRetention;

defined( 'ABSPATH' ) || exit;

/**
 * Deactivation hook handler. Stops background work; never deletes data.
 */
final class Deactivator {

	/**
	 * Scheduled event hooks owned by the plugin. Later milestones append here.
	 *
	 * @var list<string>
	 */
	public const CRON_HOOKS = array( StatsRetention::CRON_HOOK );

	/**
	 * Clears the plugin's scheduled events.
	 */
	public static function deactivate(): void {
		foreach ( self::CRON_HOOKS as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}
}
