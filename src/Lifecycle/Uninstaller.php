<?php
/**
 * Uninstall cleanup, opt-in only.
 *
 * Data is removed per site, and only for sites whose settings enable
 * "delete_data_on_uninstall". Options owned by any other plugin are never touched.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Lifecycle;

use RevGuardPro\Cache\DataVersion;
use RevGuardPro\Persistence\Schema;
use RevGuardPro\Security\Capabilities;
use RevGuardPro\Settings\SettingsRepository;
use RevGuardPro\Settings\WpOptionStore;

defined( 'ABSPATH' ) || exit;

/**
 * Uninstall cleanup, opt-in only.
 */
final class Uninstaller {

	private const SITE_BATCH = 100;

	/**
	 * Cleans every site on multisite (in bounded batches), or the single site.
	 */
	public static function run(): void {
		if ( ! is_multisite() ) {
			self::cleanCurrentSite();
			return;
		}

		$offset = 0;
		do {
			$site_ids   = get_sites(
				array(
					'fields' => 'ids',
					'number' => self::SITE_BATCH,
					'offset' => $offset,
				)
			);
			$batch_size = count( $site_ids );
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::cleanCurrentSite();
				restore_current_blog();
			}
			$offset += self::SITE_BATCH;
		} while ( self::SITE_BATCH === $batch_size );
	}

	/**
	 * Removes the plugin's data for the current site when the owner opted in.
	 */
	private static function cleanCurrentSite(): void {
		$store = new WpOptionStore();
		if ( ! ( new SettingsRepository( $store ) )->bool( 'delete_data_on_uninstall' ) ) {
			return;
		}

		Capabilities::revokeFromAllRoles();
		self::dropTables();
		$store->delete( SettingsRepository::OPTION );
		$store->delete( Installer::OPTION_VERSION );
		$store->delete( Installer::OPTION_INSTALLED_AT );
		$store->delete( Installer::OPTION_SCHEMA_FAILED_AT );
		$store->delete( Schema::OPTION_DB_VERSION );
		$store->delete( DataVersion::OPTION );
	}

	/**
	 * Drops the current site's tables. Names come from the Schema allow-list plus the site prefix.
	 * The analytics table is part of Schema::TABLES and is dropped with the rest.
	 */
	private static function dropTables(): void {
		global $wpdb;
		foreach ( Schema::TABLES as $suffix ) {
			$table = Schema::table( $wpdb, $suffix );
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		}
	}
}
