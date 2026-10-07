<?php
/**
 * Placement Engine V2: composes M2 eligibility/rotation, M3 targeting and content insertion.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Content;

use Closure;
use DateTimeImmutable;
use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\AdGroup;
use RevGuardPro\Domain\Placement;
use RevGuardPro\Domain\PlacementOrdering;
use RevGuardPro\Domain\PlacementType;
use RevGuardPro\Domain\Repository\AdGroupRepositoryInterface;
use RevGuardPro\Domain\Repository\AdRepositoryInterface;
use RevGuardPro\Domain\Repository\PlacementRepositoryInterface;
use RevGuardPro\Domain\Status;
use RevGuardPro\Domain\TargetType;
use RevGuardPro\Persistence\Paging;
use RevGuardPro\Rotation\AdEligibility;
use RevGuardPro\Rotation\GroupSelector;
use RevGuardPro\Support\ClockInterface;
use RevGuardPro\Targeting\CacheProfile;
use RevGuardPro\Targeting\TargetedNarrowing;
use RevGuardPro\Targeting\TargetingContext;
use RevGuardPro\Targeting\TargetingEvaluator;
use RevGuardPro\Targeting\TargetingRequirements;

defined( 'ABSPATH' ) || exit;

/**
 * Runs one pass over a piece of content (docs/adr/004-placement-engine-v2.md). Registers no hook and writes nothing:
 * callers (M5) hand it the content and the output context. Data loading is four queries however many placements
 * exist: active placements, active groups with members, and one batch of every referenced ad.
 */
final class PlacementEngine {

	public const MAX_INSERTIONS = 20;

	/**
	 * Constructor.
	 *
	 * @param PlacementRepositoryInterface $placements      Placements.
	 * @param AdGroupRepositoryInterface   $groups          Groups.
	 * @param AdRepositoryInterface        $ads             Ads.
	 * @param GroupSelector                $selector        M2 selector.
	 * @param TargetingEvaluator           $evaluator       M3 evaluator.
	 * @param ContentAnalyzer              $analyzer        Content scanner.
	 * @param InsertionPlanner             $planner         Boundary planner.
	 * @param InsertionApplier             $applier         Insertion applier.
	 * @param AdMarkupRendererInterface    $renderer        Renderer (M5).
	 * @param ClockInterface               $clock           Time source.
	 * @param Closure                      $context_factory Builds the targeting context once: Closure(TargetingRequirements): TargetingContext.
	 */
	public function __construct(
		private readonly PlacementRepositoryInterface $placements,
		private readonly AdGroupRepositoryInterface $groups,
		private readonly AdRepositoryInterface $ads,
		private readonly GroupSelector $selector,
		private readonly TargetingEvaluator $evaluator,
		private readonly ContentAnalyzer $analyzer,
		private readonly InsertionPlanner $planner,
		private readonly InsertionApplier $applier,
		private readonly AdMarkupRendererInterface $renderer,
		private readonly ClockInterface $clock,
		private readonly Closure $context_factory
	) {
	}

