<?php
/**
 * Kinds of top-level content segments.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Content;

defined( 'ABSPATH' ) || exit;

/**
 * What a top-level segment of post content is.
 */
enum SegmentKind: string {

	case Paragraph = 'paragraph';
	case Heading   = 'heading';
	case Text      = 'text';
	case Wrapper   = 'wrapper';
	case Other     = 'other';
}
