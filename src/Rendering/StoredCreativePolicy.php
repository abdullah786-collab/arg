<?php
/**
 * Storage-time creative policy.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rendering;

use Closure;
use RevGuardPro\Domain\CreativeType;
use RevGuardPro\Domain\Repository\CreativePolicyInterface;

defined( 'ABSPATH' ) || exit;

/**
 * The storage boundary: ad code is filtered with the allowlist unless the saving user holds `unfiltered_html`
 * (so a user without that capability can never store scripts). Render-time filtering is a second, independent layer.
 */
final class StoredCreativePolicy implements CreativePolicyInterface {

	/**
	 * Constructor.
	 *
	 * @param HtmlCreativeSanitizerInterface $html           Html filter.
	 * @param Closure                        $can_unfiltered Returns whether the current user may save unfiltered html.
	 */
	public function __construct(
		private readonly HtmlCreativeSanitizerInterface $html,
		private readonly Closure $can_unfiltered
	) {
	}

	/**
	 * Prepares a creative for storage.
	 *
	 * @param CreativeType $type     Creative type.
	 * @param string       $creative Creative as provided.
	 */
	public function prepareForStorage( CreativeType $type, string $creative ): string {
		return ( $this->can_unfiltered )() ? $creative : $this->html->sanitize( $creative );
	}
}
