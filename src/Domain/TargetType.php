<?php
/**
 * What a placement points at.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * What a placement points at.
 */
enum TargetType: string {

	case Ad    = 'ad';
	case Group = 'group';
}
