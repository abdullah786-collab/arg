<?php
/**
 * How the rules of a definition combine: all (AND) or any (OR).
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Targeting;

defined( 'ABSPATH' ) || exit;

/**
 * How the rules of a definition combine: all (AND) or any (OR).
 */
enum Mode: string {

	case All = 'all';
	case Any = 'any';
}
