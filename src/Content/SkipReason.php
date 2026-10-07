<?php
/**
 * Why a placement did not insert anything.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Stable reason codes recorded for every placement that was skipped in a pass.
 */
enum SkipReason: string {

	case ContextBlocked     = 'context_blocked';
	case Targeting          = 'targeting';
	case NoTarget           = 'no_target';
	case NoAd               = 'no_ad';
	case EmptyMarkup        = 'empty_markup';
	case Manual             = 'manual';
	case NoBoundary         = 'no_boundary';
	case UnsafeContent      = 'unsafe_content';
	case BoundaryTaken      = 'boundary_taken';
	case DuplicateAd        = 'duplicate_ad';
	case DuplicatePlacement = 'duplicate_placement';
	case LimitReached       = 'limit_reached';
}
