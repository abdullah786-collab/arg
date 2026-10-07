<?php
/**
 * A uniform door to one of the three entity repositories.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin;

use Closure;
use RevGuardPro\Domain\Status;

defined( 'ABSPATH' ) || exit;

/**
 * The repositories share method names but not types; the generic list/form flow talks to this thin wrapper so it is
 * written, reviewed and tested once. It adds no behaviour.
 */
final class Gateway {

	/**
	 * Constructor.
	 *
	 * @param Closure $find   Closure(int): ?object.
	 * @param Closure $save   Closure(object): object.
	 * @param Closure $delete Closure(int): bool.
	 * @param Closure $page   Closure(?Status, int, int): array.
	 * @param Closure $count  Closure(?Status): int.
	 */
	public function __construct(
		private readonly Closure $find,
		private readonly Closure $save,
		private readonly Closure $delete,
		private readonly Closure $page,
		private readonly Closure $count
	) {
	}

	/**
	 * Wraps a repository (anything with find, save, delete, list and count).
	 *
	 * @param object $repo Repository.
	 */
	public static function forRepository( object $repo ): self {
		return new self(
			static fn( int $id ) => $repo->find( $id ),
			static fn( object $entity ) => $repo->save( $entity ),
			static fn( int $id ): bool => $repo->delete( $id ),
			static fn( ?Status $status, int $limit, int $offset ): array => $repo->list( $status, $limit, $offset ),
			static fn( ?Status $status ): int => $repo->count( $status )
		);
	}

	/**
	 * Finds one entity.
	 *
	 * @param int $id Id.
	 */
	public function find( int $id ): ?object {
		return ( $this->find )( $id );
	}

	/**
	 * Saves an entity.
	 *
	 * @param object $entity Entity.
	 */
	public function save( object $entity ): object {
		return ( $this->save )( $entity );
	}

	/**
	 * Deletes an entity.
	 *
	 * @param int $id Id.
	 */
	public function delete( int $id ): bool {
		return (bool) ( $this->delete )( $id );
	}

	/**
	 * Lists a page.
	 *
	 * @param Status|null $status Status filter.
	 * @param int         $limit  Page size.
	 * @param int         $offset Offset.
	 * @return array<int,object>
	 */
	public function list( ?Status $status, int $limit, int $offset ): array {
		return ( $this->page )( $status, $limit, $offset );
	}

	/**
	 * Counts entities.
	 *
	 * @param Status|null $status Status filter.
	 */
	public function count( ?Status $status ): int {
		return (int) ( $this->count )( $status );
	}
}
