<?php
/**
 * Placement row mapping.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Persistence\Mapper;

use RevGuardPro\Domain\EntityKind;
use RevGuardPro\Domain\Exception\CorruptRecordException;
use RevGuardPro\Domain\Exception\InvalidEntityException;
use RevGuardPro\Domain\Placement;
use RevGuardPro\Domain\PlacementConfig;
use RevGuardPro\Domain\PlacementType;
use RevGuardPro\Domain\Status;
use RevGuardPro\Domain\TargetType;
use RevGuardPro\Targeting\TargetingDefinition;

defined( 'ABSPATH' ) || exit;

/**
 * Converts between placement rows and Placement entities. Pure.
 */
final class PlacementMapper {

	/**
	 * Builds a Placement from a stored row.
	 *
	 * @param array<string, mixed> $row Row.
	 * @throws CorruptRecordException When the row is malformed or violates an invariant.
	 */
	public static function fromRow( array $row ): Placement {
		$kind = EntityKind::Placement;
		$id   = Row::int( $row, 'id', $kind );
		try {
			$status = Status::tryFrom( Row::string( $row, 'status', $kind ) ) ?? throw new CorruptRecordException( $kind, $id, 'status' );
			$type   = PlacementType::tryFrom( Row::string( $row, 'placement_type', $kind ) ) ?? throw new CorruptRecordException( $kind, $id, 'placement_type' );
			$target = Row::nullableString( $row, 'target_type', $kind );

			return Placement::hydrate(
				$id,
				Row::string( $row, 'name', $kind ),
				$status,
				PlacementConfig::fromJson( $type, Row::string( $row, 'config', $kind ) ),
				null === $target ? null : ( TargetType::tryFrom( $target ) ?? throw new CorruptRecordException( $kind, $id, 'target_type' ) ),
				Row::nullableInt( $row, 'target_id', $kind ),
				Row::int( $row, 'priority', $kind ),
				Row::date( $row, 'created_at', $kind ),
				Row::date( $row, 'updated_at', $kind ),
				TargetingDefinition::fromStored( Row::nullableString( $row, 'targeting', $kind ) )
			);
		} catch ( InvalidEntityException $e ) {
			throw new CorruptRecordException( $kind, $id, $e->field . ':' . $e->reason );
		}
	}

	/**
	 * Writable columns (no id, no timestamps). Values may be null (unassigned target).
	 *
	 * @param Placement $placement Placement.
	 * @throws \RevGuardPro\Domain\Exception\InvalidEntityException When the targeting is corrupt and must not be stored.
	 * @return array<string, string|int|null>
	 */
	public static function columns( Placement $placement ): array {
		return array(
			'name'           => $placement->name,
			'status'         => $placement->status->value,
			'placement_type' => $placement->type()->value,
			'config'         => $placement->config->toJson(),
			'target_type'    => $placement->target_type?->value,
			'target_id'      => $placement->target_id,
			'priority'       => $placement->priority,
			'targeting'      => $placement->targeting->toJson(),
		);
	}

	/**
	 * `$wpdb` formats matching columns() in order, followed by the two timestamp columns.
	 *
	 * @return list<string>
	 */
	public static function formats(): array {
		return array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' );
	}
}
