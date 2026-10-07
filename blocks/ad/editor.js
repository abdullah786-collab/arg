( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var targets = window.revguardProTargets || null;

	function findName( list, id ) {
		for ( var i = 0; i < list.length; i++ ) {
			if ( list[ i ].id === id ) {
				return list[ i ].name;
			}
		}
		return null;
	}

	function options( list ) {
		var out = [ { label: __( 'Select...', 'revguard-pro' ), value: '0' } ];
		for ( var i = 0; i < list.length; i++ ) {
			out.push( { label: list[ i ].name + ' (#' + list[ i ].id + ')', value: String( list[ i ].id ) } );
		}
		return out;
	}

	function label( attributes ) {
		var kind = 'group' === attributes.targetType ? 'Ad group' : 'Ad';
		if ( ! attributes.targetId ) {
			return 'RevGuard Pro: select an ad or group';
		}
		var list = targets ? ( 'group' === attributes.targetType ? targets.groups : targets.ads ) : [];
		var name = findName( list, attributes.targetId );
		return null === name ? 'RevGuard Pro: ' + kind + ' (#' + attributes.targetId + ', not found)' : 'RevGuard Pro: ' + kind + ' "' + name + '" (#' + attributes.targetId + ')';
	}

	wp.blocks.registerBlockType( 'revguard-pro/ad', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var list = targets ? ( 'group' === attributes.targetType ? targets.groups : targets.ads ) : [];
			var panel;
			if ( null === targets ) {
				panel = el( wp.components.Notice, { status: 'warning', isDismissible: false }, __( 'You do not have permission to choose RevGuard Pro ads.', 'revguard-pro' ) );
			} else {
				panel = [
					el( wp.components.SelectControl, {
						key: 'type',
						label: __( 'Target type', 'revguard-pro' ),
						value: attributes.targetType,
						options: [ { label: __( 'Ad', 'revguard-pro' ), value: 'ad' }, { label: __( 'Ad group', 'revguard-pro' ), value: 'group' } ],
						onChange: function ( value ) {
							props.setAttributes( { targetType: value, targetId: 0 } );
						}
					} ),
					0 === list.length ? el( wp.components.Notice, { key: 'none', status: 'info', isDismissible: false }, __( 'None exist yet.', 'revguard-pro' ) ) : el( wp.components.SelectControl, {
						key: 'id',
						label: __( 'Target', 'revguard-pro' ),
						value: String( attributes.targetId ),
						options: options( list ),
						onChange: function ( value ) {
							props.setAttributes( { targetId: parseInt( value, 10 ) || 0 } );
						}
					} )
				];
			}
			return el(
				'div',
				wp.blockEditor.useBlockProps(),
				el( wp.blockEditor.InspectorControls, null, el( wp.components.PanelBody, { title: __( 'Ad target', 'revguard-pro' ) }, panel ) ),
				el( 'div', { className: 'rgp-ad-placeholder' }, label( attributes ) )
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp ) );
