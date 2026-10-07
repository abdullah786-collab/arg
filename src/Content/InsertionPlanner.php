<?php
/**
 * Locates the insertion boundary for a placement type.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Content;

use RevGuardPro\Domain\PlacementConfig;
use RevGuardPro\Domain\PlacementType;

defined( 'ABSPATH' ) || exit;

/**
 * Pure: maps a ContentMap and a placement configuration to a byte offset, or to the reason none exists.
 * Offsets are always segment ends (or 0 / end of content), so they are never inside protected markup.
 */
final class InsertionPlanner {

	/**
	 * Finds the offset for a placement configuration.
	 *
	 * @param ContentMap      $map    Analyzed content.
	 * @param PlacementConfig $config Placement configuration.
	 */
	public function locate( ContentMap $map, PlacementConfig $config ): int|SkipReason {
		switch ( $config->type ) {
			case PlacementType::BeforeContent:
				return 0;
			case PlacementType::AfterContent:
				return $map->length;
			case PlacementType::Manual:
				return SkipReason::Manual;
			default:
				break;
		}
		if ( $map->unsafe ) {
			return SkipReason::UnsafeContent;
		}

		return match ( $config->type ) {
			PlacementType::AfterParagraph => $this->afterNth( $map, $map->paragraphIndexes(), $config->value( 'paragraph' ) ),
			PlacementType::AfterHeading   => $this->afterNth( $map, $map->headingIndexes( $config->value( 'heading_level' ) ), $config->value( 'occurrence' ) ),
			default                       => $this->atPercentage( $map, $config->value( 'percent' ) ),
		};
	}

	/**
	 * The end of the Nth listed segment, or no boundary when there are fewer.
	 *
	 * @param ContentMap $map     Map.
	 * @param array<int> $indexes Segment indexes in order.
	 * @param int        $nth     1-based position.
	 */
	private function afterNth( ContentMap $map, array $indexes, int $nth ): int|SkipReason {
		return isset( $indexes[ $nth - 1 ] ) ? $map->segments[ $indexes[ $nth - 1 ] ]->end : SkipReason::NoBoundary;
	}

	/**
	 * The boundary between two segments whose cumulative text weight is nearest to the percentage; ties go to the
	 * earlier boundary. Integer arithmetic only.
	 *
	 * @param ContentMap $map     Map.
	 * @param int        $percent Percentage, 1 to 99.
	 */
	private function atPercentage( ContentMap $map, int $percent ): int|SkipReason {
		$count = count( $map->segments );
		$total = $map->totalWeight();
		if ( $count < 2 || 0 === $total ) {
			return SkipReason::NoBoundary;
		}

		$target     = $percent * $total;
		$cumulative = 0;
		$best       = null;
		$best_gap   = PHP_INT_MAX;
		for ( $i = 0; $i < $count - 1; ++$i ) {
			$cumulative += $map->segments[ $i ]->weight;
			$gap         = abs( $cumulative * 100 - $target );
			if ( $gap < $best_gap ) {
				$best_gap = $gap;
				$best     = $map->segments[ $i ]->end;
			}
		}
		return $best ?? SkipReason::NoBoundary;
	}
}
