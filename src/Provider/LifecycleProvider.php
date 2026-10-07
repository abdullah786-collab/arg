<?php
/**
 * Wires the lifecycle, settings and security foundation.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Provider;

use RevGuardPro\Container;
use RevGuardPro\Contracts\ServiceProviderInterface;
use RevGuardPro\Lifecycle\Installer;
use RevGuardPro\Security\AdminRequestGuard;
use RevGuardPro\Settings\OptionStoreInterface;
use RevGuardPro\Settings\SettingsRepository;
use RevGuardPro\Settings\WpOptionStore;
use WP_Site;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the lifecycle, settings and security foundation.
 */
final class LifecycleProvider implements ServiceProviderInterface {

	public const SERVICE_OPTIONS   = 'options';
	public const SERVICE_SETTINGS  = 'settings';
	public const SERVICE_INSTALLER = 'installer';
	public const SERVICE_GUARD     = 'admin_request_guard';

	/**
	 * Constructor.
	 *
	 * @param string $basename Plugin basename, used to detect network activation.
	 * @param string $version  Running plugin version.
	 */
	public function __construct(
		private readonly string $basename,
		private readonly string $version
	) {
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Service container.
	 */
	public function register( Container $container ): void {
		$version = $this->version;

		$container->set( self::SERVICE_OPTIONS, static fn(): OptionStoreInterface => new WpOptionStore() );
		$container->set(
			self::SERVICE_SETTINGS,
			static function ( Container $c ): SettingsRepository {
				$store = $c->get( self::SERVICE_OPTIONS );
				assert( $store instanceof OptionStoreInterface );
				return new SettingsRepository( $store );
			}
		);
		$container->set( self::SERVICE_INSTALLER, static fn(): Installer => Installer::forWordPress( $version ) );
		$container->set( self::SERVICE_GUARD, static fn(): AdminRequestGuard => AdminRequestGuard::forWordPress() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Service container.
	 */
	public function boot( Container $container ): void {
		$installer = $container->get( self::SERVICE_INSTALLER );
		assert( $installer instanceof Installer );

		// One autoloaded option read per request; installs only after an update or on a site that has not run it yet.
		$installer->maybeInstall();

		add_action(
			'wp_initialize_site',
			function ( WP_Site $site ) use ( $installer ): void {
				$this->installForNewSite( $site, $installer );
			}
		);
	}

	/**
	 * Installs into a newly created site when the plugin is network-active.
	 *
	 * @param WP_Site   $site      Newly initialized site.
	 * @param Installer $installer Installer for the plugin version.
	 */
	private function installForNewSite( WP_Site $site, Installer $installer ): void {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active_for_network( $this->basename ) ) {
			return;
		}
		switch_to_blog( (int) $site->blog_id );
		$installer->install();
		restore_current_blog();
	}
}
