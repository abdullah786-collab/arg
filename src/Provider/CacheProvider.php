<?php
/**
 * Cache compatibility provider (M10).
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Provider;

use RevGuardPro\Cache\CachePolicy;
use RevGuardPro\Cache\CacheSignal;
use RevGuardPro\Container;
use RevGuardPro\Contracts\ServiceProviderInterface;
use RevGuardPro\Rendering\RenderState;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the one `shutdown` hook that emits the cache signal (ADR 010). No settings, endpoint or purge logic.
 */
final class CacheProvider implements ServiceProviderInterface {

	public const SERVICE_POLICY = 'cache_policy';
	public const SERVICE_SIGNAL = 'cache_signal';

	/**
	 * Registers services.
	 *
	 * @param Container $container Service container.
	 */
	public function register( Container $container ): void {
		$container->set( self::SERVICE_POLICY, static fn(): CachePolicy => new CachePolicy() );
		$container->set(
			self::SERVICE_SIGNAL,
			static function () use ( $container ): CacheSignal {
				$state  = $container->get( RenderingProvider::SERVICE_STATE );
				$policy = $container->get( self::SERVICE_POLICY );
				assert( $state instanceof RenderState && $policy instanceof CachePolicy );
				return CacheSignal::forWordPress( $state, $policy );
			}
		);
	}

	/**
	 * Hooks `shutdown` at priority 0, before core flushes output buffers (priority 1), so buffering page caches see the constant.
	 *
	 * @param Container $container Service container.
	 */
	public function boot( Container $container ): void {
		if ( is_admin() ) {
			return;
		}
		add_action(
			'shutdown',
			static function () use ( $container ): void {
				$signal = $container->get( self::SERVICE_SIGNAL );
				if ( $signal instanceof CacheSignal ) {
					$signal->apply();
				}
			},
			0
		);
	}
}
