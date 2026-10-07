<?php
/**
 * One planned insertion.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Markup to insert at a byte offset on behalf of a placement.
 */
final class Insertion {

	/**
	 * Constructor.
	 *
	 * @param int    $placement_id Placement id.
	 * @param int    $ad_id        Ad id.
	 * @param int    $offset       Byte offset in the original content.
	 * @param string $markup       Markup produced by the renderer (opaque to the engine).
	 */
	public function __construct(
		public readonly int $placement_id,
		public readonly int $ad_id,
		public readonly int $offset,
		public readonly string $markup
	) {
	}
}
