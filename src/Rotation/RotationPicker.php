<?php
/**
 * Picks one candidate according to a rotation mode.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rotation;

use LogicException;
use RevGuardPro\Domain\RotationMode;
use RevGuardPro\Support\RandomSourceInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Pure step two of selection. Ordered: the first candidate. Weighted: draw an integer in [0, total-1] and walk
 * cumulative integer weights. Holds no state between calls and performs no I/O besides the random source.
 */
final class RotationPicker {

	/**
	 * Constructor.
	 *
	 * @param RandomSourceInterface $random Random source.
	 */
	public function __construct( private readonly RandomSourceInterface $random ) {
	}

	/**
	 * Picks a candidate, or null when there is none.
	 *
	 * @param RotationMode     $mode       Rotation mode.
	 * @param array<Candidate> $candidates Eligible candidates in member order.
	 * @throws LogicException When the random source returns a value outside the requested range.
	 */
	public function pick( RotationMode $mode, array $candidates ): ?Candidate {
		if ( array() === $candidates ) {
			return null;
		}
		return match ( $mode ) {
			RotationMode::Ordered  => $candidates[0],
			RotationMode::Weighted => $this->weighted( $candidates ),
		};
	}

	/**
	 * Weighted choice by cumulative integer weights.
	 *
	 * @param array<Candidate> $candidates Eligible candidates (at least one).
	 * @throws LogicException When the random source misbehaves.
	 */
	private function weighted( array $candidates ): Candidate {
		$total = 0;
		foreach ( $candidates as $candidate ) {
			$total += $candidate->weight;
		}

		$draw = $this->random->int( 0, $total - 1 );
		if ( $draw < 0 || $draw >= $total ) {
			throw new LogicException( 'The random source returned a value outside the requested range.' );
		}

		$cumulative = 0;
		foreach ( $candidates as $candidate ) {
			$cumulative += $candidate->weight;
			if ( $draw < $cumulative ) {
				return $candidate;
			}
		}
		// Unreachable: the draw is below the total, so some candidate's cumulative weight exceeds it.
		return $candidates[ array_key_last( $candidates ) ];
	}
}
