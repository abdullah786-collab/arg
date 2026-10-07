<?php
/**
 * List screen shared by Ads, Groups and Placements.
 *
 * @package RevGuardPro
 *
 * @var array<string,mixed> $data Template data.
 */

use RevGuardPro\Admin\View\Chrome;

defined( 'ABSPATH' ) || exit;

$rgp_copy = $data['copy'];
$rgp_new  = $data['urls']->screen( $data['screen'], array( 'action' => 'new' ) );

Chrome::open( $rgp_copy['title'], $data['notice'] ?? null, array( $rgp_copy['new'] => $rgp_new ) );

if ( $data['empty'] ) :
	?>
	<div class="rgp-empty">
		<h2><?php echo esc_html( $rgp_copy['empty_title'] ); ?></h2>
		<p><?php echo esc_html( $rgp_copy['empty_text'] ); ?></p>
		<p><a class="button button-primary" href="<?php echo esc_url( $rgp_new ); ?>"><?php echo esc_html( $rgp_copy['new'] ); ?></a></p>
	</div>
	<?php
else :
	$data['table']->views();
	?>
	<form method="post" action="<?php echo esc_url( $data['bulk_action'] ); ?>">
		<?php $data['table']->display(); ?>
	</form>
	<?php
endif;

Chrome::close();
