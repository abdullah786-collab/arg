<?php
/**
 * Plugin bootstrap: builds the container and boots every provider.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro;

use RevGuardPro\Contracts\ServiceProviderInterface;
use RevGuardPro\Provider\AdminProvider;
use RevGuardPro\Provider\AnalyticsProvider;
use RevGuardPro\Provider\CacheProvider;
use RevGuardPro\Provider\ContentProvider;
use RevGuardPro\Provider\GuardrailsProvider;
use RevGuardPro\Provider\LifecycleProvider;
use RevGuardPro\Provider\LocalizationProvider;
use RevGuardPro\Provider\PersistenceProvider;
use RevGuardPro\Provider\RenderingProvider;
use RevGuardPro\Provider\RotationProvider;
use RevGuardPro\Provider\TargetingProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap: builds the container and boots every provider.
 */
final class Plugin {

	/**
	 * Constructor.
	 *
	 * @param string $basename Plugin basename.
	 * @param string $version  Plugin version.
	 */
	public function __construct(
		private readonly string $basename,
		private readonly string $version
	) {
	}

	/**
	 * Registers all providers, then boots them.
	 */
	public function boot(): void {
		$container = new Container();
		$providers = $this->providers();

		foreach ( $providers as $provider ) {
			$provider->register( $container );
		}
		foreach ( $providers as $provider ) {
			$provider->boot( $container );
		}
	}

	/**
	 * Feature providers in boot order. Later milestones append here.
	 *
	 * @return list<ServiceProviderInterface>
	 */
	private function providers(): array {
		return array(
			new LifecycleProvider( $this->basename, $this->version ),
			new LocalizationProvider( $this->basename ),
			new PersistenceProvider(),
			new RotationProvider(),
			new TargetingProvider(),
			new ContentProvider(),
			new RenderingProvider(),
			new CacheProvider(),
			new AnalyticsProvider(),
			new GuardrailsProvider(),
			new AdminProvider(),
		);
	}
}
