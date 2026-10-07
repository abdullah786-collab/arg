<?php
/**
 * Guardrail checks on active placements and the site-level configuration.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Guardrails;

use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\AdGroup;
use RevGuardPro\Domain\Placement;
use RevGuardPro\Domain\PlacementType;
use RevGuardPro\Domain\Status;
use RevGuardPro\Domain\TargetType;

defined( 'ABSPATH' ) || exit;

/**
 * Pure and bounded: sorting plus linear passes, no pairwise scan. Positions are compared with their neighbours in a
 * sorted list, which finds every chain of close placements.
 */
final class PlacementChecks {

	public const MANY_NOTICE        = 4;
	public const MANY_WARNING       = 7;
	public const TOP_PARAGRAPH      = 2;
	public const TOP_PERCENT        = 10;
	public const SPACING_PARA       = 1;
	public const SPACING_PERCENT    = 5;
	public const REASON_SETTING_OFF = 'setting_off';

	/**
	 * Site-level findings from the active automatic placements and settings.
	 *
	 * @param array<int,Placement> $active  Active placements by id, ascending.
	 * @param GuardrailContext     $context Context.
	 * @return array<int,Finding>
	 */
	public function site( array $active, GuardrailContext $context ): array {
		$findings  = array();
		$automatic = array_filter( $active, static fn( Placement $p ): bool => PlacementType::Manual !== $p->type() );
		$count     = count( $automatic );

		if ( $count >= self::MANY_NOTICE ) {
			$severity   = $count >= self::MANY_WARNING ? Severity::Warning : Severity::Info;
			$findings[] = new Finding( GuardrailCode::ManyPlacements, $severity, Finding::SUBJECT_SITE, null, array( 'count' => $count ) );
		}

		$top = 0;
		foreach ( $automatic as $placement ) {
			$top += $this->isNearTop( $placement ) ? 1 : 0;
		}
		if ( $top >= 2 ) {
			$findings[] = new Finding( GuardrailCode::TopCluster, Severity::Warning, Finding::SUBJECT_SITE, null, array( 'count' => $top ) );
		}

		if ( $count > 0 ) {
			$reason = $this->inertReason( $context );
			if ( null !== $reason ) {
				$findings[] = new Finding( GuardrailCode::InsertionNotActive, Severity::Info, Finding::SUBJECT_SITE, null, array( 'reason' => $reason ) );
			}
		}
		if ( $context->allow_unfiltered ) {
			$findings[] = new Finding( GuardrailCode::UnfilteredHtmlEnabled, Severity::Warning, Finding::SUBJECT_SITE );
		}
		return array_merge( $findings, $this->duplicates( $active ), $this->spacing( $automatic ) );
	}

	/**
	 * Per-placement findings: broken targeting, missing or non-showing targets.
	 *
	 * @param array<int,Placement> $active  Active placements by id.
	 * @param array<int,Ad>        $ads     Ads by id.
	 * @param array<int,AdGroup>   $groups  Groups by id.
	 * @param GuardrailContext     $context Context.
	 * @return array<int,Finding>
	 */
	public function targets( array $active, array $ads, array $groups, GuardrailContext $context ): array {
		$findings = array();
		foreach ( $active as $id => $placement ) {
			if ( $placement->targeting->isCorrupt() ) {
				$findings[] = new Finding( GuardrailCode::PlacementTargetingBroken, Severity::Warning, Finding::SUBJECT_PLACEMENT, $id );
			}
			if ( null === $placement->target_type || null === $placement->target_id ) {
				continue;
			}
			$problem = TargetType::Ad === $placement->target_type
				? $this->adTargetProblem( $placement->target_id, $ads, $context )
				: $this->groupTargetProblem( $placement->target_id, $ads, $groups, $context );
			if ( null === $problem ) {
				continue;
			}
			$code       = 'missing' === $problem ? GuardrailCode::PlacementTargetMissing : GuardrailCode::PlacementNotShowing;
			$findings[] = new Finding( $code, Severity::Warning, Finding::SUBJECT_PLACEMENT, $id, 'missing' === $problem ? array() : array( 'reason' => $problem ) );
		}
		return $findings;
	}

	/**
	 * Why automatic insertion would not run, or null when Pro inserts.
	 *
	 * @param GuardrailContext $context Context.
	 */
	private function inertReason( GuardrailContext $context ): ?string {
		return $context->automatic ? null : self::REASON_SETTING_OFF;
	}

	/**
	 * Whether a placement lands near the top of the content.
	 *
	 * @param Placement $placement Placement.
	 */
	private function isNearTop( Placement $placement ): bool {
		return match ( $placement->type() ) {
			PlacementType::BeforeContent  => true,
			PlacementType::AfterParagraph => $placement->config->value( 'paragraph' ) <= self::TOP_PARAGRAPH,
			PlacementType::Percentage     => $placement->config->value( 'percent' ) <= self::TOP_PERCENT,
			default                       => false,
		};
	}

