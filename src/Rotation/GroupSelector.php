<?php
/**
 * Selects an ad from a group.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rotation;

use Closure;
use DateTimeInterface;
use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\AdGroup;

defined( 'ABSPATH' ) || exit;

/**
 * Composes candidate resolution and picking. Pure given the group, the loaded ads, the instant and the random source.
 *
 * An optional narrowing callback sits between the two steps so later milestones (targeting) can drop candidates
 * without this class knowing any targeting concept.
 */
final class GroupSelector {

	/**
	 * Constructor.
	 *
	 * @param CandidateResolver $resolver Candidate resolver.
	 * @param RotationPicker    $picker   Rotation picker.
	 */
	public function __construct(
		private readonly CandidateResolver $resolver,
		private readonly RotationPicker $picker
	) {
	}

	/**
	 * Selects an ad, or null when no candidate is eligible.
	 *
	 * @param AdGroup           $group  Group.
	 * @param array<int, Ad>    $ads    Loaded ads keyed by id.
	 * @param DateTimeInterface $now    Current instant.
	 * @param Closure|null      $narrow Optional filter: receives a Candidate, returns whether it stays.
	 */
	public function select( AdGroup $group, array $ads, DateTimeInterface $now, ?Closure $narrow = null ): ?Ad {
		$candidates = $this->resolver->resolve( $group, $ads, $now );
		if ( null !== $narrow ) {
			$candidates = array_values( array_filter( $candidates, $narrow ) );
		}
		return $this->picker->pick( $group->rotation_mode, $candidates )?->ad;
	}
}
