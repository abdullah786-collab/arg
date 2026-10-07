<?php
/**
 * Parses and validates the ad form.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Form;

use DateTimeZone;
use RevGuardPro\Admin\Support\Input;
use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\CreativeType;
use RevGuardPro\Domain\Exception\InvalidEntityException;
use RevGuardPro\Domain\Rules;
use RevGuardPro\Domain\Status;
use RevGuardPro\Scheduling\Schedule;
use RevGuardPro\Scheduling\ScheduleTime;

defined( 'ABSPATH' ) || exit;

/**
 * Domain constructors do the validating; this class only reads the request strictly and maps failures to field codes.
 * Targeting is not editable here: an existing ad keeps its stored targeting untouched.
 */
final class AdForm {

	/**
	 * Parses the POST body.
	 *
	 * @param array<string,mixed> $post     POST values.
	 * @param Ad|null             $existing The ad being edited, or null for a new one.
	 * @param DateTimeZone        $zone     The site's time zone (schedule inputs are wall-clock times in it).
	 */
	public static function parse( array $post, ?Ad $existing, DateTimeZone $zone ): FormResult {
		$values = array(
			'name'           => Input::text( $post['name'] ?? '', 1000 ),
			'status'         => Input::text( $post['status'] ?? '', 20 ),
			'creative'       => Input::block( $post['creative'] ?? '' ),
			'schedule_start' => Input::text( $post['schedule_start'] ?? '', 25 ),
			'schedule_end'   => Input::text( $post['schedule_end'] ?? '', 25 ),
		);
		$errors = array();

		$status = Input::enumCase( $values['status'], Status::class );
		$type   = CreativeType::Html;
		if ( null === $status ) {
			$errors['status'] = 'invalid';
		}
		try {
			Rules::name( $values['name'] );
		} catch ( InvalidEntityException $error ) {
			$errors['name'] = ErrorMessages::code( $error );
		}
		try {
			Rules::payload( $values['creative'], Ad::MAX_CREATIVE_BYTES, 'creative' );
		} catch ( InvalidEntityException $error ) {
			$errors['creative'] = ErrorMessages::code( $error );
		}
		if ( Status::Active === $status && '' === trim( $values['creative'] ) && ! isset( $errors['creative'] ) ) {
			$errors['creative'] = 'creative_required';
		}

		$schedule = self::schedule( $values, $zone, $errors );
		if ( array() !== $errors || null === $status || null === $schedule ) {
			return new FormResult( null, $errors, $values );
		}

		try {
			$ad = null === $existing
				? Ad::create( $values['name'], $type, $values['creative'], $status, $schedule )
				: $existing->withName( $values['name'] )->withStatus( $status )->withCreative( $type, $values['creative'] )->withSchedule( $schedule );
		} catch ( InvalidEntityException $error ) {
			$errors[ 'creative' === $error->field ? 'creative' : 'name' ] = ErrorMessages::code( $error );
			return new FormResult( null, $errors, $values );
		}
		return new FormResult( $ad, array(), $values );
	}

	/**
	 * Builds the schedule from the two wall-clock inputs.
	 *
	 * @param array<string,mixed>  $values Sticky values.
	 * @param DateTimeZone         $zone   Site zone.
	 * @param array<string,string> $errors Errors (by reference).
	 */
	private static function schedule( array $values, DateTimeZone $zone, array &$errors ): ?Schedule {
		$bounds = array();
		foreach ( array( 'schedule_start', 'schedule_end' ) as $field ) {
			if ( '' === $values[ $field ] ) {
				$bounds[ $field ] = null;
				continue;
			}
			try {
				$bounds[ $field ] = ScheduleTime::fromLocal( $values[ $field ], $zone, $field );
			} catch ( InvalidEntityException $error ) {
				$errors[ $field ] = ErrorMessages::code( $error );
			}
		}
		if ( array() !== $errors ) {
			return null;
		}
		try {
			return Schedule::between( $bounds['schedule_start'], $bounds['schedule_end'] );
		} catch ( InvalidEntityException $error ) {
			$errors['schedule_end'] = ErrorMessages::code( $error );
			return null;
		}
	}
}
