<?php
/**
 * Pagination bounds shared by every repository.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Persistence;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps list queries bounded no matter what a caller passes.
 */
final class Paging {

	public const MAX_LIMIT = 500;

	/**
	 * Page size clamped to 1 to 500.
	 *
	 * @param int $limit Requested limit.
	 */
	public static function limit( int $limit ): int {
		return max( 1, min( self::MAX_LIMIT, $limit ) );
	}

	/**
	 * Offset clamped to 0 or more.
	 *
	 * @param int $offset Requested offset.
	 */
	public static function offset( int $offset ): int {
		return max( 0, $offset );
	}
}
