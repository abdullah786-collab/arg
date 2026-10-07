<?php
/**
 * Boundary to the ad renderer (implemented in M5).
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Content;

use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\Placement;

defined( 'ABSPATH' ) || exit;

/**
 * Produces the markup for an ad in a placement. The engine treats the result as opaque trusted output: it never
 * parses, evaluates or escapes it. Returning an empty string skips the insertion.
 */
interface AdMarkupRendererInterface {

	/**
	 * Renders the ad.
	 *
	 * @param Ad        $ad        Selected ad.
	 * @param Placement $placement Placement being filled.
	 */
	public function render( Ad $ad, Placement $placement ): string;
}
