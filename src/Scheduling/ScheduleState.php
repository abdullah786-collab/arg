<?php
/**
 * Schedule state at a point in time.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Scheduling;

defined( 'ABSPATH' ) || exit;

/**
 * Where "now" falls relative to a schedule. A schedule without bounds is always Active.
 */
enum ScheduleState: string {

	case Pending = 'pending';
	case Active  = 'active';
	case Expired = 'expired';
}
