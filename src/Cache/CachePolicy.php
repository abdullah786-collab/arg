<?php
/**
 * Cache policy.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Cache;

use DateTimeImmutable;
use RevGuardPro\Targeting\CacheProfile;
use RevGuardPro\Targeting\CacheSensitivity;

defined( 'ABSPATH' ) || exit;

/**
 * Pure decision (ADR 010): bypass only when the aggregate profile holds a request-variant dimension.
 *
 * Page-stable and visitor-variant outcomes stay on the normal cache path (ADR 003). `valid_until` is carried through
 * unchanged and never causes a bypass.
 */
final class CachePolicy {

	/**
	 * Decides for one request.
	 *
	 * @param CacheProfile           $profile     Merged profile of everything rendered or considered.
	 * @param DateTimeImmutable|null $valid_until Earliest upcoming schedule transition, or null.
	 */
	public function decide( CacheProfile $profile, ?DateTimeImmutable $valid_until ): CacheDecision {
		if ( CacheSensitivity::RequestVariant === $profile->sensitivity ) {
			return new CacheDecision( true, array( CacheDecision::REASON_REQUEST_VARIANT ), $valid_until );
		}
		return new CacheDecision( false, array(), $valid_until );
	}
}
