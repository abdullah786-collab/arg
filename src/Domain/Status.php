<?php
/**
 * Lifecycle status shared by ads, groups and placements. Only active entities are ever served.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Lifecycle status shared by ads, groups and placements. Only active entities are ever served.
 */
enum Status: string {

	case Active   = 'active';
	case Inactive = 'inactive';
}
