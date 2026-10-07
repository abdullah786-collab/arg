<?php
/**
 * Resolves a group's eligible candidates.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rotation;

use DateTimeInterface;
use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\AdGroup;
use RevGuardPro\Domain\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Pure step one of selection: turns a group plus its loaded ads into the ordered list of eligible candidates.
 * Members whose ad is absent from the map (deleted or unreadable) are skipped, never an error.
 */
final class CandidateResolver {

	/**
	 * Constructor.
	 *
	 * @param AdEligibility $eligibility Eligibility rule.
	 */
	public function __construct( private readonly AdEligibility $eligibility ) {
	}

	/**
	 * Eligible candidates in member order.
	 *
	 * @param AdGroup           $group Group.
	 * @param array<int, Ad>    $ads   Loaded ads keyed by id (missing ids are simply absent).
	 * @param DateTimeInterface $now   Current instant.
	 * @return list<Candidate>
	 */
	public function resolve( AdGroup $group, array $ads, DateTimeInterface $now ): array {
		if ( Status::Active !== $group->status ) {
			return array();
		}

		$candidates = array();
		foreach ( $group->members as $position => $member ) {
			$ad = $ads[ $member->ad_id ] ?? null;
			if ( ! $ad instanceof Ad || $ad->id !== $member->ad_id || ! $this->eligibility->isEligible( $ad, $now ) ) {
				continue;
			}
			$candidates[] = new Candidate( $ad, $member->weight, $position );
		}
		return $candidates;
	}
}
