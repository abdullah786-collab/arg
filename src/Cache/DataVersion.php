<?php
/**
 * Data version token for cache keys.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Cache;

use RevGuardPro\Settings\OptionStoreInterface;

defined( 'ABSPATH' ) || exit;

/**
 * A durable token that changes whenever Ad, Group or Placement data changes. Later caches include it in
 * their keys, so a change invalidates them without enumerating keys. Stored per site, never autoloaded.
 */
final class DataVersion {

	public const OPTION = 'revguard_pro_data_version';

	/**
	 * Constructor.
	 *
	 * @param OptionStoreInterface $options Option persistence.
	 */
	public function __construct( private readonly OptionStoreInterface $options ) {
	}

	/**
	 * Current token; "0" until the first change.
	 */
	public function current(): string {
		$value = $this->options->get( self::OPTION, '0' );
		return is_string( $value ) && '' !== $value ? $value : '0';
	}

	/**
	 * Rotates the token. Returns the new value.
	 */
	public function bump(): string {
		$token = bin2hex( random_bytes( 8 ) );
		$this->options->set( self::OPTION, $token, false );
		return $token;
	}
}
