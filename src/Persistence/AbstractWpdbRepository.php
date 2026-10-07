<?php
/**
 * Shared helpers for the `$wpdb` repositories.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Persistence;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders -- Allow-listed Schema table name; this class is the database boundary.

use DateTimeImmutable;
use DateTimeZone;
use RevGuardPro\Domain\ChangeOperation;
use RevGuardPro\Domain\EntityKind;
use RevGuardPro\Domain\Exception\EntityInUseException;
use RevGuardPro\Domain\Exception\PersistenceException;
use RevGuardPro\Domain\Repository\ChangeNotifierInterface;
use RevGuardPro\Domain\TargetType;
use RevGuardPro\Support\ClockInterface;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Base for the repositories. Table names come only from the Schema allow-list plus the prefix read at call time.
 * Every value reaches SQL through `$wpdb->prepare()` or the `$wpdb` insert/update/delete helpers.
 */
abstract class AbstractWpdbRepository {

	/**
	 * Constructor.
	 *
	 * @param wpdb                    $wpdb     Database.
	 * @param ClockInterface          $clock    Time source.
	 * @param ChangeNotifierInterface $notifier Cache invalidation boundary.
	 */
	public function __construct(
		protected readonly wpdb $wpdb,
		protected readonly ClockInterface $clock,
		protected readonly ChangeNotifierInterface $notifier
	) {
	}

	/**
	 * Full table name for the current site.
	 *
	 * @param string $suffix One of the Schema table constants.
	 */
	protected function table( string $suffix ): string {
		return Schema::table( $this->wpdb, $suffix );
	}

	/**
	 * Current UTC time.
	 */
	protected function now(): DateTimeImmutable {
		return $this->clock->now()->setTimezone( new DateTimeZone( 'UTC' ) );
	}

	/**
	 * UTC datetime string for storage.
	 *
	 * @param DateTimeImmutable $value Time.
	 */
	protected function dbDate( DateTimeImmutable $value ): string {
		return $value->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Throws when a `$wpdb` write helper failed.
	 *
	 * @param int|bool $result    Return value of the helper.
	 * @param string   $operation Operation code.
	 * @throws PersistenceException On failure.
	 */
	protected function assertOk( int|bool $result, string $operation ): void {
		if ( false === $result ) {
			throw new PersistenceException( $operation, $this->wpdb->last_error );
		}
	}

	/**
	 * Comma separated `%d` placeholders.
	 *
	 * @param int $count Number of placeholders (at least 1).
	 */
	protected function intPlaceholders( int $count ): string {
		return implode( ',', array_fill( 0, max( 1, $count ), '%d' ) );
	}

	/**
	 * Normalizes an id list: positive, unique, order preserved.
	 *
	 * @param array<mixed> $ids Candidate ids.
	 * @return list<int>
	 */
	protected function cleanIds( array $ids ): array {
		$clean = array();
		foreach ( $ids as $id ) {
			if ( is_int( $id ) && $id > 0 ) {
				$clean[ $id ] = $id;
			}
		}
		return array_values( $clean );
	}

	/**
	 * Throws EntityInUseException when a placement references the entity.
	 *
	 * @param TargetType $type Target kind.
	 * @param int        $id   Target id.
	 * @throws EntityInUseException When referenced.
	 */
	protected function assertNotReferenced( TargetType $type, int $id ): void {
		$placements = $this->table( Schema::PLACEMENTS );
		$count      = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$placements} WHERE target_type = %s AND target_id = %d",
				$type->value,
				$id
			)
		);
		if ( $count > 0 ) {
			throw new EntityInUseException( TargetType::Ad === $type ? EntityKind::Ad : EntityKind::Group, $id );
		}
	}

	/**
	 * Notifies the cache boundary.
	 *
	 * @param EntityKind      $kind      Entity kind.
	 * @param int             $id        Entity id.
	 * @param ChangeOperation $operation Operation.
	 */
	protected function notify( EntityKind $kind, int $id, ChangeOperation $operation ): void {
		$this->notifier->entityChanged( $kind, $id, $operation );
	}
}
