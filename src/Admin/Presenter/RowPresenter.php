<?php
/**
 * Text for list-table cells and summaries.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Presenter;

use DateTimeImmutable;
use DateTimeZone;
use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\AdGroup;
use RevGuardPro\Domain\Placement;
use RevGuardPro\Domain\PlacementType;
use RevGuardPro\Domain\TargetType;
use RevGuardPro\Scheduling\ScheduleState;
use RevGuardPro\Scheduling\ScheduleTime;
use RevGuardPro\Targeting\TargetingDefinition;

defined( 'ABSPATH' ) || exit;

/**
 * Pure: plain strings in, plain strings out. Escaping is done where the text is printed.
 */
final class RowPresenter {

	/**
	 * When an ad runs, in the site's time zone.
	 *
	 * @param Ad                $ad   Ad.
	 * @param DateTimeZone      $zone Site time zone.
	 * @param DateTimeImmutable $now  Current instant.
	 */
	public static function schedule( Ad $ad, DateTimeZone $zone, DateTimeImmutable $now ): string {
		$schedule = $ad->schedule;
		if ( $schedule->isAlways() ) {
			return __( 'Always', 'revguard-pro' );
		}
		$start = null === $schedule->start ? '' : substr( ScheduleTime::toLocal( $schedule->start, $zone ), 0, 16 );
		$end   = null === $schedule->end ? '' : substr( ScheduleTime::toLocal( $schedule->end, $zone ), 0, 16 );
		if ( '' !== $start && '' !== $end ) {
			/* translators: 1: start date and time, 2: end date and time. */
			$text = sprintf( __( '%1$s to %2$s', 'revguard-pro' ), $start, $end );
		} elseif ( '' !== $start ) {
			/* translators: %s: start date and time. */
			$text = sprintf( __( 'From %s', 'revguard-pro' ), $start );
		} else {
			/* translators: %s: end date and time. */
			$text = sprintf( __( 'Until %s', 'revguard-pro' ), $end );
		}
		return match ( $schedule->stateAt( $now ) ) {
			ScheduleState::Expired => $text . ' ' . __( '(ended)', 'revguard-pro' ),
			ScheduleState::Pending => $text . ' ' . __( '(not started)', 'revguard-pro' ),
			ScheduleState::Active  => $text,
		};
	}

	/**
	 * A one-word targeting summary. Targeting is edited in a later release; it is preserved on every save.
	 *
	 * @param TargetingDefinition $targeting Targeting.
	 */
	public static function targeting( TargetingDefinition $targeting ): string {
		if ( $targeting->isCorrupt() ) {
			return __( 'Unreadable (matches nothing)', 'revguard-pro' );
		}
		return $targeting->isUnrestricted() ? __( 'Everyone', 'revguard-pro' ) : __( 'Restricted by rules', 'revguard-pro' );
	}

	/**
	 * Group size and rotation.
	 *
	 * @param AdGroup $group Group.
	 */
	public static function group( AdGroup $group ): string {
		$count = count( $group->members );
		return sprintf(
			/* translators: 1: number of ads, 2: rotation mode. */
			_n( '%1$d ad, %2$s', '%1$d ads, %2$s', $count, 'revguard-pro' ),
			$count,
			Labels::rotation( $group->rotation_mode )
		);
	}

	/**
	 * Where a placement puts its ad.
	 *
	 * @param Placement $placement Placement.
	 */
	public static function position( Placement $placement ): string {
		return match ( $placement->type() ) {
			PlacementType::AfterParagraph => sprintf(
				/* translators: %d: paragraph number. */
				__( 'After paragraph %d', 'revguard-pro' ),
				$placement->config->value( 'paragraph' )
			),
			PlacementType::AfterHeading   => sprintf(
				/* translators: 1: heading level number, 2: which occurrence. */
				__( 'After heading H%1$d, occurrence %2$d', 'revguard-pro' ),
				$placement->config->value( 'heading_level' ),
				$placement->config->value( 'occurrence' )
			),
			PlacementType::Percentage     => sprintf(
				/* translators: %d: percent of the way through the content. */
				__( 'At %d%% of the content', 'revguard-pro' ),
				$placement->config->value( 'percent' )
			),
			default                       => Labels::placementType( $placement->type() ),
		};
	}

	/**
	 * What a placement shows.
	 *
	 * @param Placement         $placement Placement.
	 * @param array<int,string> $ads       Ad names by id.
	 * @param array<int,string> $groups    Group names by id.
	 */
	public static function target( Placement $placement, array $ads, array $groups ): string {
		if ( null === $placement->target_type || null === $placement->target_id ) {
			return __( 'No target', 'revguard-pro' );
		}
		$id = $placement->target_id;
		if ( TargetType::Ad === $placement->target_type ) {
			/* translators: %d: ad id. */
			return $ads[ $id ] ?? sprintf( __( 'Ad #%d (missing)', 'revguard-pro' ), $id );
		}
		/* translators: %d: group id. */
		return $groups[ $id ] ?? sprintf( __( 'Group #%d (missing)', 'revguard-pro' ), $id );
	}
}
