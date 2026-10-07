<?php
/**
 * Immutable request facts that rules are evaluated against.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Targeting;

defined( 'ABSPATH' ) || exit;

/**
 * Plain data, built once per request by the context builder. It holds only what the rules in play required;
 * everything else keeps its neutral default. It performs no I/O.
 */
final class TargetingContext {

	/**
	 * Constructor.
	 *
	 * @param DeviceType                  $device        Device class.
	 * @param string                      $post_type     Current post type, or empty.
	 * @param array<string, list<string>> $terms         Term slugs of the current page, by taxonomy.
	 * @param bool                        $logged_in     Whether a user is logged in.
	 * @param array<string>               $roles         The user's roles on this site.
	 * @param string|null                 $path          Normalized request path, or null when unavailable.
	 * @param string|null                 $referrer_host Referrer host (http/https only), or null for direct.
	 * @param string                      $site_host     This site's host (lowercase).
	 * @param array<string, string>       $query_params  Scalar query parameter values, only for required names.
	 */
	public function __construct(
		public readonly DeviceType $device = DeviceType::Desktop,
		public readonly string $post_type = '',
		public readonly array $terms = array(),
		public readonly bool $logged_in = false,
		public readonly array $roles = array(),
		public readonly ?string $path = '/',
		public readonly ?string $referrer_host = null,
		public readonly string $site_host = '',
		public readonly array $query_params = array()
	) {
	}
}
