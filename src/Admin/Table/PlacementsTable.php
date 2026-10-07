<?php
/**
 * The Placements list table.
 *
 * @package RevGuardPro
 */

declare(strict_types=1);

namespace RevGuardPro\Admin\Table;

use RevGuardPro\Admin\Presenter\RowPresenter;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Name, status, position, target and priority. Target names come from two bounded reads, never one query per row.
 */
final class PlacementsTable extends EntityTable {

	/**
	 * Names of ads and groups, loaded once.
	 *
	 * @var array{0:array<int,string>,1:array<int,string>}|null
	 */
	private ?array $names = null;

	/**
	 * Columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'cb'       => '<input type="checkbox">',
			'name'     => __( 'Name', 'revguard-pro' ),
			'status'   => __( 'Status', 'revguard-pro' ),
			'position' => __( 'Position', 'revguard-pro' ),
			'target'   => __( 'Shows', 'revguard-pro' ),
			'priority' => __( 'Priority', 'revguard-pro' ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object $item Placement.
	 */
	protected function nameOf( object $item ): string {
		return (string) $item->name;
	}

	/**
	 * Ad and group names by id.
	 *
	 * @return array{0:array<int,string>,1:array<int,string>}
	 */
	private function names(): array {
		if ( null === $this->names ) {
			$ads    = array();
			$groups = array();
			try {
				foreach ( $this->ctx->ads->list( null, 500, 0 ) as $ad ) {
					$ads[ (int) $ad->id ] = (string) $ad->name;
				}
				foreach ( $this->ctx->groups->list( null, 500, 0 ) as $group ) {
					$groups[ (int) $group->id ] = (string) $group->name;
				}
			} catch ( Throwable ) {
				$ads = array();
			}
			$this->names = array( $ads, $groups );
		}
		return $this->names;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object $item        Placement.
	 * @param string $column_name Column.
	 */
	protected function column_default( $item, $column_name ): string {
		return match ( $column_name ) {
			'position' => esc_html( RowPresenter::position( $item ) ),
			'target'   => esc_html( RowPresenter::target( $item, $this->names()[0], $this->names()[1] ) ),
			'priority' => esc_html( (string) $item->priority ),
			default    => '',
		};
	}
}
