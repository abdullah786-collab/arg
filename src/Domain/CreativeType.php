<?php
/**
 * Creative type.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Kind of creative an ad carries. There is one generic kind: ad code, stored and rendered as HTML under the
 * sanitization rules of the creative policy. The enum is kept so the stored `creative_type` column stays explicit.
 */
enum CreativeType: string {

	case Html = 'html';
}
