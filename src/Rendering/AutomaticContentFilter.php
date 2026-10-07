<?php
/**
 * The `the_content` adapter for automatic insertion.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rendering;

use Closure;
use RevGuardPro\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Thin adapter around PlacementEngine: it contains no placement logic. Every early exit happens before anything
 * is built, and any Throwable returns the original content (a page must never fail because of an ad).
 */
final class AutomaticContentFilter {

	public const PRIORITY = 99;

	/**
	 * Constructor.
	 *
	 * @param Closure            $engine   Closure(): PlacementEngine, resolved lazily.
	 * @param Closure            $contexts Closure(): ContentContext.
	 * @param Closure            $post_id  Closure(): int, the current content scope.
	 * @param RenderState        $state    Request state.
	 * @param SettingsRepository $settings Settings.
	 */
	public function __construct(
		private readonly Closure $engine,
		private readonly Closure $contexts,
		private readonly Closure $post_id,
		private readonly RenderState $state,
		private readonly SettingsRepository $settings
	) {
	}

	/**
	 * Registers the filter. Runs after blocks (9), wpautop (10) and shortcodes (11).
	 */
	public function register(): void {
		add_filter( 'the_content', array( $this, 'filter' ), self::PRIORITY );
	}

	/**
	 * Inserts ads into the content.
	 *
	 * @param mixed $content Post content.
	 * @return mixed
	 */
	public function filter( $content ) {
		if ( ! is_string( $content ) || '' === $content || ! $this->settings->bool( 'automatic_insertion' ) ) {
			return $content;
		}
		try {
			$context = ( $this->contexts )();
			if ( ! $context->allowsInsertion() ) {
				return $content;
			}
			$scope  = ( $this->post_id )();
			$result = ( $this->engine )()->run( $content, $context, $this->state->renderedIds( $scope ) );
			foreach ( $result->insertions as $insertion ) {
				$this->state->markRendered( $scope, $insertion->ad_id );
			}
			$this->state->addProfile( $result->cache_profile );
			$this->state->addValidUntil( $result->valid_until );
			return $result->content;
		} catch ( \Throwable ) {
			return $content;
		}
	}
}
