<?php
/**
 * Ad persistence contract.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain\Repository;

use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Stores ads. Listing is ordered by id ascending and paginated (limit clamped to 1 to 500).
 */
interface AdRepositoryInterface {

	/**
	 * Inserts a new ad (null id) or updates an existing one, and returns the stored ad.
	 *
	 * @param Ad $ad Ad to store.
	 * @throws \RevGuardPro\Domain\Exception\EntityNotFoundException When updating a missing ad.
	 * @throws \RevGuardPro\Domain\Exception\InvalidEntityException   When the creative policy yields an invalid ad.
	 * @throws \RevGuardPro\Domain\Exception\PersistenceException     On a storage failure.
	 */
	public function save( Ad $ad ): Ad;

	/**
	 * Finds one ad.
	 *
	 * @param int $id Ad id.
	 */
	public function find( int $id ): ?Ad;

	/**
	 * Finds several ads in one query. Missing ids are absent from the result, and so are rows that cannot be
	 * hydrated (serving must never fail on one bad row); find() and list() surface corruption instead.
	 *
	 * @param array<int> $ids Ad ids.
	 * @return array<int, Ad> Existing ads keyed by id.
	 */
	public function findMany( array $ids ): array;

	/**
	 * Deletes an ad and removes it from every group.
	 *
	 * @param int $id Ad id.
	 * @return bool Whether an ad was deleted.
	 * @throws \RevGuardPro\Domain\Exception\EntityInUseException When a placement targets the ad directly.
	 */
	public function delete( int $id ): bool;

	/**
	 * Lists ads.
	 *
	 * @param Status|null $status Optional status filter.
	 * @param int         $limit  Page size.
	 * @param int         $offset Offset.
	 * @return list<Ad>
	 */
	public function list( ?Status $status = null, int $limit = 50, int $offset = 0 ): array;

	/**
	 * Counts ads.
	 *
	 * @param Status|null $status Optional status filter.
	 */
	public function count( ?Status $status = null ): int;
}
