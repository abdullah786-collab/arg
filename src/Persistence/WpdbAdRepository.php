<?php
/**
 * `$wpdb` ad repository.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Persistence;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- This class is the database boundary; read caching is the later cache layer (see the change notifier).
// phpcs:disable WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders -- Table names are allow-listed Schema constants; IN lists are built from %d placeholders.

use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\ChangeOperation;
use RevGuardPro\Domain\EntityKind;
use RevGuardPro\Domain\Exception\CorruptRecordException;
use RevGuardPro\Domain\Exception\EntityNotFoundException;
use RevGuardPro\Domain\Repository\AdRepositoryInterface;
use RevGuardPro\Domain\Repository\ChangeNotifierInterface;
use RevGuardPro\Domain\Repository\CreativePolicyInterface;
use RevGuardPro\Domain\Status;
use RevGuardPro\Domain\TargetType;
use RevGuardPro\Persistence\Mapper\AdMapper;
use RevGuardPro\Support\ClockInterface;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Stores ads in `{prefix}rgp_ads`.
 */
final class WpdbAdRepository extends AbstractWpdbRepository implements AdRepositoryInterface {

	private const COLUMNS = 'id, name, status, creative_type, creative, schedule_start, schedule_end, targeting, created_at, updated_at';

	/**
	 * Constructor.
	 *
	 * @param wpdb                    $wpdb     Database.
	 * @param ClockInterface          $clock    Time source.
	 * @param ChangeNotifierInterface $notifier Cache invalidation boundary.
	 * @param CreativePolicyInterface $policy   Creative storage policy.
	 */
	public function __construct( wpdb $wpdb, ClockInterface $clock, ChangeNotifierInterface $notifier, private readonly CreativePolicyInterface $policy ) {
		parent::__construct( $wpdb, $clock, $notifier );
	}

	/**
	 * Inserts or updates an ad.
	 *
	 * @param Ad $ad Ad to store.
	 * @throws EntityNotFoundException When updating a missing ad.
	 */
	public function save( Ad $ad ): Ad {
		$creative = $this->policy->prepareForStorage( $ad->creative_type, $ad->creative );
		if ( $creative !== $ad->creative ) {
			$ad = $ad->withCreative( $ad->creative_type, $creative );
		}

		$now     = $this->now();
		$table   = $this->table( Schema::ADS );
		$columns = AdMapper::columns( $ad );

		if ( null === $ad->id ) {
			$this->assertOk(
				$this->wpdb->insert(
					$table,
					$columns + array(
						'created_at' => $this->dbDate( $now ),
						'updated_at' => $this->dbDate( $now ),
					)
				),
				'insert_ad'
			);
			$stored    = $ad->persisted( (int) $this->wpdb->insert_id, $now, $now );
			$operation = ChangeOperation::Created;
		} else {
			$existing = $this->find( $ad->id );
			if ( null === $existing ) {
				throw new EntityNotFoundException( EntityKind::Ad, $ad->id );
			}
			$this->assertOk(
				$this->wpdb->update( $table, $columns + array( 'updated_at' => $this->dbDate( $now ) ), array( 'id' => $ad->id ), null, array( '%d' ) ),
				'update_ad'
			);
			$stored    = $ad->persisted( $ad->id, $existing->created_at ?? $now, $now );
			$operation = ChangeOperation::Updated;
		}

		$this->notify( EntityKind::Ad, (int) $stored->id, $operation );
		return $stored;
	}

