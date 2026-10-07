<?php
/**
 * The closed set of targeting rule types.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Targeting;

defined( 'ABSPATH' ) || exit;

/**
 * The closed set of targeting rule types.
 */
enum RuleType: string {

	case Device     = 'device';
	case PostType   = 'post_type';
	case Taxonomy   = 'taxonomy';
	case UserState  = 'user_state';
	case UserRole   = 'user_role';
	case Url        = 'url';
	case Referrer   = 'referrer';
	case QueryParam = 'query_param';
}
