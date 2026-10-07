<?php
/**
 * Placement priority ordering.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * The single definition of placement order: lower priority number first, ties by id ascending.
 * Priority orders placements only; it is never a weight and never affects which ad a group yields.
 * The SQL `ORDER BY priority ASC, id ASC` is kept equal to this by the shared repository contract test.
 */
final class PlacementOrdering {

	/**
	 * Comparator for usort().
	 *
	 * @param Placement $a First placement.
	 * @param Placement $b Second placement.
	 */
	public static function compare( Placement $a, Placement $b ): int {
		return array( $a->priority, $a->id ?? PHP_INT_MAX ) <=> array( $b->priority, $b->id ?? PHP_INT_MAX );
	}
}
