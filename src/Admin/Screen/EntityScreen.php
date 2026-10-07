<?php
/**
 * The shared list / form / status / bulk / delete flow of the Ads, Groups and Placements screens.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Screen;

use RevGuardPro\Admin\Form\FormResult;
use RevGuardPro\Admin\Gateway;
use RevGuardPro\Admin\Support\AdminRequest;
use RevGuardPro\Admin\Support\Input;
use RevGuardPro\Admin\Support\NoticeCode;
use RevGuardPro\Admin\Table\EntityTable;
use RevGuardPro\Domain\Exception\CorruptRecordException;
use RevGuardPro\Domain\Exception\EntityInUseException;
use RevGuardPro\Domain\Exception\EntityNotFoundException;
use RevGuardPro\Domain\Exception\InvalidEntityException;
use RevGuardPro\Domain\Exception\PersistenceException;
use RevGuardPro\Domain\Status;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Order of work for every state change: capability (403), then nonce (403), then strict input, then the domain, then a
 * redirect. A request that fails any step writes nothing. The three screens differ only in their form, their table and
 * the domain object they build; none of the rules live here.
 */
abstract class EntityScreen implements ScreenInterface {

	/**
	 * Constructor.
	 *
	 * @param ScreenContext $ctx Shared services.
	 */
	public function __construct( protected readonly ScreenContext $ctx ) {
	}

	/**
	 * Short singular word used in nonce actions: `ad`, `group`, `placement`.
	 */
	abstract protected function kind(): string;

	/**
	 * The repository gateway.
	 */
	abstract protected function gateway(): Gateway;

	/**
	 * Parses the form into a domain object or field errors.
	 *
	 * @param AdminRequest $request  Request.
	 * @param object|null  $existing Entity being edited.
	 */
	abstract protected function parse( AdminRequest $request, ?object $existing ): FormResult;

	/**
	 * A copy of the entity with another status.
	 *
	 * @param object $entity Entity.
	 * @param Status $status New status.
	 */
	abstract protected function withStatus( object $entity, Status $status ): object;

	/**
	 * Field errors for a domain failure raised while saving (for example the creative policy).
	 *
	 * @param InvalidEntityException $error  Failure.
	 * @param FormResult             $result Parsed form.
	 * @return array<string,string>
	 */
	abstract protected function saveErrors( InvalidEntityException $error, FormResult $result ): array;

	/**
	 * View data for the create/edit form.
	 *
	 * @param object|null $existing Entity being edited.
	 * @return array<string,mixed>
	 */
	abstract protected function formData( ?object $existing ): array;

	/**
	 * Display names for the confirmation screen.
	 *
	 * @param object $entity Entity.
	 */
	abstract protected function nameOf( object $entity ): string;

	/**
	 * {@inheritDoc}
	 *
	 * @param AdminRequest $request Request.
	 */
	public function handle( AdminRequest $request ): Outcome {
		$this->ctx->requireCapability( $this->id()->capability() );
		$action = $request->action();

		if ( $request->isPost() ) {
			return match ( $action ) {
				'save'   => $this->save( $request ),
				'bulk'   => $this->bulk( $request ),
				'delete' => $this->delete( $request ),
				default  => $this->listing( $request ),
			};
		}
		return match ( $action ) {
			'new'            => $this->form( null, $request ),
			'edit'           => $this->edit( $request ),
			'activate'       => $this->toggle( $request, Status::Active ),
			'deactivate'     => $this->toggle( $request, Status::Inactive ),
			'delete_confirm' => $this->confirm( $request ),
			default          => $this->listing( $request ),
		};
	}

	/**
	 * The list table for this screen.
	 *
	 * @param Status|null $filter Status filter.
	 * @param int         $page   Page number.
	 */
	abstract protected function table( ?Status $filter, int $page ): EntityTable;

	/**
	 * Words for the list header and empty state.
	 *
	 * @return array{title:string,new:string,empty_title:string,empty_text:string}
	 */
	abstract protected function copy(): array;

