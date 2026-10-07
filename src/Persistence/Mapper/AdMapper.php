<?php
/**
 * Ad row mapping.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Persistence\Mapper;

use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\CreativeType;
use RevGuardPro\Domain\EntityKind;
use RevGuardPro\Domain\Exception\CorruptRecordException;
use RevGuardPro\Domain\Exception\InvalidEntityException;
use RevGuardPro\Domain\Status;
use RevGuardPro\Scheduling\Schedule;
use RevGuardPro\Targeting\TargetingDefinition;

defined( 'ABSPATH' ) || exit;

/**
 * Converts between ad rows and Ad entities. Pure: no database access.
 */
final class AdMapper {

	/**
	 * Builds an Ad from a stored row.
	 *
	 * @param array<string, mixed> $row Row.
	 * @throws CorruptRecordException When the row is malformed or violates an invariant.
	 */
	public static function fromRow( array $row ): Ad {
		$kind = EntityKind::Ad;
		$id   = Row::int( $row, 'id', $kind );
		try {
			$status = Status::tryFrom( Row::string( $row, 'status', $kind ) ) ?? throw new CorruptRecordException( $kind, $id, 'status' );
			$type   = CreativeType::tryFrom( Row::string( $row, 'creative_type', $kind ) ) ?? throw new CorruptRecordException( $kind, $id, 'creative_type' );
			return Ad::hydrate(
				$id,
				Row::string( $row, 'name', $kind ),
				$status,
				$type,
				Row::string( $row, 'creative', $kind ),
				Row::date( $row, 'created_at', $kind ),
				Row::date( $row, 'updated_at', $kind ),
				Schedule::between( Row::nullableDate( $row, 'schedule_start', $kind ), Row::nullableDate( $row, 'schedule_end', $kind ) ),
				TargetingDefinition::fromStored( Row::nullableString( $row, 'targeting', $kind ) )
			);
		} catch ( InvalidEntityException $e ) {
			throw new CorruptRecordException( $kind, $id, $e->field . ':' . $e->reason );
		}
	}

	/**
	 * Writable columns (no id, no created/updated timestamps). Schedule bounds are UTC strings or null.
	 *
	 * @param Ad $ad Ad.
	 * @throws \RevGuardPro\Domain\Exception\InvalidEntityException When the ad's targeting is corrupt and must not be stored.
	 * @return array<string, string|null>
	 */
	public static function columns( Ad $ad ): array {
		return array(
			'name'           => $ad->name,
			'status'         => $ad->status->value,
			'creative_type'  => $ad->creative_type->value,
			'creative'       => $ad->creative,
			'schedule_start' => $ad->schedule->start?->format( 'Y-m-d H:i:s' ),
			'schedule_end'   => $ad->schedule->end?->format( 'Y-m-d H:i:s' ),
			'targeting'      => $ad->targeting->toJson(),
		);
	}
}