	/**
	 * Identical active placements: every later one repeats the first.
	 *
	 * @param array<int,Placement> $active Active placements by id, ascending.
	 * @return array<int,Finding>
	 */
	private function duplicates( array $active ): array {
		$seen     = array();
		$findings = array();
		foreach ( $active as $id => $placement ) {
			$key = $placement->type()->value . '|' . $placement->config->toJson() . '|' . ( $placement->target_type?->value ?? '' ) . '|' . ( $placement->target_id ?? 0 );
			if ( isset( $seen[ $key ] ) ) {
				$findings[] = new Finding( GuardrailCode::DuplicatePlacement, Severity::Warning, Finding::SUBJECT_PLACEMENT, $id, array( 'other' => $seen[ $key ] ) );
				continue;
			}
			$seen[ $key ] = $id;
		}
		return $findings;
	}

	/**
	 * Close positions: paragraphs within SPACING_PARA, percentages within SPACING_PERCENT, neighbours only.
	 *
	 * @param array<int,Placement> $automatic Active automatic placements by id.
	 * @return array<int,Finding>
	 */
	private function spacing( array $automatic ): array {
		$points = array();
		foreach ( $automatic as $id => $placement ) {
			if ( PlacementType::AfterParagraph === $placement->type() ) {
				$points[] = array( 'p', $placement->config->value( 'paragraph' ), $id, $this->identity( $placement ) );
			} elseif ( PlacementType::Percentage === $placement->type() ) {
				$points[] = array( '%', $placement->config->value( 'percent' ), $id, $this->identity( $placement ) );
			}
		}
		usort( $points, static fn( array $a, array $b ): int => array( $a[0], $a[1], $a[2] ) <=> array( $b[0], $b[1], $b[2] ) );

		$findings = array();
		for ( $i = 1, $n = count( $points ); $i < $n; $i++ ) {
			list( $kind, $value, $id, $identity )               = $points[ $i ];
			list( $prev_kind, $prev, $prev_id, $prev_identity ) = $points[ $i - 1 ];
			$gap = $value - $prev;
			if ( $kind !== $prev_kind || $identity === $prev_identity ) {
				continue;
			}
			if ( $gap <= ( 'p' === $kind ? self::SPACING_PARA : self::SPACING_PERCENT ) ) {
				$findings[] = new Finding(
					GuardrailCode::ClosePlacementSpacing,
					Severity::Info,
					Finding::SUBJECT_PLACEMENT,
					max( $id, $prev_id ),
					array(
						'other' => min( $id, $prev_id ),
						'gap'   => $gap,
					)
				);
			}
		}
		return $findings;
	}

	/**
	 * Placement identity used to avoid reporting exact duplicates as "close".
	 *
	 * @param Placement $placement Placement.
	 */
	private function identity( Placement $placement ): string {
		return $placement->type()->value . '|' . $placement->config->toJson() . '|' . ( $placement->target_type?->value ?? '' ) . '|' . ( $placement->target_id ?? 0 );
	}

	/**
	 * A target ad's problem: 'missing', a Showability reason, or null.
	 *
	 * @param int              $ad_id   Ad id.
	 * @param array<int,Ad>    $ads     Ads by id.
	 * @param GuardrailContext $context Context.
	 */
	private function adTargetProblem( int $ad_id, array $ads, GuardrailContext $context ): ?string {
		if ( ! isset( $ads[ $ad_id ] ) ) {
			return $context->isComplete( GuardrailContext::KIND_ADS ) ? 'missing' : null;
		}
		return Showability::adReason( $ads[ $ad_id ], $context->now );
	}

	/**
	 * A target group's problem: 'missing', a reason, or null.
	 *
	 * @param int                $group_id Group id.
	 * @param array<int,Ad>      $ads      Ads by id.
	 * @param array<int,AdGroup> $groups   Groups by id.
	 * @param GuardrailContext   $context  Context.
	 */
	private function groupTargetProblem( int $group_id, array $ads, array $groups, GuardrailContext $context ): ?string {
		if ( ! isset( $groups[ $group_id ] ) ) {
			return $context->isComplete( GuardrailContext::KIND_GROUPS ) ? 'missing' : null;
		}
		$group = $groups[ $group_id ];
		if ( Status::Active !== $group->status ) {
			return 'group_inactive';
		}
		if ( array() === $group->members ) {
			return 'group_empty';
		}
		if ( ! $context->isComplete( GuardrailContext::KIND_ADS ) ) {
			return null;
		}
		foreach ( $group->memberAdIds() as $ad_id ) {
			if ( isset( $ads[ $ad_id ] ) && null === Showability::adReason( $ads[ $ad_id ], $context->now ) ) {
				return null;
			}
		}
		return 'group_no_member';
	}
}
