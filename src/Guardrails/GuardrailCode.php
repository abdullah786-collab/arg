<?php
/**
 * The frozen catalogue of guardrail findings (ADR 008).
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Guardrails;

defined( 'ABSPATH' ) || exit;

/**
 * Declaration order is the report order within a severity.
 */
enum GuardrailCode: string {

	case ManyPlacements           = 'many_placements';
	case TopCluster               = 'top_cluster';
	case ClosePlacementSpacing    = 'close_spacing';
	case DuplicatePlacement       = 'duplicate_placement';
	case InsertionNotActive       = 'insertion_not_active';
	case UnfilteredHtmlEnabled    = 'unfiltered_html_enabled';
	case PlacementTargetMissing   = 'placement_target_missing';
	case PlacementNotShowing      = 'placement_target_not_showing';
	case PlacementTargetingBroken = 'placement_targeting_corrupt';
	case AdHtmlRendersEmpty       = 'ad_html_renders_empty';
	case AdScheduleExpired        = 'ad_schedule_expired';
	case AdTargetingBroken        = 'ad_targeting_corrupt';
	case GroupEmpty               = 'group_empty';
	case GroupNoEligibleMember    = 'group_no_eligible_member';
	case DataTruncated            = 'data_truncated';
	case DataUnreadable           = 'data_unreadable';

	/**
	 * The severity this code carries unless the analyzer raises it (only `many_placements` varies).
	 */
	public function severity(): Severity {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts -- False positive: $this is valid inside an enum method.
			self::ManyPlacements,
			self::ClosePlacementSpacing,
			self::InsertionNotActive,
			self::AdScheduleExpired,
			self::DataTruncated => Severity::Info,
			default             => Severity::Warning,
		};
	}
}
