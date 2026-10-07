<?php
/**
 * In-request impression counts.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Counts per ad for one request, bounded so a pathological page cannot grow it without limit.
 */
final class ImpressionBuffer {

	public const MAX_ADS = 200;

	/**
	 * Ad id to count.
	 *
	 * @var array<int,int>
	 */
	private array $counts = array();

	/**
	 * Adds one impression. Returns false when it was not recorded (invalid id or buffer full for a new ad).
	 *
	 * @param int $ad_id Ad id.
	 */
	public function add( int $ad_id ): bool {
		if ( $ad_id < 1 ) {
			return false;
		}
		if ( ! isset( $this->counts[ $ad_id ] ) ) {
			if ( count( $this->counts ) >= self::MAX_ADS ) {
				return false;
			}
			$this->counts[ $ad_id ] = 0;
		}
		++$this->counts[ $ad_id ];
		return true;
	}

	/**
	 * Whether nothing is buffered.
	 */
	public function isEmpty(): bool {
		return array() === $this->counts;
	}

	/**
	 * Returns and clears the counts.
	 *
	 * @return array<int,int>
	 */
	public function drain(): array {
		$counts       = $this->counts;
		$this->counts = array();
		return $counts;
	}
}
