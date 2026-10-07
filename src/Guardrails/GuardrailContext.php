<?php
/**
 * Everything besides the entities that a run depends on.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Guardrails;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * Plain facts; the analyzer performs no reads of its own.
 */
final class GuardrailContext {

	public const KIND_ADS        = 'ads';
	public const KIND_GROUPS     = 'groups';
	public const KIND_PLACEMENTS = 'placements';

	/**
	 * Constructor.
	 *
	 * @param DateTimeImmutable $now              Current instant.
	 * @param bool              $allow_unfiltered Whether HTML creatives are output without filtering.
	 * @param bool              $automatic        Whether automatic insertion is enabled in settings.
	 * @param array<string>     $truncated        Kinds with more records than the read limit.
	 * @param array<string>     $unreadable       Kinds that could not be read.
	 */
	public function __construct(
		public readonly DateTimeImmutable $now,
		public readonly bool $allow_unfiltered,
		public readonly bool $automatic,
		public readonly array $truncated = array(),
		public readonly array $unreadable = array()
	) {
	}

	/**
	 * Whether every record of a kind was read (neither truncated nor unreadable).
	 *
	 * @param string $kind One of the KIND_ constants.
	 */
	public function isComplete( string $kind ): bool {
		return ! in_array( $kind, $this->truncated, true ) && ! in_array( $kind, $this->unreadable, true );
	}
}
