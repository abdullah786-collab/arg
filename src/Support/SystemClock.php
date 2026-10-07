<?php
/**
 * System clock.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Support;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Real UTC time. Timezone-aware scheduling (M2) converts from the WordPress timezone; storage is always UTC.
 */
final class SystemClock implements ClockInterface {

	/**
	 * Current time in UTC.
	 */
	public function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}
}
