<?php
/**
 * Raised when a stored row cannot be turned into a valid entity.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain\Exception;

use RevGuardPro\Domain\EntityKind;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * A stored row violates the model (manual edit, or a value written by a newer plugin version after a downgrade).
 */
final class CorruptRecordException extends RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param EntityKind $kind   Entity kind.
	 * @param int        $id     Row id (0 when unknown).
	 * @param string     $reason Short reason code.
	 */
	public function __construct( public readonly EntityKind $kind, public readonly int $id, public readonly string $reason ) {
		parent::__construct( sprintf( 'Corrupt %s record %d: %s.', $kind->value, $id, $reason ) );
	}
}
