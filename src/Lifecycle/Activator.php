<?php
/**
 * Activation hook handler.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Lifecycle;

defined( 'ABSPATH' ) || exit;

/**
 * Activation hook handler.
 */
final class Activator {

	/**
	 * Installs for the current site. On network activation other sites install
	 * lazily on their first request (see Installer::maybeInstall()), so no
	 * unbounded per-site loop runs here.
	 */
	public static function activate(): void {
		Installer::forWordPress( REVGUARD_PRO_VERSION )->install();
	}
}
