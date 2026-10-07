<?php
/**
 * The frame shared by every admin screen.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\View;

defined( 'ABSPATH' ) || exit;

/**
 * One `h1` per screen, WordPress's own header-end marker (so core notices land below it), then our notice. Sections use
 * `h2` headings bound to their region.
 */
final class Chrome {

	/**
	 * Opens the page.
	 *
	 * @param string                                 $title   Screen title (the only `h1`).
	 * @param array{type:string,message:string}|null $notice  Notice from the fixed vocabulary.
	 * @param array<string,string>                   $actions Link label to URL (title actions such as "Add new").
	 */
	public static function open( string $title, ?array $notice = null, array $actions = array() ): void {
		echo '<div class="wrap rgp-admin">';
		printf( '<h1 class="wp-heading-inline">%s</h1>', esc_html( $title ) );
		foreach ( $actions as $label => $url ) {
			printf( ' <a class="page-title-action" href="%1$s">%2$s</a>', esc_url( $url ), esc_html( (string) $label ) );
		}
		echo '<hr class="wp-header-end">';
		Fields::notice( $notice );
	}

	/**
	 * Closes the page.
	 */
	public static function close(): void {
		echo '</div>';
	}

	/**
	 * Opens a titled section.
	 *
	 * @param string $id      Section id (without prefix).
	 * @param string $heading Visible `h2`.
	 * @param string $class   Extra class.
	 */
	public static function section( string $id, string $heading, string $class = '' ): void { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.classFound -- Mirrors the HTML attribute.
		printf(
			'<section class="rgp-card %1$s" aria-labelledby="%2$s"><h2 id="%2$s">%3$s</h2>',
			esc_attr( $class ),
			esc_attr( Fields::dom( $id ) ),
			esc_html( $heading )
		);
	}

	/**
	 * Closes a section.
	 */
	public static function endSection(): void {
		echo '</section>';
	}
}
