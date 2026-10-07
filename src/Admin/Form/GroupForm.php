<?php
/**
 * Parses and validates the group form.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Form;

use RevGuardPro\Admin\Support\Input;
use RevGuardPro\Domain\AdGroup;
use RevGuardPro\Domain\Exception\InvalidEntityException;
use RevGuardPro\Domain\GroupMember;
use RevGuardPro\Domain\RotationMode;
use RevGuardPro\Domain\Rules;
use RevGuardPro\Domain\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Members arrive as `members[<ad id>][include|weight|order]`. Only ids of ads that exist can be members; order decides
 * the sequence for ordered rotation, weight the share for weighted rotation.
 */
final class GroupForm {

	public const MAX_ORDER = 100000;

	/**
	 * Parses the POST body.
	 *
	 * @param array<string,mixed> $post         POST values.
	 * @param AdGroup|null        $existing     The group being edited, or null.
	 * @param array<int,int>      $known_ad_ids Ids of ads that exist (as keys).
	 */
	public static function parse( array $post, ?AdGroup $existing, array $known_ad_ids ): FormResult {
		$values = array(
			'name'          => Input::text( $post['name'] ?? '', 1000 ),
			'status'        => Input::text( $post['status'] ?? '', 20 ),
			'rotation_mode' => Input::text( $post['rotation_mode'] ?? '', 20 ),
			'members'       => array(),
		);
		$errors = array();

		$status = Input::enumCase( $values['status'], Status::class );
		$mode   = Input::enumCase( $values['rotation_mode'], RotationMode::class );
		if ( null === $status ) {
			$errors['status'] = 'invalid';
		}
		if ( null === $mode ) {
			$errors['rotation_mode'] = 'invalid';
		}
		try {
			Rules::name( $values['name'] );
		} catch ( InvalidEntityException $error ) {
			$errors['name'] = ErrorMessages::code( $error );
		}

		$members = self::members( $post['members'] ?? array(), $known_ad_ids, $values, $errors );
		if ( array() !== $errors || null === $status || null === $mode ) {
			return new FormResult( null, $errors, $values );
		}

		try {
			$group = null === $existing
				? AdGroup::create( $values['name'], $mode, $members, $status )
				: $existing->withName( $values['name'] )->withStatus( $status )->withRotationMode( $mode )->withMembers( $members );
		} catch ( InvalidEntityException $error ) {
			$errors[ 'members' === $error->field ? 'members' : 'name' ] = ErrorMessages::code( $error );
			return new FormResult( null, $errors, $values );
		}
		return new FormResult( $group, array(), $values );
	}

	/**
	 * Reads the member rows.
	 *
	 * @param mixed                $raw          Raw `members` value.
	 * @param array<int,int>       $known_ad_ids Existing ad ids.
	 * @param array<string,mixed>  $values       Sticky values (by reference).
	 * @param array<string,string> $errors       Errors (by reference).
	 * @return array<int,GroupMember>
	 */
	private static function members( mixed $raw, array $known_ad_ids, array &$values, array &$errors ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$rows = array();
		foreach ( $raw as $key => $row ) {
			$ad_id = Input::id( $key );
			if ( null === $ad_id || ! is_array( $row ) || ! isset( $row['include'] ) ) {
				continue;
			}
			$weight = Input::int( $row['weight'] ?? GroupMember::WEIGHT_DEFAULT, GroupMember::WEIGHT_MIN, GroupMember::WEIGHT_MAX );
			// A blank Order (the form's default) means 0; anything else must be a valid number.
			$order = '' === trim( (string) ( $row['order'] ?? '' ) ) ? 0 : Input::int( $row['order'], 0, self::MAX_ORDER );
			if ( ! isset( $known_ad_ids[ $ad_id ] ) ) {
				$errors['members'] = 'unknown_reference';
				continue;
			}
			if ( null === $weight || null === $order ) {
				$errors['members'] = 'out_of_range';
			}
			$values['members'][ $ad_id ] = array(
				'weight' => (string) ( $row['weight'] ?? '' ),
				'order'  => (string) ( $row['order'] ?? '' ),
			);
			$rows[]                      = array( $order ?? 0, $ad_id, $weight ?? GroupMember::WEIGHT_DEFAULT );
		}
		if ( count( $rows ) > AdGroup::MAX_MEMBERS ) {
			$errors['members'] = 'too_many';
		}
		usort( $rows, static fn( array $a, array $b ): int => array( $a[0], $a[1] ) <=> array( $b[0], $b[1] ) );
		return array_map( static fn( array $row ): GroupMember => new GroupMember( $row[1], $row[2] ), $rows );
	}
}
