<?php
/**
 * The segment map of one piece of content.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Result of analyzing content: ordered top-level segments, whether scanning had to give up, and which
 * placement markers already exist anywhere in the content.
 */
final class ContentMap {

	/**
	 * Constructor.
	 *
	 * @param array<Segment> $segments      Top-level segments, ascending and non-overlapping.
	 * @param int            $length        Byte length of the analyzed content.
	 * @param bool           $unsafe        Whether scanning gave up (limits exceeded or unterminated markup).
	 * @param array<int,int> $placement_ids Ids of every engine marker pair found, keys equal values.
	 */
	public function __construct(
		public readonly array $segments,
		public readonly int $length,
		public readonly bool $unsafe,
		public readonly array $placement_ids
	) {
	}

	/**
	 * Indexes of paragraph segments that contain visible text.
	 *
	 * @return array<int>
	 */
	public function paragraphIndexes(): array {
		$out = array();
		foreach ( $this->segments as $index => $segment ) {
			if ( SegmentKind::Paragraph === $segment->kind && $segment->weight > 0 ) {
				$out[] = $index;
			}
		}
		return $out;
	}

	/**
	 * Indexes of headings of exactly the given level.
	 *
	 * @param int $level Heading level.
	 * @return array<int>
	 */
	public function headingIndexes( int $level ): array {
		$out = array();
		foreach ( $this->segments as $index => $segment ) {
			if ( SegmentKind::Heading === $segment->kind && $segment->level === $level ) {
				$out[] = $index;
			}
		}
		return $out;
	}

	/**
	 * Total visible text weight.
	 */
	public function totalWeight(): int {
		$total = 0;
		foreach ( $this->segments as $segment ) {
			$total += $segment->weight;
		}
		return $total;
	}
}
