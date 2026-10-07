<?php
/**
 * Where a guardrail finding should send the owner.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Presenter;

use RevGuardPro\Admin\ScreenId;
use RevGuardPro\Admin\Support\Urls;
use RevGuardPro\Guardrails\Finding;
use RevGuardPro\Guardrails\GuardrailCode;

defined( 'ABSPATH' ) || exit;

/**
 * A finding links to the edit screen of its subject, or to Settings for the two site-level findings that Settings can
 * change. Links are built by `Urls` from fixed words and integers only.
 */
final class FindingPresenter {

	/**
	 * The URL to review a finding, or null when there is nothing to open.
	 *
	 * @param Finding $finding Finding.
	 * @param Urls    $urls    URL builder.
	 */
	public static function url( Finding $finding, Urls $urls ): ?string {
		if ( null !== $finding->subject_id ) {
			$screen = match ( $finding->subject ) {
				Finding::SUBJECT_AD        => ScreenId::Ads,
				Finding::SUBJECT_GROUP     => ScreenId::Groups,
				Finding::SUBJECT_PLACEMENT => ScreenId::Placements,
				default                    => null,
			};
			return null === $screen ? null : $urls->screen(
				$screen,
				array(
					'action' => 'edit',
					'id'     => $finding->subject_id,
				)
			);
		}
		return match ( $finding->code ) {
			GuardrailCode::UnfilteredHtmlEnabled,
			GuardrailCode::InsertionNotActive => $urls->screen( ScreenId::Settings ),
			default                           => null,
		};
	}
}
