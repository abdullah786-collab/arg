<?php
/**
 * The Ads screen.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Screen;

use RevGuardPro\Admin\Form\AdForm;
use RevGuardPro\Admin\Form\FormResult;
use RevGuardPro\Admin\Gateway;
use RevGuardPro\Admin\ScreenId;
use RevGuardPro\Admin\Support\AdminRequest;
use RevGuardPro\Domain\Ad;
use RevGuardPro\Domain\CreativeType;
use RevGuardPro\Domain\Exception\InvalidEntityException;
use RevGuardPro\Admin\Table\EntityTable;
use RevGuardPro\Admin\Table\AdsTable;
use RevGuardPro\Domain\Status;
use RevGuardPro\Admin\Form\ErrorMessages;

defined( 'ABSPATH' ) || exit;

/**
 * Create, edit, activate, deactivate and delete ads. Targeting is preserved on every edit and shown read-only.
 */
final class AdsScreen extends EntityScreen {

	/**
	 * {@inheritDoc}
	 */
	public function id(): ScreenId {
		return ScreenId::Ads;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function kind(): string {
		return 'ad';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function gateway(): Gateway {
		return $this->ctx->ads;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param AdminRequest $request  Request.
	 * @param object|null  $existing Ad being edited.
	 */
	protected function parse( AdminRequest $request, ?object $existing ): FormResult {
		return AdForm::parse( $request->post, $existing instanceof Ad ? $existing : null, $this->ctx->zone() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object $entity Ad.
	 * @param Status $status Status.
	 */
	protected function withStatus( object $entity, Status $status ): object {
		return $entity instanceof Ad ? $entity->withStatus( $status ) : $entity;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param InvalidEntityException $error  Failure.
	 * @param FormResult             $result Parsed form.
	 */
	protected function saveErrors( InvalidEntityException $error, FormResult $result ): array {
		if ( 'creative' === $error->field ) {
			return array( 'creative' => ErrorMessages::code( $error ) );
		}
		return array( '_form' => 'storage' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object|null $existing Ad being edited.
	 * @return array<string,mixed>
	 */
	protected function formData( ?object $existing ): array {
		return array(
			'entity' => $existing instanceof Ad ? $existing : null,
			'zone'   => $this->ctx->zone(),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object $entity Ad.
	 */
	protected function nameOf( object $entity ): string {
		return $entity instanceof Ad ? $entity->name : '';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Status|null $filter Status filter.
	 * @param int         $page   Page number.
	 */
	protected function table( ?Status $filter, int $page ): EntityTable {
		return new AdsTable( $this->ctx, $this->gateway(), $this->id(), 'ad', $filter, $page );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function copy(): array {
		return array(
			'title'       => __( 'Ads', 'revguard-pro' ),
			'new'         => __( 'Add new ad', 'revguard-pro' ),
			'empty_title' => __( 'No ads yet', 'revguard-pro' ),
			'empty_text'  => __( 'An ad is the code you want to show: an ad unit code or your own HTML. Add one, then choose where it appears with a placement.', 'revguard-pro' ),
		);
	}
}
