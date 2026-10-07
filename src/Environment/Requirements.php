<?php
/**
 * Pure environment requirement check.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Environment;

defined( 'ABSPATH' ) || exit;

/**
 * Pure environment requirement check.
 */
final class Requirements {

	public const MIN_PHP = '8.2';
	public const MIN_WP  = '6.0';

	public const PHP_TOO_OLD = 'php_too_old';
	public const WP_TOO_OLD  = 'wp_too_old';

	/**
	 * Returns the codes of every unmet requirement (empty when all are met).
	 *
	 * @param string $php_version Running PHP version.
	 * @param string $wp_version  Running WordPress version.
	 * @return list<string>
	 */
	public static function check( string $php_version, string $wp_version ): array {
		$errors = array();
		if ( version_compare( $php_version, self::MIN_PHP, '<' ) ) {
			$errors[] = self::PHP_TOO_OLD;
		}
		if ( version_compare( $wp_version, self::MIN_WP, '<' ) ) {
			$errors[] = self::WP_TOO_OLD;
		}
		return $errors;
	}

	/**
	 * Localized, admin-facing text for a requirement code.
	 *
	 * @param string $code One of the class constants returned by check().
	 */
	public static function message( string $code ): string {
		return match ( $code ) {
			self::PHP_TOO_OLD => sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'RevGuard Pro requires PHP %1$s or higher. This server runs PHP %2$s.', 'revguard-pro' ),
				self::MIN_PHP,
				PHP_VERSION
			),
			self::WP_TOO_OLD => sprintf(
				/* translators: 1: required WordPress version, 2: current WordPress version. */
				__( 'RevGuard Pro requires WordPress %1$s or higher. This site runs WordPress %2$s.', 'revguard-pro' ),
				self::MIN_WP,
				get_bloginfo( 'version' )
			),
			default => __( 'RevGuard Pro cannot start in this environment.', 'revguard-pro' ),
		};
	}
}
