<?php
/**
 * Raised when a service id is not registered.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Raised when a service id is not registered.
 */
final class NotFoundException extends ContainerException {
}
