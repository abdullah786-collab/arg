<?php
/**
 * The result of a guardrails run.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Guardrails;

defined( 'ABSPATH' ) || exit;

/**
 * Ordered findings plus how much could not be looked at. `isComplete()` is false whenever anything was unreadable,
 * truncated or skipped, so an empty list can never be mistaken for "everything was checked and is fine".
 */
final class GuardrailReport {

	/**
	 * Constructor.
	 *
	 * @param array<int,Finding> $findings   Ordered findings (warnings first).
	 * @param int                $suppressed Findings hidden by the per-code cap.
	 * @param int                $skipped    Checks skipped by a resource bound.
	 * @param bool               $complete   Whether every record was read and every check ran.
	 */
	public function __construct(
		public readonly array $findings,
		public readonly int $suppressed = 0,
		public readonly int $skipped = 0,
		private readonly bool $complete = true
	) {
	}

	/**
	 * Whether every record was read and every check ran.
	 */
	public function isComplete(): bool {
		return $this->complete && 0 === $this->skipped;
	}

	/**
	 * Findings with a given code.
	 *
	 * @param GuardrailCode $code Code.
	 * @return array<int,Finding>
	 */
	public function withCode( GuardrailCode $code ): array {
		return array_values( array_filter( $this->findings, static fn( Finding $f ): bool => $f->code === $code ) );
	}

	/**
	 * Number of findings of a severity.
	 *
	 * @param Severity $severity Severity.
	 */
	public function countOf( Severity $severity ): int {
		return count( array_filter( $this->findings, static fn( Finding $f ): bool => $f->severity === $severity ) );
	}
}
