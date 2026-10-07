<?php
/**
 * Field error codes and their messages.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Form;

use RevGuardPro\Domain\Exception\InvalidEntityException;

defined( 'ABSPATH' ) || exit;

/**
 * A small fixed vocabulary: forms report codes, never text built from input.
 */
final class ErrorMessages {

	/**
	 * Maps a domain validation failure to a form error code.
	 *
	 * @param InvalidEntityException $error Domain exception.
	 */
	public static function code( InvalidEntityException $error ): string {
		return match ( $error->reason ) {
			InvalidEntityException::REASON_EMPTY, InvalidEntityException::REASON_MISSING => 'required',
			InvalidEntityException::REASON_TOO_LONG                                       => 'too_long',
			InvalidEntityException::REASON_OUT_OF_RANGE                                   => 'out_of_range',
			InvalidEntityException::REASON_DUPLICATE                                      => 'duplicate',
			InvalidEntityException::REASON_TOO_MANY                                       => 'too_many',
			InvalidEntityException::REASON_UNKNOWN_REFERENCE                              => 'unknown_reference',
			InvalidEntityException::REASON_INVALID_INTERVAL                               => 'schedule_order',
			InvalidEntityException::REASON_NONEXISTENT_LOCAL                              => 'schedule_nonexistent',
			InvalidEntityException::REASON_AMBIGUOUS_LOCAL                                => 'schedule_ambiguous',
			default                                                                       => 'invalid',
		};
	}

	/**
	 * The message for an error code.
	 *
	 * @param string $code Error code.
	 */
	public static function text( string $code ): string {
		return match ( $code ) {
			'required'             => __( 'This field is required.', 'revguard-pro' ),
			'too_long'             => __( 'This is too long.', 'revguard-pro' ),
			'out_of_range'         => __( 'This number is outside the allowed range.', 'revguard-pro' ),
			'duplicate'            => __( 'This appears more than once.', 'revguard-pro' ),
			'too_many'             => __( 'There are too many items.', 'revguard-pro' ),
			'unknown_reference'    => __( 'The selected item does not exist.', 'revguard-pro' ),
			'schedule_order'       => __( 'The end must be after the start.', 'revguard-pro' ),
			'schedule_nonexistent' => __( 'That local time does not exist because of a daylight-saving change. Choose another time.', 'revguard-pro' ),
			'schedule_ambiguous'   => __( 'That local time happens twice because of a daylight-saving change. Choose another time.', 'revguard-pro' ),
			'creative_required'    => __( 'An active ad needs ad code. Add the code or save it as inactive.', 'revguard-pro' ),
			'target_required'      => __( 'An active placement needs a target. Choose an ad or group, or save it as inactive.', 'revguard-pro' ),
			'not_allowed'          => __( 'You are not allowed to change this setting.', 'revguard-pro' ),
			'storage'              => __( 'It could not be saved. Nothing was changed. Please try again.', 'revguard-pro' ),
			default                => __( 'This value is not valid.', 'revguard-pro' ),
		};
	}
}
