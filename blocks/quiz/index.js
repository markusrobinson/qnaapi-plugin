( function ( blocks, element, blockEditor, components, serverSideRender, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var Placeholder = components.Placeholder;
	var ServerSideRender = serverSideRender;

	blocks.registerBlockType( 'qnaapi-connect/quiz', {
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
						{ title: __( 'Quiz settings', 'qnaapi-connect' ) },
						el( TextControl, {
							label: __( 'Quiz identifier', 'qnaapi-connect' ),
							help: __( 'The identifier shown next to the quiz under QNAAPI → Quizzes.', 'qnaapi-connect' ),
							value: attributes.identifier,
							onChange: function ( value ) {
								props.setAttributes( { identifier: value } );
							},
						} )
					)
				),
				attributes.identifier
					? el( ServerSideRender, {
							block: 'qnaapi-connect/quiz',
							attributes: attributes,
					  } )
					: el(
							Placeholder,
							{
								icon: 'forms',
								label: __( 'QNAAPI Quiz', 'qnaapi-connect' ),
								instructions: __( 'Enter a quiz identifier in the block settings panel.', 'qnaapi-connect' ),
							},
							el( TextControl, {
								placeholder: __( 'e.g. js-basics', 'qnaapi-connect' ),
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
