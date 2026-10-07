<?php
/**
 * `$wpdb` ad group repository.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Persistence;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- This class is the database boundary; read caching is the later cache layer (see the change notifier).
// phpcs:disable WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders -- Table names are allow-listed Schema constants; IN/VALUES lists are built from %d placeholders.

use RevGuardPro\Domain\AdGroup;
use RevGuardPro\Domain\ChangeOperation;
use RevGuardPro\Domain\EntityKind;
use RevGuardPro\Domain\Exception\EntityNotFoundException;
use RevGuardPro\Domain\Exception\InvalidEntityException;
use RevGuardPro\Domain\GroupMember;
use RevGuardPro\Domain\Repository\AdGroupRepositoryInterface;
use RevGuardPro\Domain\Status;
use RevGuardPro\Domain\TargetType;
use RevGuardPro\Persistence\Mapper\GroupMapper;

defined( 'ABSPATH' ) || exit;

/**
 * Stores groups in `{prefix}rgp_ad_groups` and their ordered, weighted members in `{prefix}rgp_group_ads`.
 *
 * Member replacement is an insert, an update and a delete of only what changed (no transaction, no empty-group window).
 */
final class WpdbAdGroupRepository extends AbstractWpdbRepository implements AdGroupRepositoryInterface {

	private const COLUMNS = 'id, name, status, rotation_mode, created_at, updated_at';

	/**
	 * Inserts or updates a group and replaces its members.
	 *
	 * @param AdGroup $group Group to store.
	 * @throws EntityNotFoundException When updating a missing group.
	 */
	public function save( AdGroup $group ): AdGroup {
		$this->assertMembersExist( $group );

		$now     = $this->now();
		$table   = $this->table( Schema::GROUPS );
		$columns = GroupMapper::columns( $group );

		if ( null === $group->id ) {
			$this->assertOk(
				$this->wpdb->insert(
					$table,
					$columns + array(
						'created_at' => $this->dbDate( $now ),
						'updated_at' => $this->dbDate( $now ),
					)
				),
				'insert_group'
			);
			$stored    = $group->persisted( (int) $this->wpdb->insert_id, $now, $now );
			$operation = ChangeOperation::Created;
		} else {
			$existing = $this->find( $group->id );
			if ( null === $existing ) {
				throw new EntityNotFoundException( EntityKind::Group, $group->id );
			}
			$this->assertOk(
				$this->wpdb->update( $table, $columns + array( 'updated_at' => $this->dbDate( $now ) ), array( 'id' => $group->id ), null, array( '%d' ) ),
				'update_group'
			);
			$stored    = $group->persisted( $group->id, $existing->created_at ?? $now, $now );
			$operation = ChangeOperation::Updated;
		}

		// The group row is already written: invalidate even if replacing members fails, so no cache keeps the old row.
		try {
			$this->replaceMembers( (int) $stored->id, $stored->members );
		} finally {
			$this->notify( EntityKind::Group, (int) $stored->id, $operation );
		}
		return $stored;
	}

