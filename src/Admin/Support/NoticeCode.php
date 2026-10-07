<?php
/**
 * The fixed vocabulary of admin notices.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Support;

defined( 'ABSPATH' ) || exit;

/**
 * A notice travels as one of these codes (plus integer counts) in the query string, never as text, so there is nothing
 * to reflect or forge.
 */
enum NoticeCode: string {

	case Saved         = 'saved';
	case Deleted       = 'deleted';
	case Activated     = 'activated';
	case Deactivated   = 'deactivated';
	case BulkDone      = 'bulk_done';
	case BulkNone      = 'bulk_none';
	case InUse         = 'in_use';
	case NotFound      = 'not_found';
	case Failed        = 'failed';
	case SettingsSaved = 'settings_saved';

	/**
	 * Notice type: success, error, warning or info.
	 */
	public function type(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts -- False positive: $this is valid inside an enum method.
			self::InUse, self::NotFound, self::Failed => 'error',
			self::BulkNone      => 'warning',
			default                                                         => 'success',
		};
	}

	/**
	 * The message text.
	 *
	 * @param array<string,int> $counts Integer counts from the query string (n, c, r, s).
	 */
	public function message( array $counts = array() ): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts -- False positive: $this is valid inside an enum method.
			self::Saved         => __( 'Saved.', 'revguard-pro' ),
			self::Deleted       => __( 'Deleted.', 'revguard-pro' ),
			self::Activated     => __( 'Activated.', 'revguard-pro' ),
			self::Deactivated   => __( 'Deactivated.', 'revguard-pro' ),
			self::BulkDone      => sprintf(
				/* translators: %d: number of items changed. */
				_n( '%d item updated.', '%d items updated.', (int) ( $counts['n'] ?? 0 ), 'revguard-pro' ),
				(int) ( $counts['n'] ?? 0 )
			),
			self::BulkNone      => __( 'Nothing was changed. Select at least one item and choose an action.', 'revguard-pro' ),
			self::InUse         => __( 'That item is still used by something else, so it was not deleted. Remove it from the placement or group first.', 'revguard-pro' ),
			self::NotFound      => __( 'That item no longer exists.', 'revguard-pro' ),
			self::Failed        => __( 'The change could not be saved. Nothing was changed. Please try again.', 'revguard-pro' ),
			self::SettingsSaved => __( 'Settings saved.', 'revguard-pro' ),
		};
	}

	/**
	 * Resolves the notice carried by a query string, or null.
	 *
	 * @param array<string,mixed> $query Query values.
	 * @return array{type:string,message:string}|null
	 */
	public static function fromQuery( array $query ): ?array {
		$code = $query['rgp_notice'] ?? null;
		if ( ! is_string( $code ) ) {
			return null;
		}
		$case = self::tryFrom( $code );
		if ( null === $case ) {
			return null;
		}
		$counts = array();
		foreach ( array( 'n', 'c', 'r', 's' ) as $key ) {
			$counts[ $key ] = Input::int( $query[ $key ] ?? null, 0, 100000 ) ?? 0;
		}
		return array(
			'type'    => $case->type(),
			'message' => $case->message( $counts ),
		);
	}
}