	/**
	 * Runs a pass.
	 *
	 * @param string         $content Post content.
	 * @param ContentContext $context Output context.
	 * @param array<int>     $already_rendered_ad_ids Ads already rendered in this content scope (shortcodes, blocks); they are skipped.
	 */
	public function run( string $content, ContentContext $context, array $already_rendered_ad_ids = array() ): PlacementResult {
		if ( ! $context->allowsInsertion() ) {
			return new PlacementResult( $content, array(), array(), CacheProfile::none(), null, true );
		}
		$placements = $this->placements->list( Status::Active, Paging::MAX_LIMIT );
		if ( array() === $placements ) {
			return new PlacementResult( $content, array(), array(), CacheProfile::none(), null );
		}
		usort( $placements, PlacementOrdering::compare( ... ) );

		$groups = $this->loadGroups( $placements );
		$ads    = $this->ads->findMany( $this->adIds( $placements, $groups ) );
		$now    = $this->clock->now();

		$targeting = $this->targetingContext( $placements, $ads );
		$narrowing = new TargetedNarrowing( $this->evaluator, $targeting );
		$profile   = $this->cacheProfile( $placements, $ads );

		$map        = $this->analyzer->analyze( $content );
		$insertions = array();
		$skipped    = array();
		$used_ads   = array_fill_keys( array_map( 'intval', $already_rendered_ad_ids ), true );
		$taken      = array();
		$eligible   = new AdEligibility();

		foreach ( $placements as $placement ) {
			$id     = (int) $placement->id;
			$reason = $this->skipReason( $placement, $map, count( $insertions ) );
			if ( null === $reason && $this->evaluator->matches( $placement->targeting, $targeting ) ) {
				$offset = $this->planner->locate( $map, $placement->config );
				if ( $offset instanceof SkipReason ) {
					$reason = $offset;
				} elseif ( isset( $taken[ $offset ] ) ) {
					$reason = SkipReason::BoundaryTaken;
				} else {
					$ad = $this->resolveAd( $placement, $groups, $ads, $now, $targeting, $narrowing, $eligible );
					if ( $ad instanceof SkipReason ) {
						$reason = $ad;
					} elseif ( isset( $used_ads[ (int) $ad->id ] ) ) {
						$reason = SkipReason::DuplicateAd;
					} else {
						$markup = $this->renderer->render( $ad, $placement );
						if ( '' === trim( $markup ) ) {
							$reason = SkipReason::EmptyMarkup;
						} else {
							$insertions[]              = new Insertion( $id, (int) $ad->id, $offset, $markup );
							$used_ads[ (int) $ad->id ] = true;
							$taken[ $offset ]          = true;
						}
					}
				}
			} elseif ( null === $reason ) {
				$reason = SkipReason::Targeting;
			}
			if ( null !== $reason ) {
				$skipped[ $id ] = $reason;
			}
		}

		return new PlacementResult( $this->applier->apply( $content, $insertions ), $insertions, $skipped, $profile, $this->validUntil( $ads, $now ) );
	}

	/**
	 * Cheap pre-checks that need no targeting, location or rendering.
	 *
	 * @param Placement  $placement  Placement.
	 * @param ContentMap $map        Analyzed content.
	 * @param int        $made       Insertions made so far.
	 */
	private function skipReason( Placement $placement, ContentMap $map, int $made ): ?SkipReason {
		if ( $made >= self::MAX_INSERTIONS ) {
			return SkipReason::LimitReached;
		}
		if ( PlacementType::Manual === $placement->type() ) {
			return SkipReason::Manual;
		}
		return isset( $map->placement_ids[ (int) $placement->id ] ) ? SkipReason::DuplicatePlacement : null;
	}

	/**
	 * Resolves the ad a placement shows, or the reason there is none.
	 *
	 * @param Placement          $placement Placement.
	 * @param array<int,AdGroup> $groups    Active groups by id.
	 * @param array<int,Ad>      $ads       Loaded ads by id.
	 * @param DateTimeImmutable  $now       Current instant.
	 * @param TargetingContext   $targeting Targeting context.
	 * @param TargetedNarrowing  $narrowing M3 narrowing for group candidates.
	 * @param AdEligibility      $eligible  M2 eligibility.
	 */
	private function resolveAd( Placement $placement, array $groups, array $ads, DateTimeImmutable $now, TargetingContext $targeting, TargetedNarrowing $narrowing, AdEligibility $eligible ): Ad|SkipReason {
		$target = (int) $placement->target_id;
		if ( TargetType::Ad === $placement->target_type ) {
			$ad = $ads[ $target ] ?? null;
			if ( ! $ad instanceof Ad || ! $eligible->isEligible( $ad, $now ) ) {
				return SkipReason::NoAd;
			}
			return $this->evaluator->matches( $ad->targeting, $targeting ) ? $ad : SkipReason::Targeting;
		}
		if ( TargetType::Group !== $placement->target_type || ! isset( $groups[ $target ] ) ) {
			return SkipReason::NoTarget;
		}
		return $this->selector->select( $groups[ $target ], $ads, $now, $narrowing->forCandidates() ) ?? SkipReason::NoAd;
	}

