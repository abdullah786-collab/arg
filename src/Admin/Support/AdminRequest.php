<?php
/**
 * The request as the admin screens see it.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable copy of the query string and the POST body, already unslashed. It is the single place that touches the
 * superglobals; every value is then read through the typed `Input` readers.
 */
final class AdminRequest {

	/**
	 * Constructor.
	 *
	 * @param string              $method HTTP method, upper case.
	 * @param array<string,mixed> $query  Query string values.
	 * @param array<string,mixed> $post   POST values.
	 */
	public function __construct(
		public readonly string $method = 'GET',
		public readonly array $query = array(),
		public readonly array $post = array()
	) {
	}

	/**
	 * Builds the request from the superglobals.
	 */
	public static function fromGlobals(): self {
		// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- Values are unslashed here and only ever read through the strict typed Input readers; nonces are verified by AdminRequestGuard before any state change.
		$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : 'GET';
		$query  = wp_unslash( $_GET );
		$post   = 'POST' === $method ? wp_unslash( $_POST ) : array();
		// phpcs:enable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		return new self( $method, $query, $post );
	}

	/**
	 * Whether this is a POST request.
	 */
	public function isPost(): bool {
		return 'POST' === $this->method;
	}

	/**
	 * The `action` query value, restricted to short lowercase words.
	 */
	public function action(): string {
		$value = $this->query['action'] ?? $this->post['action'] ?? '';
		return is_string( $value ) && 1 === preg_match( '/^[a-z_]{1,30}$/D', $value ) ? $value : '';
	}

	/**
	 * The entity id from the query string or the body.
	 */
	public function id(): ?int {
		return Input::id( $this->query['id'] ?? $this->post['id'] ?? null );
	}

	/**
	 * A POST value.
	 *
	 * @param string $key Key.
	 */
	public function input( string $key ): mixed {
		return $this->post[ $key ] ?? null;
	}

	/**
	 * A query value.
	 *
	 * @param string $key Key.
	 */
	public function param( string $key ): mixed {
		return $this->query[ $key ] ?? null;
	}
}
