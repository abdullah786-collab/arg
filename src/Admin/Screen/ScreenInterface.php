<?php
/**
 * An admin screen.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Screen;

use RevGuardPro\Admin\ScreenId;
use RevGuardPro\Admin\Support\AdminRequest;

defined( 'ABSPATH' ) || exit;

/**
 * `handle()` runs before any output (it may redirect); `render()` prints the page from the data `handle()` returned.
 */
interface ScreenInterface {

	/**
	 * Which screen this is.
	 */
	public function id(): ScreenId;

	/**
	 * Authorises and processes the request.
	 *
	 * @param AdminRequest $request The request.
	 */
	public function handle( AdminRequest $request ): Outcome;

	/**
	 * Prints the page.
	 *
	 * @param AdminRequest        $request The request.
	 * @param array<string,mixed> $data    Data from handle().
	 */
	public function render( AdminRequest $request, array $data ): void;
}
