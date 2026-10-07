<?php
/**
 * Uninstall handler. Removes data only when the site owner opted in via the
 * "delete data on uninstall" setting; see Lifecycle\Uninstaller.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$revguard_pro_autoload = __DIR__ . '/vendor/autoload.php';

// Without the autoloader nothing is removed; leaving data behind is the safe failure mode.
if ( is_readable( $revguard_pro_autoload ) ) {
	require_once $revguard_pro_autoload;
	\RevGuardPro\Lifecycle\Uninstaller::run();
}
