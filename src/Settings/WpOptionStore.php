<?php
/**
 * WordPress options backed store.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress options backed store.
 */
final class WpOptionStore implements OptionStoreInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key           Option name.
	 * @param mixed  $default_value Fallback.
	 */
	public function get( string $key, mixed $default_value = null ): mixed {
		return get_option( $key, $default_value );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key      Option name.
	 * @param mixed  $value    Value.
	 * @param bool   $autoload Autoload flag.
	 */
	public function set( string $key, mixed $value, bool $autoload = false ): bool {
		return update_option( $key, $value, $autoload );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $key Option name.
	 */
	public function delete( string $key ): bool {
		return delete_option( $key );
	}
}
