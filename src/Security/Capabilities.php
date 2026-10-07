<?php
/**
 * Plugin capabilities. Granted to administrators; other roles opt in via a filter.
 *
 * Capabilities live in each site's roles option, so on multisite they are
 * granted lazily per site by the Installer. Super admins keep WordPress's
 * default meta-capability mapping; map_meta_cap is intentionally not overridden.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin capabilities. Granted to administrators; other roles opt in via a filter.
 */
final class Capabilities {

	public const MANAGE_ADS      = 'rgp_manage_ads';
	public const VIEW_ANALYTICS  = 'rgp_view_analytics';
	public const MANAGE_SETTINGS = 'rgp_manage_settings';
	private const DEFAULT_OWNER  = 'administrator';

	/**
	 * Every capability the plugin defines.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array( self::MANAGE_ADS, self::VIEW_ANALYTICS, self::MANAGE_SETTINGS );
	}

	/**
	 * Capabilities a role should hold. Only known capabilities survive the filter.
	 *
	 * @param string $role_name Role slug.
	 * @return list<string>
	 */
	public static function forRole( string $role_name ): array {
		$default = self::DEFAULT_OWNER === $role_name ? self::all() : array();

		/**
		 * Filters the Pro capabilities granted to a role on install.
		 *
		 * @param list<string> $caps      Capability names.
		 * @param string       $role_name Role slug.
		 */
		$filtered = apply_filters( 'revguard_pro_capabilities_for_role', $default, $role_name );

		return is_array( $filtered ) ? array_values( array_intersect( self::all(), $filtered ) ) : array();
	}

	/**
	 * Grants the default capabilities to administrators.
	 */
	public static function grantToAdministrator(): void {
		$role = get_role( self::DEFAULT_OWNER );
		if ( null === $role ) {
			return;
		}
		foreach ( self::forRole( self::DEFAULT_OWNER ) as $cap ) {
			$role->add_cap( $cap );
		}
	}

	/**
	 * Removes every Pro capability from every role of the current site.
	 */
	public static function revokeFromAllRoles(): void {
		$roles = wp_roles();
		$roles->for_site( get_current_blog_id() );
		foreach ( $roles->role_objects as $role ) {
			foreach ( self::all() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}
}
