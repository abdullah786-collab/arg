<?php
/**
 * Create / edit a placement.
 *
 * @package RevGuardPro
 *
 * @var array<string,mixed> $data Template data.
 */

use RevGuardPro\Admin\Form\ErrorMessages;
use RevGuardPro\Admin\Presenter\Labels;
use RevGuardPro\Admin\Presenter\RowPresenter;
use RevGuardPro\Admin\View\Chrome;
use RevGuardPro\Admin\View\Fields;
use RevGuardPro\Domain\Placement;
use RevGuardPro\Domain\PlacementType;
use RevGuardPro\Domain\Status;
use RevGuardPro\Domain\TargetType;

defined( 'ABSPATH' ) || exit;

$rgp_placement = $data['entity'];
$rgp_errors    = $data['errors'];
$rgp_sticky    = $data['values'];
$rgp_value     = static function ( string $key, string $fallback ) use ( $rgp_sticky ): string {
	return null !== $rgp_sticky && isset( $rgp_sticky[ $key ] ) ? (string) $rgp_sticky[ $key ] : $fallback;
};
$rgp_config    = static function ( string $key, string $fallback ) use ( $rgp_placement ): string {
	if ( null === $rgp_placement ) {
		return $fallback;
	}
	$stored = $rgp_placement->config->toArray();
	return isset( $stored[ $key ] ) ? (string) $stored[ $key ] : $fallback;
};
$rgp_id        = null === $rgp_placement ? null : $rgp_placement->id;
$rgp_action    = array( 'action' => 'save' ) + ( null === $rgp_id ? array() : array( 'id' => $rgp_id ) );

$rgp_types = array();
foreach ( PlacementType::cases() as $rgp_type ) {
	$rgp_types[ $rgp_type->value ] = Labels::placementType( $rgp_type );
}
$rgp_targets = array( '' => __( '— Choose an ad or group —', 'revguard-pro' ) );
foreach ( array(
	'Ads'    => array( 'ad', $data['ads'], __( 'Ads', 'revguard-pro' ) ),
	'Groups' => array( 'group', $data['groups'], __( 'Groups', 'revguard-pro' ) ),
) as $rgp_set ) {
	if ( array() !== $rgp_set[1] ) {
		$rgp_targets[ $rgp_set[2] ] = array();
		foreach ( $rgp_set[1] as $rgp_entity ) {
			$rgp_targets[ $rgp_set[2] ][ $rgp_set[0] . ':' . $rgp_entity->id ] = $rgp_entity->name;
		}
	}
}
$rgp_current_target = null === $rgp_placement || null === $rgp_placement->target_type ? '' : $rgp_placement->target_type->value . ':' . $rgp_placement->target_id;

