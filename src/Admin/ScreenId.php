<?php
/**
 * The admin screens of RevGuard Pro (ADR 009).
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin;

use RevGuardPro\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Frozen navigation: slug, capability and title per screen.
 */
enum ScreenId: string {

	case Dashboard  = 'revguard-pro';
	case Ads        = 'revguard-pro-ads';
	case Groups     = 'revguard-pro-groups';
	case Placements = 'revguard-pro-placements';
	case Analytics  = 'revguard-pro-analytics';
	case Settings   = 'revguard-pro-settings';

	/**
	 * The capability required to open the screen.
	 */
	public function capability(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts -- False positive: $this is valid inside an enum method.
			self::Analytics => Capabilities::VIEW_ANALYTICS,
			self::Settings  => Capabilities::MANAGE_SETTINGS,
			default         => Capabilities::MANAGE_ADS,
		};
	}

	/**
	 * The screen title (also the menu label).
	 */
	public function title(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts -- False positive: $this is valid inside an enum method.
			self::Dashboard  => __( 'Dashboard', 'revguard-pro' ),
			self::Ads        => __( 'Ads', 'revguard-pro' ),
			self::Groups     => __( 'Groups', 'revguard-pro' ),
			self::Placements => __( 'Placements', 'revguard-pro' ),
			self::Analytics  => __( 'Analytics', 'revguard-pro' ),
			self::Settings   => __( 'Settings', 'revguard-pro' ),
		};
	}

	/**
	 * Every screen, in menu order.
	 *
	 * @return array<int,self>
	 */
	public static function all(): array {
		return self::cases();
	}
}
