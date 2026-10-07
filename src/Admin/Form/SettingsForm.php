<?php
/**
 * Parses and validates the settings form.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Form;

use RevGuardPro\Admin\Support\Input;
use RevGuardPro\Analytics\StatsRetention;

defined( 'ABSPATH' ) || exit;

/**
 * Five settings, each validated strictly. Nothing is clamped silently: an out-of-range retention is an error the owner
 * sees. Turning unfiltered HTML on requires the core `unfiltered_html` capability; turning it off never does.
 */
final class SettingsForm {

	/**
	 * Parses the POST body.
	 *
	 * @param array<string,mixed> $post          POST values.
	 * @param array<string,mixed> $current       Current settings.
	 * @param bool                $can_unfiltered Whether the user holds `unfiltered_html`.
	 */
	public static function parse( array $post, array $current, bool $can_unfiltered ): FormResult {
		$errors    = array();
		$retention = Input::int( $post['analytics_retention_days'] ?? null, StatsRetention::MIN_DAYS, StatsRetention::MAX_DAYS );
		if ( null === $retention ) {
			$errors['analytics_retention_days'] = 'out_of_range';
		}
		$unfiltered = isset( $post['allow_unfiltered_html_creatives'] );
		if ( $unfiltered && ! $can_unfiltered && true !== ( $current['allow_unfiltered_html_creatives'] ?? false ) ) {
			$errors['allow_unfiltered_html_creatives'] = 'not_allowed';
		}
		$values = array(
			'delete_data_on_uninstall'        => isset( $post['delete_data_on_uninstall'] ),
			'allow_unfiltered_html_creatives' => $unfiltered,
			'automatic_insertion'             => isset( $post['automatic_insertion'] ),
			'analytics_enabled'               => isset( $post['analytics_enabled'] ),
			'analytics_retention_days'        => $retention ?? (int) ( $current['analytics_retention_days'] ?? StatsRetention::DEFAULT_DAYS ),
		);
		if ( array() !== $errors ) {
			$values['analytics_retention_days'] = Input::text( $post['analytics_retention_days'] ?? '', 6 );
			return new FormResult( null, $errors, $values );
		}
		return new FormResult( $values, array(), $values );
	}
}
