<?php
/**
 * Typed access to the small, non-autoloaded settings option.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Settings;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Typed access to the small, non-autoloaded settings option.
 */
final class SettingsRepository {

	public const OPTION = 'revguard_pro_settings';

	/**
	 * Constructor.
	 *
	 * @param OptionStoreInterface $store Option persistence.
	 */
	public function __construct( private readonly OptionStoreInterface $store ) {
	}

	/**
	 * Every known setting, merged over defaults. Unknown stored keys are dropped.
	 *
	 * @return array<string, bool|int|string>
	 */
	public function all(): array {
		$stored = $this->store->get( self::OPTION, array() );
		return $this->normalize( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Boolean setting.
	 *
	 * @param string $key Setting key.
	 */
	public function bool( string $key ): bool {
		return (bool) $this->value( $key );
	}

	/**
	 * Integer setting.
	 *
	 * @param string $key Setting key.
	 */
	public function int( string $key ): int {
		return (int) $this->value( $key );
	}

	/**
	 * String setting.
	 *
	 * @param string $key Setting key.
	 */
	public function string( string $key ): string {
		return (string) $this->value( $key );
	}

	/**
	 * Merges and persists the given values. Unknown keys are ignored, values are coerced to the default's type.
	 *
	 * @param array<string, mixed> $input Raw input.
	 */
	public function update( array $input ): bool {
		$merged = $this->normalize( array_merge( $this->all(), $input ) );
		return $this->store->set( self::OPTION, $merged, false );
	}

	/**
	 * Resolves one known setting.
	 *
	 * @param string $key Setting key.
	 * @throws InvalidArgumentException When the key is not a known setting.
	 */
	private function value( string $key ): bool|int|string {
		$all = $this->all();
		if ( ! array_key_exists( $key, $all ) ) {
			throw new InvalidArgumentException( 'Unknown setting key.' );
		}
		return $all[ $key ];
	}

	/**
	 * Whitelists keys and coerces each value to the type of its default.
	 *
	 * @param array<mixed> $values Candidate values.
	 * @return array<string, bool|int|string>
	 */
	private function normalize( array $values ): array {
		$result = array();
		foreach ( Defaults::all() as $key => $fallback ) {
			$result[ $key ] = array_key_exists( $key, $values ) ? $this->coerce( $values[ $key ], $fallback ) : $fallback;
		}
		return $result;
	}

	/**
	 * Coerces a value to the scalar type of the default.
	 *
	 * @param mixed           $value   Candidate value.
	 * @param bool|int|string $fallback Default whose type wins.
	 */
	private function coerce( mixed $value, bool|int|string $fallback ): bool|int|string {
		if ( is_bool( $fallback ) ) {
			return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
		}
		if ( is_int( $fallback ) ) {
			return is_scalar( $value ) ? (int) $value : $fallback;
		}
		return is_scalar( $value ) ? trim( (string) $value ) : $fallback;
	}
}
