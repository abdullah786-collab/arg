<?php
/**
 * Kind of change reported to the cache invalidation boundary.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Kind of change reported to the cache invalidation boundary.
 */
enum ChangeOperation: string {

	case Created = 'created';
	case Updated = 'updated';
	case Deleted = 'deleted';
}
