<?php
/**
 * Pure URL path normalization.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Targeting;

defined( 'ABSPATH' ) || exit;

/**
 * The single URL normalization used for both requests and rule values (see docs/adr/003-targeting-engine.md):
 * strip query and fragment, percent-decode once, reject invalid UTF-8 and control characters, collapse slashes,
 * resolve dot segments (escaping the root is unavailable), lowercase ASCII, drop a trailing slash.
 */
final class UrlPath {

	public const MAX_RULE_VALUE_LENGTH = 200;
	private const MAX_REQUEST_LENGTH   = 2000;

	/**
	 * Normalizes a request URI relative to the site home path. Null when the path cannot be trusted.
	 *
	 * @param string $request_uri Raw REQUEST_URI (path, optional query and fragment, or an absolute URL).
	 * @param string $home_path   Path component of the site home URL (empty or "/" for a root install).
	 */
	public static function fromRequestUri( string $request_uri, string $home_path = '' ): ?string {
		if ( '' === $request_uri || strlen( $request_uri ) > self::MAX_REQUEST_LENGTH ) {
			return null;
		}
		if ( 1 === preg_match( '#^[a-z][a-z0-9+.-]*://#i', $request_uri ) ) {
			$parsed = parse_url( $request_uri, PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure layer: no WordPress functions.
			$raw    = is_string( $parsed ) ? $parsed : '/';
		} else {
			$raw = self::stripQueryAndFragment( $request_uri );
		}

		$path = self::normalizePath( $raw );
		if ( null === $path ) {
			return null;
		}

		$home = '' === $home_path ? '/' : self::normalizePath( $home_path );
		if ( null !== $home && '/' !== $home ) {
			if ( $path === $home ) {
				return '/';
			}
			if ( str_starts_with( $path, $home . '/' ) ) {
				return substr( $path, strlen( $home ) );
			}
		}
		return $path;
	}

	/**
	 * Normalizes a rule path value for `equals` and `starts_with`. Null when invalid.
	 *
	 * @param string $value Candidate value; must start with "/" and contain no query or fragment.
	 */
	public static function normalizePathValue( string $value ): ?string {
		if ( '' === $value || strlen( $value ) > self::MAX_RULE_VALUE_LENGTH || '/' !== $value[0] || self::hasQueryOrFragment( $value ) ) {
			return null;
		}
		return self::normalizePath( $value );
	}

	/**
	 * Normalizes a rule substring value for `ends_with` and `contains`. Null when invalid.
	 *
	 * @param string $value Candidate value.
	 */
	public static function normalizeSubstringValue( string $value ): ?string {
		if ( '' === $value || strlen( $value ) > self::MAX_RULE_VALUE_LENGTH || self::hasQueryOrFragment( $value ) ) {
			return null;
		}
		$decoded = rawurldecode( $value );
		if ( ! self::isCleanText( $decoded ) ) {
			return null;
		}
		return strtolower( $decoded );
	}

	/**
	 * Normalizes a raw path: decode once, validate, collapse and resolve segments, lowercase.
	 *
	 * @param string $raw Raw path without query or fragment.
	 */
	private static function normalizePath( string $raw ): ?string {
		$decoded = rawurldecode( $raw );
		if ( '' === $decoded || '/' !== $decoded[0] || ! self::isCleanText( $decoded ) ) {
			return null;
		}

		$segments = array();
		foreach ( explode( '/', $decoded ) as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}
			if ( '..' === $segment ) {
				if ( array() === $segments ) {
					return null;
				}
				array_pop( $segments );
				continue;
			}
			$segments[] = $segment;
		}
		return strtolower( '/' . implode( '/', $segments ) );
	}

	/**
	 * Cuts a URI at the first "?" or "#".
	 *
	 * @param string $uri Raw URI.
	 */
	private static function stripQueryAndFragment( string $uri ): string {
		$cut = strcspn( $uri, '?#' );
		return substr( $uri, 0, $cut );
	}

	/**
	 * Whether the value contains "?" or "#".
	 *
	 * @param string $value Value.
	 */
	private static function hasQueryOrFragment( string $value ): bool {
		return strcspn( $value, '?#' ) !== strlen( $value );
	}

	/**
	 * Valid UTF-8 with no control characters.
	 *
	 * @param string $value Value.
	 */
	private static function isCleanText( string $value ): bool {
		return mb_check_encoding( $value, 'UTF-8' ) && 1 !== preg_match( '/[\x00-\x1F\x7F]/', $value );
	}
}
