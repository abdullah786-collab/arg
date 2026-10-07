<?php
/**
 * Validated attributes of the ad block.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rendering;

use RevGuardPro\Domain\TargetType;

defined( 'ABSPATH' ) || exit;

/**
 * The two block attributes, validated. Anything unexpected makes the block render nothing.
 */
final class BlockAttributes {

	/**
	 * Constructor.
	 *
	 * @param TargetType $type Target type.
	 * @param int        $id   Target id.
	 */
	public function __construct( public readonly TargetType $type, public readonly int $id ) {
	}

	/**
	 * Validates raw block attributes.
	 *
	 * @param array<mixed> $attributes Raw attributes.
	 */
	public static function fromArray( array $attributes ): ?self {
		$type = array_key_exists( 'targetType', $attributes ) ? $attributes['targetType'] : 'ad';
		if ( ! is_string( $type ) || null === TargetType::tryFrom( $type ) ) {
			return null;
		}
		$id = $attributes['targetId'] ?? null;
		if ( is_string( $id ) && 1 === preg_match( '/^[0-9]{1,10}$/D', $id ) ) {
			$id = (int) $id;
		}
		if ( ! is_int( $id ) || $id < 1 || $id > 2147483647 ) {
			return null;
		}
		return new self( TargetType::from( $type ), $id );
	}
}