	/**
	 * Finds one ad.
	 *
	 * @param int $id Ad id.
	 */
	public function find( int $id ): ?Ad {
		if ( $id < 1 ) {
			return null;
		}
		$table = $this->table( Schema::ADS );
		$row   = $this->wpdb->get_row(
			$this->wpdb->prepare( 'SELECT ' . self::COLUMNS . " FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);
		return is_array( $row ) ? AdMapper::fromRow( $row ) : null;
	}

	/**
	 * Finds several ads in one query per 500 ids. Rows that cannot be hydrated are skipped, never thrown.
	 *
	 * @param array<int> $ids Ad ids.
	 * @return array<int, Ad>
	 */
	public function findMany( array $ids ): array {
		$table = $this->table( Schema::ADS );
		$found = array();
		foreach ( array_chunk( $this->cleanIds( $ids ), Paging::MAX_LIMIT ) as $chunk ) {
			$rows = $this->wpdb->get_results(
				$this->wpdb->prepare( 'SELECT ' . self::COLUMNS . " FROM {$table} WHERE id IN (" . $this->intPlaceholders( count( $chunk ) ) . ')', ...$chunk ),
				ARRAY_A
			);
			foreach ( (array) $rows as $row ) {
				try {
					$ad = AdMapper::fromRow( (array) $row );
				} catch ( CorruptRecordException ) {
					// Serving must never fail because of one unreadable row; find() and list() still surface it.
					continue;
				}
				$found[ (int) $ad->id ] = $ad;
			}
		}
		return $found;
	}

	/**
	 * Deletes an ad, first removing it from every group.
	 *
	 * @param int $id Ad id.
	 */
	public function delete( int $id ): bool {
		if ( $id < 1 ) {
			return false;
		}
		$this->assertNotReferenced( TargetType::Ad, $id );

		$members   = $this->table( Schema::GROUP_ADS );
		$group_ids = $this->wpdb->get_col( $this->wpdb->prepare( "SELECT group_id FROM {$members} WHERE ad_id = %d ORDER BY group_id ASC", $id ) );

		// Memberships first: a failure between the two steps leaves the ad unreferenced, never a membership pointing at a reusable id.
		$memberships_removed = false;
		$deleted             = 0;
		try {
			$this->assertOk( $this->wpdb->delete( $members, array( 'ad_id' => $id ), array( '%d' ) ), 'delete_ad_memberships' );
			$memberships_removed = true;
			$result              = $this->wpdb->delete( $this->table( Schema::ADS ), array( 'id' => $id ), array( '%d' ) );
			$this->assertOk( $result, 'delete_ad' );
			$deleted = (int) $result;
		} finally {
			// Anything already written must invalidate caches even when a later step failed.
			if ( $deleted > 0 ) {
				$this->notify( EntityKind::Ad, $id, ChangeOperation::Deleted );
			}
			if ( $memberships_removed ) {
				foreach ( array_map( 'intval', (array) $group_ids ) as $group_id ) {
					$this->notify( EntityKind::Group, $group_id, ChangeOperation::Updated );
				}
			}
		}
		return $deleted > 0;
	}

	/**
	 * Lists ads by id ascending.
	 *
	 * @param Status|null $status Optional status filter.
	 * @param int         $limit  Page size.
	 * @param int         $offset Offset.
	 * @return list<Ad>
	 */
	public function list( ?Status $status = null, int $limit = 50, int $offset = 0 ): array {
		$table  = $this->table( Schema::ADS );
		$limit  = Paging::limit( $limit );
		$offset = Paging::offset( $offset );
		$sql    = null === $status
			? $this->wpdb->prepare( 'SELECT ' . self::COLUMNS . " FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d", $limit, $offset )
			: $this->wpdb->prepare( 'SELECT ' . self::COLUMNS . " FROM {$table} WHERE status = %s ORDER BY id ASC LIMIT %d OFFSET %d", $status->value, $limit, $offset );

		$ads = array();
		foreach ( (array) $this->wpdb->get_results( $sql, ARRAY_A ) as $row ) {
			$ads[] = AdMapper::fromRow( (array) $row );
		}
		return $ads;
	}

	/**
	 * Counts ads.
	 *
	 * @param Status|null $status Optional status filter.
	 */
	public function count( ?Status $status = null ): int {
		$table = $this->table( Schema::ADS );
		$sql   = null === $status
			? "SELECT COUNT(*) FROM {$table}"
			: $this->wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status->value );
		return (int) $this->wpdb->get_var( $sql );
	}
}
