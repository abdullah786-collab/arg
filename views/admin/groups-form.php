<?php
/**
 * Create / edit a group.
 *
 * @package RevGuardPro
 *
 * @var array<string,mixed> $data Template data.
 */

use RevGuardPro\Admin\Form\ErrorMessages;
use RevGuardPro\Admin\Presenter\Labels;
use RevGuardPro\Admin\View\Chrome;
use RevGuardPro\Admin\View\Fields;
use RevGuardPro\Domain\GroupMember;
use RevGuardPro\Domain\RotationMode;
use RevGuardPro\Domain\Status;

defined( 'ABSPATH' ) || exit;

$rgp_group  = $data['entity'];
$rgp_errors = $data['errors'];
$rgp_sticky = $data['values'];
$rgp_value  = static function ( string $key, string $fallback ) use ( $rgp_sticky ): string {
	return null !== $rgp_sticky && isset( $rgp_sticky[ $key ] ) ? (string) $rgp_sticky[ $key ] : $fallback;
};
$rgp_id     = null === $rgp_group ? null : $rgp_group->id;
$rgp_action = array( 'action' => 'save' ) + ( null === $rgp_id ? array() : array( 'id' => $rgp_id ) );

// Current membership: from the failed submission when there is one, otherwise from the stored group.
$rgp_members = array();
if ( null !== $rgp_sticky ) {
	foreach ( (array) ( $rgp_sticky['members'] ?? array() ) as $rgp_ad_id => $rgp_row ) {
		$rgp_members[ (int) $rgp_ad_id ] = array( (string) $rgp_row['weight'], (string) $rgp_row['order'] );
	}
} elseif ( null !== $rgp_group ) {
	foreach ( $rgp_group->members as $rgp_position => $rgp_member ) {
		$rgp_members[ $rgp_member->ad_id ] = array( (string) $rgp_member->weight, (string) ( $rgp_position + 1 ) );
	}
}

