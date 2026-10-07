<?php
/**
 * The Settings screen.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Screen;

use RevGuardPro\Admin\Form\SettingsForm;
use RevGuardPro\Admin\ScreenId;
use RevGuardPro\Admin\Support\AdminRequest;
use RevGuardPro\Admin\Support\NoticeCode;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * The five plugin settings. Validation lives in `SettingsForm`; enabling unfiltered HTML additionally needs the core
 * `unfiltered_html` capability.
 */
final class SettingsScreen implements ScreenInterface {

	/**
	 * Constructor.
	 *
	 * @param ScreenContext $ctx Shared services.
	 */
	public function __construct( private readonly ScreenContext $ctx ) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): ScreenId {
		return ScreenId::Settings;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param AdminRequest $request Request.
	 */
	public function handle( AdminRequest $request ): Outcome {
		$this->ctx->requireCapability( $this->id()->capability() );
		$current        = $this->ctx->settings->all();
		$can_unfiltered = $this->ctx->can( 'unfiltered_html' );

		if ( $request->isPost() && 'save' === $request->action() ) {
			$this->ctx->guard->authorize( 'rgp_save_settings', $this->id()->capability() );
			$result = SettingsForm::parse( $request->post, $current, $can_unfiltered );
			if ( ! $result->ok() || ! is_array( $result->entity ) ) {
				return $this->view( $request, $result->values, $result->errors, $can_unfiltered );
			}
			try {
				$this->ctx->settings->update( $result->entity );
			} catch ( Throwable ) {
				return $this->view( $request, $result->values, array( '_form' => 'storage' ), $can_unfiltered );
			}
			return Outcome::redirect( $this->ctx->urls->withNotice( $this->id(), NoticeCode::SettingsSaved ) );
		}
		return $this->view( $request, $current, array(), $can_unfiltered );
	}

	/**
	 * The settings view.
	 *
	 * @param AdminRequest         $request        Request.
	 * @param array<string,mixed>  $values         Values to show.
	 * @param array<string,string> $errors         Errors.
	 * @param bool                 $can_unfiltered Whether the user may enable unfiltered HTML.
	 */
	private function view( AdminRequest $request, array $values, array $errors, bool $can_unfiltered ): Outcome {
		return Outcome::render(
			array(
				'notice'         => NoticeCode::fromQuery( $request->query ),
				'values'         => $values,
				'errors'         => $errors,
				'can_unfiltered' => $can_unfiltered,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param AdminRequest        $request Request.
	 * @param array<string,mixed> $data    Data from handle().
	 */
	public function render( AdminRequest $request, array $data ): void {
		$this->ctx->view->render(
			'settings',
			$data + array(
				'screen' => $this->id(),
				'urls'   => $this->ctx->urls,
			)
		);
	}
}
