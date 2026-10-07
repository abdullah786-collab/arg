<?php
/**
 * Parses and validates the placement form.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Form;

use RevGuardPro\Admin\Support\Input;
use RevGuardPro\Domain\Exception\InvalidEntityException;
use RevGuardPro\Domain\Placement;
use RevGuardPro\Domain\PlacementConfig;
use RevGuardPro\Domain\PlacementType;
use RevGuardPro\Domain\Rules;
use RevGuardPro\Domain\Status;
use RevGuardPro\Domain\TargetType;

defined( 'ABSPATH' ) || exit;

/**
 * The target arrives as `ad:<id>` or `group:<id>`; only existing ids are accepted. Only the position fields that belong
 * to the chosen type are read. An existing placement keeps its stored targeting untouched.
 */
final class PlacementForm {

	/**
	 * Parses the POST body.
	 *
	 * @param array<string,mixed> $post      POST values.
	 * @param Placement|null      $existing  The placement being edited, or null.
	 * @param array<int,int>      $ad_ids    Existing ad ids (as keys).
	 * @param array<int,int>      $group_ids Existing group ids (as keys).
	 */
	public static function parse( array $post, ?Placement $existing, array $ad_ids, array $group_ids ): FormResult {
		$values = array(
			'name'          => Input::text( $post['name'] ?? '', 1000 ),
			'status'        => Input::text( $post['status'] ?? '', 20 ),
			'type'          => Input::text( $post['type'] ?? '', 30 ),
			'target'        => Input::text( $post['target'] ?? '', 20 ),
			'priority'      => Input::text( $post['priority'] ?? '', 6 ),
			'paragraph'     => Input::text( $post['paragraph'] ?? '', 6 ),
			'heading_level' => Input::text( $post['heading_level'] ?? '', 6 ),
			'occurrence'    => Input::text( $post['occurrence'] ?? '', 6 ),
			'percent'       => Input::text( $post['percent'] ?? '', 6 ),
		);
		$errors = array();

		$status = Input::enumCase( $values['status'], Status::class );
		$type   = Input::enumCase( $values['type'], PlacementType::class );
		if ( null === $status ) {
			$errors['status'] = 'invalid';
		}
		if ( null === $type ) {
			$errors['type'] = 'invalid';
		}
		try {
			Rules::name( $values['name'] );
		} catch ( InvalidEntityException $error ) {
			$errors['name'] = ErrorMessages::code( $error );
		}
		$priority = Input::int( '' === $values['priority'] ? (string) Placement::PRIORITY_DEFAULT : $values['priority'], Placement::PRIORITY_MIN, Placement::PRIORITY_MAX );
		if ( null === $priority ) {
			$errors['priority'] = 'out_of_range';
		}
		list( $target_type, $target_id ) = self::target( $values['target'], $ad_ids, $group_ids, $errors );
		if ( Status::Active === $status && null === $target_type && ! isset( $errors['target'] ) ) {
			$errors['target'] = 'target_required';
		}
		$config = null === $type ? null : self::config( $type, $values, $errors );

		if ( array() !== $errors || null === $status || null === $type || null === $config || null === $priority ) {
			return new FormResult( null, $errors, $values );
		}
		try {
			$placement = null === $existing
				? Placement::create( $values['name'], $config, $target_type, $target_id, $priority, $status )
				: $existing->withName( $values['name'] )->withStatus( $status )->withConfig( $config )->withTarget( $target_type, $target_id )->withPriority( $priority );
		} catch ( InvalidEntityException $error ) {
			$errors['name'] = ErrorMessages::code( $error );
			return new FormResult( null, $errors, $values );
		}
		return new FormResult( $placement, array(), $values );
	}

	/**
	 * Reads the target choice.
	 *
	 * @param string               $raw       Raw choice.
	 * @param array<int,int>       $ad_ids    Existing ad ids.
	 * @param array<int,int>       $group_ids Existing group ids.
	 * @param array<string,string> $errors    Errors (by reference).
	 * @return array{0:TargetType|null,1:int|null}
	 */
	private static function target( string $raw, array $ad_ids, array $group_ids, array &$errors ): array {
		if ( '' === $raw ) {
			return array( null, null );
		}
		if ( 1 !== preg_match( '/^(ad|group):([0-9]{1,10})$/D', $raw, $match ) ) {
			$errors['target'] = 'invalid';
			return array( null, null );
		}
		$id   = Input::id( $match[2] );
		$type = TargetType::from( $match[1] );
		$pool = TargetType::Ad === $type ? $ad_ids : $group_ids;
		if ( null === $id || ! isset( $pool[ $id ] ) ) {
			$errors['target'] = 'unknown_reference';
			return array( null, null );
		}
		return array( $type, $id );
	}

	/**
	 * Builds the typed position config from only the fields that belong to the type.
	 *
	 * @param PlacementType        $type   Type.
	 * @param array<string,mixed>  $values Sticky values.
	 * @param array<string,string> $errors Errors (by reference).
	 */
	private static function config( PlacementType $type, array $values, array &$errors ): ?PlacementConfig {
		$fields = match ( $type ) {
			PlacementType::AfterParagraph => array( 'paragraph' ),
			PlacementType::AfterHeading   => array( 'heading_level', 'occurrence' ),
			PlacementType::Percentage     => array( 'percent' ),
			default                       => array(),
		};
		$config = array();
		foreach ( $fields as $field ) {
			if ( '' === $values[ $field ] ) {
				continue;
			}
			$number = Input::int( $values[ $field ], 0, 1000000 );
			if ( null === $number ) {
				$errors[ $field ] = 'invalid';
				continue;
			}
			$config[ $field ] = $number;
		}
		if ( array() !== $errors ) {
			return null;
		}
		try {
			return PlacementConfig::of( $type, $config );
		} catch ( InvalidEntityException $error ) {
			$errors[ $fields[0] ?? 'type' ] = ErrorMessages::code( $error );
			return null;
		}
	}
}
