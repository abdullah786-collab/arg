<?php
/**
 * Authorization guard for rendering surfaces.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rendering;

use Closure;
use RevGuardPro\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether the current request may render ads in a given post. Embedding is a monetization act:
 * only posts whose author holds the manage-ads capability are served. Outside a post (widgets) the surface
 * is always allowed.
 */
final class SurfaceAuthorization {

	/**
	 * Memoized results keyed by post id.
	 *
	 * @var array<int, bool>
	 */
	private array $memo = array();

	/**
	 * Constructor.
	 *
	 * @param Closure $author_of Closure(int $post_id): int — post author user id, 0 if unknown.
	 * @param Closure $can       Closure(int $user_id, string $capability): bool.
	 */
	public function __construct(
		private readonly Closure $author_of,
		private readonly Closure $can
	) {
	}

	/**
	 * Whether the surface may render for the given post id.
	 * Post id <= 0 (outside a post, for example a widget) is always allowed.
	 * Author 0 (unknown) is denied. Otherwise checks the manage-ads capability.
	 *
	 * @param int $post_id Post id, or 0 when outside the loop.
	 */
	public function allows( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return true;
		}
		if ( isset( $this->memo[ $post_id ] ) ) {
			return $this->memo[ $post_id ];
		}
		$author = ( $this->author_of )( $post_id );
		if ( 0 === $author ) {
			$this->memo[ $post_id ] = false;
			return false;
		}
		$result                 = (bool) ( $this->can )( $author, Capabilities::MANAGE_ADS );
		$this->memo[ $post_id ] = $result;
		return $result;
	}
}
