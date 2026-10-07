<?php
/**
 * Dashboard.
 *
 * @package RevGuardPro
 *
 * @var array<string,mixed> $data Template data.
 */

use RevGuardPro\Admin\Presenter\FindingPresenter;
use RevGuardPro\Admin\Presenter\Labels;
use RevGuardPro\Admin\ScreenId;
use RevGuardPro\Admin\View\Chrome;
use RevGuardPro\Guardrails\GuardrailMessages;
use RevGuardPro\Guardrails\Severity;

defined( 'ABSPATH' ) || exit;

$rgp_urls   = $data['urls'];
$rgp_counts = $data['counts'];
$rgp_review = $data['review'];

Chrome::open( __( 'Dashboard', 'revguard-pro' ), $data['notice'] ?? null );

Chrome::section( 'glance', __( 'At a glance', 'revguard-pro' ) );
if ( null === $rgp_counts ) :
	echo '<p>' . esc_html__( 'The counts could not be loaded right now.', 'revguard-pro' ) . '</p>';
else :
	?>
	<dl class="rgp-tiles">
		<div class="rgp-tile">
			<dt><a href="<?php echo esc_url( $rgp_urls->screen( ScreenId::Ads, array( 'status' => 'active' ) ) ); ?>"><?php esc_html_e( 'Active ads', 'revguard-pro' ); ?></a></dt>
			<dd><?php echo esc_html( number_format_i18n( $rgp_counts['active_ads'] ) ); ?></dd>
		</div>
		<div class="rgp-tile">
			<dt><a href="<?php echo esc_url( $rgp_urls->screen( ScreenId::Groups, array( 'status' => 'active' ) ) ); ?>"><?php esc_html_e( 'Active groups', 'revguard-pro' ); ?></a></dt>
			<dd><?php echo esc_html( number_format_i18n( $rgp_counts['active_groups'] ) ); ?></dd>
		</div>
		<div class="rgp-tile">
			<dt><a href="<?php echo esc_url( $rgp_urls->screen( ScreenId::Placements, array( 'status' => 'active' ) ) ); ?>"><?php esc_html_e( 'Active placements', 'revguard-pro' ); ?></a></dt>
			<dd><?php echo esc_html( number_format_i18n( $rgp_counts['active_placements'] ) ); ?></dd>
		</div>
		<?php if ( null !== $data['impressions'] ) : ?>
			<div class="rgp-tile">
				<dt>
					<a href="<?php echo esc_url( $rgp_urls->screen( ScreenId::Analytics, array( 'range' => 7 ) ) ); ?>">
						<?php
						/* translators: %d: number of days. */
						echo esc_html( sprintf( __( 'Impressions, last %d days', 'revguard-pro' ), $data['impressions']['days'] ) );
						?>
					</a>
				</dt>
				<dd><?php echo esc_html( number_format_i18n( $data['impressions']['total'] ) ); ?></dd>
				<?php if ( ! $data['impressions']['enabled'] ) : ?>
					<dd class="rgp-tile__note"><?php esc_html_e( 'Counting is turned off in Settings.', 'revguard-pro' ); ?></dd>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</dl>
	<?php if ( 0 === $rgp_counts['ads'] ) : ?>
		<div class="rgp-empty">
			<h3><?php esc_html_e( 'Getting started', 'revguard-pro' ); ?></h3>
			<ol>
				<li><a href="<?php echo esc_url( $rgp_urls->screen( ScreenId::Ads, array( 'action' => 'new' ) ) ); ?>"><?php esc_html_e( 'Add your first ad.', 'revguard-pro' ); ?></a></li>
				<li><a href="<?php echo esc_url( $rgp_urls->screen( ScreenId::Placements, array( 'action' => 'new' ) ) ); ?>"><?php esc_html_e( 'Add a placement to choose where it appears.', 'revguard-pro' ); ?></a></li>
			</ol>
		</div>
	<?php endif; ?>
<?php endif; ?>
<?php Chrome::endSection(); ?>

<?php Chrome::section( 'review', __( 'Suggestions', 'revguard-pro' ) ); ?>
	<p class="description"><?php esc_html_e( 'These suggestions look only at how your ads are set up. They are advice, not an approval, a certification or a compliance check, and they cannot tell you whether a page is acceptable to any ad network.', 'revguard-pro' ); ?></p>
	<?php if ( null === $rgp_review ) : ?>
		<p><?php esc_html_e( 'The suggestions could not be loaded right now.', 'revguard-pro' ); ?></p>
	<?php else : ?>
		<?php if ( ! $rgp_review->isComplete() ) : ?>
			<p class="notice notice-warning inline"><?php esc_html_e( 'Some records could not be read or some checks were skipped, so this list is incomplete.', 'revguard-pro' ); ?></p>
		<?php endif; ?>
		<?php if ( array() === $rgp_review->findings ) : ?>
			<p><?php esc_html_e( 'None of the checks that ran had anything to suggest.', 'revguard-pro' ); ?></p>
		<?php else : ?>
			<ul class="rgp-findings">
				<?php foreach ( array_slice( $rgp_review->findings, 0, 10 ) as $rgp_finding ) : ?>
					<?php $rgp_link = FindingPresenter::url( $rgp_finding, $rgp_urls ); ?>
					<li class="rgp-finding rgp-finding--<?php echo esc_attr( $rgp_finding->severity->value ); ?>">
						<span class="rgp-sev">
							<span class="dashicons dashicons-<?php echo esc_attr( Severity::Warning === $rgp_finding->severity ? 'warning' : 'info-outline' ); ?>" aria-hidden="true"></span>
							<?php echo esc_html( Labels::severity( $rgp_finding->severity ) ); ?>
						</span>
						<span class="rgp-finding__text"><?php echo esc_html( GuardrailMessages::text( $rgp_finding ) ); ?></span>
						<?php if ( null !== $rgp_link ) : ?>
							<a href="<?php echo esc_url( $rgp_link ); ?>"><?php esc_html_e( 'Review', 'revguard-pro' ); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php $rgp_more = max( 0, count( $rgp_review->findings ) - 10 ) + $rgp_review->suppressed; ?>
			<?php if ( $rgp_more > 0 ) : ?>
				<p class="description">
					<?php
					/* translators: %d: number of further suggestions. */
					echo esc_html( sprintf( _n( 'and %d more suggestion.', 'and %d more suggestions.', $rgp_more, 'revguard-pro' ), $rgp_more ) );
					?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
<?php
Chrome::endSection();
Chrome::close();
