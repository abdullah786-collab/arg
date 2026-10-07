<?php
/**
 * Delete confirmation shared by Ads, Groups and Placements.
 *
 * @package RevGuardPro
 *
 * @var array<string,mixed> $data Template data.
 */

use RevGuardPro\Admin\View\Chrome;

defined( 'ABSPATH' ) || exit;

$rgp_items = $data['items'];
$rgp_count = count( $rgp_items );

Chrome::open( $data['copy']['title'] );
?>
<form method="post" action="<?php echo esc_url( $data['urls']->screen( $data['screen'], array( 'action' => 'delete' ) ) ); ?>" class="rgp-confirm">
	<?php wp_nonce_field( 'rgp_delete_' . $data['kind'] ); ?>
	<h2>
		<?php
		/* translators: %d: number of items about to be deleted. */
		echo esc_html( sprintf( _n( 'Delete this item?', 'Delete these %d items?', $rgp_count, 'revguard-pro' ), $rgp_count ) );
		?>
	</h2>
	<ul class="rgp-confirm__list">
		<?php foreach ( $rgp_items as $rgp_id => $rgp_name ) : ?>
			<li>
				<?php echo esc_html( $rgp_name ); ?>
				<input type="hidden" name="ids[]" value="<?php echo esc_attr( (string) $rgp_id ); ?>">
			</li>
		<?php endforeach; ?>
	</ul>
	<p><?php esc_html_e( 'This cannot be undone. Anything that is still in use is kept and you will be told.', 'revguard-pro' ); ?></p>
	<p>
		<button type="submit" class="button button-primary rgp-danger"><?php esc_html_e( 'Delete permanently', 'revguard-pro' ); ?></button>
		<a class="button" href="<?php echo esc_url( $data['urls']->screen( $data['screen'] ) ); ?>"><?php esc_html_e( 'Cancel', 'revguard-pro' ); ?></a>
	</p>
</form>
<?php
Chrome::close();
