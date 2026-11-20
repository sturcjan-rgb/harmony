/* Editor bloku harmony/rozvrh – bez build kroku (globální wp.*). */
( function ( wp ) {
	var el = wp.element.createElement;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var RangeControl = wp.components.RangeControl;
	var ToggleControl = wp.components.ToggleControl;
	var ServerSideRender = wp.serverSideRender;

	wp.blocks.registerBlockType( 'harmony/rozvrh', {
		edit: function ( props ) {
			var a = props.attributes;
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'Nastavení rozvrhu' },
						el( RangeControl, {
							label: 'Počet týdnů',
							min: 1,
							max: 5,
							value: a.tydny,
							onChange: function ( v ) { props.setAttributes( { tydny: v } ); }
						} ),
						el( ToggleControl, {
							label: 'Filtry skupin',
							checked: a.filtry,
							onChange: function ( v ) { props.setAttributes( { filtry: v } ); }
						} )
					)
				),
				el( ServerSideRender, { block: 'harmony/rozvrh', attributes: a } )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp );
