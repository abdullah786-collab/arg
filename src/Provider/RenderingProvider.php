<?php
/**
 * Wires the rendering surfaces and the automatic insertion hook.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Provider;

use RevGuardPro\Container;
use RevGuardPro\Content\Wp\WpContentContextFactory;
use RevGuardPro\Contracts\ServiceProviderInterface;
use RevGuardPro\Domain\Repository\AdGroupRepositoryInterface;
use RevGuardPro\Domain\Repository\AdRepositoryInterface;
use RevGuardPro\Rendering\AdBlock;
use RevGuardPro\Rendering\AdRenderer;
use RevGuardPro\Rendering\AutomaticContentFilter;
use RevGuardPro\Rendering\RenderState;
use RevGuardPro\Rendering\RequestTargetingContext;
use RevGuardPro\Rendering\ShortcodeSurface;
use RevGuardPro\Rendering\StoredCreativePolicy;
use RevGuardPro\Rendering\SurfaceAuthorization;
use RevGuardPro\Rendering\SurfaceRenderer;
use RevGuardPro\Rendering\VerbatimHtmlSanitizer;
use RevGuardPro\Rendering\WpKsesHtmlSanitizer;
use RevGuardPro\Rotation\GroupSelector;
use RevGuardPro\Settings\SettingsRepository;
use RevGuardPro\Support\ClockInterface;
use RevGuardPro\Targeting\Context\WpContextFactory;
use RevGuardPro\Targeting\TargetingEvaluator;
use RevGuardPro\Targeting\TargetingRequirements;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the renderer, the shortcode, the block and the automatic filter. Registration is lazy: hooks are added
 * at boot, but no service is built until a hook actually runs, and the front end enqueues no assets.
 */
final class RenderingProvider implements ServiceProviderInterface {

	public const SERVICE_STATE           = 'render_state';
	public const SERVICE_REQUEST_CONTEXT = 'request_targeting_context';
	public const SERVICE_RENDERER_CORE   = 'ad_renderer';
	public const SERVICE_SURFACE         = 'surface_renderer';
	public const SERVICE_SHORTCODE       = 'shortcode_surface';
	public const SERVICE_BLOCK           = 'ad_block';
	public const SERVICE_FILTER          = 'automatic_content_filter';

	/**
	 * Registers service factories.
	 *
	 * @param Container $container Service container.
	 */
	public function register( Container $container ): void {
		$container->set( self::SERVICE_STATE, static fn(): RenderState => new RenderState() );
		$container->set(
			self::SERVICE_REQUEST_CONTEXT,
			static function () use ( $container ): RequestTargetingContext {
				$factory = $container->get( TargetingProvider::SERVICE_CONTEXT_FACTORY );
				assert( $factory instanceof WpContextFactory );
				return new RequestTargetingContext( static fn( TargetingRequirements $requirements ) => $factory->build( $requirements ) );
			}
		);
		$container->set(
			self::SERVICE_RENDERER_CORE,
			static function () use ( $container ): AdRenderer {
				$settings = $container->get( LifecycleProvider::SERVICE_SETTINGS );
				assert( $settings instanceof SettingsRepository );
				$sanitizer = $settings->bool( 'allow_unfiltered_html_creatives' ) ? new VerbatimHtmlSanitizer() : new WpKsesHtmlSanitizer();
				return new AdRenderer( $sanitizer );
			}
		);
		// The M4 engine and every surface share this one renderer.
		$container->set( ContentProvider::SERVICE_RENDERER, static fn() => $container->get( self::SERVICE_RENDERER_CORE ) );
		// Storage-time creative policy (the M1 boundary).
		$container->set(
			PersistenceProvider::SERVICE_POLICY,
			static fn(): StoredCreativePolicy => new StoredCreativePolicy( new WpKsesHtmlSanitizer(), static fn(): bool => current_user_can( 'unfiltered_html' ) )
		);
		$container->set( self::SERVICE_SURFACE, fn() => $this->surface( $container ) );
		$container->set(
			self::SERVICE_SHORTCODE,
			static function () use ( $container ): ShortcodeSurface {
				$surface = $container->get( self::SERVICE_SURFACE );
				assert( $surface instanceof SurfaceRenderer );
				return new ShortcodeSurface( $surface, new WpContentContextFactory() );
			}
		);
		$container->set(
			self::SERVICE_BLOCK,
			static function () use ( $container ): AdBlock {
				$surface = $container->get( self::SERVICE_SURFACE );
				$ads     = $container->get( PersistenceProvider::SERVICE_ADS );
				$groups  = $container->get( PersistenceProvider::SERVICE_GROUPS );
				assert( $surface instanceof SurfaceRenderer && $ads instanceof AdRepositoryInterface && $groups instanceof AdGroupRepositoryInterface );
				return new AdBlock( $surface, new WpContentContextFactory(), $ads, $groups, dirname( REVGUARD_PRO_FILE ) . '/blocks/ad' );
			}
		);
		$container->set(
			self::SERVICE_FILTER,
			static function () use ( $container ): AutomaticContentFilter {
				$state    = $container->get( self::SERVICE_STATE );
				$settings = $container->get( LifecycleProvider::SERVICE_SETTINGS );
				assert( $state instanceof RenderState && $settings instanceof SettingsRepository );
				return new AutomaticContentFilter(
					static fn() => $container->get( ContentProvider::SERVICE_ENGINE ),
					static fn() => ( new WpContentContextFactory() )->build(),
					static fn(): int => (int) get_the_ID(),
					$state,
					$settings
				);
			}
		);
	}

	/**
	 * Adds the hooks. Nothing is built here: every callback resolves its service on first use.
	 *
	 * @param Container $container Service container.
	 */
	public function boot( Container $container ): void {
		add_action(
			'init',
			static function () use ( $container ): void {
				add_shortcode(
					ShortcodeSurface::TAG,
					static function ( $atts = array() ) use ( $container ): string {
						$shortcode = $container->get( self::SERVICE_SHORTCODE );
						return $shortcode instanceof ShortcodeSurface ? $shortcode->handle( $atts ) : '';
					}
				);
				$block = $container->get( self::SERVICE_BLOCK );
				if ( $block instanceof AdBlock ) {
					$block->register();
				}
			}
		);
		add_action(
			'enqueue_block_editor_assets',
			static function () use ( $container ): void {
				$block = $container->get( self::SERVICE_BLOCK );
				if ( $block instanceof AdBlock ) {
					$block->enqueueEditorAssets();
				}
			}
		);
		add_filter(
			'the_content',
			static function ( $content ) use ( $container ) {
				$filter = $container->get( self::SERVICE_FILTER );
				return $filter instanceof AutomaticContentFilter ? $filter->filter( $content ) : $content;
			},
			AutomaticContentFilter::PRIORITY
		);
	}

	/**
	 * Builds the shared surface renderer from existing services.
	 *
	 * @param Container $container Service container.
	 */
	private function surface( Container $container ): SurfaceRenderer {
		$ads       = $container->get( PersistenceProvider::SERVICE_ADS );
		$groups    = $container->get( PersistenceProvider::SERVICE_GROUPS );
		$selector  = $container->get( RotationProvider::SERVICE_GROUP_SELECTOR );
		$evaluator = $container->get( TargetingProvider::SERVICE_EVALUATOR );
		$request   = $container->get( self::SERVICE_REQUEST_CONTEXT );
		$renderer  = $container->get( self::SERVICE_RENDERER_CORE );
		$state     = $container->get( self::SERVICE_STATE );
		$clock     = $container->get( PersistenceProvider::SERVICE_CLOCK );
		assert( $ads instanceof AdRepositoryInterface && $groups instanceof AdGroupRepositoryInterface && $selector instanceof GroupSelector );
		assert( $evaluator instanceof TargetingEvaluator && $request instanceof RequestTargetingContext && $renderer instanceof AdRenderer );
		assert( $state instanceof RenderState && $clock instanceof ClockInterface );

		return new SurfaceRenderer(
			$ads,
			$groups,
			$selector,
			$evaluator,
			$request,
			$renderer,
			$state,
			$clock,
			new SurfaceAuthorization(
				static fn( int $post_id ): int => (int) get_post_field( 'post_author', $post_id ),
				static fn( int $user_id, string $cap ): bool => user_can( $user_id, $cap )
			)
		);
	}
}
