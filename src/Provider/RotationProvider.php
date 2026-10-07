<?php
/**
 * Wires scheduling and rotation services.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Provider;

use RevGuardPro\Container;
use RevGuardPro\Contracts\ServiceProviderInterface;
use RevGuardPro\Domain\Repository\AdGroupRepositoryInterface;
use RevGuardPro\Domain\Repository\AdRepositoryInterface;
use RevGuardPro\Rotation\AdEligibility;
use RevGuardPro\Rotation\CandidateResolver;
use RevGuardPro\Rotation\GroupAdSelector;
use RevGuardPro\Rotation\GroupSelector;
use RevGuardPro\Rotation\RotationPicker;
use RevGuardPro\Support\ClockInterface;
use RevGuardPro\Support\NativeRandom;
use RevGuardPro\Support\RandomSourceInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the selection services. Lazy: nothing runs until a selector is requested.
 */
final class RotationProvider implements ServiceProviderInterface {

	public const SERVICE_RANDOM         = 'random';
	public const SERVICE_ELIGIBILITY    = 'ad_eligibility';
	public const SERVICE_GROUP_SELECTOR = 'group_selector';
	public const SERVICE_GROUP_AD       = 'group_ad_selector';

	/**
	 * Registers service factories.
	 *
	 * @param Container $container Service container.
	 */
	public function register( Container $container ): void {
		$container->set( self::SERVICE_RANDOM, static fn(): RandomSourceInterface => new NativeRandom() );
		$container->set( self::SERVICE_ELIGIBILITY, static fn(): AdEligibility => new AdEligibility() );
		$container->set(
			self::SERVICE_GROUP_SELECTOR,
			static function () use ( $container ): GroupSelector {
				$random      = $container->get( self::SERVICE_RANDOM );
				$eligibility = $container->get( self::SERVICE_ELIGIBILITY );
				assert( $random instanceof RandomSourceInterface && $eligibility instanceof AdEligibility );
				return new GroupSelector( new CandidateResolver( $eligibility ), new RotationPicker( $random ) );
			}
		);
		$container->set(
			self::SERVICE_GROUP_AD,
			static function () use ( $container ): GroupAdSelector {
				$groups   = $container->get( PersistenceProvider::SERVICE_GROUPS );
				$ads      = $container->get( PersistenceProvider::SERVICE_ADS );
				$selector = $container->get( self::SERVICE_GROUP_SELECTOR );
				$clock    = $container->get( PersistenceProvider::SERVICE_CLOCK );
				assert( $groups instanceof AdGroupRepositoryInterface && $ads instanceof AdRepositoryInterface );
				assert( $selector instanceof GroupSelector && $clock instanceof ClockInterface );
				return new GroupAdSelector( $groups, $ads, $selector, $clock );
			}
		);
	}

	/**
	 * Nothing to hook.
	 *
	 * @param Container $container Service container.
	 */
	public function boot( Container $container ): void {
	}
}
