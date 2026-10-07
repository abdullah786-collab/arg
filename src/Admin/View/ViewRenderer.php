<?php
/**
 * Renders admin templates.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\View;

defined( 'ABSPATH' ) || exit;

/**
 * Includes a template from the plugin's own `views/admin` directory. The template name is checked against a strict
 * pattern and the resolved file must live inside that directory, so no request value can select a file.
 */
final class ViewRenderer {

	/**
	 * Constructor.
	 *
	 * @param string $directory Absolute path of the templates directory.
	 */
	public function __construct( private readonly string $directory ) {
	}

	/**
	 * Prints a template.
	 *
	 * @param string              $template Template name without extension, e.g. `ads-list`.
	 * @param array<string,mixed> $vars     Variables for the template.
	 */
	public function render( string $template, array $vars = array() ): void {
		$file = $this->resolve( $template );
		if ( null === $file ) {
			return;
		}
		( static function ( string $rgp_file, array $rgp_vars ): void {
			// Template variables are passed explicitly and namespaced to one array to avoid colliding with the scope.
			$data = $rgp_vars;
			include $rgp_file;
		} )( $file, $vars );
	}

	/**
	 * The template file, or null when the name is not a plain template name or the file is missing.
	 *
	 * @param string $template Template name.
	 */
	public function resolve( string $template ): ?string {
		if ( 1 !== preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $template ) ) {
			return null;
		}
		$file = $this->directory . '/' . $template . '.php';
		$real = realpath( $file );
		$base = realpath( $this->directory );
		if ( false === $real || false === $base || ! str_starts_with( $real, $base . DIRECTORY_SEPARATOR ) ) {
			return null;
		}
		return $real;
	}
}
