<?php
/**
 * Raised when a domain invariant is violated.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain\Exception;

use DomainException;

defined( 'ABSPATH' ) || exit;

/**
 * Domain invariant violation. The field and reason are stable codes for callers (the admin layer
 * maps them to translated messages); the exception message is for developers and logs only.
 */
final class InvalidEntityException extends DomainException {

	public const REASON_EMPTY             = 'empty';
	public const REASON_TOO_LONG          = 'too_long';
	public const REASON_OUT_OF_RANGE      = 'out_of_range';
	public const REASON_INVALID_VALUE     = 'invalid_value';
	public const REASON_INVALID_ENCODING  = 'invalid_encoding';
	public const REASON_DUPLICATE         = 'duplicate';
	public const REASON_UNKNOWN_KEY       = 'unknown_key';
	public const REASON_MISSING           = 'missing';
	public const REASON_TOO_MANY          = 'too_many';
	public const REASON_UNKNOWN_REFERENCE = 'unknown_reference';
	public const REASON_INVALID_INTERVAL  = 'invalid_interval';
	public const REASON_NONEXISTENT_LOCAL = 'nonexistent_local_time';
	public const REASON_AMBIGUOUS_LOCAL   = 'ambiguous_local_time';

	/**
	 * Constructor.
	 *
	 * @param string $field  Stable field code, for example "name" or "members".
	 * @param string $reason One of the REASON_* constants.
	 */
	public function __construct(
		public readonly string $field,
		public readonly string $reason
	) {
		parent::__construct( sprintf( 'Invalid %s: %s.', $field, $reason ) );
	}
}
