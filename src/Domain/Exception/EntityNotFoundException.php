<?php
/**
 * Raised when an update targets an entity that does not exist.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain\Exception;

use RevGuardPro\Domain\EntityKind;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Update of a missing entity.
 */
final class EntityNotFoundException extends RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param EntityKind $kind Entity kind.
	 * @param int        $id   Missing id.
	 */
	public function __construct( public readonly EntityKind $kind, public readonly int $id ) {
		parent::__construct( sprintf( 'The %s with id %d does not exist.', $kind->value, $id ) );
	}
}
