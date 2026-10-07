<?php
/**
 * Applies planned insertions to content.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Pure string builder (equal offsets keep their given order: uasort is stable on PHP 8). Everything outside the inserted marker pairs stays byte-identical to the input.
 * Each insertion is wrapped in `<!-- rgp:pID -->` ... `<!-- /rgp:pID -->`; marker look-alikes inside the markup
 * are neutralized so a creative can never forge or close a wrapper.
 */
final class InsertionApplier {

	/**
	 * Builds the new content in one pass.
	 *
	 * @param string           $content    Original content.
	 * @param array<Insertion> $insertions Insertions; those with an out-of-range offset are ignored.
	 */
	public function apply( string $content, array $insertions ): string {
		$length = strlen( $content );
		$valid  = array();
		foreach ( $insertions as $order => $insertion ) {
			if ( $insertion->offset >= 0 && $insertion->offset <= $length ) {
				$valid[ $order ] = $insertion;
			}
		}
		uasort(
			$valid,
			static fn( Insertion $a, Insertion $b ): int => $a->offset <=> $b->offset
		);

		$out  = '';
		$from = 0;
		foreach ( $valid as $insertion ) {
			$out .= substr( $content, $from, $insertion->offset - $from ) . self::wrap( $insertion );
			$from = $insertion->offset;
		}
		return $out . substr( $content, $from );
	}

	/**
	 * The insertion wrapped in its marker pair.
	 *
	 * @param Insertion $insertion Insertion.
	 */
	public static function wrap( Insertion $insertion ): string {
		$markup = preg_replace( '/<!--(\s*\/?)rgp:p(\d)/i', '<!--$1rgp_:p$2', $insertion->markup ) ?? '';
		return '<!-- rgp:p' . $insertion->placement_id . ' -->' . $markup . '<!-- /rgp:p' . $insertion->placement_id . ' -->';
	}
}
