<?php
/**
 * Single rendering path shared by shortcode, block, and automatic insertion.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rendering;

use DateTimeImmutable;
use RevGuardPro\Content\ContentContext;
use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\AdGroup;
use RevGuardPro\Domain\Exception\CorruptRecordException;
use RevGuardPro\Domain\Exception\InvalidEntityException;
use RevGuardPro\Domain\Exception\PersistenceException;
use RevGuardPro\Domain\Repository\AdGroupRepositoryInterface;
use RevGuardPro\Domain\Repository\AdRepositoryInterface;
use RevGuardPro\Domain\Status;
use RevGuardPro\Domain\TargetType;
use RevGuardPro\Rotation\AdEligibility;
use RevGuardPro\Rotation\GroupSelector;
use RevGuardPro\Support\ClockInterface;
use RevGuardPro\Targeting\CacheProfile;
use RevGuardPro\Targeting\TargetedNarrowing;
use RevGuardPro\Targeting\TargetingEvaluator;
use RevGuardPro\Targeting\TargetingRequirements;

defined( 'ABSPATH' ) || exit;

/**
 * Single rendering path used by the shortcode, block, and automatic insertion. All eligibility,
 * targeting, rotation, duplicate-prevention and cache-profile recording happens here.
 */
final class SurfaceRenderer {

	/**
	 * Eligibility checker (no dependencies; one instance per renderer).
	 *
	 * @var AdEligibility
	 */
	private readonly AdEligibility $eligibility;

	/**
	 * Memoized single-ad lookups. Null means the ad did not exist.
	 *
	 * @var array<int, Ad|null>
	 */
	private array $ad_cache = array();

	/**
	 * Memoized group lookups. Null means the group did not exist.
	 *
	 * @var array<int, AdGroup|null>
	 */
	private array $group_cache = array();

	/**
	 * Memoized group-member ad maps, keyed by group id.
	 *
	 * @var array<int, array<int, Ad>>
	 */
	private array $group_ads_cache = array();

	/**
	 * Constructor.
	 *
	 * @param AdRepositoryInterface      $ads           Ad repository.
	 * @param AdGroupRepositoryInterface $groups        Group repository.
	 * @param GroupSelector              $selector      Rotation/selection service.
	 * @param TargetingEvaluator         $evaluator     Targeting evaluator.
	 * @param RequestTargetingContext    $contexts      Request-scoped context cache.
	 * @param AdRenderer                 $renderer      Markup renderer.
	 * @param RenderState                $state         Request-scoped render state.
	 * @param ClockInterface             $clock         Time source.
	 * @param SurfaceAuthorization       $authorization Authorization guard.
	 */
	public function __construct(
		private readonly AdRepositoryInterface $ads,
		private readonly AdGroupRepositoryInterface $groups,
		private readonly GroupSelector $selector,
		private readonly TargetingEvaluator $evaluator,
		private readonly RequestTargetingContext $contexts,
		private readonly AdRenderer $renderer,
		private readonly RenderState $state,
		private readonly ClockInterface $clock,
		private readonly SurfaceAuthorization $authorization
	) {
		$this->eligibility = new AdEligibility();
	}

	/**
	 * Renders an ad or group for the given context. Returns '' on any ineligible or blocked condition.
	 *
	 * @param TargetType     $type        Target type (Ad or Group).
	 * @param int            $id          Target id.
	 * @param int            $post_id     Current post id (0 when outside the loop).
	 * @param ContentContext $context     Output context.
	 * @param string         $extra_class Extra CSS class tokens (space-separated).
	 */
	public function render( TargetType $type, int $id, int $post_id, ContentContext $context, string $extra_class = '' ): string {
		// Step 1: gate checks.
		if ( ! $context->allowsEmbeddedSurface() ) {
			return '';
		}
		if ( $id < 1 ) {
			return '';
		}
		if ( ! $this->authorization->allows( $post_id ) ) {
			return '';
		}

		try {
			return $this->doRender( $type, $id, $post_id, $extra_class );
		} catch ( CorruptRecordException | PersistenceException | InvalidEntityException ) {
			return '';
		}
	}

	/**
	 * Inner render after gate checks. Throws domain exceptions (caught by render()).
	 *
	 * @param TargetType $type        Target type.
	 * @param int        $id          Target id.
	 * @param int        $post_id     Post id.
	 * @param string     $extra_class Extra class.
	 * @throws CorruptRecordException  On corrupt records.
	 * @throws PersistenceException    On storage failures.
	 * @throws InvalidEntityException  On invalid entity data.
	 */
	private function doRender( TargetType $type, int $id, int $post_id, string $extra_class ): string {
		$now = $this->clock->now();

		if ( TargetType::Ad === $type ) {
			return $this->renderAd( $id, $post_id, $extra_class, $now );
		}
		return $this->renderGroup( $id, $post_id, $extra_class, $now );
	}

