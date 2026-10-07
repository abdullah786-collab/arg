<?php
/**
 * Runs the guardrail checks over plain data.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Guardrails;

use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\AdGroup;
use RevGuardPro\Domain\Placement;
use RevGuardPro\Domain\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Pure: the same input always produces the same ordered report. It performs no reads and no writes.
 */
final class GuardrailAnalyzer {

	public const MAX_PER_CODE = 25;

	private const SUBJECT_ORDER = array(
		Finding::SUBJECT_SITE      => 0,
		Finding::SUBJECT_AD        => 1,
		Finding::SUBJECT_GROUP     => 2,
		Finding::SUBJECT_PLACEMENT => 3,
	);

	/**
	 * Constructor.
	 *
	 * @param EntityChecks    $entities   Ad and group checks.
	 * @param PlacementChecks $placements Placement and site checks.
	 */
	public function __construct(
		private readonly EntityChecks $entities,
		private readonly PlacementChecks $placements
	) {
	}

	/**
	 * Analyses the configuration.
	 *
	 * @param array<int,Ad>        $ads        Ads.
	 * @param array<int,AdGroup>   $groups     Groups.
	 * @param array<int,Placement> $placements Placements.
	 * @param GuardrailContext     $context    Context.
	 */
	public function analyze( array $ads, array $groups, array $placements, GuardrailContext $context ): GuardrailReport {
		$ads_by_id        = $this->byId( $ads );
		$groups_by_id     = $this->byId( $groups );
		$active_placement = array_filter( $this->byId( $placements ), static fn( Placement $p ): bool => Status::Active === $p->status );
		$skipped          = 0;

		$findings = array_merge(
			$this->placements->site( $active_placement, $context ),
			$this->entities->ads( $ads_by_id, $context, $skipped ),
			$this->entities->groups( $groups_by_id, $ads_by_id, $context ),
			$this->placements->targets( $active_placement, $ads_by_id, $groups_by_id, $context ),
			$this->dataNotes( $context )
		);

		list( $kept, $suppressed ) = $this->capped( $this->sorted( $findings ) );
		$complete                  = array() === $context->truncated && array() === $context->unreadable;

		return new GuardrailReport( $kept, $suppressed, $skipped, $complete );
	}

	/**
	 * Notes about records that were not fully read.
	 *
	 * @param GuardrailContext $context Context.
	 * @return array<int,Finding>
	 */
	private function dataNotes( GuardrailContext $context ): array {
		$notes = array();
		foreach ( $context->truncated as $kind ) {
			$notes[] = new Finding( GuardrailCode::DataTruncated, Severity::Info, Finding::SUBJECT_SITE, null, array( 'kind' => $kind ) );
		}
		foreach ( $context->unreadable as $kind ) {
			$notes[] = new Finding( GuardrailCode::DataUnreadable, Severity::Warning, Finding::SUBJECT_SITE, null, array( 'kind' => $kind ) );
		}
		return $notes;
	}

	/**
	 * Keys a list of entities by id, ascending, skipping unsaved ones.
	 *
	 * @param array<int,Ad|AdGroup|Placement> $entities Entities.
	 * @return array<int,Ad|AdGroup|Placement>
	 */
	private function byId( array $entities ): array {
		$map = array();
		foreach ( $entities as $entity ) {
			if ( null !== $entity->id ) {
				$map[ $entity->id ] = $entity;
			}
		}
		ksort( $map );
		return $map;
	}

	/**
	 * Warnings first, then catalogue order, subject kind, id, parameters.
	 *
	 * @param array<int,Finding> $findings Findings.
	 * @return array<int,Finding>
	 */
	private function sorted( array $findings ): array {
		$code_order = array_flip( array_map( static fn( GuardrailCode $c ): string => $c->value, GuardrailCode::cases() ) );
		usort(
			$findings,
			static fn( Finding $a, Finding $b ): int => array(
				Severity::Warning === $a->severity ? 0 : 1,
				$code_order[ $a->code->value ],
				self::SUBJECT_ORDER[ $a->subject ],
				$a->subject_id ?? 0,
				implode( ',', $a->params ),
			) <=> array(
				Severity::Warning === $b->severity ? 0 : 1,
				$code_order[ $b->code->value ],
				self::SUBJECT_ORDER[ $b->subject ],
				$b->subject_id ?? 0,
				implode( ',', $b->params ),
			)
		);
		return $findings;
	}

	/**
	 * Applies the per-code cap.
	 *
	 * @param array<int,Finding> $findings Sorted findings.
	 * @return array{0:array<int,Finding>,1:int}
	 */
	private function capped( array $findings ): array {
		$per_code   = array();
		$kept       = array();
		$suppressed = 0;
		foreach ( $findings as $finding ) {
			$per_code[ $finding->code->value ] = ( $per_code[ $finding->code->value ] ?? 0 ) + 1;
			if ( $per_code[ $finding->code->value ] > self::MAX_PER_CODE ) {
				++$suppressed;
				continue;
			}
			$kept[] = $finding;
		}
		return array( $kept, $suppressed );
	}
}
