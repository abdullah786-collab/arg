<?php
/**
 * How a group chooses among its ads. Execution belongs to M2; M1 only stores the choice.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * How a group chooses among its ads. Execution belongs to M2; M1 only stores the choice.
 */
enum RotationMode: string {

	case Weighted = 'weighted';
	case Ordered  = 'ordered';
}
