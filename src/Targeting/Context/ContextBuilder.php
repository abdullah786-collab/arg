<?php
/**
 * Pure construction of a TargetingContext.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Targeting\Context;

defined( 'ABSPATH' ) || exit;

use RevGuardPro\Targeting\DeviceType;
use RevGuardPro\Targeting\TargetingContext;
use RevGuardPro\Targeting\TargetingRequirements;
use RevGuardPro\Targeting\UrlPath;

/**
 * Turns an untrusted RequestSnapshot into a TargetingContext, computing only what the requirements ask for.
 * Pure: no WordPress functions, no superglobals, no I/O. Every request string is bounded and validated here.
 */
final class ContextBuilder {

	private const MAX_PARAM_BYTES = 256;
	private const MAX_HOST_LENGTH = 253;

	/**
	 * Builds the context.
	 *
	 * @param RequestSnapshot       $snapshot     Raw request facts.
	 * @param TargetingRequirements $requirements What the rules need.
	 */
	public function build( RequestSnapshot $snapshot, TargetingRequirements $requirements ): TargetingContext {
		return new TargetingContext(
			$requirements->device ? DeviceDetector::detect( $snapshot->user_agent ) : DeviceType::Desktop,
			$requirements->post_type ? strtolower( $snapshot->post_type ) : '',
			$this->terms( $snapshot, $requirements ),
			$snapshot->logged_in,
			$requirements->user_roles ? $this->roles( $snapshot->roles ) : array(),
			$requirements->url ? UrlPath::fromRequestUri( $snapshot->request_uri, $snapshot->home_path ) : '/',
			$requirements->referrer ? self::referrerHost( $snapshot->referer ) : null,
			strtolower( $snapshot->site_host ),
			$this->queryParams( $snapshot, $requirements )
		);
	}

	/**
	 * The host of an http(s) Referer, lowercased; null for a missing, malformed or other-scheme referrer.
	 *
	 * @param string $referer Raw Referer header.
	 */
	public static function referrerHost( string $referer ): ?string {
		if ( '' === $referer || strlen( $referer ) > 2048 ) {
			return null;
		}
		$parts = parse_url( $referer ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure layer: no WordPress functions.
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return null;
		}
		$host = rtrim( strtolower( $parts['host'] ), '.' );
		return '' !== $host && strlen( $host ) <= self::MAX_HOST_LENGTH && 1 === preg_match( '/^[a-z0-9.-]+$/D', $host ) ? $host : null;
	}

	/**
	 * Terms for the required taxonomies only, lowercase.
	 *
	 * @param RequestSnapshot       $snapshot     Snapshot.
	 * @param TargetingRequirements $requirements Requirements.
	 * @return array<string, list<string>>
	 */
	private function terms( RequestSnapshot $snapshot, TargetingRequirements $requirements ): array {
		$out = array();
		foreach ( $requirements->taxonomies as $taxonomy ) {
			$slugs = array();
			foreach ( $snapshot->terms[ $taxonomy ] ?? array() as $slug ) {
				if ( is_string( $slug ) && '' !== $slug ) {
					$slugs[] = strtolower( $slug );
				}
			}
			$out[ (string) $taxonomy ] = $slugs;
		}
		return $out;
	}

	/**
	 * String roles only.
	 *
	 * @param array<mixed> $roles Raw roles.
	 * @return list<string>
	 */
	private function roles( array $roles ): array {
		$out = array();
		foreach ( $roles as $role ) {
			if ( is_string( $role ) && '' !== $role ) {
				$out[] = $role;
			}
		}
		return $out;
	}

	/**
	 * Scalar string values for the required parameter names only; arrays and other types count as absent.
	 *
	 * @param RequestSnapshot       $snapshot     Snapshot.
	 * @param TargetingRequirements $requirements Requirements.
	 * @return array<string, string>
	 */
	private function queryParams( RequestSnapshot $snapshot, TargetingRequirements $requirements ): array {
		$out = array();
		foreach ( $requirements->query_keys as $name ) {
			$value = $snapshot->query[ $name ] ?? null;
			if ( is_string( $value ) ) {
				$out[ (string) $name ] = substr( $value, 0, self::MAX_PARAM_BYTES );
			}
		}
		return $out;
	}
}
