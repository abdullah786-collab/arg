<?php
/**
 * The Ads list table.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Table;

use RevGuardPro\Admin\Presenter\RowPresenter;

defined( 'ABSPATH' ) || exit;

/**
 * Name, status, type, schedule and a one-word targeting summary.
 */
final class AdsTable extends EntityTable {

	/**
	 * Columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'cb'        => '<input type="checkbox">',
			'name'      => __( 'Name', 'revguard-pro' ),
			'status'    => __( 'Status', 'revguard-pro' ),
			'schedule'  => __( 'Schedule', 'revguard-pro' ),
			'targeting' => __( 'Shown to', 'revguard-pro' ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object $item Ad.
	 */
	protected function nameOf( object $item ): string {
		return (string) $item->name;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object $item        Ad.
	 * @param string $column_name Column.
	 */
	protected function column_default( $item, $column_name ): string {
		return match ( $column_name ) {
			'schedule'  => esc_html( RowPresenter::schedule( $item, $this->ctx->zone(), $this->ctx->clock->now() ) ),
			'targeting' => esc_html( RowPresenter::targeting( $item->targeting ) ),
			default     => '',
		};
	}
}
