<?php
/**
 * Human labels for domain enums.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Presenter;

use RevGuardPro\Domain\PlacementType;
use RevGuardPro\Domain\RotationMode;
use RevGuardPro\Domain\Status;
use RevGuardPro\Guardrails\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * One place for the words the admin uses, all translatable.
 */
final class Labels {

	/**
	 * Status label.
	 *
	 * @param Status $status Status.
	 */
	public static function status( Status $status ): string {
		return Status::Active === $status ? __( 'Active', 'revguard-pro' ) : __( 'Inactive', 'revguard-pro' );
	}


	/**
	 * Rotation mode label.
	 *
	 * @param RotationMode $mode Mode.
	 */
	public static function rotation( RotationMode $mode ): string {
		return RotationMode::Ordered === $mode ? __( 'In order', 'revguard-pro' ) : __( 'Weighted random', 'revguard-pro' );
	}

	/**
	 * Placement type label.
	 *
	 * @param PlacementType $type Type.
	 */
	public static function placementType( PlacementType $type ): string {
		return match ( $type ) {
			PlacementType::BeforeContent  => __( 'Before the content', 'revguard-pro' ),
			PlacementType::AfterContent   => __( 'After the content', 'revguard-pro' ),
			PlacementType::AfterParagraph => __( 'After a paragraph', 'revguard-pro' ),
			PlacementType::AfterHeading   => __( 'After a heading', 'revguard-pro' ),
			PlacementType::Percentage     => __( 'At a point in the content', 'revguard-pro' ),
			PlacementType::Manual         => __( 'Manual (shortcode or block only)', 'revguard-pro' ),
		};
	}

	/**
	 * Severity label.
	 *
	 * @param Severity $severity Severity.
	 */
	public static function severity( Severity $severity ): string {
		return Severity::Warning === $severity ? __( 'Worth checking', 'revguard-pro' ) : __( 'For your information', 'revguard-pro' );
	}
}
