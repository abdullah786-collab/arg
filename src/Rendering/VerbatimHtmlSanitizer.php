<?php
/**
 * Verbatim HTML sanitizer (trusted-administrator mode).
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rendering;

defined( 'ABSPATH' ) || exit;

/**
 * Returns the input HTML unchanged. Use only when the site owner has explicitly enabled trusted-administrator mode,
 * meaning the creative was entered by a trusted admin and no sanitization is needed.
 */
final class VerbatimHtmlSanitizer implements HtmlCreativeSanitizerInterface {

	/**
	 * Returns the input unchanged.
	 *
	 * @param string $html Raw HTML creative.
	 */
	public function sanitize( string $html ): string {
		return $html;
	}
}
