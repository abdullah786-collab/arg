<?php
/**
 * Pure User-Agent device heuristic.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Targeting\Context;

defined( 'ABSPATH' ) || exit;

use RevGuardPro\Targeting\DeviceType;

/**
 * Classifies a User-Agent as tablet, mobile or desktop. A heuristic for audience hinting, not a security control.
 * Tablet markers win over mobile markers; an empty or unknown agent is desktop. iPadOS Safari presents as a Mac
 * and therefore reads as desktop.
 */
final class DeviceDetector {

	private const MAX_LENGTH = 512;

	/**
	 * Device class for a User-Agent string.
	 *
	 * @param string $user_agent Raw User-Agent.
	 */
	public static function detect( string $user_agent ): DeviceType {
		$ua = strtolower( substr( $user_agent, 0, self::MAX_LENGTH ) );
		if ( '' === $ua ) {
			return DeviceType::Desktop;
		}

		$is_android = str_contains( $ua, 'android' );
		if ( str_contains( $ua, 'ipad' ) || str_contains( $ua, 'tablet' ) || str_contains( $ua, 'kindle' ) || str_contains( $ua, 'silk/' ) || str_contains( $ua, 'playbook' ) || ( $is_android && ! str_contains( $ua, 'mobile' ) ) ) {
			return DeviceType::Tablet;
		}
		foreach ( array( 'mobi', 'iphone', 'ipod', 'android', 'windows phone', 'opera mini', 'blackberry' ) as $marker ) {
			if ( str_contains( $ua, $marker ) ) {
				return DeviceType::Mobile;
			}
		}
		return DeviceType::Desktop;
	}
}
