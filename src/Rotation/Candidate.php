<?php
/**
 * Rotation candidate.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rotation;

use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\GroupMember;
use RevGuardPro\Domain\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * An eligible ad with its group weight and its position (index) in the group.
 */
final class Candidate {

	/**
	 * Constructor.
	 *
	 * @param Ad  $ad       Eligible ad.
	 * @param int $weight   Group weight, 1 to 1000.
	 * @param int $position Index in the group's member order.
	 * @throws \RevGuardPro\Domain\Exception\InvalidEntityException When the weight is out of range.
	 */
	public function __construct( public readonly Ad $ad, public readonly int $weight, public readonly int $position ) {
		Rules::range( $weight, GroupMember::WEIGHT_MIN, GroupMember::WEIGHT_MAX, 'candidate_weight' );
	}
}
