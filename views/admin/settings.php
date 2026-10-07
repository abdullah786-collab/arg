<?php
/**
 * Settings.
 *
 * @package RevGuardPro
 *
 * @var array<string,mixed> $data Template data.
 */

use RevGuardPro\Admin\Form\ErrorMessages;
use RevGuardPro\Admin\View\Chrome;
use RevGuardPro\Admin\View\Fields;

defined( 'ABSPATH' ) || exit;

$rgp_values = $data['values'];
$rgp_errors = $data['errors'];

Chrome::open( __( 'Settings', 'revguard-pro' ), $data['notice'] ?? null );
Fields::errorSummary(
	$rgp_errors,
	array(
		'analytics_retention_days'        => array( 'analytics_retention_days', __( 'Keep statistics for', 'revguard-pro' ) ),
		'allow_unfiltered_html_creatives' => array( 'allow_unfiltered_html_creatives', __( 'Allow unfiltered HTML ad code', 'revguard-pro' ) ),
	)
);
?>
<form method="post" action="<?php echo esc_url( $data['urls']->screen( $data['screen'], array( 'action' => 'save' ) ) ); ?>" class="rgp-form">
	<?php wp_nonce_field( 'rgp_save_settings' ); ?>

	<fieldset class="rgp-card">
		<legend><?php esc_html_e( 'Showing ads', 'revguard-pro' ); ?></legend>
		<?php
		Fields::checkbox( 'automatic_insertion', 'automatic_insertion', __( 'Insert ads automatically using placements', 'revguard-pro' ), ! empty( $rgp_values['automatic_insertion'] ), null, __( 'Turn this off to show ads only where you use the shortcode or block.', 'revguard-pro' ) );
		Fields::checkbox(
			'allow_unfiltered_html_creatives',
			'allow_unfiltered_html_creatives',
			__( 'Allow unfiltered HTML ad code', 'revguard-pro' ),
			! empty( $rgp_values['allow_unfiltered_html_creatives'] ),
			$rgp_errors['allow_unfiltered_html_creatives'] ?? null,
			$data['can_unfiltered']
				? __( 'Advanced. Custom HTML ads are shown exactly as written, including scripts. Anyone who can edit ads can then run code on your pages. Leave this off unless you fully trust every editor.', 'revguard-pro' )
				: __( 'Advanced. Only a user who is allowed to post unfiltered HTML can turn this on.', 'revguard-pro' ),
			! $data['can_unfiltered'] && empty( $rgp_values['allow_unfiltered_html_creatives'] )
		);
		?>
	</fieldset>

	<fieldset class="rgp-card">
		<legend><?php esc_html_e( 'Statistics', 'revguard-pro' ); ?></legend>
		<?php
		Fields::checkbox( 'analytics_enabled', 'analytics_enabled', __( 'Count impressions', 'revguard-pro' ), ! empty( $rgp_values['analytics_enabled'] ), null, __( 'Counts how often each ad is added to a page, per day. No visitor information is stored.', 'revguard-pro' ) );
		Fields::number(
			'analytics_retention_days',
			'analytics_retention_days',
			__( 'Keep statistics for (days)', 'revguard-pro' ),
			(string) ( $rgp_values['analytics_retention_days'] ?? 90 ),
			$rgp_errors['analytics_retention_days'] ?? null,
			__( 'Older daily totals are deleted automatically. 7 to 3650.', 'revguard-pro' ),
			array(
				'min'       => 7,
				'max'       => 3650,
				'step'      => 1,
				'inputmode' => 'numeric',
			)
		);
		?>
	</fieldset>

	<fieldset class="rgp-card">
		<legend><?php esc_html_e( 'Uninstalling', 'revguard-pro' ); ?></legend>
		<?php Fields::checkbox( 'delete_data_on_uninstall', 'delete_data_on_uninstall', __( 'Delete all RevGuard Pro data when the plugin is deleted', 'revguard-pro' ), ! empty( $rgp_values['delete_data_on_uninstall'] ), null, __( 'Removes your ads, groups, placements, statistics and settings. It does not touch the free RevGuard plugin. Leave this off to keep your data.', 'revguard-pro' ) ); ?>
	</fieldset>

	<?php if ( isset( $rgp_errors['_form'] ) ) : ?>
		<div class="notice notice-error inline" role="alert"><p><?php echo esc_html( ErrorMessages::text( $rgp_errors['_form'] ) ); ?></p></div>
	<?php endif; ?>
	<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'revguard-pro' ); ?></button></p>
</form>
<?php
Chrome::close();
