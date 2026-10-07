<?php
/**
 * Human-readable, carefully worded text for findings.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Guardrails;

defined( 'ABSPATH' ) || exit;

/**
 * Fixed translatable templates with integer parameters only. Hedged on purpose ("may", "consider", "review"); no
 * claim of approval, certification or protection (ADR 008 section 3). Output is plain text for the caller to escape.
 */
final class GuardrailMessages {

	/**
	 * The text for a finding.
	 *
	 * @param Finding $finding Finding.
	 */
	public static function text( Finding $finding ): string {
		$count = (int) ( $finding->params['count'] ?? 0 );
		$other = (int) ( $finding->params['other'] ?? 0 );
		$gap   = (int) ( $finding->params['gap'] ?? 0 );
		return match ( $finding->code ) {
			GuardrailCode::ManyPlacements           => sprintf(
				/* translators: %d: number of active automatic placements. */
				__( '%d automatic placements are active. A page may show that many ads at once, which can make content harder to read. Consider whether you need all of them.', 'revguard-pro' ),
				$count
			),
			GuardrailCode::TopCluster               => sprintf(
				/* translators: %d: number of placements near the top of the content. */
				__( '%d active placements sit near the top of the content. Several ads before the content may make it hard to find. Consider spacing them out.', 'revguard-pro' ),
				$count
			),
			GuardrailCode::ClosePlacementSpacing    => sprintf(
				/* translators: 1: other placement id, 2: distance between the two positions. */
				__( 'This placement is very close to placement #%1$d (distance %2$d). Ads placed this close together may feel crowded. Review the spacing.', 'revguard-pro' ),
				$other,
				$gap
			),
			GuardrailCode::DuplicatePlacement       => sprintf(
				/* translators: %d: id of the placement this one repeats. */
				__( 'This placement repeats placement #%d (same position and same target). The second one may add nothing. Consider removing it.', 'revguard-pro' ),
				$other
			),
			GuardrailCode::InsertionNotActive       => __( 'Active placements exist, but RevGuard Pro is not inserting them automatically right now (another plugin owns insertion, or automatic insertion is turned off). Review the settings if you expected them to appear.', 'revguard-pro' ),
			GuardrailCode::UnfilteredHtmlEnabled    => __( 'HTML ad code is output without filtering. Anyone who can edit ads can then run scripts on your pages. Consider turning this off unless you fully trust every editor.', 'revguard-pro' ),
			GuardrailCode::PlacementTargetMissing   => __( 'This placement points at an ad or group that no longer exists, so it may show nothing. Review its target.', 'revguard-pro' ),
			GuardrailCode::PlacementNotShowing      => __( 'This placement is active, but its target cannot show right now (it may be inactive, outside its schedule, empty, or have unreadable targeting). Review the target.', 'revguard-pro' ),
			GuardrailCode::PlacementTargetingBroken => __( 'The targeting stored for this placement could not be read, so it matches nothing. Review and save its targeting again.', 'revguard-pro' ),
			GuardrailCode::AdHtmlRendersEmpty       => __( 'This active ad\'s HTML is reduced to nothing by the output filter (for example, only scripts), so it may show nothing. Review the ad code.', 'revguard-pro' ),
			GuardrailCode::AdScheduleExpired        => __( 'This active ad\'s schedule has ended, so it no longer shows. Consider extending the schedule or deactivating the ad.', 'revguard-pro' ),
			GuardrailCode::AdTargetingBroken        => __( 'The targeting stored for this active ad could not be read, so it matches nothing. Review and save its targeting again.', 'revguard-pro' ),
			GuardrailCode::GroupEmpty               => __( 'This active group has no ads, so it may show nothing. Consider adding ads or deactivating it.', 'revguard-pro' ),
			GuardrailCode::GroupNoEligibleMember    => __( 'None of the ads in this active group can show right now (inactive, outside their schedule, or unreadable targeting). Review the members.', 'revguard-pro' ),
			GuardrailCode::DataTruncated            => __( 'There are more records than the checks read, so some checks were skipped. Review the rest by hand.', 'revguard-pro' ),
			GuardrailCode::DataUnreadable           => __( 'Some records could not be read, so this report is incomplete. An empty result does not mean everything was checked.', 'revguard-pro' ),
		};
	}
}
