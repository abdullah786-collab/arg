<?php
/**
 * The Groups screen.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Screen;

use RevGuardPro\Admin\Form\ErrorMessages;
use RevGuardPro\Admin\Form\FormResult;
use RevGuardPro\Admin\Form\GroupForm;
use RevGuardPro\Admin\Gateway;
use RevGuardPro\Admin\ScreenId;
use RevGuardPro\Admin\Support\AdminRequest;
use RevGuardPro\Domain\AdGroup;
use RevGuardPro\Domain\Exception\InvalidEntityException;
use RevGuardPro\Admin\Table\EntityTable;
use RevGuardPro\Admin\Table\GroupsTable;
use RevGuardPro\Domain\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Create, edit, activate, deactivate and delete groups of ads.
 */
final class GroupsScreen extends EntityScreen {

	/**
	 * {@inheritDoc}
	 */
	public function id(): ScreenId {
		return ScreenId::Groups;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function kind(): string {
		return 'group';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function gateway(): Gateway {
		return $this->ctx->groups;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param AdminRequest $request  Request.
	 * @param object|null  $existing Group being edited.
	 */
	protected function parse( AdminRequest $request, ?object $existing ): FormResult {
		$known = array();
		foreach ( $this->pool( $this->ctx->ads )['items'] as $ad ) {
			$known[ (int) $ad->id ] = (int) $ad->id;
		}
		return GroupForm::parse( $request->post, $existing instanceof AdGroup ? $existing : null, $known );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object $entity Group.
	 * @param Status $status Status.
	 */
	protected function withStatus( object $entity, Status $status ): object {
		return $entity instanceof AdGroup ? $entity->withStatus( $status ) : $entity;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param InvalidEntityException $error  Failure.
	 * @param FormResult             $result Parsed form.
	 */
	protected function saveErrors( InvalidEntityException $error, FormResult $result ): array {
		return 'members' === $error->field ? array( 'members' => ErrorMessages::code( $error ) ) : array( '_form' => 'storage' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object|null $existing Group being edited.
	 * @return array<string,mixed>
	 */
	protected function formData( ?object $existing ): array {
		$pool = $this->pool( $this->ctx->ads );
		return array(
			'entity'    => $existing instanceof AdGroup ? $existing : null,
			'ads'       => $pool['items'],
			'truncated' => $pool['truncated'],
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object $entity Group.
	 */
	protected function nameOf( object $entity ): string {
		return $entity instanceof AdGroup ? $entity->name : '';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Status|null $filter Status filter.
	 * @param int         $page   Page number.
	 */
	protected function table( ?Status $filter, int $page ): EntityTable {
		return new GroupsTable( $this->ctx, $this->gateway(), $this->id(), 'group', $filter, $page );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function copy(): array {
		return array(
			'title'       => __( 'Groups', 'revguard-pro' ),
			'new'         => __( 'Add new group', 'revguard-pro' ),
			'empty_title' => __( 'No groups yet', 'revguard-pro' ),
			'empty_text'  => __( 'A group rotates several ads in one spot, in order or by weight. Groups are optional: a placement can show a single ad.', 'revguard-pro' ),
		);
	}
}
