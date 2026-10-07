<?php
/**
 * A top-level content segment.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Content;

defined( 'ABSPATH' ) || exit;

/**
 * A complete top-level unit of content with its byte range. Insertion boundaries are segment starts and ends.
 */
final class Segment {

	/**
	 * Constructor.
	 *
	 * @param SegmentKind $kind         Kind.
	 * @param int         $start        Start byte offset (inclusive).
	 * @param int         $end          End byte offset (exclusive).
	 * @param int         $weight       Visible text length in bytes (whitespace and &nbsp; removed).
	 * @param int|null    $level        Heading level 1 to 6, for headings.
	 * @param int|null    $placement_id Placement id of an engine wrapper.
	 */
	public function __construct(
		public readonly SegmentKind $kind,
		public readonly int $start,
		public readonly int $end,
		public readonly int $weight,
		public readonly ?int $level = null,
		public readonly ?int $placement_id = null
	) {
	}
}
