<?php
/**
 * What a screen decided to do with a request.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Screen;

defined( 'ABSPATH' ) || exit;

/**
 * Either a redirect (after a successful action) or a view to render with data.
 */
final class Outcome {

	/**
	 * Constructor (use the factories).
	 *
	 * @param string|null         $redirect URL to redirect to, or null to render.
	 * @param array<string,mixed> $data     View data.
	 */
	private function __construct( public readonly ?string $redirect, public readonly array $data ) {
	}

	/**
	 * A redirect.
	 *
	 * @param string $url Same-site URL built by `Urls`.
	 */
	public static function redirect( string $url ): self {
		return new self( $url, array() );
	}

	/**
	 * A view.
	 *
	 * @param array<string,mixed> $data View data.
	 */
	public static function render( array $data = array() ): self {
		return new self( null, $data );
	}
}
