<?php
/**
 * WordPress change notifier.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Cache;

use RevGuardPro\Domain\ChangeOperation;
use RevGuardPro\Domain\EntityKind;
use RevGuardPro\Domain\Repository\ChangeNotifierInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Rotates the data version and fires the one public change action.
 */
final class WpChangeNotifier implements ChangeNotifierInterface {

	/**
	 * Constructor.
	 *
	 * @param DataVersion $version Data version token.
	 */
	public function __construct( private readonly DataVersion $version ) {
	}

	/**
	 * Reports a completed change.
	 *
	 * @param EntityKind      $kind      Entity kind.
	 * @param int             $id        Entity id.
	 * @param ChangeOperation $operation What happened.
	 */
	public function entityChanged( EntityKind $kind, int $id, ChangeOperation $operation ): void {
		$this->version->bump();

		/**
		 * Fires after an ad, group or placement was created, updated or deleted, once the data is stored.
		 * The data version token has already been rotated. Callbacks must not write RevGuard Pro entities.
		 *
		 * @param string $kind      "ad", "group" or "placement".
		 * @param int    $id        Entity id.
		 * @param string $operation "created", "updated" or "deleted".
		 */
		do_action( 'revguard_pro_entity_changed', $kind->value, $id, $operation->value );
	}
}
