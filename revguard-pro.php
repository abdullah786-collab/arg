<?php
/**
 * Plugin Name:       RevGuard Pro
 * Description:       Fast, cache-aware ad management for WordPress publishers: ad groups, scheduling, placements, weighted rotation and lightweight aggregate analytics.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            Abdul Kadir
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       revguard-pro
 * Domain Path:       /languages
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/*
 * This file only guards the environment and hands over to the Plugin class.
 * The PHP check deliberately runs before the autoloader so that old PHP
 * versions get a notice instead of a parse/type fatal.
 */
$revguard_pro_notice = static function ( Closure $message ): void {
	add_action(
		'admin_notices',
		static function () use ( $message ): void {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message() ) );
		}
	);
};

if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
	// Messages are built lazily: translating before `init` triggers a notice on WordPress 6.7+.
	$revguard_pro_notice(
		static fn(): string => sprintf(
			/* translators: 1: required PHP version, 2: current PHP version. */
			__( 'RevGuard Pro requires PHP %1$s or higher. This server runs PHP %2$s.', 'revguard-pro' ),
			'8.2',
			PHP_VERSION
		)
	);
	return;
}

// A second copy (for example in another folder) must register nothing: duplicate hooks would double-insert ads.
if ( defined( 'REVGUARD_PRO_VERSION' ) ) {
	$revguard_pro_notice(
		static fn(): string => __( 'RevGuard Pro is already loaded from another folder. This copy was not started; please deactivate and delete the duplicate.', 'revguard-pro' )
	);
	return;
}

define( 'REVGUARD_PRO_VERSION', '1.0.0' );
define( 'REVGUARD_PRO_FILE', __FILE__ );
define( 'REVGUARD_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'REVGUARD_PRO_URL', plugin_dir_url( __FILE__ ) );
define( 'REVGUARD_PRO_BASENAME', plugin_basename( __FILE__ ) );

$revguard_pro_autoload = REVGUARD_PRO_PATH . 'vendor/autoload.php';
if ( ! is_readable( $revguard_pro_autoload ) ) {
	$revguard_pro_notice(
		static fn(): string => __( 'RevGuard Pro could not start because its files are incomplete. Please reinstall the plugin from a fresh copy.', 'revguard-pro' )
	);
	return;
}
require_once $revguard_pro_autoload;

$revguard_pro_errors = \RevGuardPro\Environment\Requirements::check( PHP_VERSION, get_bloginfo( 'version' ) );
if ( array() !== $revguard_pro_errors ) {
	foreach ( $revguard_pro_errors as $revguard_pro_code ) {
		$revguard_pro_notice(
			static fn(): string => \RevGuardPro\Environment\Requirements::message( $revguard_pro_code )
		);
	}
	return;
}

register_activation_hook( REVGUARD_PRO_FILE, array( \RevGuardPro\Lifecycle\Activator::class, 'activate' ) );
register_deactivation_hook( REVGUARD_PRO_FILE, array( \RevGuardPro\Lifecycle\Deactivator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		( new \RevGuardPro\Plugin( REVGUARD_PRO_BASENAME, REVGUARD_PRO_VERSION ) )->boot();
	}
);
