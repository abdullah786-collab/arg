<?php
/**
 * Creative storage policy boundary.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Domain\Repository;

use RevGuardPro\Domain\CreativeType;

defined( 'ABSPATH' ) || exit;

/**
 * The single choke point where ad markup policy (KSES, ad-code validation) is applied before storage.
 * It receives the creative exactly as provided and returns what should be stored. It must not HTML-escape.
 */
interface CreativePolicyInterface {

	/**
	 * Prepares a creative for storage.
	 *
	 * @param CreativeType $type     Creative type.
	 * @param string       $creative Creative as provided.
	 */
	public function prepareForStorage( CreativeType $type, string $creative ): string;
}
