<?php
/**
 * Collects rendered impressions and writes them once per request.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Analytics;

use Closure;
use RevGuardPro\Support\ClockInterface;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Listener for `RenderState`. It never throws and never outputs: analytics must not be able to change a page.
 * The first accepted impression of a request schedules one flush. Counts are buffered per originating site and
 * written inside that site's context, so a page that renders while switched to another site (multisite) can never
 * add its impressions to the wrong site's table.
 */
final class ImpressionCollector {

	public const MAX_SITES = 20;

	/**
	 * Memoized answer of the allowed check.
	 *
	 * @var bool|null
	 */
	private ?bool $allowed = null;

	/**
	 * One buffer per originating site.
	 *
	 * @var array<int,ImpressionBuffer>
	 */
	private array $buffers = array();

	/**
	 * Whether a flush is already scheduled.
	 *
	 * @var bool
	 */
	private bool $scheduled = false;

	/**
	 * UTC day of the first buffered impression.
	 *
	 * @var string|null
	 */
	private ?string $day = null;

	/**
	 * Constructor.
	 *
	 * @param StatsStoreInterface $store         Aggregate storage.
	 * @param ClockInterface      $clock         Clock (UTC day).
	 * @param Closure             $allowed_check Closure(): bool, whether this request counts (evaluated once).
	 * @param Closure             $schedule      Closure(): void, arranges one call to flush() at shutdown.
	 * @param Closure             $blog          Closure(): int, the current site id (1 on a single site).
	 * @param Closure             $in_site       Closure(int $site, Closure $work): void, runs the work in that site's context.
	 */
	public function __construct(
		private readonly StatsStoreInterface $store,
		private readonly ClockInterface $clock,
		private readonly Closure $allowed_check,
		private readonly Closure $schedule,
		private readonly Closure $blog,
		private readonly Closure $in_site
	) {
	}

	/**
	 * Observer entry point: one newly rendered ad.
	 *
	 * @param int $scope Post scope (unused: impressions are per ad).
	 * @param int $ad_id Ad id.
	 */
	public function record( int $scope, int $ad_id ): void {
		unset( $scope );
		try {
			$this->allowed ??= (bool) ( $this->allowed_check )();
			if ( ! $this->allowed ) {
				return;
			}
			$this->day ??= DayKey::fromInstant( $this->clock->now() );
			$site        = (int) ( $this->blog )();
			if ( ! isset( $this->buffers[ $site ] ) ) {
				if ( count( $this->buffers ) >= self::MAX_SITES ) {
					return;
				}
				$this->buffers[ $site ] = new ImpressionBuffer();
			}
			if ( $this->buffers[ $site ]->add( $ad_id ) && ! $this->scheduled ) {
				$this->scheduled = true;
				( $this->schedule )();
			}
		} catch ( Throwable ) {
			return;
		}
	}

	/**
	 * Writes the buffered counts, one write per originating site. Safe to call with nothing buffered.
	 */
	public function flush(): void {
		$buffers         = $this->buffers;
		$day             = $this->day;
		$this->buffers   = array();
		$this->day       = null;
		$this->scheduled = false;
		if ( null === $day ) {
			return;
		}
		foreach ( $buffers as $site => $buffer ) {
			try {
				$counts = $buffer->drain();
				if ( array() === $counts ) {
					continue;
				}
				( $this->in_site )(
					$site,
					function () use ( $day, $counts ): void {
						$this->store->increment( $day, $counts );
					}
				);
			} catch ( Throwable ) {
				continue;
			}
		}
	}
}
