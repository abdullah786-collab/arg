<?php
/**
 * Idempotent per-site installer.
 *
 * Runs on activation and lazily when the stored version differs from the
 * running version, which also covers network-activated multisite installs
 * without iterating every site.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Lifecycle;

use Closure;
use RevGuardPro\Persistence\SchemaInstaller;
use RevGuardPro\Security\Capabilities;
use RevGuardPro\Settings\OptionStoreInterface;
use RevGuardPro\Settings\WpOptionStore;

defined( 'ABSPATH' ) || exit;

/**
 * Installs capabilities and the database schema for the current site. The plugin version is recorded
 * only after the schema is verified, so a failed migration is retried (at most once per throttle window).
 */
final class Installer {

	public const OPTION_VERSION          = 'revguard_pro_version';
	public const OPTION_INSTALLED_AT     = 'revguard_pro_installed_at';
	public const OPTION_SCHEMA_FAILED_AT = 'revguard_pro_schema_failed_at';
	public const RETRY_AFTER_SECONDS     = 600;

	/**
	 * Constructor.
	 *
	 * @param OptionStoreInterface $options Option persistence.
	 * @param string               $version Running plugin version.
	 * @param Closure              $grant   Grants capabilities for the current site; takes no arguments.
	 * @param Closure              $clock   Returns the current Unix time as int.
	 * @param Closure              $migrate Migrates the schema for the current site; returns whether it is in place.
	 */
	public function __construct(
		private readonly OptionStoreInterface $options,
		private readonly string $version,
		private readonly Closure $grant,
		private readonly Closure $clock,
		private readonly Closure $migrate
	) {
	}

	/**
	 * Builds an installer wired to WordPress.
	 *
	 * @param string $version Running plugin version.
	 */
	public static function forWordPress( string $version ): self {
		global $wpdb;
		$options = new WpOptionStore();
		$schema  = new SchemaInstaller( $wpdb, $options );

		return new self(
			$options,
			$version,
			static function (): void {
				Capabilities::grantToAdministrator();
			},
			static fn(): int => time(),
			static fn(): bool => $schema->migrate()
		);
	}

	/**
	 * Installs only when the stored version differs. Cheap enough for every request.
	 *
	 * @return bool Whether an install ran.
	 */
	public function maybeInstall(): bool {
		if ( $this->options->get( self::OPTION_VERSION ) === $this->version ) {
			return false;
		}
		$failed_at = $this->options->get( self::OPTION_SCHEMA_FAILED_AT );
		if ( is_int( $failed_at ) && ( ( $this->clock )() - $failed_at ) < self::RETRY_AFTER_SECONDS ) {
			return false;
		}
		$this->install();
		return true;
	}

	/**
	 * Runs the idempotent install steps for the current site.
	 *
	 * @return bool Whether the install completed (schema in place and version recorded).
	 */
	public function install(): bool {
		( $this->grant )();

		if ( null === $this->options->get( self::OPTION_INSTALLED_AT ) ) {
			$this->options->set( self::OPTION_INSTALLED_AT, ( $this->clock )(), false );
		}

		if ( true !== ( $this->migrate )() ) {
			$this->options->set( self::OPTION_SCHEMA_FAILED_AT, ( $this->clock )(), false );
			return false;
		}
		$this->options->delete( self::OPTION_SCHEMA_FAILED_AT );

		// Only this tiny key is autoloaded: it is read on every request by maybeInstall().
		$this->options->set( self::OPTION_VERSION, $this->version, true );
		return true;
	}
}
