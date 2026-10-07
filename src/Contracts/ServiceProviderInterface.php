<?php
/**
 * Boundary between the Plugin bootstrap and feature modules.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Contracts;

use RevGuardPro\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Boundary between the Plugin bootstrap and feature modules.
 */
interface ServiceProviderInterface {

	/**
	 * Registers service factories. Must not run business logic or add hooks.
	 *
	 * @param Container $container Service container.
	 */
	public function register( Container $container ): void;

	/**
	 * Attaches WordPress hooks once every provider has registered.
	 *
	 * @param Container $container Service container.
	 */
	public function boot( Container $container ): void;
}
