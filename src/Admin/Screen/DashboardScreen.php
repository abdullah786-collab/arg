<?php
/**
 * The Dashboard screen.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Screen;

use RevGuardPro\Admin\ScreenId;
use RevGuardPro\Admin\Support\AdminRequest;
use RevGuardPro\Admin\Support\NoticeCode;
use RevGuardPro\Analytics\DateRange;
use RevGuardPro\Domain\Status;
use RevGuardPro\Security\Capabilities;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Only real data: counts from the repositories, statistics only for users who may see them, the M8 findings as
 * advisory suggestions. Each panel loads independently, so one failing service never blanks the page.
 */
final class DashboardScreen implements ScreenInterface {

	public const RECENT_DAYS = 7;

	/**
	 * Constructor.
	 *
	 * @param ScreenContext $ctx Shared services.
	 */
	public function __construct( private readonly ScreenContext $ctx ) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): ScreenId {
		return ScreenId::Dashboard;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param AdminRequest $request Request.
	 */
	public function handle( AdminRequest $request ): Outcome {
		$this->ctx->requireCapability( $this->id()->capability() );

		return Outcome::render(
			array(
				'notice'      => NoticeCode::fromQuery( $request->query ),
				'counts'      => $this->guarded( fn(): array => $this->counts() ),
				'impressions' => $this->ctx->can( Capabilities::VIEW_ANALYTICS ) ? $this->impressions() : null,
				'review'      => $this->guarded( fn() => $this->ctx->guardrails->run() ),
			)
		);
	}

	/**
	 * Runs a panel loader; a failure becomes null, which the template shows as "could not be loaded".
	 *
	 * @param callable $loader Loader.
	 */
	private function guarded( callable $loader ): mixed {
		try {
			return $loader();
		} catch ( Throwable ) {
			return null;
		}
	}

	/**
	 * Entity counts.
	 *
	 * @return array{ads:int,active_ads:int,active_groups:int,active_placements:int}
	 */
	private function counts(): array {
		return array(
			'ads'               => $this->ctx->ads->count( null ),
			'active_ads'        => $this->ctx->ads->count( Status::Active ),
			'active_groups'     => $this->ctx->groups->count( Status::Active ),
			'active_placements' => $this->ctx->placements->count( Status::Active ),
		);
	}

	/**
	 * Recent impressions, or null when they cannot be read.
	 *
	 * @return array{total:int,days:int,enabled:bool}|null
	 */
	private function impressions(): ?array {
		return $this->guarded(
			function (): ?array {
				$range = DateRange::last( $this->ctx->clock->now(), self::RECENT_DAYS );
				if ( null === $range ) {
					return null;
				}
				return array(
					'total'   => $this->ctx->stats->total( $range ),
					'days'    => self::RECENT_DAYS,
					'enabled' => $this->ctx->settings->bool( 'analytics_enabled' ),
				);
			}
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param AdminRequest        $request Request.
	 * @param array<string,mixed> $data    Data from handle().
	 */
	public function render( AdminRequest $request, array $data ): void {
		$this->ctx->view->render(
			'dashboard',
			$data + array(
				'screen' => $this->id(),
				'urls'   => $this->ctx->urls,
			)
		);
	}
}
