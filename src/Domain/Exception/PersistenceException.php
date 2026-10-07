<?php
/**
 * Raised when the database rejects an operation.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain\Exception;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Storage failure. The raw database error is kept out of the message and exposed only as a property for diagnostics.
 */
final class PersistenceException extends RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param string $operation  Short operation code, for example "insert_ad".
	 * @param string $last_error Database error text, for diagnostics only.
	 */
	public function __construct( public readonly string $operation, public readonly string $last_error = '' ) {
		parent::__construct( sprintf( 'Storage operation "%s" failed.', $operation ) );
	}
}
