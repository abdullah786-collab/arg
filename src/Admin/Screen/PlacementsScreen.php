<?php
/**
 * The Placements screen.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Screen;

use RevGuardPro\Admin\Form\ErrorMessages;
use RevGuardPro\Admin\Form\FormResult;
use RevGuardPro\Admin\Form\PlacementForm;
use RevGuardPro\Admin\Gateway;
use RevGuardPro\Admin\ScreenId;
use RevGuardPro\Admin\Support\AdminRequest;
use RevGuardPro\Domain\Exception\InvalidEntityException;
use RevGuardPro\Domain\Placement;
use RevGuardPro\Admin\Table\EntityTable;
use RevGuardPro\Admin\Table\PlacementsTable;
use RevGuardPro\Domain\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Create, edit, activate, deactivate and delete placements. Targeting is preserved on every edit and shown read-only.
 */
final class PlacementsScreen extends EntityScreen {

	/**
	 * {@inheritDoc}
	 */
	public function id(): ScreenId {
		return ScreenId::Placements;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function kind(): string {
		return 'placement';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function gateway(): Gateway {
		return $this->ctx->placements;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param AdminRequest $request  Request.
	 * @param object|null  $existing Placement being edited.
	 */
	protected function parse( AdminRequest $request, ?object $existing ): FormResult {
		$ads    = array();
		$groups = array();
		foreach ( $this->pool( $this->ctx->ads )['items'] as $ad ) {
			$ads[ (int) $ad->id ] = (int) $ad->id;
		}
		foreach ( $this->pool( $this->ctx->groups )['items'] as $group ) {
			$groups[ (int) $group->id ] = (int) $group->id;
		}
		return PlacementForm::parse( $request->post, $existing instanceof Placement ? $existing : null, $ads, $groups );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object $entity Placement.
	 * @param Status $status Status.
	 */
	protected function withStatus( object $entity, Status $status ): object {
		return $entity instanceof Placement ? $entity->withStatus( $status ) : $entity;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param InvalidEntityException $error  Failure.
	 * @param FormResult             $result Parsed form.
	 */
	protected function saveErrors( InvalidEntityException $error, FormResult $result ): array {
		return 'target' === $error->field ? array( 'target' => ErrorMessages::code( $error ) ) : array( '_form' => 'storage' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object|null $existing Placement being edited.
	 * @return array<string,mixed>
	 */
	protected function formData( ?object $existing ): array {
		$ads    = $this->pool( $this->ctx->ads );
		$groups = $this->pool( $this->ctx->groups );
		return array(
			'entity'    => $existing instanceof Placement ? $existing : null,
			'ads'       => $ads['items'],
			'groups'    => $groups['items'],
			'truncated' => $ads['truncated'] || $groups['truncated'],
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object $entity Placement.
	 */
	protected function nameOf( object $entity ): string {
		return $entity instanceof Placement ? $entity->name : '';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Status|null $filter Status filter.
	 * @param int         $page   Page number.
	 */
	protected function table( ?Status $filter, int $page ): EntityTable {
		return new PlacementsTable( $this->ctx, $this->gateway(), $this->id(), 'placement', $filter, $page );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function copy(): array {
		return array(
			'title'       => __( 'Placements', 'revguard-pro' ),
			'new'         => __( 'Add new placement', 'revguard-pro' ),
			'empty_title' => __( 'No placements yet', 'revguard-pro' ),
			'empty_text'  => __( 'A placement decides where an ad or group appears in your posts, for example after the third paragraph. You can also use the shortcode or the block.', 'revguard-pro' ),
		);
	}
}
