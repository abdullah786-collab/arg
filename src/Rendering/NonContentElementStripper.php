<?php
/**
 * Removes elements whose contents are code or fallback text, together with those contents.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rendering;

use DOMDocument;
use DOMElement;
use DOMNode;

defined( 'ABSPATH' ) || exit;

/**
 * A pre-pass for the allowlist filter. The allowlist removes a disallowed tag but keeps its text, so the body of a removed
 * script or style element would otherwise be shown to visitors as plain text. This class parses the markup with a real HTML
 * parser (never a regular expression over the markup) and drops those elements with their contents.
 *
 * It is a cleanup, not a security boundary: its output always goes through the allowlist filter afterwards, which stays the
 * only authority on what may be output. It only ever removes more, and when the parser is unavailable or fails the input is
 * returned unchanged for the filter to handle.
 */
final class NonContentElementStripper {

	/**
	 * Elements removed together with everything inside them.
	 *
	 * @var array<int,string>
	 */
	public const ELEMENTS = array( 'script', 'style', 'noscript', 'template', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'noembed', 'noframes', 'svg', 'math' );

	/**
	 * Removes the elements, or returns the input unchanged when there is nothing to remove or parsing is not possible.
	 *
	 * @param string $html Markup.
	 */
	public static function strip( string $html ): string {
		if ( '' === $html || ! str_contains( $html, '<' ) || ! class_exists( DOMDocument::class ) || ! self::mentionsAny( $html ) ) {
			return $html;
		}

		$previous = libxml_use_internal_errors( true );
		try {
			$doc    = new DOMDocument();
			$loaded = $doc->loadHTML( '<?xml encoding="UTF-8"><div id="rgp-root">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET );
			$root   = $loaded ? $doc->getElementById( 'rgp-root' ) : null;
			if ( ! $root instanceof DOMElement ) {
				return $html;
			}
			foreach ( self::ELEMENTS as $name ) {
				$found = array();
				foreach ( $doc->getElementsByTagName( $name ) as $element ) {
					$found[] = $element;
				}
				foreach ( $found as $element ) {
					$element->parentNode?->removeChild( $element ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOM API property.
				}
			}
			$out = '';
			foreach ( $root->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOM API property.
				$out .= self::serialize( $doc, $child );
			}
			return $out;
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
		}
	}

	/**
	 * Cheap check that avoids a parse (and any change to the markup) when no listed element is present.
	 *
	 * A miss here can only leave the allowlist's own text remnant behind; it never lets a tag through.
	 *
	 * @param string $html Markup.
	 */
	private static function mentionsAny( string $html ): bool {
		foreach ( self::ELEMENTS as $name ) {
			if ( false !== stripos( $html, '<' . $name ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Serializes one node.
	 *
	 * @param DOMDocument $doc  Document.
	 * @param DOMNode     $node Node.
	 */
	private static function serialize( DOMDocument $doc, DOMNode $node ): string {
		$markup = $doc->saveHTML( $node );
		return false === $markup ? '' : $markup;
	}
}
