<?php
/**
 * Raw request facts collected by the WordPress adapter.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Targeting\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Untrusted, unparsed request data: the input of the pure ContextBuilder. Only the WordPress adapter creates it
 * from real request state; tests create it directly.
 */
final class RequestSnapshot {

	/**
	 * Constructor.
	 *
	 * @param string                      $user_agent  Raw User-Agent header.
	 * @param string                      $referer     Raw Referer header.
	 * @param string                      $request_uri Raw REQUEST_URI.
	 * @param string                      $home_path   Path of the site home URL.
	 * @param string                      $site_host   Host of the site home URL.
	 * @param array<string, mixed>        $query       Query parameters (only the needed names need be present).
	 * @param string                      $post_type   Current post type, or empty.
	 * @param array<string, array<mixed>> $terms       Term slugs of the current page, by taxonomy (untrusted).
	 * @param bool                        $logged_in   Whether a user is logged in.
	 * @param array<mixed>                $roles       The user's roles on this site (untrusted).
	 */
	public function __construct(
		public readonly string $user_agent = '',
		public readonly string $referer = '',
		public readonly string $request_uri = '',
		public readonly string $home_path = '',
		public readonly string $site_host = '',
		public readonly array $query = array(),
		public readonly string $post_type = '',
		public readonly array $terms = array(),
		public readonly bool $logged_in = false,
		public readonly array $roles = array()
	) {
	}
}