Chrome::open( null === $rgp_placement ? __( 'Add new placement', 'revguard-pro' ) : __( 'Edit placement', 'revguard-pro' ), $data['notice'] ?? null );
Fields::errorSummary(
	$rgp_errors,
	array(
		'name'          => array( 'name', __( 'Name', 'revguard-pro' ) ),
		'status'        => array( 'status', __( 'Status', 'revguard-pro' ) ),
		'type'          => array( 'type', __( 'Position', 'revguard-pro' ) ),
		'paragraph'     => array( 'paragraph', __( 'Paragraph number', 'revguard-pro' ) ),
		'heading_level' => array( 'heading_level', __( 'Heading level', 'revguard-pro' ) ),
		'occurrence'    => array( 'occurrence', __( 'Which heading', 'revguard-pro' ) ),
		'percent'       => array( 'percent', __( 'Percent of the content', 'revguard-pro' ) ),
		'target'        => array( 'target', __( 'What to show', 'revguard-pro' ) ),
		'priority'      => array( 'priority', __( 'Priority', 'revguard-pro' ) ),
	)
);
?>
<form method="post" action="<?php echo esc_url( $data['urls']->screen( $data['screen'], $rgp_action ) ); ?>" class="rgp-form">
	<?php wp_nonce_field( 'rgp_save_placement_' . ( $rgp_id ?? 'new' ) ); ?>

	<fieldset class="rgp-card">
		<legend><?php esc_html_e( 'Placement', 'revguard-pro' ); ?></legend>
		<?php
		Fields::text(
			'name',
			'name',
			__( 'Name', 'revguard-pro' ),
			$rgp_value( 'name', null === $rgp_placement ? '' : $rgp_placement->name ),
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
			$rgp_value( 'status', null === $rgp_placement ? Status::Inactive->value : $rgp_placement->status->value ),
			$rgp_errors['status'] ?? null,
			__( 'An inactive placement is never used.', 'revguard-pro' )
		);
		Fields::select( 'target', 'target', __( 'What to show', 'revguard-pro' ), $rgp_targets, $rgp_value( 'target', $rgp_current_target ), $rgp_errors['target'] ?? null, __( 'A single ad, or a group that rotates several ads. An active placement needs one.', 'revguard-pro' ) );
		if ( $data['truncated'] ) {
			echo '<p class="description">' . esc_html__( 'Only the first 500 ads and groups are listed.', 'revguard-pro' ) . '</p>';
		}
		?>
	</fieldset>

	<fieldset class="rgp-card">
		<legend><?php esc_html_e( 'Where it appears', 'revguard-pro' ); ?></legend>
		<?php
		Fields::select( 'type', 'type', __( 'Position', 'revguard-pro' ), $rgp_types, $rgp_value( 'type', null === $rgp_placement ? PlacementType::AfterParagraph->value : $rgp_placement->type()->value ), $rgp_errors['type'] ?? null, __( 'Fill in only the setting that goes with the position you choose. The others are ignored.', 'revguard-pro' ) );
		Fields::number(
			'paragraph',
			'paragraph',
			__( 'Paragraph number', 'revguard-pro' ),
			$rgp_value( 'paragraph', $rgp_config( 'paragraph', '3' ) ),
			$rgp_errors['paragraph'] ?? null,
			__( 'For "After a paragraph": 1 to 100.', 'revguard-pro' ),
			array(
				'min'       => 1,
				'max'       => 100,
				'step'      => 1,
				'inputmode' => 'numeric',
			)
		);
		Fields::number(
			'heading_level',
			'heading_level',
			__( 'Heading level', 'revguard-pro' ),
			$rgp_value( 'heading_level', $rgp_config( 'heading_level', '2' ) ),
			$rgp_errors['heading_level'] ?? null,
			__( 'For "After a heading": 2 to 6 (H2 to H6).', 'revguard-pro' ),
			array(
				'min'       => 2,
				'max'       => 6,
				'step'      => 1,
				'inputmode' => 'numeric',
			)
		);
		Fields::number(
			'occurrence',
			'occurrence',
			__( 'Which heading', 'revguard-pro' ),
			$rgp_value( 'occurrence', $rgp_config( 'occurrence', '1' ) ),
			$rgp_errors['occurrence'] ?? null,
			__( 'For "After a heading": 1 means the first heading of that level, 2 the second, and so on.', 'revguard-pro' ),
			array(
				'min'       => 1,
				'max'       => 20,
				'step'      => 1,
				'inputmode' => 'numeric',
			)
		);
		Fields::number(
			'percent',
			'percent',
			__( 'Percent of the content', 'revguard-pro' ),
			$rgp_value( 'percent', $rgp_config( 'percent', '50' ) ),
			$rgp_errors['percent'] ?? null,
			__( 'For "At a point in the content": 1 to 99. 50 is about the middle.', 'revguard-pro' ),
			array(
				'min'       => 1,
				'max'       => 99,
				'step'      => 1,
				'inputmode' => 'numeric',
			)
		);
		Fields::number(
			'priority',
			'priority',
			__( 'Priority', 'revguard-pro' ),
			$rgp_value( 'priority', null === $rgp_placement ? (string) Placement::PRIORITY_DEFAULT : (string) $rgp_placement->priority ),
			$rgp_errors['priority'] ?? null,
			__( 'Lower numbers are placed first. If two placements want the same spot, the lower number wins.', 'revguard-pro' ),
			array(
				'min'       => Placement::PRIORITY_MIN,
				'max'       => Placement::PRIORITY_MAX,
				'step'      => 1,
				'inputmode' => 'numeric',
			)
		);
		?>
	</fieldset>

	<?php if ( null !== $rgp_placement ) : ?>
		<fieldset class="rgp-card">
			<legend><?php esc_html_e( 'Shown to', 'revguard-pro' ); ?></legend>
			<p><?php echo esc_html( RowPresenter::targeting( $rgp_placement->targeting ) ); ?></p>
			<p class="description"><?php esc_html_e( 'Targeting rules are kept exactly as they are when you save. A rules editor is planned for a later release.', 'revguard-pro' ); ?></p>
		</fieldset>
	<?php endif; ?>

	<?php if ( isset( $rgp_errors['_form'] ) ) : ?>
		<div class="notice notice-error inline" role="alert"><p><?php echo esc_html( ErrorMessages::text( $rgp_errors['_form'] ) ); ?></p></div>
	<?php endif; ?>
	<p class="submit">
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Save placement', 'revguard-pro' ); ?></button>
		<a class="button" href="<?php echo esc_url( $data['urls']->screen( $data['screen'] ) ); ?>"><?php esc_html_e( 'Cancel', 'revguard-pro' ); ?></a>
	</p>
</form>
<?php
Chrome::close();
