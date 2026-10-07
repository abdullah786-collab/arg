<?php
/**
 * Loads the configuration and runs the guardrail checks.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Guardrails;

use Closure;
use RevGuardPro\Domain\Repository\AdGroupRepositoryInterface;
use RevGuardPro\Domain\Repository\AdRepositoryInterface;
use RevGuardPro\Domain\Repository\PlacementRepositoryInterface;
use RevGuardPro\Support\ClockInterface;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * The only class that reads. It performs three bounded list reads (ads, groups, placements) plus a one-row probe for any kind that returned exactly the limit, never writes, and never
 * throws: any failure becomes an incomplete report. It has no entry point; whoever exposes it must check
 * `rgp_manage_ads` (ADR 008).
 */
final class GuardrailService {

	public const LIMIT = 500;

	/**
	 * Constructor.
	 *
	 * @param AdRepositoryInterface        $ads        Ads.
	 * @param AdGroupRepositoryInterface   $groups     Groups.
	 * @param PlacementRepositoryInterface $placements Placements.
	 * @param GuardrailAnalyzer            $analyzer   Pure analyzer.
	 * @param ClockInterface               $clock      Clock.
	 * @param Closure                      $settings   Closure(): array{allow_unfiltered: bool, automatic: bool}.
	 * @param Closure                      $clear_error Closure(): void, clears the database's last-error state.
	 * @param Closure                      $last_error  Closure(): string, the database's last error ('' when none).
	 */
	public function __construct(
		private readonly AdRepositoryInterface $ads,
		private readonly AdGroupRepositoryInterface $groups,
		private readonly PlacementRepositoryInterface $placements,
		private readonly GuardrailAnalyzer $analyzer,
		private readonly ClockInterface $clock,
		private readonly Closure $settings,
		private readonly Closure $clear_error,
		private readonly Closure $last_error
	) {
	}

	/**
	 * Runs the checks on the current site's configuration.
	 */
	public function run(): GuardrailReport {
		$truncated  = array();
		$unreadable = array();
		try {
			$ads        = $this->read( GuardrailContext::KIND_ADS, fn( int $limit, int $offset ): array => $this->ads->list( null, $limit, $offset ), $truncated, $unreadable );
			$groups     = $this->read( GuardrailContext::KIND_GROUPS, fn( int $limit, int $offset ): array => $this->groups->list( null, $limit, $offset ), $truncated, $unreadable );
			$placements = $this->read( GuardrailContext::KIND_PLACEMENTS, fn( int $limit, int $offset ): array => $this->placements->list( null, $limit, $offset ), $truncated, $unreadable );

			$settings = ( $this->settings )();
			$context  = new GuardrailContext(
				$this->clock->now(),
				true === ( $settings['allow_unfiltered'] ?? false ),
				true === ( $settings['automatic'] ?? false ),
				$truncated,
				$unreadable
			);
			return $this->analyzer->analyze( $ads, $groups, $placements, $context );
		} catch ( Throwable ) {
			return new GuardrailReport(
				array( new Finding( GuardrailCode::DataUnreadable, Severity::Warning, Finding::SUBJECT_SITE, null, array( 'kind' => 'configuration' ) ) ),
				0,
				0,
				false
			);
		}
	}

	/**
	 * Reads one kind with a bound, recording truncation or failure instead of throwing.
	 *
	 * @param string        $kind       Kind.
	 * @param Closure       $load       Closure(int $limit, int $offset): array.
	 * @param array<string> $truncated  Truncated kinds (by reference).
	 * @param array<string> $unreadable Unreadable kinds (by reference).
	 * @return array<int,object>
	 */
	private function read( string $kind, Closure $load, array &$truncated, array &$unreadable ): array {
		try {
			// The repositories cap a page at 500 rows, so "more than the limit" is detected with a one-row probe.
			( $this->clear_error )();
			$rows = $load( self::LIMIT, 0 );
			if ( count( $rows ) >= self::LIMIT && array() !== $load( 1, self::LIMIT ) ) {
				$truncated[] = $kind;
			}
			// The repositories turn a failed query into an empty list, so the database's own error state is the
			// only way to tell "nothing there" from "could not read". Never report the second as the first.
			if ( '' !== (string) ( $this->last_error )() ) {
				$unreadable[] = $kind;
				return array();
			}
			return $rows;
		} catch ( Throwable ) {
			$unreadable[] = $kind;
			return array();
		}
	}
}
