( function ( blocks, element, blockEditor, components, serverSideRender, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var Placeholder = components.Placeholder;
	var ServerSideRender = serverSideRender;

	blocks.registerBlockType( 'qnaapi-connect/form', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var blockProps = useBlockProps();

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Form settings', 'qnaapi-connect' ) },
						el( TextControl, {
							label: __( 'Form identifier', 'qnaapi-connect' ),
							help: __( 'The identifier shown next to the form under QNAAPI → Forms.', 'qnaapi-connect' ),
							value: attributes.identifier,
							onChange: function ( value ) {
								props.setAttributes( { identifier: value } );
							},
						} )
					)
				),
				attributes.identifier
					? el( ServerSideRender, {
							block: 'qnaapi-connect/form',
							attributes: attributes,
					  } )
					: el(
							Placeholder,
							{
								icon: 'feedback',
								label: __( 'QNAAPI Form', 'qnaapi-connect' ),
								instructions: __( 'Enter a form identifier in the block settings panel.', 'qnaapi-connect' ),
							},
							el( TextControl, {
								placeholder: __( 'e.g. customer-feedback', 'qnaapi-connect' ),
								value: attributes.identifier,
								onChange: function ( value ) {
									props.setAttributes( { identifier: value } );
								},
							} )
					  )
			);
		},
		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.serverSideRender,
	window.wp.i18n
);
