<?php
/**
 * Default creative policy.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Persistence;

use RevGuardPro\Domain\CreativeType;
use RevGuardPro\Domain\Repository\CreativePolicyInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Stores the creative unchanged. Structural limits are enforced by the Ad entity.
 *
 * SECURITY GATE: this applies no markup policy. No code path that accepts ad HTML from a request
 * (admin save, importer, REST) may ship before a real policy replaces this one and the output side
 * applies scoped KSES at render time. See docs/adr/001-domain-storage.md.
 */
final class PassThroughCreativePolicy implements CreativePolicyInterface {

	/**
	 * Returns the creative unchanged.
	 *
	 * @param CreativeType $type     Creative type.
	 * @param string       $creative Creative as provided.
	 */
	public function prepareForStorage( CreativeType $type, string $creative ): string {
		return $creative;
	}
}
