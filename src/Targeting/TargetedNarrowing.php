<?php
/**
 * M2/M3 composition: targeting as a candidate filter.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Targeting;

defined( 'ABSPATH' ) || exit;

use Closure;
use RevGuardPro\Domain\Placement;
use RevGuardPro\Rotation\Candidate;

/**
 * Builds the narrowing callback that GroupSelector accepts, so targeting runs after M2 eligibility (status and
 * schedule) and before the draw. An ad that fails targeting is removed from the candidate list and therefore
 * contributes zero weight. GroupSelector itself knows no targeting concept.
 */
final class TargetedNarrowing {

	/**
	 * Constructor.
	 *
	 * @param TargetingEvaluator $evaluator Pure evaluator.
	 * @param TargetingContext   $context   Context built once for the request.
	 */
	public function __construct(
		private readonly TargetingEvaluator $evaluator,
		private readonly TargetingContext $context
	) {
	}

	/**
	 * The callback for GroupSelector::select() and GroupAdSelector::select().
	 *
	 * @return Closure(Candidate): bool
	 */
	public function forCandidates(): Closure {
		return fn( Candidate $candidate ): bool => $this->evaluator->matches( $candidate->ad->targeting, $this->context );
	}

	/**
	 * Whether a placement's own targeting permits it on this request.
	 *
	 * @param Placement $placement Placement.
	 */
	public function allowsPlacement( Placement $placement ): bool {
		return $this->evaluator->matches( $placement->targeting, $this->context );
	}
}
