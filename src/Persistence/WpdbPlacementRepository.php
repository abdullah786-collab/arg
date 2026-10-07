<?php
/**
 * `$wpdb` placement repository.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Persistence;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- This class is the database boundary; read caching is the later cache layer (see the change notifier).
// phpcs:disable WordPress.DB.PreparedSQL -- Table names are allow-listed Schema constants.

use RevGuardPro\Domain\ChangeOperation;
use RevGuardPro\Domain\EntityKind;
use RevGuardPro\Domain\Exception\EntityNotFoundException;
use RevGuardPro\Domain\Exception\InvalidEntityException;
use RevGuardPro\Domain\Placement;
use RevGuardPro\Domain\Repository\PlacementRepositoryInterface;
use RevGuardPro\Domain\Status;
use RevGuardPro\Domain\TargetType;
use RevGuardPro\Persistence\Mapper\PlacementMapper;

defined( 'ABSPATH' ) || exit;

/**
 * Stores placements in `{prefix}rgp_placements`.
 */
final class WpdbPlacementRepository extends AbstractWpdbRepository implements PlacementRepositoryInterface {

	private const COLUMNS = 'id, name, status, placement_type, config, target_type, target_id, priority, targeting, created_at, updated_at';

	/**
	 * Inserts or updates a placement after verifying its target exists.
	 *
	 * @param Placement $placement Placement to store.
	 * @throws EntityNotFoundException When updating a missing placement.
	 */
	public function save( Placement $placement ): Placement {
		$this->assertTargetExists( $placement );

		$now     = $this->now();
		$table   = $this->table( Schema::PLACEMENTS );
		$columns = PlacementMapper::columns( $placement );

		if ( null === $placement->id ) {
			$data = $columns + array(
				'created_at' => $this->dbDate( $now ),
				'updated_at' => $this->dbDate( $now ),
			);
			$this->assertOk( $this->wpdb->insert( $table, $data, PlacementMapper::formats() ), 'insert_placement' );
			$stored    = $placement->persisted( (int) $this->wpdb->insert_id, $now, $now );
			$operation = ChangeOperation::Created;
		} else {
			$existing = $this->find( $placement->id );
			if ( null === $existing ) {
				throw new EntityNotFoundException( EntityKind::Placement, $placement->id );
			}
			$data    = $columns + array( 'updated_at' => $this->dbDate( $now ) );
			$formats = PlacementMapper::formats();
			array_pop( $formats );
			array_pop( $formats );
			$formats[] = '%s';
			$this->assertOk( $this->wpdb->update( $table, $data, array( 'id' => $placement->id ), $formats, array( '%d' ) ), 'update_placement' );
			$stored    = $placement->persisted( $placement->id, $existing->created_at ?? $now, $now );
			$operation = ChangeOperation::Updated;
		}

		$this->notify( EntityKind::Placement, (int) $stored->id, $operation );
		return $stored;
	}

	/**
	 * Finds one placement.
	 *
	 * @param int $id Placement id.
	 */
	public function find( int $id ): ?Placement {
		if ( $id < 1 ) {
			return null;
		}
		$table = $this->table( Schema::PLACEMENTS );
		$row   = $this->wpdb->get_row(
			$this->wpdb->prepare( 'SELECT ' . self::COLUMNS . " FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);
		return is_array( $row ) ? PlacementMapper::fromRow( $row ) : null;
	}

	/**
	 * Deletes a placement.
	 *
	 * @param int $id Placement id.
	 */
	public function delete( int $id ): bool {
		if ( $id < 1 ) {
			return false;
		}
		$deleted = $this->wpdb->delete( $this->table( Schema::PLACEMENTS ), array( 'id' => $id ), array( '%d' ) );
		$this->assertOk( $deleted, 'delete_placement' );
		if ( 0 === (int) $deleted ) {
			return false;
		}
		$this->notify( EntityKind::Placement, $id, ChangeOperation::Deleted );
		return true;
	}

	/**
	 * Lists placements by priority ascending, then id.
	 *
	 * @param Status|null $status Optional status filter.
	 * @param int         $limit  Page size.
	 * @param int         $offset Offset.
	 * @return list<Placement>
	 */
	public function list( ?Status $status = null, int $limit = 50, int $offset = 0 ): array {
		$table  = $this->table( Schema::PLACEMENTS );
		$limit  = Paging::limit( $limit );
		$offset = Paging::offset( $offset );
		$sql    = null === $status
			? $this->wpdb->prepare( 'SELECT ' . self::COLUMNS . " FROM {$table} ORDER BY priority ASC, id ASC LIMIT %d OFFSET %d", $limit, $offset )
			: $this->wpdb->prepare( 'SELECT ' . self::COLUMNS . " FROM {$table} WHERE status = %s ORDER BY priority ASC, id ASC LIMIT %d OFFSET %d", $status->value, $limit, $offset );

		$placements = array();
		foreach ( (array) $this->wpdb->get_results( $sql, ARRAY_A ) as $row ) {
			$placements[] = PlacementMapper::fromRow( (array) $row );
		}
		return $placements;
	}

	/**
	 * Counts placements.
	 *
	 * @param Status|null $status Optional status filter.
	 */
	public function count( ?Status $status = null ): int {
		$table = $this->table( Schema::PLACEMENTS );
		$sql   = null === $status
			? "SELECT COUNT(*) FROM {$table}"
			: $this->wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status->value );
		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * Verifies that the referenced ad or group exists.
	 *
	 * @param Placement $placement Placement being stored.
	 * @throws InvalidEntityException When the target does not exist.
	 */
	private function assertTargetExists( Placement $placement ): void {
		if ( null === $placement->target_type || null === $placement->target_id ) {
			return;
		}
		$table = $this->table( TargetType::Ad === $placement->target_type ? Schema::ADS : Schema::GROUPS );
		$found = (int) $this->wpdb->get_var( $this->wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE id = %d", $placement->target_id ) );
		if ( 0 === $found ) {
			throw new InvalidEntityException( 'target', InvalidEntityException::REASON_UNKNOWN_REFERENCE );
		}
	}
}
