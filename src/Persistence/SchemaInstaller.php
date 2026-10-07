<?php
/**
 * Idempotent, verified schema migration.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Persistence;

use RevGuardPro\Settings\OptionStoreInterface;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Creates or upgrades the tables with dbDelta. The schema version is stored only after every table is
 * verified to exist, so a failed migration is retried instead of being recorded as done. Migrations are additive.
 */
final class SchemaInstaller {

	/**
	 * Constructor.
	 *
	 * @param wpdb                 $wpdb    Database.
	 * @param OptionStoreInterface $options Option persistence.
	 */
	public function __construct(
		private readonly wpdb $wpdb,
		private readonly OptionStoreInterface $options
	) {
	}

	/**
	 * Brings the current site's schema up to date.
	 *
	 * @return bool Whether the schema is in place afterwards.
	 */
	public function migrate(): bool {
		$stored = (int) $this->options->get( Schema::OPTION_DB_VERSION, 0 );

		// A newer stored version means a downgrade: never alter a schema this code does not know.
		if ( $stored >= Schema::VERSION && $this->schemaReady() ) {
			return true;
		}

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		foreach ( Schema::ddl( $this->wpdb ) as $statement ) {
			dbDelta( $statement );
		}

		if ( ! $this->schemaReady() ) {
			return false;
		}
		if ( $stored < Schema::VERSION ) {
			$this->options->set( Schema::OPTION_DB_VERSION, Schema::VERSION, false );
		}
		return true;
	}

	/**
	 * Whether every table exists with every column of the current schema (works for temporary tables).
	 */
	public function schemaReady(): bool {
		$previous = $this->wpdb->suppress_errors( true );
		$ready    = true;
		foreach ( Schema::COLUMNS as $suffix => $columns ) {
			$table = Schema::table( $this->wpdb, $suffix );
			// Table and column names come from the Schema allow-list constants.
			$result = $this->wpdb->query( 'SELECT ' . implode( ', ', $columns ) . " FROM {$table} WHERE 1 = 0" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			if ( false === $result ) {
				$ready = false;
				break;
			}
		}
		$this->wpdb->suppress_errors( $previous );
		return $ready;
	}

	/**
	 * Whether every table exists (works for temporary tables, unlike SHOW TABLES).
	 */
	public function tablesExist(): bool {
		$previous = $this->wpdb->suppress_errors( true );
		$all      = true;
		foreach ( Schema::TABLES as $suffix ) {
			$table = Schema::table( $this->wpdb, $suffix );
			// Table name comes from the allow-list plus the WordPress prefix.
			$result = $this->wpdb->query( "SELECT 1 FROM {$table} WHERE 1 = 0" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( false === $result ) {
				$all = false;
				break;
			}
		}
		$this->wpdb->suppress_errors( $previous );
		return $all;
	}
}