	/**
	 * Active groups referenced by group placements, in one listing (plus a find per group only if the listing was truncated).
	 *
	 * @param array<Placement> $placements Placements.
	 * @return array<int, AdGroup>
	 */
	private function loadGroups( array $placements ): array {
		$wanted = array();
		foreach ( $placements as $placement ) {
			if ( TargetType::Group === $placement->target_type ) {
				$wanted[ (int) $placement->target_id ] = true;
			}
		}
		if ( array() === $wanted ) {
			return array();
		}
		$listed = $this->groups->list( Status::Active, Paging::MAX_LIMIT );
		$found  = array();
		foreach ( $listed as $group ) {
			$found[ (int) $group->id ] = $group;
		}
		if ( count( $listed ) >= Paging::MAX_LIMIT ) {
			foreach ( array_keys( array_diff_key( $wanted, $found ) ) as $missing ) {
				$group = $this->groups->find( $missing );
				if ( null !== $group && Status::Active === $group->status ) {
					$found[ $missing ] = $group;
				}
			}
		}
		return array_intersect_key( $found, $wanted );
	}

	/**
	 * Every ad id the pass may need: ad targets and members of the referenced groups.
	 *
	 * @param array<Placement>   $placements Placements.
	 * @param array<int,AdGroup> $groups     Referenced groups.
	 * @return array<int>
	 */
	private function adIds( array $placements, array $groups ): array {
		$ids = array();
		foreach ( $placements as $placement ) {
			if ( TargetType::Ad === $placement->target_type ) {
				$ids[ (int) $placement->target_id ] = (int) $placement->target_id;
			}
		}
		foreach ( $groups as $group ) {
			foreach ( $group->memberAdIds() as $ad_id ) {
				$ids[ $ad_id ] = $ad_id;
			}
		}
		return array_values( $ids );
	}

	/**
	 * Builds the targeting context once, for exactly what every placement and candidate ad needs.
	 *
	 * @param array<Placement> $placements Placements.
	 * @param array<int,Ad>    $ads        Loaded ads.
	 */
	private function targetingContext( array $placements, array $ads ): TargetingContext {
		$all = array();
		foreach ( $placements as $placement ) {
			$all[] = $placement->targeting->requirements();
		}
		foreach ( $ads as $ad ) {
			$all[] = $ad->targeting->requirements();
		}
		$context = ( $this->context_factory )( TargetingRequirements::merge( ...$all ) );
		return $context instanceof TargetingContext ? $context : new TargetingContext();
	}

	/**
	 * What the output depends on: union of every placement's and every loaded ad's targeting.
	 *
	 * @param array<Placement> $placements Placements.
	 * @param array<int,Ad>    $ads        Loaded ads.
	 */
	private function cacheProfile( array $placements, array $ads ): CacheProfile {
		$profiles = array();
		foreach ( $placements as $placement ) {
			$profiles[] = $placement->targeting->cacheProfile();
		}
		foreach ( $ads as $ad ) {
			$profiles[] = $ad->targeting->cacheProfile();
		}
		return CacheProfile::merge( ...$profiles );
	}

	/**
	 * Earliest upcoming schedule transition among active ads.
	 *
	 * @param array<int,Ad>     $ads Loaded ads.
	 * @param DateTimeImmutable $now Current instant.
	 */
	private function validUntil( array $ads, DateTimeImmutable $now ): ?DateTimeImmutable {
		$earliest = null;
		foreach ( $ads as $ad ) {
			$next = Status::Active === $ad->status ? $ad->schedule->nextTransitionAfter( $now ) : null;
			if ( null !== $next && ( null === $earliest || $next->getTimestamp() < $earliest->getTimestamp() ) ) {
				$earliest = $next;
			}
		}
		return $earliest;
	}
}
