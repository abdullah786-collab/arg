<?php
/**
 * Ad eligibility.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rotation;

use DateTimeInterface;
use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\Status;

defined( 'ABSPATH' ) || exit;

/**
 * What M2 owns: an ad is eligible when it is active and its schedule is active at the given instant.
 * Targeting and placement rules are deliberately not part of this (M3/M4 narrow the result).
 */
final class AdEligibility {

	/**
	 * Whether the ad may be served now.
	 *
	 * @param Ad                $ad  Ad.
	 * @param DateTimeInterface $now Current instant.
	 */
	public function isEligible( Ad $ad, DateTimeInterface $now ): bool {
		return Status::Active === $ad->status && $ad->schedule->isActiveAt( $now );
	}
}
