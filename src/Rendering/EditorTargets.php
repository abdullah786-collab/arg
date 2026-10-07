<?php
/**
 * Data for the block editor target pickers.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rendering;

use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\AdGroup;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the id and name lists the editor script shows. Names only: creatives never leave the server.
 */
final class EditorTargets {

	/**
	 * Builds the lists.
	 *
	 * @param array<mixed> $ads    Ads.
	 * @param array<mixed> $groups Groups.
	 * @param int          $limit  Maximum entries per list (1 to 500).
	 * @return array{ads: array<array{id: int, name: string}>, groups: array<array{id: int, name: string}>}
	 */
	public static function build( array $ads, array $groups, int $limit = 200 ): array {
		$limit = max( 1, min( 500, $limit ) );
		return array(
			'ads'    => self::entries( $ads, Ad::class, $limit ),
			'groups' => self::entries( $groups, AdGroup::class, $limit ),
		);
	}

	/**
	 * Id and name pairs of the saved entities of a class.
	 *
	 * @param array<mixed> $items Items.
	 * @param string       $class_name Expected class.
	 * @param int          $limit Limit.
	 * @return array<array{id: int, name: string}>
	 */
	private static function entries( array $items, string $class_name, int $limit ): array {
		$out = array();
		foreach ( $items as $item ) {
			if ( count( $out ) >= $limit ) {
				break;
			}
			if ( $item instanceof $class_name && null !== $item->id ) {
				$out[] = array(
					'id'   => $item->id,
					'name' => $item->name,
				);
			}
		}
		return $out;
	}
}
