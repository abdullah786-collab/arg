<?php
/**
 * Which requests count as impressions.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Pure decision: a plain GET, not a preview, not a site manager, not an obvious bot. The user agent is only matched,
 * never stored. Heuristic by nature: it removes the obvious noise, it does not claim to detect every bot.
 */
final class CountingPolicy {

	private const BOT_PATTERN  = '/bot|crawl|spider|slurp|preview|headless|monitor|fetch/i';
	private const MAX_UA_BYTES = 512;

	/**
	 * Whether the request should be counted.
	 *
	 * @param string $method     HTTP method.
	 * @param string $user_agent User agent (matched only).
	 * @param bool   $is_preview Whether this is a post preview.
	 * @param bool   $is_manager Whether the user manages ads.
	 */
	public static function allows( string $method, string $user_agent, bool $is_preview, bool $is_manager ): bool {
		if ( 'GET' !== $method || $is_preview || $is_manager ) {
			return false;
		}
		return 1 !== preg_match( self::BOT_PATTERN, substr( $user_agent, 0, self::MAX_UA_BYTES ) );
	}
}
