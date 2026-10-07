<?php
/**
 * Minimal lazy service container.
 *
 * Used only by Plugin and service providers to wire constructors. Services
 * must receive their dependencies through their constructors and never hold
 * a reference to the container.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro;

use Closure;
use RevGuardPro\Support\ContainerException;
use RevGuardPro\Support\NotFoundException;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal lazy service container.
 */
final class Container {

	/**
	 * Factories keyed by service id.
	 *
	 * @var array<string, Closure(Container): mixed>
	 */
	private array $factories = array();

	/**
	 * Resolved shared instances keyed by service id.
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = array();

	/**
	 * Ids currently being resolved, used to detect circular dependencies.
	 *
	 * @var array<string, true>
	 */
	private array $resolving = array();

	/**
	 * Registers (or replaces) a lazily built, shared service.
	 *
	 * @param string  $id      Service id.
	 * @param Closure $factory Receives the container and builds the service on first use.
	 */
	public function set( string $id, Closure $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Whether a service id is registered.
	 *
	 * @param string $id Service id.
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] );
	}

	/**
	 * Resolves a service, building it once.
	 *
	 * @param string $id Service id.
	 * @throws NotFoundException  When the id is unknown.
	 * @throws ContainerException On circular resolution.
	 */
	public function get( string $id ): mixed {
		if ( array_key_exists( $id, $this->instances ) ) {
			return $this->instances[ $id ];
		}
		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new NotFoundException( sprintf( 'Unknown service "%s".', esc_html( $id ) ) );
		}
		if ( isset( $this->resolving[ $id ] ) ) {
			throw new ContainerException( sprintf( 'Circular dependency while resolving "%s".', esc_html( $id ) ) );
		}

		$this->resolving[ $id ] = true;
		try {
			$this->instances[ $id ] = ( $this->factories[ $id ] )( $this );
		} finally {
			unset( $this->resolving[ $id ] );
		}

		return $this->instances[ $id ];
	}
}
