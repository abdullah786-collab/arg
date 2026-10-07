<?php
/**
 * WordPress adapter for the output context.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Content\Wp;

use RevGuardPro\Content\ContentContext;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the WordPress conditional state once and returns pure flags. It writes nothing.
 */
final class WpContentContextFactory {

	/**
	 * Builds the context for the output being produced right now.
	 */
	public function build(): ContentContext {
		return new ContentContext(
			is_main_query() && in_the_loop(),
			is_feed(),
			defined( 'REST_REQUEST' ) && REST_REQUEST,
			is_admin(),
			is_embed(),
			doing_filter( 'get_the_excerpt' ) || doing_filter( 'the_excerpt' )
		);
	}
}
