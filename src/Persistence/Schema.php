<?php
/**
 * Database schema definition (version 3: adds the targeting columns).
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Persistence;

use InvalidArgumentException;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Table names and dbDelta DDL. Tables are per site: names are built from the current `$wpdb->prefix`
 * at call time so switch_to_blog() redirects every query. No foreign keys (see ADR 001).
 */
final class Schema {

	public const VERSION = 4;

	public const ADS        = 'rgp_ads';
	public const GROUPS     = 'rgp_ad_groups';
	public const GROUP_ADS  = 'rgp_group_ads';
	public const PLACEMENTS = 'rgp_placements';
	public const STATS      = 'rgp_stats_daily';

	/**
	 * Allow-list of table suffixes. Only these may be interpolated into SQL.
	 *
	 * @var list<string>
	 */
	public const TABLES = array( self::ADS, self::GROUPS, self::GROUP_ADS, self::PLACEMENTS, self::STATS );

	/**
	 * Every column each table must have at the current version. Readiness checks use it, so a stalled upgrade
	 * (a column that dbDelta failed to add) is detected instead of being recorded as done.
	 *
	 * @var array<string, list<string>>
	 */
	public const COLUMNS = array(
		self::ADS        => array( 'id', 'name', 'status', 'creative_type', 'creative', 'schedule_start', 'schedule_end', 'targeting', 'created_at', 'updated_at' ),
		self::GROUPS     => array( 'id', 'name', 'status', 'rotation_mode', 'created_at', 'updated_at' ),
		self::GROUP_ADS  => array( 'group_id', 'ad_id', 'sort_order', 'weight' ),
		self::PLACEMENTS => array( 'id', 'name', 'status', 'placement_type', 'config', 'target_type', 'target_id', 'priority', 'targeting', 'created_at', 'updated_at' ),
		self::STATS      => array( 'day', 'ad_id', 'impressions' ),
	);

	public const OPTION_DB_VERSION = 'revguard_pro_db_version';

	/**
	 * Full table name for the current site.
	 *
	 * @param wpdb   $wpdb   Database.
	 * @param string $suffix One of the TABLES constants.
	 * @throws InvalidArgumentException When the suffix is not allow-listed.
	 */
	public static function table( wpdb $wpdb, string $suffix ): string {
		if ( ! in_array( $suffix, self::TABLES, true ) ) {
			throw new InvalidArgumentException( 'Unknown table.' );
		}
		return $wpdb->prefix . $suffix;
	}

	/**
	 * The dbDelta statements for the current site.
	 *
	 * Indexes follow the real queries: status listing `(status, id)`, active placements ordered by priority
	 * `(status, priority, id)`, "which groups hold this ad" `(ad_id)`, and "what references this target" `(target_type, target_id)`.
	 *
	 * @param wpdb $wpdb Database.
	 * @return list<string>
	 */
	public static function ddl( wpdb $wpdb ): array {
		$collate = $wpdb->get_charset_collate();
		$ads     = self::table( $wpdb, self::ADS );
		$groups  = self::table( $wpdb, self::GROUPS );
		$members = self::table( $wpdb, self::GROUP_ADS );
		$places  = self::table( $wpdb, self::PLACEMENTS );
		$stats   = self::table( $wpdb, self::STATS );

		return array(
			"CREATE TABLE {$ads} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'inactive',
  creative_type varchar(20) NOT NULL,
  creative longtext NOT NULL,
  schedule_start datetime DEFAULT NULL,
  schedule_end datetime DEFAULT NULL,
  targeting text DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY status_id (status,id)
) {$collate};",
			"CREATE TABLE {$groups} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'inactive',
  rotation_mode varchar(20) NOT NULL DEFAULT 'weighted',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY status_id (status,id)
) {$collate};",
			"CREATE TABLE {$members} (
  group_id bigint(20) unsigned NOT NULL,
  ad_id bigint(20) unsigned NOT NULL,
  sort_order int(10) unsigned NOT NULL DEFAULT 0,
  weight smallint(5) unsigned NOT NULL DEFAULT 100,
  PRIMARY KEY  (group_id,ad_id),
  KEY ad_id (ad_id)
) {$collate};",
			"CREATE TABLE {$places} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'inactive',
  placement_type varchar(30) NOT NULL,
  config text NOT NULL,
  target_type varchar(10) DEFAULT NULL,
  target_id bigint(20) unsigned DEFAULT NULL,
  priority int(10) unsigned NOT NULL DEFAULT 100,
  targeting text DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY status_priority (status,priority,id),
  KEY target (target_type,target_id)
) {$collate};",
			"CREATE TABLE {$stats} (
  day date NOT NULL,
  ad_id bigint(20) unsigned NOT NULL,
  impressions bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (day,ad_id)
) {$collate};",
		);
	}
}
