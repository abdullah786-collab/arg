<?php
/**
 * How a rule behaves under full-page caching, weakest to strongest hazard.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Targeting;

defined( 'ABSPATH' ) || exit;

/**
 * How a rule behaves under full-page caching, weakest to strongest hazard.
 */
enum CacheSensitivity: string {

	case PageStable     = 'page_stable';
	case VisitorVariant = 'visitor_variant';
	case RequestVariant = 'request_variant';

	/**
	 * Hazard rank: higher is a stronger reason not to freeze the outcome in a cached page.
	 */
	public function rank(): int {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts -- False positive: $this is valid inside an enum method.
			self::PageStable     => 0,
			self::VisitorVariant => 1,
			self::RequestVariant => 2,
		};
	}
}
