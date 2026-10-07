<?php
/**
 * Wires the targeting services.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Provider;

use RevGuardPro\Container;
use RevGuardPro\Contracts\ServiceProviderInterface;
use RevGuardPro\Targeting\Context\ContextBuilder;
use RevGuardPro\Targeting\Context\WpContextFactory;
use RevGuardPro\Targeting\TargetingEvaluator;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the pure evaluator and the WordPress context factory. Lazy: nothing runs until requested.
 */
final class TargetingProvider implements ServiceProviderInterface {

	public const SERVICE_EVALUATOR       = 'targeting_evaluator';
	public const SERVICE_CONTEXT_FACTORY = 'targeting_context_factory';

	/**
	 * Registers service factories.
	 *
	 * @param Container $container Service container.
	 */
	public function register( Container $container ): void {
		$container->set( self::SERVICE_EVALUATOR, static fn(): TargetingEvaluator => new TargetingEvaluator() );
		$container->set( self::SERVICE_CONTEXT_FACTORY, static fn(): WpContextFactory => new WpContextFactory( new ContextBuilder() ) );
	}

	/**
	 * Nothing to hook.
	 *
	 * @param Container $container Service container.
	 */
	public function boot( Container $container ): void {
	}
}
