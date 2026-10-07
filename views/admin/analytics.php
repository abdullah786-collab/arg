<?php
/**
 * Analytics.
 *
 * @package RevGuardPro
 *
 * @var array<string,mixed> $data Template data.
 */

use RevGuardPro\Admin\ScreenId;
use RevGuardPro\Admin\View\Chrome;

defined( 'ABSPATH' ) || exit;

$rgp_urls   = $data['urls'];
$rgp_report = $data['report'];

Chrome::open( __( 'Analytics', 'revguard-pro' ), $data['notice'] ?? null );
?>
<nav class="rgp-ranges" aria-label="<?php esc_attr_e( 'Time range', 'revguard-pro' ); ?>">
	<ul>
		<?php foreach ( array( 7, 30, 90 ) as $rgp_days ) : ?>
			<li>
				<a href="<?php echo esc_url( $rgp_urls->screen( ScreenId::Analytics, array( 'range' => $rgp_days ) ) ); ?>"<?php echo $rgp_days === $data['days'] ? ' aria-current="page" class="current"' : ''; ?>>
					<?php
					/* translators: %d: number of days. */
					echo esc_html( sprintf( __( 'Last %d days', 'revguard-pro' ), $rgp_days ) );
					?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>

<?php if ( ! $data['enabled'] ) : ?>
	<div class="notice notice-info inline"><p><?php esc_html_e( 'Counting is turned off in Settings. Numbers recorded earlier are still shown.', 'revguard-pro' ); ?></p></div>
<?php endif; ?>

<?php if ( null === $rgp_report ) : ?>
	<div class="notice notice-error inline" role="alert"><p><?php esc_html_e( 'The numbers could not be loaded right now. Nothing has been lost; please try again.', 'revguard-pro' ); ?></p></div>
<?php elseif ( 0 === $rgp_report['total'] ) : ?>
	<div class="rgp-empty">
		<h2><?php esc_html_e( 'No impressions in this period', 'revguard-pro' ); ?></h2>
		<p><?php esc_html_e( 'An impression is counted when an ad is shown on a page your visitors load. Nothing has been recorded for this range yet.', 'revguard-pro' ); ?></p>
	</div>
<?php else : ?>
	<?php Chrome::section( 'total', __( 'Total', 'revguard-pro' ) ); ?>
		<p class="rgp-big"><?php echo esc_html( number_format_i18n( $rgp_report['total'] ) ); ?></p>
	<?php Chrome::endSection(); ?>

	<?php Chrome::section( 'daily', __( 'Impressions per day', 'revguard-pro' ) ); ?>
		<?php $rgp_max = max( 1, max( $rgp_report['daily'] ) ); ?>
		<div class="rgp-table-wrap">
			<table class="widefat striped rgp-table">
				<caption class="screen-reader-text"><?php esc_html_e( 'Impressions for each day, oldest first', 'revguard-pro' ); ?></caption>
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'Date (UTC)', 'revguard-pro' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Impressions', 'revguard-pro' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Relative size', 'revguard-pro' ); ?></span></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rgp_report['daily'] as $rgp_day => $rgp_count ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( (string) $rgp_day ); ?></th>
						<td><?php echo esc_html( number_format_i18n( $rgp_count ) ); ?></td>
						<td><meter min="0" max="<?php echo esc_attr( (string) $rgp_max ); ?>" value="<?php echo esc_attr( (string) $rgp_count ); ?>" aria-hidden="true"><?php echo esc_html( (string) $rgp_count ); ?></meter></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php Chrome::endSection(); ?>

	<?php Chrome::section( 'top', __( 'Top ads', 'revguard-pro' ) ); ?>
		<div class="rgp-table-wrap">
			<table class="widefat striped rgp-table">
				<caption class="screen-reader-text"><?php esc_html_e( 'Ads with the most impressions in this period', 'revguard-pro' ); ?></caption>
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'Ad', 'revguard-pro' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Impressions', 'revguard-pro' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rgp_report['top'] as $rgp_row ) : ?>
					<tr>
						<th scope="row">
							<?php if ( $rgp_row->deleted ) : ?>
								<?php
								/* translators: %d: ad id. */
								echo esc_html( sprintf( __( 'Deleted ad #%d', 'revguard-pro' ), $rgp_row->ad_id ) );
								?>
							<?php elseif ( $data['can_edit'] ) : ?>
								<a href="
								<?php
								echo esc_url(
									$rgp_urls->screen(
										ScreenId::Ads,
										array(
											'action' => 'edit',
											'id'     => $rgp_row->ad_id,
										)
									)
								);
								?>
											"><?php echo esc_html( (string) $rgp_row->name ); ?></a>
							<?php else : ?>
								<?php echo esc_html( (string) $rgp_row->name ); ?>
							<?php endif; ?>
						</th>
						<td><?php echo esc_html( number_format_i18n( $rgp_row->impressions ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php Chrome::endSection(); ?>
<?php endif; ?>

<p class="description rgp-footnote"><?php esc_html_e( 'These are server-side impressions: an ad is counted when its code is added to a page that loads. Pages served from a full-page cache do not run this step and are not counted, and this is not viewability or click data. No visitor information is stored.', 'revguard-pro' ); ?></p>
<?php
Chrome::close();