	/**
	 * {@inheritDoc}
	 *
	 * @param AdminRequest        $request Request.
	 * @param array<string,mixed> $data    Data from handle().
	 */
	public function render( AdminRequest $request, array $data ): void {
		$mode = in_array( $data['mode'] ?? '', array( 'list', 'form', 'confirm' ), true ) ? $data['mode'] : 'list';
		$vars = $data + array(
			'screen' => $this->id(),
			'urls'   => $this->ctx->urls,
			'kind'   => $this->kind(),
			'copy'   => $this->copy(),
		);
		if ( 'list' === $mode ) {
			if ( ! class_exists( 'WP_List_Table' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
			}
			$table = $this->table( $data['status'] ?? null, Input::int( $request->param( 'paged' ), 1, 100000 ) ?? 1 );
			$table->prepare_items();
			$vars['table']       = $table;
			$vars['empty']       = $table->isCompletelyEmpty();
			$vars['bulk_action'] = $this->ctx->urls->screen( $this->id(), array( 'action' => 'bulk' ) );
			$this->ctx->view->render( 'entity-list', $vars );
			return;
		}
		$this->ctx->view->render( 'confirm' === $mode ? 'entity-confirm' : $this->kind() . 's-form', $vars );
	}

	/**
	 * The nonce action used by the list form's bulk controls (WP_List_Table convention).
	 */
	public function bulkNonceAction(): string {
		return 'bulk-rgp_' . $this->kind() . 's';
	}

	/**
	 * The list.
	 *
	 * @param AdminRequest $request Request.
	 */
	protected function listing( AdminRequest $request ): Outcome {
		return Outcome::render(
			array(
				'mode'   => 'list',
				'notice' => NoticeCode::fromQuery( $request->query ),
				'status' => Input::enumCase( $request->param( 'status' ), Status::class ),
			)
		);
	}

	/**
	 * The create/edit form (also used to re-display after an error).
	 *
	 * @param object|null          $existing Entity being edited.
	 * @param AdminRequest         $request  Request.
	 * @param FormResult|null      $result   Failed parse, if any.
	 * @param array<string,string> $extra    Extra errors.
	 */
	protected function form( ?object $existing, AdminRequest $request, ?FormResult $result = null, array $extra = array() ): Outcome {
		return Outcome::render(
			array_merge(
				$this->formData( $existing ),
				array(
					'mode'   => 'form',
					'notice' => NoticeCode::fromQuery( $request->query ),
					'errors' => array_merge( null === $result ? array() : $result->errors, $extra ),
					'values' => null === $result ? null : $result->values,
				)
			)
		);
	}

	/**
	 * The edit form for an existing entity.
	 *
	 * @param AdminRequest $request Request.
	 */
	protected function edit( AdminRequest $request ): Outcome {
		$id = $request->id();
		if ( null === $id ) {
			return $this->notice( NoticeCode::NotFound );
		}
		try {
			$existing = $this->gateway()->find( $id );
		} catch ( Throwable ) {
			return $this->notice( NoticeCode::Failed );
		}
		return null === $existing ? $this->notice( NoticeCode::NotFound ) : $this->form( $existing, $request );
	}

	/**
	 * Saves the form.
	 *
	 * @param AdminRequest $request Request.
	 */
	protected function save( AdminRequest $request ): Outcome {
		$id = $request->id();
		$this->ctx->guard->authorize( 'rgp_save_' . $this->kind() . '_' . ( $id ?? 'new' ), $this->id()->capability() );

		$existing = null;
		if ( null !== $id ) {
			try {
				$existing = $this->gateway()->find( $id );
			} catch ( Throwable ) {
				return $this->notice( NoticeCode::Failed );
			}
			if ( null === $existing ) {
				return $this->notice( NoticeCode::NotFound );
			}
		}

		$result = $this->parse( $request, $existing );
		if ( ! $result->ok() || ! is_object( $result->entity ) ) {
			return $this->form( $existing, $request, $result );
		}
		try {
			$this->gateway()->save( $result->entity );
		} catch ( InvalidEntityException $error ) {
			return $this->form( $existing, $request, $result, $this->saveErrors( $error, $result ) );
		} catch ( EntityNotFoundException ) {
			return $this->notice( NoticeCode::NotFound );
		} catch ( PersistenceException | CorruptRecordException ) {
			return $this->form( $existing, $request, $result, array( '_form' => 'storage' ) );
		}
		return $this->notice( NoticeCode::Saved );
	}

	/**
	 * Activates or deactivates one entity (nonce-protected link).
	 *
	 * @param AdminRequest $request Request.
	 * @param Status       $status  Target status.
	 */
	protected function toggle( AdminRequest $request, Status $status ): Outcome {
		$id = $request->id();
		if ( null === $id ) {
			return $this->notice( NoticeCode::NotFound );
		}
		$this->ctx->guard->authorize( 'rgp_status_' . $this->kind() . '_' . $id, $this->id()->capability() );
		$code = $this->changeStatus( $id, $status );
		if ( null !== $code ) {
			return $this->notice( $code );
		}
		return $this->notice( Status::Active === $status ? NoticeCode::Activated : NoticeCode::Deactivated );
	}

	/**
	 * Changes one status; returns a failure notice or null on success.
	 *
	 * @param int    $id     Id.
	 * @param Status $status Status.
	 */
	private function changeStatus( int $id, Status $status ): ?NoticeCode {
		try {
			$entity = $this->gateway()->find( $id );
			if ( null === $entity ) {
				return NoticeCode::NotFound;
			}
			$this->gateway()->save( $this->withStatus( $entity, $status ) );
		} catch ( EntityNotFoundException ) {
			return NoticeCode::NotFound;
		} catch ( Throwable ) {
			return NoticeCode::Failed;
		}
		return null;
	}

	/**
	 * The list form's bulk actions. Delete only shows the confirmation screen; nothing is written for it here.
	 *
	 * @param AdminRequest $request Request.
	 */
	protected function bulk( AdminRequest $request ): Outcome {
		$this->ctx->guard->authorize( $this->bulkNonceAction(), $this->id()->capability() );
		$operation = Input::text( $request->input( 'action' ), 20 );
		if ( '' === $operation || '-1' === $operation ) {
			$operation = Input::text( $request->input( 'action2' ), 20 );
		}
		$ids = Input::ids( $request->input( 'ids' ) );
		if ( array() === $ids || ! in_array( $operation, array( 'activate', 'deactivate', 'delete' ), true ) ) {
			return $this->notice( NoticeCode::BulkNone );
		}
		if ( 'delete' === $operation ) {
			return $this->confirmIds( $ids );
		}
		$status  = 'activate' === $operation ? Status::Active : Status::Inactive;
		$changed = 0;
		foreach ( $ids as $id ) {
			if ( null === $this->changeStatus( $id, $status ) ) {
				++$changed;
			}
		}
		return 0 === $changed ? $this->notice( NoticeCode::BulkNone ) : $this->notice( NoticeCode::BulkDone, array( 'n' => $changed ) );
	}

	/**
	 * The delete confirmation for one entity.
	 *
	 * @param AdminRequest $request Request.
	 */
	protected function confirm( AdminRequest $request ): Outcome {
		$id = $request->id();
		return null === $id ? $this->notice( NoticeCode::NotFound ) : $this->confirmIds( array( $id ) );
	}

	/**
	 * Builds the confirmation view for some ids.
	 *
	 * @param array<int,int> $ids Ids.
	 */
	private function confirmIds( array $ids ): Outcome {
		$items = array();
		foreach ( $ids as $id ) {
			try {
				$entity = $this->gateway()->find( $id );
			} catch ( Throwable ) {
				$entity = null;
			}
			if ( null !== $entity ) {
				$items[ $id ] = $this->nameOf( $entity );
			}
		}
		return array() === $items ? $this->notice( NoticeCode::NotFound ) : Outcome::render(
			array(
				'mode'  => 'confirm',
				'items' => $items,
			)
		);
	}

	/**
	 * Deletes the confirmed entities.
	 *
	 * @param AdminRequest $request Request.
	 */
	protected function delete( AdminRequest $request ): Outcome {
		$this->ctx->guard->authorize( 'rgp_delete_' . $this->kind(), $this->id()->capability() );
		$ids     = Input::ids( $request->input( 'ids' ) );
		$deleted = 0;
		$blocked = 0;
		$missing = 0;
		foreach ( $ids as $id ) {
			try {
				$this->gateway()->delete( $id ) ? ++$deleted : ++$missing;
			} catch ( EntityInUseException ) {
				++$blocked;
			} catch ( Throwable ) {
				continue;
			}
		}
		if ( 1 === $deleted && 1 === count( $ids ) ) {
			return $this->notice( NoticeCode::Deleted );
		}
		if ( $deleted > 0 ) {
			return $this->notice( NoticeCode::BulkDone, array( 'n' => $deleted ) );
		}
		if ( $blocked > 0 ) {
			return $this->notice( NoticeCode::InUse );
		}
		return $this->notice( array() === $ids ? NoticeCode::BulkNone : ( $missing > 0 ? NoticeCode::NotFound : NoticeCode::Failed ) );
	}

	/**
	 * Up to 500 entities (the repositories' page cap) for pickers, and whether there are more.
	 *
	 * @param Gateway $gateway Gateway.
	 * @return array{items:array<int,object>,truncated:bool}
	 */
	protected function pool( Gateway $gateway ): array {
		try {
			$items = $gateway->list( null, 500, 0 );
			return array(
				'items'     => $items,
				'truncated' => count( $items ) >= 500 && $gateway->count( null ) > 500,
			);
		} catch ( Throwable ) {
			return array(
				'items'     => array(),
				'truncated' => false,
			);
		}
	}

	/**
	 * A redirect back to this screen's list with a notice.
	 *
	 * @param NoticeCode        $notice Notice.
	 * @param array<string,int> $counts Counts.
	 */
	protected function notice( NoticeCode $notice, array $counts = array() ): Outcome {
		return Outcome::redirect( $this->ctx->urls->withNotice( $this->id(), $notice, $counts ) );
	}
}
