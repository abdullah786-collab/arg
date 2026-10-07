<?php
/**
 * Ad group persistence contract.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain\Repository;

use RevGuardPro\Domain\AdGroup;
use RevGuardPro\Domain\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Stores groups together with their ordered, weighted members. Listing is ordered by id ascending.
 */
interface AdGroupRepositoryInterface {

	/**
	 * Inserts or updates a group and replaces its member list.
	 *
	 * @param AdGroup $group Group to store.
	 * @throws \RevGuardPro\Domain\Exception\EntityNotFoundException When updating a missing group.
	 * @throws \RevGuardPro\Domain\Exception\InvalidEntityException   When a member ad does not exist.
	 */
	public function save( AdGroup $group ): AdGroup;

	/**
	 * Finds one group with its members.
	 *
	 * @param int $id Group id.
	 */
	public function find( int $id ): ?AdGroup;

	/**
	 * Deletes a group and its memberships.
	 *
	 * @param int $id Group id.
	 * @return bool Whether a group was deleted.
	 * @throws \RevGuardPro\Domain\Exception\EntityInUseException When a placement targets the group.
	 */
	public function delete( int $id ): bool;

	/**
	 * Lists groups with their members (members are loaded in one extra query, not per group).
	 *
	 * @param Status|null $status Optional status filter.
	 * @param int         $limit  Page size.
	 * @param int         $offset Offset.
	 * @return list<AdGroup>
	 */
	public function list( ?Status $status = null, int $limit = 50, int $offset = 0 ): array;

	/**
	 * Counts groups.
	 *
	 * @param Status|null $status Optional status filter.
	 */
	public function count( ?Status $status = null ): int;
}
