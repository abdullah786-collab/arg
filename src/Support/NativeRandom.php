<?php
/**
 * Production random source.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Uses PHP's cryptographically secure generator. It never seeds or touches the global mt_rand state.
 */
final class NativeRandom implements RandomSourceInterface {

	/**
	 * A uniformly distributed integer in [min, max].
	 *
	 * @param int $min Lower bound.
	 * @param int $max Upper bound.
	 */
	public function int( int $min, int $max ): int {
		return random_int( $min, $max );
	}
}
