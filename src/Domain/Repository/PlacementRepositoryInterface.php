<?php
/**
 * Placement persistence contract.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain\Repository;

use RevGuardPro\Domain\Placement;
use RevGuardPro\Domain\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Stores placements. Listing is ordered by priority ascending, then id ascending.
 */
interface PlacementRepositoryInterface {

	/**
	 * Inserts or updates a placement.
	 *
	 * @param Placement $placement Placement to store.
	 * @throws \RevGuardPro\Domain\Exception\EntityNotFoundException When updating a missing placement.
	 * @throws \RevGuardPro\Domain\Exception\InvalidEntityException   When the target does not exist.
	 */
	public function save( Placement $placement ): Placement;

	/**
	 * Finds one placement.
	 *
	 * @param int $id Placement id.
	 */
	public function find( int $id ): ?Placement;

	/**
	 * Deletes a placement.
	 *
	 * @param int $id Placement id.
	 * @return bool Whether a placement was deleted.
	 */
	public function delete( int $id ): bool;

	/**
	 * Lists placements.
	 *
	 * @param Status|null $status Optional status filter.
	 * @param int         $limit  Page size.
	 * @param int         $offset Offset.
	 * @return list<Placement>
	 */
	public function list( ?Status $status = null, int $limit = 50, int $offset = 0 ): array;

	/**
	 * Counts placements.
	 *
	 * @param Status|null $status Optional status filter.
	 */
	public function count( ?Status $status = null ): int;
}
