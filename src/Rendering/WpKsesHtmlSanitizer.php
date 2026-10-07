<?php
/**
 * Wp_kses-based HTML creative sanitizer.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Rendering;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitizes an HTML creative using WordPress's wp_kses() with a curated allowlist.
 * This is the only class in the Rendering package that calls WordPress functions.
 */
final class WpKsesHtmlSanitizer implements HtmlCreativeSanitizerInterface {

	/**
	 * Removes script-like elements with their contents, then filters the result with wp_kses().
	 *
	 * @param string $html Raw HTML creative.
	 */
	public function sanitize( string $html ): string {
		return wp_kses( NonContentElementStripper::strip( $html ), self::allowed(), array( 'http', 'https', 'mailto' ) );
	}

	/**
	 * Returns the allowlist for wp_kses().
	 *
	 * @return array<string,array<string,bool|array<string,bool>>>
	 */
	public static function allowed(): array {
		$data_star       = array( 'data-*' => true );
		$class_id        = array(
			'class' => true,
			'id'    => true,
		);
		$style_role_aria = array(
			'style'       => true,
			'role'        => true,
			'aria-label'  => true,
			'aria-hidden' => true,
		);
		$allowed         = array(
			'a'          => array_merge(
				$class_id,
				$data_star,
				array(
					'href'   => true,
					'title'  => true,
					'target' => true,
					'rel'    => true,
				)
			),
			'abbr'       => array(),
			'b'          => array(),
			'strong'     => array(),
			'em'         => array(),
			'i'          => array(),
			'small'      => array(),
			'br'         => array(),
			'p'          => array(),
			'span'       => array_merge( $class_id, $data_star, $style_role_aria ),
			'div'        => array_merge( $class_id, $data_star, $style_role_aria ),
			'h1'         => array(),
			'h2'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'h5'         => array(),
			'h6'         => array(),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'figure'     => array(),
			'figcaption' => array(),
			'picture'    => array(),
			'source'     => array(
				'srcset' => true,
				'sizes'  => true,
				'type'   => true,
				'media'  => true,
			),
			'img'        => array_merge(
				$data_star,
				array(
					'src'     => true,
					'srcset'  => true,
					'sizes'   => true,
					'alt'     => true,
					'width'   => true,
					'height'  => true,
					'loading' => true,
					'class'   => true,
					'style'   => true,
					'title'   => true,
				)
			),
			'ins'        => array(
				'class' => true,
				'style' => true,
				'title' => true,
			),
		);

		// Plain text and structure tags may carry styling hooks, which creatives routinely use.
		foreach ( array( 'abbr', 'b', 'strong', 'em', 'i', 'small', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'figure', 'figcaption', 'picture' ) as $tag ) {
			$allowed[ $tag ] = array(
				'class' => true,
				'style' => true,
				'title' => true,
			);
		}
		return $allowed;
	}
}
