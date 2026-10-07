<?php
/**
 * Where a placement inserts its ad. Insertion logic belongs to M4; M1 only stores and validates the configuration.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Where a placement inserts its ad. Insertion logic belongs to M4; M1 only stores and validates the configuration.
 */
enum PlacementType: string {

	case BeforeContent  = 'before_content';
	case AfterContent   = 'after_content';
	case AfterParagraph = 'after_paragraph';
	case AfterHeading   = 'after_heading';
	case Percentage     = 'percentage';
	case Manual         = 'manual';
}