	/**
	 * Handles an ad-target render.
	 *
	 * @param int               $id          Ad id.
	 * @param int               $post_id     Post id.
	 * @param string            $extra_class Extra class.
	 * @param DateTimeImmutable $now         Current time.
	 */
	private function renderAd( int $id, int $post_id, string $extra_class, DateTimeImmutable $now ): string {
		// Step 2: load, memoized.
		if ( ! array_key_exists( $id, $this->ad_cache ) ) {
			$this->ad_cache[ $id ] = $this->ads->find( $id );
		}
		$ad = $this->ad_cache[ $id ];

		if ( null === $ad ) {
			return '';
		}

		// Step 4: record cache metadata as soon as the target loaded successfully.
		$this->recordCacheMetadata( array( $ad ), $now );

		// Step 3: eligibility check.
		if ( ! $this->eligibility->isEligible( $ad, $now ) ) {
			return '';
		}

		// Step 3: targeting check.
		$requirements = $ad->targeting->requirements();
		$ctx          = $this->contexts->forRequirements( $requirements );
		if ( ! $this->evaluator->matches( $ad->targeting, $ctx ) ) {
			return '';
		}

		// Step 5: duplicate prevention.
		if ( $this->state->rendered( $post_id, (int) $ad->id ) ) {
			return '';
		}

		// Step 6: render.
		$markup = $this->renderer->renderAd( $ad, $extra_class );
		if ( '' === $markup ) {
			return '';
		}
		$this->state->markRendered( $post_id, (int) $ad->id );
		return $markup;
	}

	/**
	 * Handles a group-target render.
	 *
	 * @param int               $id          Group id.
	 * @param int               $post_id     Post id.
	 * @param string            $extra_class Extra class.
	 * @param DateTimeImmutable $now         Current time.
	 */
	private function renderGroup( int $id, int $post_id, string $extra_class, DateTimeImmutable $now ): string {
		// Step 2: load group, memoized.
		if ( ! array_key_exists( $id, $this->group_cache ) ) {
			$this->group_cache[ $id ] = $this->groups->find( $id );
		}
		$group = $this->group_cache[ $id ];

		if ( null === $group ) {
			return '';
		}

		// Step 3: group must be active and non-empty.
		if ( Status::Active !== $group->status ) {
			return '';
		}
		$member_ids = $group->memberAdIds();
		if ( array() === $member_ids ) {
			return '';
		}

		// Step 2: load member ads, memoized per group id.
		if ( ! array_key_exists( $id, $this->group_ads_cache ) ) {
			$this->group_ads_cache[ $id ] = $this->ads->findMany( $member_ids );
		}
		$ads_by_id = $this->group_ads_cache[ $id ];

		// Step 3: build merged requirements and get context.
		$requirement_list = array();
		foreach ( $ads_by_id as $member_ad ) {
			$requirement_list[] = $member_ad->targeting->requirements();
		}
		$merged_requirements = TargetingRequirements::merge( ...$requirement_list );
		$ctx                 = $this->contexts->forRequirements( $merged_requirements );

		// Select via rotation + targeting narrowing.
		$ad = $this->selector->select(
			$group,
			$ads_by_id,
			$now,
			( new TargetedNarrowing( $this->evaluator, $ctx ) )->forCandidates()
		);

		// Step 4: record cache metadata for all loaded member ads.
		$this->recordCacheMetadata( array_values( $ads_by_id ), $now );

		if ( null === $ad ) {
			return '';
		}

		// Step 5: duplicate prevention.
		if ( $this->state->rendered( $post_id, (int) $ad->id ) ) {
			return '';
		}

		// Step 6: render.
		$markup = $this->renderer->renderAd( $ad, $extra_class );
		if ( '' === $markup ) {
			return '';
		}
		$this->state->markRendered( $post_id, (int) $ad->id );
		return $markup;
	}

	/**
	 * Records cache profile and valid-until for the given ads. Considers only active ads for valid_until.
	 *
	 * @param array<Ad>         $ads Ads to consider.
	 * @param DateTimeImmutable $now Current time.
	 */
	private function recordCacheMetadata( array $ads, DateTimeImmutable $now ): void {
		$profiles = array();
		foreach ( $ads as $ad ) {
			$profiles[] = $ad->targeting->cacheProfile();
		}
		if ( array() !== $profiles ) {
			$this->state->addProfile( CacheProfile::merge( ...$profiles ) );
		}
		foreach ( $ads as $ad ) {
			if ( Status::Active !== $ad->status ) {
				continue;
			}
			$this->state->addValidUntil( $ad->schedule->nextTransitionAfter( $now ) );
		}
	}
}
