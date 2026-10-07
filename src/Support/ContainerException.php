<?php
/**
 * Base container failure.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Support;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Base container failure.
 */
class ContainerException extends RuntimeException {
}
