<?php
/**
 * Domain entity kinds, used by the change notification boundary.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Domain entity kinds, used by the change notification boundary.
 */
enum EntityKind: string {

	case Ad        = 'ad';
	case Group     = 'group';
	case Placement = 'placement';
}
