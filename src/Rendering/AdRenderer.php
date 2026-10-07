<?php
/**
 * Central ad-to-markup renderer.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rendering;

use RevGuardPro\Content\AdMarkupRendererInterface;
use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\Placement;

defined( 'ABSPATH' ) || exit;

/**
 * The only class in the plugin that converts an Ad entity into HTML markup.
 * Implements the AdMarkupRendererInterface contract required by the placement engine.
 *
 * Security invariants (docs/adr/005-rendering-surfaces.md §2):
 * 1. Ad code is passed through the active sanitizer (an allowlist filter, or verbatim only when the site owner
 *    has switched on unfiltered output); the wrapper markup is generated here, never taken from stored text.
 * 2. Any failure returns '' (empty string); no partial markup; no thrown exceptions.
 * 3. Every emitted attribute value is validated by a strict pattern AND escaped with htmlspecialchars().
 */
final class AdRenderer implements AdMarkupRendererInterface {

	/**
	 * Constructor.
	 *
	 * @param HtmlCreativeSanitizerInterface $html  HTML sanitizer for Html-type creatives.
	 */
	public function __construct(
		private readonly HtmlCreativeSanitizerInterface $html
	) {}

	/**
	 * Renders the ad for a placement. Delegates to renderAd().
	 *
	 * @param Ad        $ad        Ad to render.
	 * @param Placement $placement Placement context (unused by this layer; surfaces use it for markRendered).
	 */
	public function render( Ad $ad, Placement $placement ): string {
		return $this->renderAd( $ad );
	}

	/**
	 * Renders an ad, optionally appending extra CSS classes to the wrapper.
	 *
	 * Wrapper format: <div class="rgp-ad rgp-ad--html EXTRA" data-rgp-ad="ID">INNER</div>
	 * - EXTRA: up to 3 tokens from $extra_class matching ^[A-Za-z0-9_-]{1,40}$; omitted when empty.
	 * - Returns '' when: ad has no id or the sanitized code is blank.
	 * - Never calls markRendered (surfaces do that).
	 *
	 * @param Ad     $ad          Ad to render.
	 * @param string $extra_class Extra CSS class tokens (space-separated).
	 */
	public function renderAd( Ad $ad, string $extra_class = '' ): string {
		if ( null === $ad->id ) {
			return '';
		}

		$sanitized = $this->html->sanitize( $ad->creative );
		if ( '' === trim( $sanitized ) ) {
			return '';
		}
		$inner = $sanitized;
		$class = 'rgp-ad rgp-ad--html';
		// Collect up to 3 valid extra class tokens.
		$extra_tokens = array();
		if ( '' !== $extra_class ) {
			$tokens = preg_split( '/\s+/', trim( $extra_class ) );
			if ( false !== $tokens ) {
				foreach ( $tokens as $token ) {
					if ( '' === $token ) {
						continue;
					}
					if ( 1 === preg_match( '/^[A-Za-z0-9_-]{1,40}$/D', $token ) ) {
						$extra_tokens[] = $token;
						if ( 3 === count( $extra_tokens ) ) {
							break;
						}
					}
				}
			}
		}
		if ( array() !== $extra_tokens ) {
			$class .= ' ' . implode( ' ', $extra_tokens );
		}

		$id = $ad->id;
		return '<div class="' . htmlspecialchars( $class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '" data-rgp-ad="' . $id . '">' . $inner . '</div>';
	}
}
