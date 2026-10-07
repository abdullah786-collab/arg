<?php
/**
 * The services every screen may use.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Screen;

use Closure;
use DateTimeZone;
use RevGuardPro\Admin\Gateway;
use RevGuardPro\Admin\Support\Urls;
use RevGuardPro\Admin\View\ViewRenderer;
use RevGuardPro\Analytics\ReportBuilder;
use RevGuardPro\Analytics\StatsReader;
use RevGuardPro\Guardrails\GuardrailService;
use RevGuardPro\Security\AdminRequestGuard;
use RevGuardPro\Settings\SettingsRepository;
use RevGuardPro\Support\ClockInterface;

defined( 'ABSPATH' ) || exit;

/**
 * A plain bundle. The screens contain no business logic: they authorise, read the request strictly, call these
 * services and show the result (ADR 009).
 */
final class ScreenContext {

	public const PER_PAGE = 20;

	/**
	 * Constructor.
	 *
	 * @param AdminRequestGuard  $guard      Capability and nonce check.
	 * @param Closure            $can        Closure(string $capability): bool.
	 * @param Closure            $deny       Closure(): void, ends the request with a 403.
	 * @param Urls               $urls       URL builder.
	 * @param ViewRenderer       $view       Template renderer.
	 * @param Gateway            $ads        Ads.
	 * @param Gateway            $groups     Groups.
	 * @param Gateway            $placements Placements.
	 * @param SettingsRepository $settings  Settings.
	 * @param Closure            $zone       Closure(): DateTimeZone, the site time zone.
	 * @param ClockInterface     $clock      Clock.
	 * @param GuardrailService   $guardrails Guardrails (M8).
	 * @param StatsReader        $stats      Statistics reader (M7).
	 * @param ReportBuilder      $reports    Statistics report builder (M7).
	 */
	public function __construct(
		public readonly AdminRequestGuard $guard,
		public readonly Closure $can,
		public readonly Closure $deny,
		public readonly Urls $urls,
		public readonly ViewRenderer $view,
		public readonly Gateway $ads,
		public readonly Gateway $groups,
		public readonly Gateway $placements,
		public readonly SettingsRepository $settings,
		public readonly Closure $zone,
		public readonly ClockInterface $clock,
		public readonly GuardrailService $guardrails,
		public readonly StatsReader $stats,
		public readonly ReportBuilder $reports
	) {
	}

	/**
	 * Ends the request with a 403 unless the user holds the capability.
	 *
	 * @param string $capability Capability.
	 */
	public function requireCapability( string $capability ): void {
		if ( ! ( $this->can )( $capability ) ) {
			( $this->deny )();
		}
	}

	/**
	 * Whether the user holds a capability.
	 *
	 * @param string $capability Capability.
	 */
	public function can( string $capability ): bool {
		return (bool) ( $this->can )( $capability );
	}

	/**
	 * The site time zone.
	 */
	public function zone(): DateTimeZone {
		return ( $this->zone )();
	}
}
