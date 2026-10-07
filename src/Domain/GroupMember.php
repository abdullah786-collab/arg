<?php
/**
 * Group membership value object.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * One ad inside a group, with its weight. Position is the member's index in the group, never stored on the value.
 *
 * The weight is storage-only in M1; how it is used belongs to M2.
 */
final class GroupMember {

	public const WEIGHT_MIN     = 1;
	public const WEIGHT_MAX     = 1000;
	public const WEIGHT_DEFAULT = 100;

	/**
	 * Constructor.
	 *
	 * @param int $ad_id  Ad id.
	 * @param int $weight Weight, 1 to 1000.
	 * @throws Exception\InvalidEntityException When out of range.
	 */
	public function __construct( public readonly int $ad_id, public readonly int $weight = self::WEIGHT_DEFAULT ) {
		Rules::id( $ad_id, 'member_ad_id' );
		Rules::range( $weight, self::WEIGHT_MIN, self::WEIGHT_MAX, 'member_weight' );
	}
}
