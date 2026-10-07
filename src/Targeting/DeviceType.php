<?php
/**
 * Device class derived from the User-Agent.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Targeting;

defined( 'ABSPATH' ) || exit;

/**
 * Device class derived from the User-Agent.
 */
enum DeviceType: string {

	case Desktop = 'desktop';
	case Tablet  = 'tablet';
	case Mobile  = 'mobile';
}
