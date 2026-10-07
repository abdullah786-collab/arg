<?php
/**
 * Request-scoped targeting context cache.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rendering;

use Closure;
use RevGuardPro\Targeting\TargetingContext;
use RevGuardPro\Targeting\TargetingRequirements;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps a TargetingContext factory and rebuilds the context only when the accumulated requirements grow.
 * Every call contributes its requirements to the union; the factory is called at most once per unique union.
 */
final class RequestTargetingContext {

	/**
	 * Accumulated union of all requirements seen so far.
	 *
	 * @var TargetingRequirements|null
	 */
	private ?TargetingRequirements $union = null;

	/**
	 * The last context built by the factory, or null on the first call.
	 *
	 * @var TargetingContext|null
	 */
	private ?TargetingContext $cached = null;

	/**
	 * How many times the factory has been called.
	 *
	 * @var int
	 */
	private int $build_count = 0;

	/**
	 * Constructor.
	 *
	 * @param Closure $factory Factory: Closure(TargetingRequirements): TargetingContext.
	 */
	public function __construct( private readonly Closure $factory ) {
	}

	/**
	 * Returns a context that satisfies the given requirements and the accumulated union.
	 * Rebuilds the context only when the union grows; otherwise returns the cached context.
	 *
	 * @param TargetingRequirements $needed Requirements for this call.
	 */
	public function forRequirements( TargetingRequirements $needed ): TargetingContext {
		if ( null === $this->union ) {
			// First call always builds.
			$this->union  = $needed;
			$this->cached = ( $this->factory )( $this->union );
			++$this->build_count;
			return $this->cached;
		}

		if ( $this->isCoveredBy( $needed, $this->union ) ) {
			return $this->cached;
		}

		$this->union  = TargetingRequirements::merge( $this->union, $needed );
		$this->cached = ( $this->factory )( $this->union );
		++$this->build_count;
		return $this->cached;
	}

	/**
	 * How many times the factory has been called.
	 */
	public function buildCount(): int {
		return $this->build_count;
	}

	/**
	 * Whether every requirement in $needed is already covered by $union.
	 *
	 * @param TargetingRequirements $needed Requirements to check.
	 * @param TargetingRequirements $union  Accumulated union to check against.
	 */
	private function isCoveredBy( TargetingRequirements $needed, TargetingRequirements $union ): bool {
		if ( $needed->device && ! $union->device ) {
			return false;
		}
		if ( $needed->url && ! $union->url ) {
			return false;
		}
		if ( $needed->referrer && ! $union->referrer ) {
			return false;
		}
		if ( $needed->user_state && ! $union->user_state ) {
			return false;
		}
		if ( $needed->user_roles && ! $union->user_roles ) {
			return false;
		}
		if ( $needed->post_type && ! $union->post_type ) {
			return false;
		}
		// Every taxonomy in $needed must be in $union.
		foreach ( $needed->taxonomies as $taxonomy ) {
			if ( ! in_array( $taxonomy, $union->taxonomies, true ) ) {
				return false;
			}
		}
		// Every query key in $needed must be in $union.
		foreach ( $needed->query_keys as $key ) {
			if ( ! in_array( $key, $union->query_keys, true ) ) {
				return false;
			}
		}
		return true;
	}
}
