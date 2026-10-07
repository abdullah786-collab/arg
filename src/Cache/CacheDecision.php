<?php
/**
 * Cache decision value object.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Cache;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * Whether the page must bypass full-page caching, and until when the rendered outcome is known to hold.
 */
final class CacheDecision {

	public const REASON_REQUEST_VARIANT = 'request_variant';

	/**
	 * Builds a decision.
	 *
	 * @param bool                   $bypass      True when a page cache must not store this response.
	 * @param array<string>          $reasons     Fixed reason codes (REASON_*).
	 * @param DateTimeImmutable|null $valid_until Earliest upcoming schedule transition among the ads considered, or null.
	 */
	public function __construct(
		public readonly bool $bypass,
		public readonly array $reasons,
		public readonly ?DateTimeImmutable $valid_until
	) {
	}
}
