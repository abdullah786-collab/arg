<?php
/**
 * Shortcode surface for [revguard_pro].
 *
 * Shortcodes are processed after blocks and before the automatic insertion (priority 99).
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rendering;

use RevGuardPro\Content\Wp\WpContentContextFactory;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress-aware shortcode surface. The only class in this package that calls WordPress functions.
 * Shortcodes are processed after blocks and before the automatic insertion (priority 99).
 */
final class ShortcodeSurface {

	/**
	 * Shortcode tag name.
	 *
	 * @var string
	 */
	public const TAG = 'revguard_pro';

	/**
	 * Constructor.
	 *
	 * @param SurfaceRenderer         $surface  Renderer.
	 * @param WpContentContextFactory $contexts Content context factory.
	 */
	public function __construct(
		private readonly SurfaceRenderer $surface,
		private readonly WpContentContextFactory $contexts
	) {
	}

	/**
	 * Registers the shortcode with WordPress.
	 */
	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'handle' ) );
	}

	/**
	 * Handles the shortcode. Never throws; any Throwable is caught and '' returned.
	 * Does not echo; returns the markup string.
	 *
	 * @param mixed $atts Shortcode attributes (array or '' when WordPress has no attributes).
	 */
	public function handle( $atts = array() ): string {
		$atts = is_array( $atts ) ? $atts : array();

		try {
			$args = ShortcodeArguments::fromAtts(
				shortcode_atts(
					array(
						'ad'    => '',
						'group' => '',
						'class' => '',
					),
					$atts,
					self::TAG
				)
			);

			if ( null === $args ) {
				return '';
			}

			return $this->surface->render(
				$args->type,
				$args->id,
				(int) get_the_ID(),
				$this->contexts->build(),
				$args->class
			);
		} catch ( \Throwable ) {
			return '';
		}
	}
}
