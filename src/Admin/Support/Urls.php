<?php
/**
 * Admin URLs.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Support;

use RevGuardPro\Admin\ScreenId;

defined( 'ABSPATH' ) || exit;

/**
 * Builds only same-site admin URLs from screen ids, fixed action words and integers, so a redirect can never leave the
 * site and a link can never carry free text.
 */
final class Urls {

	/**
	 * Constructor.
	 *
	 * @param string $base The site's `admin.php` URL.
	 */
	public function __construct( private readonly string $base ) {
	}

	/**
	 * A screen URL.
	 *
	 * @param ScreenId                 $screen Screen.
	 * @param array<string,string|int> $args   Extra query arguments (fixed words and integers).
	 */
	public function screen( ScreenId $screen, array $args = array() ): string {
		$query = array( 'page' => $screen->value );
		foreach ( $args as $key => $value ) {
			if ( 1 === preg_match( '/^[a-z_]{1,20}$/D', (string) $key ) ) {
				$query[ $key ] = is_int( $value ) ? (string) $value : preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) );
			}
		}
		return $this->base . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * A screen URL carrying a notice.
	 *
	 * @param ScreenId          $screen Screen.
	 * @param NoticeCode        $notice Notice.
	 * @param array<string,int> $counts Integer counts.
	 */
	public function withNotice( ScreenId $screen, NoticeCode $notice, array $counts = array() ): string {
		$args = array( 'rgp_notice' => $notice->value );
		foreach ( $counts as $key => $count ) {
			$args[ $key ] = max( 0, $count );
		}
		return $this->screen( $screen, $args );
	}
}
