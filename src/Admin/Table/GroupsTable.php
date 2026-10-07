<?php
/**
 * The Groups list table.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Table;

use RevGuardPro\Admin\Presenter\RowPresenter;

defined( 'ABSPATH' ) || exit;

/**
 * Name, status and a summary of size and rotation.
 */
final class GroupsTable extends EntityTable {

	/**
	 * Columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'cb'      => '<input type="checkbox">',
			'name'    => __( 'Name', 'revguard-pro' ),
			'status'  => __( 'Status', 'revguard-pro' ),
			'members' => __( 'Ads', 'revguard-pro' ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object $item Group.
	 */
	protected function nameOf( object $item ): string {
		return (string) $item->name;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object $item        Group.
	 * @param string $column_name Column.
	 */
	protected function column_default( $item, $column_name ): string {
		return 'members' === $column_name ? esc_html( RowPresenter::group( $item ) ) : '';
	}
}
