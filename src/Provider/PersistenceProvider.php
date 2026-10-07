<?php
/**
 * Wires the domain persistence services.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Provider;

use RevGuardPro\Cache\DataVersion;
use RevGuardPro\Cache\WpChangeNotifier;
use RevGuardPro\Container;
use RevGuardPro\Contracts\ServiceProviderInterface;
use RevGuardPro\Domain\Repository\AdGroupRepositoryInterface;
use RevGuardPro\Domain\Repository\AdRepositoryInterface;
use RevGuardPro\Domain\Repository\ChangeNotifierInterface;
use RevGuardPro\Domain\Repository\CreativePolicyInterface;
use RevGuardPro\Domain\Repository\PlacementRepositoryInterface;
use RevGuardPro\Persistence\PassThroughCreativePolicy;
use RevGuardPro\Persistence\WpdbAdGroupRepository;
use RevGuardPro\Persistence\WpdbAdRepository;
use RevGuardPro\Persistence\WpdbPlacementRepository;
use RevGuardPro\Settings\OptionStoreInterface;
use RevGuardPro\Settings\WpOptionStore;
use RevGuardPro\Support\ClockInterface;
use RevGuardPro\Support\SystemClock;

defined( 'ABSPATH' ) || exit;

/**
 * Registers repositories and their collaborators. Registration is lazy: nothing touches the database until a
 * repository is first used, and the table prefix is read per query so multisite switching is always honored.
 */
final class PersistenceProvider implements ServiceProviderInterface {

	public const SERVICE_CLOCK      = 'clock';
	public const SERVICE_NOTIFIER   = 'change_notifier';
	public const SERVICE_POLICY     = 'creative_policy';
	public const SERVICE_ADS        = 'ad_repository';
	public const SERVICE_GROUPS     = 'group_repository';
	public const SERVICE_PLACEMENTS = 'placement_repository';

	/**
	 * Registers service factories.
	 *
	 * @param Container $container Service container.
	 */
	public function register( Container $container ): void {
		$container->set( self::SERVICE_CLOCK, static fn(): ClockInterface => new SystemClock() );
		$container->set( self::SERVICE_POLICY, static fn(): CreativePolicyInterface => new PassThroughCreativePolicy() );
		$container->set(
			self::SERVICE_NOTIFIER,
			static fn(): ChangeNotifierInterface => new WpChangeNotifier( new DataVersion( self::options( $container ) ) )
		);
		$container->set(
			self::SERVICE_ADS,
			static function () use ( $container ): AdRepositoryInterface {
				global $wpdb;
				return new WpdbAdRepository(
					$wpdb,
					self::clock( $container ),
					self::notifier( $container ),
					self::policy( $container )
				);
			}
		);
		$container->set(
			self::SERVICE_GROUPS,
			static function () use ( $container ): AdGroupRepositoryInterface {
				global $wpdb;
				return new WpdbAdGroupRepository( $wpdb, self::clock( $container ), self::notifier( $container ) );
			}
		);
		$container->set(
			self::SERVICE_PLACEMENTS,
			static function () use ( $container ): PlacementRepositoryInterface {
				global $wpdb;
				return new WpdbPlacementRepository( $wpdb, self::clock( $container ), self::notifier( $container ) );
			}
		);
	}

	/**
	 * Nothing to hook: repositories are used by later milestones.
	 *
	 * @param Container $container Service container.
	 */
	public function boot( Container $container ): void {
	}

	/**
	 * Options store from the container.
	 *
	 * @param Container $container Service container.
	 */
	private static function options( Container $container ): OptionStoreInterface {
		$store = $container->has( LifecycleProvider::SERVICE_OPTIONS ) ? $container->get( LifecycleProvider::SERVICE_OPTIONS ) : new WpOptionStore();
		return $store instanceof OptionStoreInterface ? $store : new WpOptionStore();
	}

	/**
	 * Clock from the container.
	 *
	 * @param Container $container Service container.
	 */
	private static function clock( Container $container ): ClockInterface {
		$clock = $container->get( self::SERVICE_CLOCK );
		return $clock instanceof ClockInterface ? $clock : new SystemClock();
	}

	/**
	 * Change notifier from the container.
	 *
	 * @param Container $container Service container.
	 */
	private static function notifier( Container $container ): ChangeNotifierInterface {
		$notifier = $container->get( self::SERVICE_NOTIFIER );
		assert( $notifier instanceof ChangeNotifierInterface );
		return $notifier;
	}

	/**
	 * Creative policy from the container.
	 *
	 * @param Container $container Service container.
	 */
	private static function policy( Container $container ): CreativePolicyInterface {
		$policy = $container->get( self::SERVICE_POLICY );
		assert( $policy instanceof CreativePolicyInterface );
		return $policy;
	}
}
