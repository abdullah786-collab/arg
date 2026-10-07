<?php
/**
 * Default renderer: renders nothing.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Content;

use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\Placement;

defined( 'ABSPATH' ) || exit;

/**
 * Until M5 supplies the real renderer, nothing is rendered and therefore nothing is inserted.
 */
final class NullAdMarkupRenderer implements AdMarkupRendererInterface {

	/**
	 * Renders nothing.
	 *
	 * @param Ad        $ad        Ad.
	 * @param Placement $placement Placement.
	 */
	public function render( Ad $ad, Placement $placement ): string {
		return '';
	}
}