	/**
	 * Finds one group with its members.
	 *
	 * @param int $id Group id.
	 */
	public function find( int $id ): ?AdGroup {
		if ( $id < 1 ) {
			return null;
		}
		$table = $this->table( Schema::GROUPS );
		$row   = $this->wpdb->get_row(
			$this->wpdb->prepare( 'SELECT ' . self::COLUMNS . " FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		$members = $this->memberRows( array( $id ) );
		return GroupMapper::fromRow( $row, $members[ $id ] ?? array() );
	}

	/**
	 * Deletes a group and its memberships.
	 *
	 * @param int $id Group id.
	 */
	public function delete( int $id ): bool {
		if ( $id < 1 ) {
			return false;
		}
		$this->assertNotReferenced( TargetType::Group, $id );

		// Memberships first so a failure leaves an empty (fail-safe) group, never memberships pointing at a reusable id.
		$members_removed = false;
		$deleted         = 0;
		try {
			$this->assertOk( $this->wpdb->delete( $this->table( Schema::GROUP_ADS ), array( 'group_id' => $id ), array( '%d' ) ), 'delete_group_members' );
			$members_removed = true;
			$result          = $this->wpdb->delete( $this->table( Schema::GROUPS ), array( 'id' => $id ), array( '%d' ) );
			$this->assertOk( $result, 'delete_group' );
			$deleted = (int) $result;
		} finally {
			// Anything already written must invalidate caches even when a later step failed.
			if ( $deleted > 0 ) {
				$this->notify( EntityKind::Group, $id, ChangeOperation::Deleted );
			} elseif ( $members_removed ) {
				$this->notify( EntityKind::Group, $id, ChangeOperation::Updated );
			}
		}
		return $deleted > 0;
	}

	/**
	 * Lists groups by id ascending, loading all members of the page in one extra query.
	 *
	 * @param Status|null $status Optional status filter.
	 * @param int         $limit  Page size.
	 * @param int         $offset Offset.
	 * @return list<AdGroup>
	 */
	public function list( ?Status $status = null, int $limit = 50, int $offset = 0 ): array {
		$table  = $this->table( Schema::GROUPS );
		$limit  = Paging::limit( $limit );
		$offset = Paging::offset( $offset );
		$sql    = null === $status
			? $this->wpdb->prepare( 'SELECT ' . self::COLUMNS . " FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d", $limit, $offset )
			: $this->wpdb->prepare( 'SELECT ' . self::COLUMNS . " FROM {$table} WHERE status = %s ORDER BY id ASC LIMIT %d OFFSET %d", $status->value, $limit, $offset );

		$rows = (array) $this->wpdb->get_results( $sql, ARRAY_A );
		if ( array() === $rows ) {
			return array();
		}
		$ids     = array_map( static fn( $row ): int => (int) ( (array) $row )['id'], $rows );
		$members = $this->memberRows( $ids );

		$groups = array();
		foreach ( $rows as $row ) {
			$row      = (array) $row;
			$groups[] = GroupMapper::fromRow( $row, $members[ (int) $row['id'] ] ?? array() );
		}
		return $groups;
	}

	/**
	 * Counts groups.
	 *
	 * @param Status|null $status Optional status filter.
	 */
	public function count( ?Status $status = null ): int {
		$table = $this->table( Schema::GROUPS );
		$sql   = null === $status
			? "SELECT COUNT(*) FROM {$table}"
			: $this->wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status->value );
		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * Verifies every member ad exists.
	 *
	 * @param AdGroup $group Group being stored.
	 * @throws InvalidEntityException When a member ad is unknown.
	 */
	private function assertMembersExist( AdGroup $group ): void {
		$ids = $group->memberAdIds();
		if ( array() === $ids ) {
			return;
		}
		$table = $this->table( Schema::ADS );
		$found = (int) $this->wpdb->get_var(
			$this->wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE id IN (" . $this->intPlaceholders( count( $ids ) ) . ')', ...$ids )
		);
		if ( count( $ids ) !== $found ) {
			throw new InvalidEntityException( 'members', InvalidEntityException::REASON_UNKNOWN_REFERENCE );
		}
	}

	/**
	 * Makes the stored members equal the given list with portable statements (no deprecated VALUES()):
	 * one INSERT for new members, one UPDATE (CASE) for members whose order or weight changed, one DELETE for
	 * removed members. New members are added before old ones are removed, so there is no empty-group window.
	 *
	 * @param int                $group_id Group id.
	 * @param array<GroupMember> $members  Members in order.
	 */
	private function replaceMembers( int $group_id, array $members ): void {
		$table = $this->table( Schema::GROUP_ADS );

		if ( array() === $members ) {
			$this->assertOk( $this->wpdb->delete( $table, array( 'group_id' => $group_id ), array( '%d' ) ), 'clear_group_members' );
			return;
		}

		$existing = array();
		foreach ( (array) $this->wpdb->get_results( $this->wpdb->prepare( "SELECT ad_id, sort_order, weight FROM {$table} WHERE group_id = %d", $group_id ), ARRAY_A ) as $row ) {
			$row                             = (array) $row;
			$existing[ (int) $row['ad_id'] ] = array( (int) $row['sort_order'], (int) $row['weight'] );
		}

		$insert = array();
		$update = array();
		$keep   = array();
		foreach ( $members as $index => $member ) {
			$keep[] = $member->ad_id;
			if ( ! isset( $existing[ $member->ad_id ] ) ) {
				$insert[] = array( $member->ad_id, $index, $member->weight );
			} elseif ( array( $index, $member->weight ) !== $existing[ $member->ad_id ] ) {
				$update[] = array( $member->ad_id, $index, $member->weight );
			}
		}

		if ( array() !== $insert ) {
			$args = array();
			foreach ( $insert as $tuple ) {
				array_push( $args, $group_id, ...$tuple );
			}
			$sql = "INSERT INTO {$table} (group_id, ad_id, sort_order, weight) VALUES " . implode( ',', array_fill( 0, count( $insert ), '(%d,%d,%d,%d)' ) );
			$this->assertOk( $this->wpdb->query( $this->wpdb->prepare( $sql, ...$args ) ), 'insert_group_members' );
		}

		if ( array() !== $update ) {
			$order_cases  = array();
			$weight_cases = array();
			$order_args   = array();
			$weight_args  = array();
			$ids          = array();
			foreach ( $update as list( $ad_id, $index, $weight ) ) {
				$order_cases[]  = 'WHEN %d THEN %d';
				$weight_cases[] = 'WHEN %d THEN %d';
				array_push( $order_args, $ad_id, $index );
				array_push( $weight_args, $ad_id, $weight );
				$ids[] = $ad_id;
			}
			$sql = "UPDATE {$table} SET sort_order = CASE ad_id " . implode( ' ', $order_cases ) . ' ELSE sort_order END, weight = CASE ad_id ' . implode( ' ', $weight_cases )
				. ' ELSE weight END WHERE group_id = %d AND ad_id IN (' . $this->intPlaceholders( count( $ids ) ) . ')';
			$this->assertOk( $this->wpdb->query( $this->wpdb->prepare( $sql, ...$order_args, ...$weight_args, ...array( $group_id ), ...$ids ) ), 'update_group_members' );
		}

		$removed = array_values( array_diff( array_keys( $existing ), $keep ) );
		if ( array() !== $removed ) {
			$sql = "DELETE FROM {$table} WHERE group_id = %d AND ad_id IN (" . $this->intPlaceholders( count( $removed ) ) . ')';
			$this->assertOk( $this->wpdb->query( $this->wpdb->prepare( $sql, $group_id, ...$removed ) ), 'prune_group_members' );
		}
	}

	/**
	 * Member rows for several groups in one query, grouped by group id and ordered by sort_order, ad_id.
	 *
	 * @param array<int> $group_ids Group ids.
	 * @return array<int, list<array<string, mixed>>>
	 */
	private function memberRows( array $group_ids ): array {
		$table   = $this->table( Schema::GROUP_ADS );
		$rows    = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT group_id, ad_id, weight FROM {$table} WHERE group_id IN (" . $this->intPlaceholders( count( $group_ids ) ) . ') ORDER BY group_id ASC, sort_order ASC, ad_id ASC',
				...$group_ids
			),
			ARRAY_A
		);
		$grouped = array();
		foreach ( (array) $rows as $row ) {
			$row                                 = (array) $row;
			$grouped[ (int) $row['group_id'] ][] = $row;
		}
		return $grouped;
	}
}
