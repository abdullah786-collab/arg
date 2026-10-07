<?php
/**
 * Time source, injectable so tests control time.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Support;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * Current time provider. Implementations must return UTC.
 */
interface ClockInterface {

	/**
	 * Current time in UTC.
	 */
	public function now(): DateTimeImmutable;
}
