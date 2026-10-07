<?php
/**
 * Authorizes admin actions: capability first, then nonce.
 *
 * Collaborators are injected so the ordering and failure behaviour is
 * unit-testable; forWordPress() wires the real WordPress functions.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Security;

use Closure;

defined( 'ABSPATH' ) || exit;

/**
 * Authorizes admin actions: capability first, then nonce.
 */
final class AdminRequestGuard {

	public const DENY_FORBIDDEN = 'forbidden';
	public const DENY_BAD_NONCE = 'bad_nonce';

	/**
	 * Constructor.
	 *
	 * @param Closure $can          Receives a capability; returns whether the current user has it.
	 * @param Closure $verify_nonce Receives action and field; returns whether the request carries a valid nonce.
	 * @param Closure $deny         Receives a DENY_* reason and stops the request; must not return normally in production.
	 */
	public function __construct(
		private readonly Closure $can,
		private readonly Closure $verify_nonce,
		private readonly Closure $deny
	) {
	}

	/**
	 * Builds a guard bound to the current WordPress request.
	 */
	public static function forWordPress(): self {
		return new self(
			static fn( string $capability ): bool => current_user_can( $capability ),
			// check_admin_referer() reads the nonce itself, so no request superglobal is touched here.
			static fn( string $action, string $field ): bool => false !== check_admin_referer( $action, $field ),
			static function ( string $reason ): void {
				$message = self::DENY_BAD_NONCE === $reason
					? __( 'The link you followed has expired. Please go back and try again.', 'revguard-pro' )
					: __( 'Sorry, you are not allowed to do that.', 'revguard-pro' );
				wp_die( esc_html( $message ), '', array( 'response' => 403 ) );
			}
		);
	}

	/**
	 * Checks the capability first, then the nonce, and denies on the first failure.
	 *
	 * @param string $action      Nonce action.
	 * @param string $capability  Required capability.
	 * @param string $nonce_field Request field that carries the nonce.
	 */
	public function authorize( string $action, string $capability, string $nonce_field = '_wpnonce' ): void {
		if ( ! ( $this->can )( $capability ) ) {
			( $this->deny )( self::DENY_FORBIDDEN );
			return;
		}
		if ( ! ( $this->verify_nonce )( $action, $nonce_field ) ) {
			( $this->deny )( self::DENY_BAD_NONCE );
		}
	}
}
