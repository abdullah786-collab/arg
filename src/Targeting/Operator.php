<?php
/**
 * Rule operators. Which operators a rule type accepts is fixed in Rule.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Targeting;

defined( 'ABSPATH' ) || exit;

/**
 * Rule operators. Which operators a rule type accepts is fixed in Rule.
 */
enum Operator: string {

	case In         = 'in';
	case Equals     = 'equals';
	case StartsWith = 'starts_with';
	case EndsWith   = 'ends_with';
	case Contains   = 'contains';
	case Exists     = 'exists';
	case HostIn     = 'host_in';
	case Direct     = 'direct';
	case Internal   = 'internal';
}
