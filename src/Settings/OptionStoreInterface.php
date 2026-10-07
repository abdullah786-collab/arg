<?php
/**
 * Persistence boundary for options, so settings logic is testable without WordPress.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Persistence boundary for options, so settings logic is testable without WordPress.
 */
interface OptionStoreInterface {

	/**
	 * Reads an option.
	 *
	 * @param string $key           Option name.
	 * @param mixed  $default_value Returned when the option does not exist.
	 */
	public function get( string $key, mixed $default_value = null ): mixed;

	/**
	 * Writes an option with an explicit autoload decision.
	 *
	 * @param string $key      Option name.
	 * @param mixed  $value    Value to store.
	 * @param bool   $autoload Whether WordPress should load it on every request.
	 */
	public function set( string $key, mixed $value, bool $autoload = false ): bool;

	/**
	 * Deletes an option.
	 *
	 * @param string $key Option name.
	 */
	public function delete( string $key ): bool;
}
