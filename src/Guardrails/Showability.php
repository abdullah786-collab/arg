<?php
/**
 * Whether an ad can show right now.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Guardrails;

use DateTimeImmutable;
use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\Status;
use RevGuardPro\Scheduling\ScheduleState;

defined( 'ABSPATH' ) || exit;

/**
 * Status, schedule and targeting readability: the same facts the serving path (M2/M3) depends on, without evaluating
 * visitor-specific targeting rules.
 */
final class Showability {

	public const AD_INACTIVE  = 'ad_inactive';
	public const AD_SCHEDULE  = 'ad_schedule';
	public const AD_TARGETING = 'ad_targeting';

	/**
	 * Why an ad cannot show now, or null when nothing in its own configuration prevents it.
	 *
	 * @param Ad                $ad  Ad.
	 * @param DateTimeImmutable $now Current instant.
	 */
	public static function adReason( Ad $ad, DateTimeImmutable $now ): ?string {
		if ( Status::Active !== $ad->status ) {
			return self::AD_INACTIVE;
		}
		if ( ScheduleState::Active !== $ad->schedule->stateAt( $now ) ) {
			return self::AD_SCHEDULE;
		}
		if ( $ad->targeting->isCorrupt() ) {
			return self::AD_TARGETING;
		}
		return null;
	}
}
