<?php
/**
 * Shared behaviour of the Ads, Groups and Placements list tables.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Table;

use RevGuardPro\Admin\Gateway;
use RevGuardPro\Admin\Presenter\Labels;
use RevGuardPro\Admin\Screen\ScreenContext;
use RevGuardPro\Admin\ScreenId;
use RevGuardPro\Domain\Status;
use WP_List_Table;

defined( 'ABSPATH' ) || exit;

/**
 * A native `WP_List_Table`: status views with counts, bulk actions, a select box per row (each labelled), row actions,
 * pagination and a friendly empty message. Cells print through escaping functions only.
 */
abstract class EntityTable extends WP_List_Table {

	/**
	 * Constructor.
	 *
	 * @param ScreenContext $ctx     Shared services.
	 * @param Gateway       $gateway Entity gateway.
	 * @param ScreenId      $screen_id Owning screen.
	 * @param string        $kind    `ad`, `group` or `placement`.
	 * @param Status|null   $filter  Status filter.
	 * @param int           $page    Current page (1-based).
	 */
	public function __construct(
		protected readonly ScreenContext $ctx,
		protected readonly Gateway $gateway,
		protected readonly ScreenId $screen_id,
		protected readonly string $kind,
		protected readonly ?Status $filter,
		private readonly int $page
	) {
		parent::__construct(
			array(
				'singular' => 'rgp_' . $kind,
				'plural'   => 'rgp_' . $kind . 's',
				'ajax'     => false,
			)
		);
	}

	/**
	 * The cell for the name column. Each subclass prints its own entity name via `nameOf()`.
	 *
	 * @param object $item Entity.
	 */
	abstract protected function nameOf( object $item ): string;

	/**
	 * Loads the current page.
	 */
	public function prepare_items(): void {
		$this->_column_headers = array( $this->get_columns(), array(), array(), 'name' );
		$total                 = $this->gateway->count( $this->filter );
		$per_page              = ScreenContext::PER_PAGE;
		$pages                 = max( 1, (int) ceil( $total / $per_page ) );
		$page                  = min( max( 1, $this->page ), $pages );
		$this->items           = $this->gateway->list( $this->filter, $per_page, ( $page - 1 ) * $per_page );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => $pages,
			)
		);
	}

	/**
	 * Whether there is nothing at all (not just nothing in the current filter).
	 */
	public function isCompletelyEmpty(): bool {
		return 0 === $this->gateway->count( null );
	}

	/**
	 * Extra table classes.
	 *
	 * @return array<int,string>
	 */
	protected function get_table_classes(): array {
		return array( 'widefat', 'fixed', 'striped', 'rgp-table', 'table-view-list' );
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string,string>
	 */
	protected function get_bulk_actions(): array {
		return array(
			'activate'   => __( 'Activate', 'revguard-pro' ),
			'deactivate' => __( 'Deactivate', 'revguard-pro' ),
			'delete'     => __( 'Delete…', 'revguard-pro' ),
		);
	}

	/**
	 * Status links: All, Active, Inactive, each with its count.
	 *
	 * @return array<string,string>
	 */
	protected function get_views(): array {
		$views = array();
		foreach ( array(
			'all'      => array( null, __( 'All', 'revguard-pro' ) ),
			'active'   => array( Status::Active, __( 'Active', 'revguard-pro' ) ),
			'inactive' => array( Status::Inactive, __( 'Inactive', 'revguard-pro' ) ),
		) as $key => $definition ) {
			$status        = $definition[0];
			$current       = $status === $this->filter;
			$url           = $this->ctx->urls->screen( $this->screen_id, null === $status ? array() : array( 'status' => $status->value ) );
			$views[ $key ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
				esc_url( $url ),
				$current ? ' class="current" aria-current="page"' : '',
				esc_html( $definition[1] ),
				$this->gateway->count( $status )
			);
		}
		return $views;
	}

	/**
	 * The select box of a row, with a label for assistive technology.
	 *
	 * @param object $item Entity.
	 */
	protected function column_cb( $item ): string {
		$id = (int) $item->id;
		return sprintf(
			'<input type="checkbox" name="ids[]" value="%1$d" id="rgp-select-%1$d"><label class="screen-reader-text" for="rgp-select-%1$d">%2$s</label>',
			$id,
			/* translators: %s: item name. */
			esc_html( sprintf( __( 'Select %s', 'revguard-pro' ), $this->nameOf( $item ) ) )
		);
	}

	/**
	 * The name cell with its row actions.
	 *
	 * @param object $item Entity.
	 */
	protected function column_name( $item ): string {
		$id      = (int) $item->id;
		$active  = Status::Active === $item->status;
		$actions = array(
			'edit'   => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url(
					$this->ctx->urls->screen(
						$this->screen_id,
						array(
							'action' => 'edit',
							'id'     => $id,
						)
					)
				),
				esc_html__( 'Edit', 'revguard-pro' )
			),
			'status' => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url(
					wp_nonce_url(
						$this->ctx->urls->screen(
							$this->screen_id,
							array(
								'action' => $active ? 'deactivate' : 'activate',
								'id'     => $id,
							)
						),
						'rgp_status_' . $this->kind . '_' . $id
					)
				),
				$active ? esc_html__( 'Deactivate', 'revguard-pro' ) : esc_html__( 'Activate', 'revguard-pro' )
			),
			'delete' => sprintf(
				'<a href="%1$s" class="submitdelete">%2$s</a>',
				esc_url(
					$this->ctx->urls->screen(
						$this->screen_id,
						array(
							'action' => 'delete_confirm',
							'id'     => $id,
						)
					)
				),
				esc_html__( 'Delete…', 'revguard-pro' )
			),
		);
		return sprintf(
			'<strong><a class="row-title" href="%1$s">%2$s</a></strong>%3$s',
			esc_url(
				$this->ctx->urls->screen(
					$this->screen_id,
					array(
						'action' => 'edit',
						'id'     => $id,
					)
				)
			),
			esc_html( $this->nameOf( $item ) ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * The status cell: text, never colour alone.
	 *
	 * @param object $item Entity.
	 */
	protected function column_status( $item ): string {
		$active = Status::Active === $item->status;
		return sprintf( '<span class="rgp-badge rgp-badge--%1$s">%2$s</span>', $active ? 'active' : 'inactive', esc_html( Labels::status( $item->status ) ) );
	}

	/**
	 * Fallback cell.
	 *
	 * @param object $item        Entity.
	 * @param string $column_name Column.
	 */
	protected function column_default( $item, $column_name ): string {
		return '';
	}

	/**
	 * A caption for screen readers, printed just inside the table by core.
	 */
	public function print_table_description(): void {
		$caption = match ( $this->kind ) {
			'ad'    => __( 'Ads', 'revguard-pro' ),
			'group' => __( 'Groups', 'revguard-pro' ),
			default => __( 'Placements', 'revguard-pro' ),
		};
		printf( '<caption class="screen-reader-text">%s</caption>', esc_html( $caption ) );
	}

	/**
	 * Message for an empty filtered list.
	 */
	public function no_items(): void {
		esc_html_e( 'Nothing matches this view.', 'revguard-pro' );
	}
}
