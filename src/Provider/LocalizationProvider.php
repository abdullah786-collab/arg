<?php
/**
 * Translation loading.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Provider;

use RevGuardPro\Container;
use RevGuardPro\Contracts\ServiceProviderInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the plugin text domain from the bundled languages folder and wires the block editor script's translations.
 */
final class LocalizationProvider implements ServiceProviderInterface {

	public const DOMAIN              = 'revguard-pro';
	public const BLOCK_SCRIPT_HANDLE = 'revguard-pro-ad-editor-script';

	/**
	 * Constructor.
	 *
	 * @param string $basename Plugin basename (folder/file.php).
	 */
	public function __construct( private readonly string $basename ) {
	}

	/**
	 * Registers nothing; this provider only hooks WordPress.
	 *
	 * @param Container $container Service container.
	 */
	public function register( Container $container ): void {
	}

	/**
	 * Hooks translation loading on `init` (after core is ready, as WordPress 6.7+ expects).
	 *
	 * @param Container $container Service container.
	 */
	public function boot( Container $container ): void {
		add_action( 'init', array( $this, 'loadTextDomain' ), 1 );
		// After the block is registered (default priority), so its editor script handle exists.
		add_action( 'init', array( $this, 'wireBlockScript' ), 20 );
	}

	/**
	 * Loads the bundled translation files for the plugin text domain.
	 */
	public function loadTextDomain(): void {
		load_plugin_textdomain( self::DOMAIN, false, dirname( $this->basename ) . '/languages' );
	}

	/**
	 * Tells WordPress where the block editor script's JSON translations live.
	 */
	public function wireBlockScript(): void {
		if ( wp_script_is( self::BLOCK_SCRIPT_HANDLE, 'registered' ) ) {
			wp_set_script_translations( self::BLOCK_SCRIPT_HANDLE, self::DOMAIN, WP_PLUGIN_DIR . '/' . dirname( $this->basename ) . '/languages' );
		}
	}
}
