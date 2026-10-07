<?php
/**
 * Cache invalidation boundary.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain\Repository;

use RevGuardPro\Domain\ChangeOperation;
use RevGuardPro\Domain\EntityKind;

defined( 'ABSPATH' ) || exit;

/**
 * Called by repositories after every successful write. Later caches (targeting, placement) invalidate from here.
 */
interface ChangeNotifierInterface {

	/**
	 * Reports a completed change.
	 *
	 * @param EntityKind      $kind      Entity kind.
	 * @param int             $id        Entity id.
	 * @param ChangeOperation $operation What happened.
	 */
	public function entityChanged( EntityKind $kind, int $id, ChangeOperation $operation ): void;
}
