<?php
/**
 * Interface for HTML creative sanitizers.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rendering;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitizes an HTML creative string before output.
 */
interface HtmlCreativeSanitizerInterface {

	/**
	 * Sanitizes the given HTML and returns the result.
	 *
	 * @param string $html Raw HTML creative.
	 */
	public function sanitize( string $html ): string;
}
