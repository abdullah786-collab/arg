<?php
/**
 * Registers the RevGuard Pro admin menu and runs the screens.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin;

use Closure;
use RevGuardPro\Admin\Screen\ScreenInterface;
use RevGuardPro\Admin\Support\AdminRequest;

defined( 'ABSPATH' ) || exit;

/**
 * One top-level menu on the site admin (never the network admin). The menu lists only the screens the user may open, and
 * each screen checks its own capability again when it runs. A screen is processed on WordPress's `load-<page>` hook,
 * before any output, so a successful action can redirect cleanly.
 */
final class Menu {

	/**
	 * Hook suffix to screen.
	 *
	 * @var array<string,ScreenId>
	 */
	private array $suffixes = array();

	/**
	 * Request and view data prepared by `load-<page>`.
	 *
	 * @var array<string,array{0:AdminRequest,1:array<string,mixed>}>
	 */
	private array $pending = array();

	/**
	 * Constructor.
	 *
	 * @param Closure $screens Closure(ScreenId): ScreenInterface, builds a screen on demand.
	 * @param Closure $can     Closure(string $capability): bool.
	 */
	public function __construct( private readonly Closure $screens, private readonly Closure $can ) {
	}

	/**
	 * The screens a user may open, in menu order.
	 *
	 * @param Closure $can Closure(string $capability): bool.
	 * @return array<int,ScreenId>
	 */
	public static function accessible( Closure $can ): array {
		return array_values( array_filter( ScreenId::all(), static fn( ScreenId $screen ): bool => (bool) $can( $screen->capability() ) ) );
	}

	/**
	 * Adds the menu. Runs on `admin_menu`.
	 */
	public function register(): void {
		$screens = self::accessible( $this->can );
		if ( array() === $screens ) {
			return;
		}
		$top    = $screens[0];
		$suffix = add_menu_page(
			__( 'RevGuard Pro', 'revguard-pro' ),
			__( 'RevGuard Pro', 'revguard-pro' ),
			$top->capability(),
			$top->value,
			fn() => $this->page( $top ),
			'dashicons-megaphone',
			58.3
		);
		$this->hook( $suffix, $top );
		foreach ( $screens as $screen ) {
			// The first submenu shares the top-level slug and so the same page hook; giving it a callback too would print the screen twice.
			$callback = $screen === $top ? '' : fn() => $this->page( $screen );
			$submenu  = add_submenu_page( $top->value, $screen->title(), $screen->title(), $screen->capability(), $screen->value, $callback );
			if ( $screen !== $top ) {
				$this->hook( $submenu, $screen );
			}
		}
	}

	/**
	 * Whether a hook suffix belongs to one of our screens (used to load assets only there).
	 *
	 * @param string $suffix Admin page hook suffix.
	 */
	public function owns( string $suffix ): bool {
		return isset( $this->suffixes[ $suffix ] );
	}

	/**
	 * Processes a screen before output: redirects after a successful action, otherwise keeps the data for rendering.
	 *
	 * @param ScreenId $id Screen.
	 */
	public function load( ScreenId $id ): void {
		$request = AdminRequest::fromGlobals();
		$outcome = $this->screen( $id )->handle( $request );
		if ( null !== $outcome->redirect ) {
			wp_safe_redirect( $outcome->redirect );
			exit;
		}
		$this->pending[ $id->value ] = array( $request, $outcome->data );
	}

	/**
	 * Prints a screen.
	 *
	 * @param ScreenId $id Screen.
	 */
	public function page( ScreenId $id ): void {
		if ( ! isset( $this->pending[ $id->value ] ) ) {
			$this->load( $id );
		}
		list( $request, $data ) = $this->pending[ $id->value ];
		$this->screen( $id )->render( $request, $data );
	}

	/**
	 * Remembers a hook suffix and attaches the pre-output handler.
	 *
	 * @param string|false $suffix Hook suffix returned by WordPress.
	 * @param ScreenId     $screen Screen.
	 */
	private function hook( string|false $suffix, ScreenId $screen ): void {
		if ( false === $suffix ) {
			return;
		}
		$this->suffixes[ $suffix ] = $screen;
		add_action( 'load-' . $suffix, fn() => $this->load( $screen ) );
	}

	/**
	 * Builds the screen.
	 *
	 * @param ScreenId $id Screen.
	 */
	private function screen( ScreenId $id ): ScreenInterface {
		return ( $this->screens )( $id );
	}
}
