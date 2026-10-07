<?php
/**
 * The Analytics screen.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Screen;

use RevGuardPro\Admin\ScreenId;
use RevGuardPro\Admin\Support\AdminRequest;
use RevGuardPro\Admin\Support\Input;
use RevGuardPro\Analytics\DateRange;
use RevGuardPro\Security\Capabilities;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Totals, a daily series and the top ads for 7, 30 or 90 days. Read-only; reports server-side rendered impressions
 * only and says so (ADR 007).
 */
final class AnalyticsScreen implements ScreenInterface {

	public const RANGES  = array( 7, 30, 90 );
	public const DEFAULT = 30;
	public const TOP_ADS = 10;

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
		return ScreenId::Analytics;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param AdminRequest $request Request.
	 */
	public function handle( AdminRequest $request ): Outcome {
		$this->ctx->requireCapability( $this->id()->capability() );

		$days = Input::int( $request->param( 'range' ), 1, 366 );
		$days = in_array( $days, self::RANGES, true ) ? $days : self::DEFAULT;
		$data = array(
			'days'     => $days,
			'enabled'  => $this->ctx->settings->bool( 'analytics_enabled' ),
			'can_edit' => $this->ctx->can( Capabilities::MANAGE_ADS ),
			'report'   => null,
		);
		try {
			$range = DateRange::last( $this->ctx->clock->now(), $days );
			if ( null !== $range ) {
				$data['report'] = array(
					'total' => $this->ctx->stats->total( $range ),
					'daily' => $this->ctx->stats->daily( $range ),
					'top'   => $this->ctx->reports->topAds( $range, self::TOP_ADS ),
				);
			}
		} catch ( Throwable ) {
			$data['report'] = null;
		}
		return Outcome::render( $data );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param AdminRequest        $request Request.
	 * @param array<string,mixed> $data    Data from handle().
	 */
	public function render( AdminRequest $request, array $data ): void {
		$this->ctx->view->render(
			'analytics',
			$data + array(
				'screen' => $this->id(),
				'urls'   => $this->ctx->urls,
			)
		);
	}
}
