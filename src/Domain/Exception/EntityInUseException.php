<?php
/**
 * Raised when deleting an entity that a placement still references.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain\Exception;

use RevGuardPro\Domain\EntityKind;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Delete refused because the entity is referenced.
 */
final class EntityInUseException extends RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param EntityKind $kind Entity kind.
	 * @param int        $id   Entity id.
	 */
	public function __construct( public readonly EntityKind $kind, public readonly int $id ) {
		parent::__construct( sprintf( 'The %s with id %d is in use by a placement.', $kind->value, $id ) );
	}
}