Chrome::open( null === $rgp_group ? __( 'Add new group', 'revguard-pro' ) : __( 'Edit group', 'revguard-pro' ), $data['notice'] ?? null );
Fields::errorSummary(
	$rgp_errors,
	array(
		'name'          => array( 'name', __( 'Name', 'revguard-pro' ) ),
		'status'        => array( 'status', __( 'Status', 'revguard-pro' ) ),
		'rotation_mode' => array( 'rotation_mode', __( 'Rotation', 'revguard-pro' ) ),
		'members'       => array( 'members', __( 'Ads in this group', 'revguard-pro' ) ),
	)
);
?>
<form method="post" action="<?php echo esc_url( $data['urls']->screen( $data['screen'], $rgp_action ) ); ?>" class="rgp-form">
	<?php wp_nonce_field( 'rgp_save_group_' . ( $rgp_id ?? 'new' ) ); ?>

	<fieldset class="rgp-card">
		<legend><?php esc_html_e( 'Group', 'revguard-pro' ); ?></legend>
		<?php
		Fields::text(
			'name',
			'name',
			__( 'Name', 'revguard-pro' ),
			$rgp_value( 'name', null === $rgp_group ? '' : $rgp_group->name ),
			$rgp_errors['name'] ?? null,
			'',
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
			$rgp_value( 'status', null === $rgp_group ? Status::Inactive->value : $rgp_group->status->value ),
			$rgp_errors['status'] ?? null
		);
		Fields::select(
			'rotation_mode',
			'rotation_mode',
			__( 'Rotation', 'revguard-pro' ),
			array(
				RotationMode::Weighted->value => Labels::rotation( RotationMode::Weighted ),
				RotationMode::Ordered->value  => Labels::rotation( RotationMode::Ordered ),
			),
			$rgp_value( 'rotation_mode', null === $rgp_group ? RotationMode::Weighted->value : $rgp_group->rotation_mode->value ),
			$rgp_errors['rotation_mode'] ?? null,
			__( 'Weighted random picks an ad by its weight on every page. In order uses the order numbers below and shows the first ad that is allowed to appear.', 'revguard-pro' )
		);
		?>
	</fieldset>

	<fieldset class="rgp-card" aria-describedby="rgp-members-hint">
		<legend><?php esc_html_e( 'Ads in this group', 'revguard-pro' ); ?></legend>
		<p class="description" id="rgp-members-hint"><?php esc_html_e( 'Tick the ads to include. Weight (1 to 1000) is used by weighted rotation. Order is used by in-order rotation; lower numbers come first.', 'revguard-pro' ); ?></p>
		<?php if ( isset( $rgp_errors['members'] ) ) : ?>
			<p class="rgp-error" id="rgp-members-error"><span class="screen-reader-text"><?php esc_html_e( 'Error:', 'revguard-pro' ); ?> </span><?php echo esc_html( ErrorMessages::text( $rgp_errors['members'] ) ); ?></p>
		<?php endif; ?>
		<?php if ( array() === $data['ads'] ) : ?>
			<p><?php esc_html_e( 'You have no ads yet. Add an ad first, then come back to build a group.', 'revguard-pro' ); ?></p>
		<?php else : ?>
			<div class="rgp-table-wrap" role="region" tabindex="0" aria-label="<?php esc_attr_e( 'Ads in this group', 'revguard-pro' ); ?>">
				<table class="widefat striped rgp-table">
					<caption class="screen-reader-text"><?php esc_html_e( 'Ads you can add to this group', 'revguard-pro' ); ?></caption>
					<thead><tr>
						<th scope="col"><?php esc_html_e( 'Include', 'revguard-pro' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Ad', 'revguard-pro' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Order', 'revguard-pro' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Weight', 'revguard-pro' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $data['ads'] as $rgp_ad ) : ?>
						<?php
						$rgp_ad_id  = (int) $rgp_ad->id;
						$rgp_in     = isset( $rgp_members[ $rgp_ad_id ] );
						$rgp_weight = $rgp_members[ $rgp_ad_id ][0] ?? (string) GroupMember::WEIGHT_DEFAULT;
						$rgp_order  = $rgp_members[ $rgp_ad_id ][1] ?? '';
						$rgp_prefix = 'members[' . $rgp_ad_id . ']';
						?>
						<tr>
							<td>
								<input type="checkbox" id="rgp-m-<?php echo esc_attr( (string) $rgp_ad_id ); ?>-inc" name="<?php echo esc_attr( $rgp_prefix . '[include]' ); ?>" value="1"<?php echo $rgp_in ? ' checked' : ''; ?>>
								<?php /* translators: %s: ad name. */ ?>
								<label class="screen-reader-text" for="rgp-m-<?php echo esc_attr( (string) $rgp_ad_id ); ?>-inc"><?php echo esc_html( sprintf( __( 'Include %s', 'revguard-pro' ), $rgp_ad->name ) ); ?></label>
							</td>
							<td><?php echo esc_html( $rgp_ad->name ); ?> <span class="rgp-badge rgp-badge--<?php echo esc_attr( \RevGuardPro\Domain\Status::Active === $rgp_ad->status ? 'active' : 'inactive' ); ?>"><?php echo esc_html( Labels::status( $rgp_ad->status ) ); ?></span></td>
							<td>
								<input type="number" min="0" max="100000" step="1" id="rgp-m-<?php echo esc_attr( (string) $rgp_ad_id ); ?>-ord" name="<?php echo esc_attr( $rgp_prefix . '[order]' ); ?>" value="<?php echo esc_attr( $rgp_order ); ?>" class="small-text">
								<?php /* translators: %s: ad name. */ ?>
								<label class="screen-reader-text" for="rgp-m-<?php echo esc_attr( (string) $rgp_ad_id ); ?>-ord"><?php echo esc_html( sprintf( __( 'Order for %s', 'revguard-pro' ), $rgp_ad->name ) ); ?></label>
							</td>
							<td>
								<input type="number" min="1" max="1000" step="1" id="rgp-m-<?php echo esc_attr( (string) $rgp_ad_id ); ?>-wt" name="<?php echo esc_attr( $rgp_prefix . '[weight]' ); ?>" value="<?php echo esc_attr( $rgp_weight ); ?>" class="small-text">
								<?php /* translators: %s: ad name. */ ?>
								<label class="screen-reader-text" for="rgp-m-<?php echo esc_attr( (string) $rgp_ad_id ); ?>-wt"><?php echo esc_html( sprintf( __( 'Weight for %s', 'revguard-pro' ), $rgp_ad->name ) ); ?></label>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php if ( $data['truncated'] ) : ?>
				<p class="description"><?php esc_html_e( 'Only the first 500 ads are listed.', 'revguard-pro' ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</fieldset>

	<?php if ( isset( $rgp_errors['_form'] ) ) : ?>
		<div class="notice notice-error inline" role="alert"><p><?php echo esc_html( ErrorMessages::text( $rgp_errors['_form'] ) ); ?></p></div>
	<?php endif; ?>
	<p class="submit">
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Save group', 'revguard-pro' ); ?></button>
		<a class="button" href="<?php echo esc_url( $data['urls']->screen( $data['screen'] ) ); ?>"><?php esc_html_e( 'Cancel', 'revguard-pro' ); ?></a>
	</p>
</form>
<?php
Chrome::close();
