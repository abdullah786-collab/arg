<?php
/**
 * Group row mapping.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Persistence\Mapper;

use RevGuardPro\Domain\AdGroup;
use RevGuardPro\Domain\EntityKind;
use RevGuardPro\Domain\Exception\CorruptRecordException;
use RevGuardPro\Domain\Exception\InvalidEntityException;
use RevGuardPro\Domain\GroupMember;
use RevGuardPro\Domain\RotationMode;
use RevGuardPro\Domain\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Converts between group rows (plus their member rows) and AdGroup entities. Pure.
 */
final class GroupMapper {

	/**
	 * Builds a group from a row and its member rows.
	 *
	 * @param array<string, mixed>       $row         Group row.
	 * @param list<array<string, mixed>> $member_rows Member rows already ordered by sort_order, ad_id.
	 * @throws CorruptRecordException When a row is malformed or violates an invariant.
	 */
	public static function fromRow( array $row, array $member_rows ): AdGroup {
		$kind = EntityKind::Group;
		$id   = Row::int( $row, 'id', $kind );
		try {
			$status = Status::tryFrom( Row::string( $row, 'status', $kind ) ) ?? throw new CorruptRecordException( $kind, $id, 'status' );
			$mode   = RotationMode::tryFrom( Row::string( $row, 'rotation_mode', $kind ) ) ?? throw new CorruptRecordException( $kind, $id, 'rotation_mode' );

			$members = array();
			foreach ( $member_rows as $member_row ) {
				$members[] = new GroupMember( Row::int( $member_row, 'ad_id', $kind ), Row::int( $member_row, 'weight', $kind ) );
			}

			return AdGroup::hydrate(
				$id,
				Row::string( $row, 'name', $kind ),
				$status,
				$mode,
				$members,
				Row::date( $row, 'created_at', $kind ),
				Row::date( $row, 'updated_at', $kind )
			);
		} catch ( InvalidEntityException $e ) {
			throw new CorruptRecordException( $kind, $id, $e->field . ':' . $e->reason );
		}
	}

	/**
	 * Writable group columns (no id, no timestamps, no members).
	 *
	 * @param AdGroup $group Group.
	 * @return array<string, string>
	 */
	public static function columns( AdGroup $group ): array {
		return array(
			'name'          => $group->name,
			'status'        => $group->status->value,
			'rotation_mode' => $group->rotation_mode->value,
		);
	}
}
