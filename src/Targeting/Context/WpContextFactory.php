<?php
/**
 * WordPress adapter that collects request state once.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Targeting\Context;

defined( 'ABSPATH' ) || exit;

use RevGuardPro\Targeting\TargetingContext;
use RevGuardPro\Targeting\TargetingRequirements;
use WP_Post;
use WP_Post_Type;
use WP_Term;

/**
 * The only targeting class that reads WordPress state and superglobals. It collects a RequestSnapshot containing
 * just what the requirements need, then hands it to the pure ContextBuilder. It never writes and never queries
 * custom tables; term lookups use WordPress's own cached term relationships.
 */
final class WpContextFactory {

	/**
	 * Constructor.
	 *
	 * @param ContextBuilder $builder Pure context builder.
	 */
	public function __construct( private readonly ContextBuilder $builder ) {
	}

	/**
	 * Builds the context for the current request.
	 *
	 * @param TargetingRequirements $requirements What the rules need.
	 */
	public function build( TargetingRequirements $requirements ): TargetingContext {
		$home = home_url();
		$user = $requirements->user_state || $requirements->user_roles ? wp_get_current_user() : null;

		return $this->builder->build(
			new RequestSnapshot(
				$requirements->device ? $this->server( 'HTTP_USER_AGENT' ) : '',
				$requirements->referrer ? $this->server( 'HTTP_REFERER' ) : '',
				$requirements->url ? $this->server( 'REQUEST_URI' ) : '',
				(string) wp_parse_url( $home, PHP_URL_PATH ),
				(string) wp_parse_url( $home, PHP_URL_HOST ),
				$this->query( $requirements->query_keys ),
				$requirements->post_type ? $this->postType() : '',
				$this->terms( $requirements->taxonomies ),
				null !== $user && $user->exists(),
				null !== $user ? array_values( (array) $user->roles ) : array()
			),
			$requirements
		);
	}

	/**
	 * A server variable as a string (empty when absent). Bounded and validated later by ContextBuilder.
	 *
	 * @param string $key Server variable name.
	 */
	private function server( string $key ): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Read-only, length-bounded and validated by the pure ContextBuilder; never output.
		return isset( $_SERVER[ $key ] ) && is_string( $_SERVER[ $key ] ) ? wp_unslash( $_SERVER[ $key ] ) : '';
	}

	/**
	 * The required query parameters from the request, unslashed.
	 *
	 * @param array<mixed> $names Parameter names.
	 * @return array<string, mixed>
	 */
	private function query( array $names ): array {
		$out = array();
		foreach ( $names as $name ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- Read-only targeting input, compared as plain strings, never output or stored.
			if ( is_string( $name ) && isset( $_GET[ $name ] ) ) {
				$out[ $name ] = wp_unslash( $_GET[ $name ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
			}
		}
		return $out;
	}

	/**
	 * Post type of the queried object: the post's type, or the type of a post-type archive.
	 */
	private function postType(): string {
		$object = get_queried_object();
		if ( $object instanceof WP_Post ) {
			return $object->post_type;
		}
		return $object instanceof WP_Post_Type ? $object->name : '';
	}

	/**
	 * Term slugs per required taxonomy for the current page: a singular post's terms, or a term archive's term.
	 *
	 * @param array<mixed> $taxonomies Taxonomy slugs.
	 * @return array<string, list<string>>
	 */
	private function terms( array $taxonomies ): array {
		$object = array() === $taxonomies ? null : get_queried_object();
		$out    = array();
		foreach ( $taxonomies as $taxonomy ) {
			if ( ! is_string( $taxonomy ) || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$slugs = array();
			if ( $object instanceof WP_Post ) {
				$terms = get_the_terms( $object, $taxonomy );
				foreach ( is_array( $terms ) ? $terms : array() as $term ) {
					$slugs[] = $term->slug;
				}
			} elseif ( $object instanceof WP_Term && $object->taxonomy === $taxonomy ) {
				$slugs[] = $object->slug;
			}
			$out[ $taxonomy ] = $slugs;
		}
		return $out;
	}
}
