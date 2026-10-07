<?php
/**
 * Create / edit an ad.
 *
 * @package RevGuardPro
 *
 * @var array<string,mixed> $data Template data.
 */

use RevGuardPro\Admin\Presenter\Labels;
use RevGuardPro\Admin\Presenter\RowPresenter;
use RevGuardPro\Admin\View\Chrome;
use RevGuardPro\Admin\View\Fields;
use RevGuardPro\Domain\Status;
use RevGuardPro\Scheduling\ScheduleTime;

defined( 'ABSPATH' ) || exit;

$rgp_ad     = $data['entity'];
$rgp_errors = $data['errors'];
$rgp_sticky = $data['values'];
$rgp_zone   = $data['zone'];
$rgp_local  = static fn( ?DateTimeInterface $at ): string => null === $at ? '' : str_replace( ' ', 'T', substr( ScheduleTime::toLocal( $at, $rgp_zone ), 0, 16 ) );
$rgp_value  = static function ( string $key, string $fallback ) use ( $rgp_sticky ): string {
	return null !== $rgp_sticky && isset( $rgp_sticky[ $key ] ) ? (string) $rgp_sticky[ $key ] : $fallback;
};
$rgp_id     = null === $rgp_ad ? null : $rgp_ad->id;
$rgp_action = array( 'action' => 'save' ) + ( null === $rgp_id ? array() : array( 'id' => $rgp_id ) );

Chrome::open( null === $rgp_ad ? __( 'Add new ad', 'revguard-pro' ) : __( 'Edit ad', 'revguard-pro' ), $data['notice'] ?? null );
Fields::errorSummary(
	$rgp_errors,
	array(
		'name'           => array( 'name', __( 'Name', 'revguard-pro' ) ),
		'status'         => array( 'status', __( 'Status', 'revguard-pro' ) ),
		'creative_type'  => array( 'creative_type', __( 'Type', 'revguard-pro' ) ),
		'creative'       => array( 'creative', __( 'Ad code', 'revguard-pro' ) ),
		'schedule_start' => array( 'schedule_start', __( 'Start', 'revguard-pro' ) ),
		'schedule_end'   => array( 'schedule_end', __( 'End', 'revguard-pro' ) ),
	)
);
?>
<form method="post" action="<?php echo esc_url( $data['urls']->screen( $data['screen'], $rgp_action ) ); ?>" class="rgp-form">
	<?php wp_nonce_field( 'rgp_save_ad_' . ( $rgp_id ?? 'new' ) ); ?>

	<fieldset class="rgp-card">
		<legend><?php esc_html_e( 'Ad', 'revguard-pro' ); ?></legend>
		<?php
		Fields::text(
			'name',
			'name',
			__( 'Name', 'revguard-pro' ),
			$rgp_value( 'name', null === $rgp_ad ? '' : $rgp_ad->name ),
			$rgp_errors['name'] ?? null,
			__( 'Only you see this. Use something you will recognise in a list.', 'revguard-pro' ),
			array(
				'maxlength' => 190,
				'required'  => true,
			)
		);
		Fields::select(
			'status',
			'status',
			__( 'Status', 'revguard-pro' ),
			array(
				Status::Active->value   => Labels::status( Status::Active ),
				Status::Inactive->value => Labels::status( Status::Inactive ),
			),
			$rgp_value( 'status', null === $rgp_ad ? Status::Inactive->value : $rgp_ad->status->value ),
			$rgp_errors['status'] ?? null,
			__( 'An inactive ad is never shown.', 'revguard-pro' )
		);
		Fields::textarea(
			'creative',
			'creative',
			__( 'Ad code', 'revguard-pro' ),
			$rgp_value( 'creative', null === $rgp_ad ? '' : $rgp_ad->creative ),
			$rgp_errors['creative'] ?? null,
			__( 'Ad code is HTML. Unless Settings allow unfiltered code, it is filtered when shown: text, links, images and basic layout are kept; scripts, frames and event handlers are removed. The code is shown here as text and is never run in this screen.', 'revguard-pro' ),
			array(
				'rows'       => 12,
				'spellcheck' => 'false',
			)
		);
		?>
	</fieldset>

	<fieldset class="rgp-card">
		<legend><?php esc_html_e( 'Schedule', 'revguard-pro' ); ?></legend>
		<p class="description">
			<?php
			/* translators: %s: site time zone name. */
			echo esc_html( sprintf( __( 'Leave both empty to show the ad all the time. Times are in your site time zone (%s).', 'revguard-pro' ), $rgp_zone->getName() ) );
			?>
		</p>
		<?php
		Fields::datetime( 'schedule_start', 'schedule_start', __( 'Start', 'revguard-pro' ), $rgp_value( 'schedule_start', null === $rgp_ad ? '' : $rgp_local( $rgp_ad->schedule->start ) ), $rgp_errors['schedule_start'] ?? null );
		Fields::datetime( 'schedule_end', 'schedule_end', __( 'End', 'revguard-pro' ), $rgp_value( 'schedule_end', null === $rgp_ad ? '' : $rgp_local( $rgp_ad->schedule->end ) ), $rgp_errors['schedule_end'] ?? null, __( 'The ad stops showing at this time.', 'revguard-pro' ) );
		?>
	</fieldset>

	<?php if ( null !== $rgp_ad ) : ?>
		<fieldset class="rgp-card">
			<legend><?php esc_html_e( 'Shown to', 'revguard-pro' ); ?></legend>
			<p><?php echo esc_html( RowPresenter::targeting( $rgp_ad->targeting ) ); ?></p>
			<p class="description"><?php esc_html_e( 'Targeting rules are kept exactly as they are when you save. A rules editor is planned for a later release.', 'revguard-pro' ); ?></p>
		</fieldset>
	<?php endif; ?>

	<?php if ( isset( $rgp_errors['_form'] ) ) : ?>
		<div class="notice notice-error inline" role="alert"><p><?php echo esc_html( \RevGuardPro\Admin\Form\ErrorMessages::text( $rgp_errors['_form'] ) ); ?></p></div>
	<?php endif; ?>
	<p class="submit">
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Save ad', 'revguard-pro' ); ?></button>
		<a class="button" href="<?php echo esc_url( $data['urls']->screen( $data['screen'] ) ); ?>"><?php esc_html_e( 'Cancel', 'revguard-pro' ); ?></a>
	</p>
</form>
<?php
Chrome::close();
