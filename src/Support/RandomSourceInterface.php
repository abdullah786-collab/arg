<?php
/**
 * Injectable integer random source.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Source of uniformly distributed integers, injectable so selection is testable without real randomness.
 */
interface RandomSourceInterface {

	/**
	 * A uniformly distributed integer in the inclusive range [min, max].
	 *
	 * @param int $min Lower bound.
	 * @param int $max Upper bound (at least min).
	 */
	public function int( int $min, int $max ): int;
}
