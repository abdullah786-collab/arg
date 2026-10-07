<?php
/**
 * Guardrail checks on active ads and groups.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Guardrails;

use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\AdGroup;
use RevGuardPro\Domain\Status;
use RevGuardPro\Rendering\HtmlCreativeSanitizerInterface;
use RevGuardPro\Scheduling\ScheduleState;

defined( 'ABSPATH' ) || exit;

/**
 * Only active entities are examined: an inactive ad or group is a draft. Reads nothing; sanitises at most
 * MAX_CREATIVE_CHECKS creatives per run and reports the rest as skipped.
 */
final class EntityChecks {

	public const MAX_CREATIVE_CHECKS = 200;

	/**
	 * Constructor.
	 *
	 * @param HtmlCreativeSanitizerInterface $sanitizer The output filter used at render time.
	 */
	public function __construct( private readonly HtmlCreativeSanitizerInterface $sanitizer ) {
	}

	/**
	 * Findings for active ads.
	 *
	 * @param array<int,Ad>    $ads     Ads by id.
	 * @param GuardrailContext $context Context.
	 * @param int              $skipped Checks skipped by a bound (by reference).
	 * @return array<int,Finding>
	 */
	public function ads( array $ads, GuardrailContext $context, int &$skipped ): array {
		$findings = array();
		$checked  = 0;
		foreach ( $ads as $id => $ad ) {
			if ( Status::Active !== $ad->status ) {
				continue;
			}
			if ( ! $context->allow_unfiltered && '' !== trim( $ad->creative ) ) {
				if ( $checked >= self::MAX_CREATIVE_CHECKS ) {
					++$skipped;
				} else {
					++$checked;
					if ( '' === trim( $this->sanitizer->sanitize( $ad->creative ) ) || ! self::hasVisibleContent( $ad->creative ) ) {
						$findings[] = $this->finding( GuardrailCode::AdHtmlRendersEmpty, Finding::SUBJECT_AD, $id );
					}
				}
			}
			if ( ScheduleState::Expired === $ad->schedule->stateAt( $context->now ) ) {
				$findings[] = $this->finding( GuardrailCode::AdScheduleExpired, Finding::SUBJECT_AD, $id );
			}
			if ( $ad->targeting->isCorrupt() ) {
				$findings[] = $this->finding( GuardrailCode::AdTargetingBroken, Finding::SUBJECT_AD, $id );
			}
		}
		return $findings;
	}

	/**
	 * Findings for active groups.
	 *
	 * @param array<int,AdGroup> $groups  Groups by id.
	 * @param array<int,Ad>      $ads     Ads by id.
	 * @param GuardrailContext   $context Context.
	 * @return array<int,Finding>
	 */
	public function groups( array $groups, array $ads, GuardrailContext $context ): array {
		$findings = array();
		foreach ( $groups as $id => $group ) {
			if ( Status::Active !== $group->status ) {
				continue;
			}
			if ( array() === $group->members ) {
				$findings[] = $this->finding( GuardrailCode::GroupEmpty, Finding::SUBJECT_GROUP, $id );
				continue;
			}
			if ( ! $context->isComplete( GuardrailContext::KIND_ADS ) ) {
				continue;
			}
			$showable = false;
			foreach ( $group->memberAdIds() as $ad_id ) {
				if ( isset( $ads[ $ad_id ] ) && null === Showability::adReason( $ads[ $ad_id ], $context->now ) ) {
					$showable = true;
					break;
				}
			}
			if ( ! $showable ) {
				$findings[] = $this->finding( GuardrailCode::GroupNoEligibleMember, Finding::SUBJECT_GROUP, $id );
			}
		}
		return $findings;
	}

	/**
	 * Whether any content would be left once active elements are removed. The output filter drops the tag of a script
	 * but keeps the text inside it, so "not empty after filtering" is not enough: a script-only creative would show
	 * code as text. Visible content means text outside active elements, or a media element.
	 *
	 * @param string $html Creative HTML.
	 */
	private static function hasVisibleContent( string $html ): bool {
		$visible = (string) preg_replace( '#<(script|style|iframe|object|embed|noscript|template)\b.*?</\1\s*>#is', '', $html );
		if ( '' !== trim( wp_strip_all_tags( $visible ) ) ) {
			return true;
		}
		return 1 === preg_match( '#<(img|picture|svg|video|audio|canvas|a)\b#i', $visible );
	}

	/**
	 * Builds a finding with the code's default severity.
	 *
	 * @param GuardrailCode $code    Code.
	 * @param string        $subject Subject kind.
	 * @param int           $id      Subject id.
	 */
	private function finding( GuardrailCode $code, string $subject, int $id ): Finding {
		return new Finding( $code, $code->severity(), $subject, $id );
	}
}
