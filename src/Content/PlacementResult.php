<?php
/**
 * Outcome of one placement pass.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Content;

use DateTimeImmutable;
use RevGuardPro\Targeting\CacheProfile;

defined( 'ABSPATH' ) || exit;

/**
 * The new content, what was inserted, why other placements were skipped, and what the output depends on.
 */
final class PlacementResult {

	/**
	 * Constructor.
	 *
	 * @param string                 $content       Content after insertion (identical to the input when nothing was inserted).
	 * @param array<Insertion>       $insertions    Insertions made.
	 * @param array<int, SkipReason> $skipped       Skip reason per placement id.
	 * @param CacheProfile           $cache_profile Union of the targeting of every placement and candidate ad considered.
	 * @param DateTimeImmutable|null $valid_until   Earliest upcoming schedule transition among the ads considered, or null.
	 * @param bool                   $blocked       Whether the output context did not allow insertion at all.
	 */
	public function __construct(
		public readonly string $content,
		public readonly array $insertions,
		public readonly array $skipped,
		public readonly CacheProfile $cache_profile,
		public readonly ?DateTimeImmutable $valid_until,
		public readonly bool $blocked = false
	) {
	}
}
