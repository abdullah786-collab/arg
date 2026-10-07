<?php
/**
 * Wires the content placement engine.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Provider;

use RevGuardPro\Container;
use RevGuardPro\Content\AdMarkupRendererInterface;
use RevGuardPro\Content\ContentAnalyzer;
use RevGuardPro\Content\InsertionApplier;
use RevGuardPro\Content\InsertionPlanner;
use RevGuardPro\Content\NullAdMarkupRenderer;
use RevGuardPro\Content\PlacementEngine;
use RevGuardPro\Content\Wp\WpContentContextFactory;
use RevGuardPro\Contracts\ServiceProviderInterface;
use RevGuardPro\Domain\Repository\AdGroupRepositoryInterface;
use RevGuardPro\Domain\Repository\AdRepositoryInterface;
use RevGuardPro\Domain\Repository\PlacementRepositoryInterface;
use RevGuardPro\Rendering\RequestTargetingContext;
use RevGuardPro\Rotation\GroupSelector;
use RevGuardPro\Support\ClockInterface;
use RevGuardPro\Targeting\TargetingEvaluator;
use RevGuardPro\Targeting\TargetingRequirements;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the engine and its collaborators. It adds no hook: nothing is inserted into any output until a later
 * milestone calls the engine with a real renderer (M5) and the Free/Pro coexistence guard exists (M6).
 */
final class ContentProvider implements ServiceProviderInterface {

	public const SERVICE_RENDERER = 'ad_markup_renderer';
	public const SERVICE_CONTEXT  = 'content_context_factory';
	public const SERVICE_ENGINE   = 'placement_engine';

	/**
	 * Registers service factories.
	 *
	 * @param Container $container Service container.
	 */
	public function register( Container $container ): void {
		$container->set( self::SERVICE_RENDERER, static fn(): AdMarkupRendererInterface => new NullAdMarkupRenderer() );
		$container->set( self::SERVICE_CONTEXT, static fn(): WpContentContextFactory => new WpContentContextFactory() );
		$container->set(
			self::SERVICE_ENGINE,
			static function () use ( $container ): PlacementEngine {
				$placements = $container->get( PersistenceProvider::SERVICE_PLACEMENTS );
				$groups     = $container->get( PersistenceProvider::SERVICE_GROUPS );
				$ads        = $container->get( PersistenceProvider::SERVICE_ADS );
				$clock      = $container->get( PersistenceProvider::SERVICE_CLOCK );
				$selector   = $container->get( RotationProvider::SERVICE_GROUP_SELECTOR );
				$evaluator  = $container->get( TargetingProvider::SERVICE_EVALUATOR );
				$renderer   = $container->get( self::SERVICE_RENDERER );
				$request    = $container->get( RenderingProvider::SERVICE_REQUEST_CONTEXT );
				assert( $placements instanceof PlacementRepositoryInterface && $groups instanceof AdGroupRepositoryInterface && $ads instanceof AdRepositoryInterface );
				assert( $clock instanceof ClockInterface && $selector instanceof GroupSelector && $evaluator instanceof TargetingEvaluator );
				assert( $renderer instanceof AdMarkupRendererInterface && $request instanceof RequestTargetingContext );

				return new PlacementEngine(
					$placements,
					$groups,
					$ads,
					$selector,
					$evaluator,
					new ContentAnalyzer(),
					new InsertionPlanner(),
					new InsertionApplier(),
					$renderer,
					$clock,
					static fn( TargetingRequirements $requirements ) => $request->forRequirements( $requirements )
				);
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
