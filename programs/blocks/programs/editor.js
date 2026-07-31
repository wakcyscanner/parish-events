/**
 * "Parish Programs" block editor UI: inspector controls + server-rendered
 * preview. Written against the wp.* globals so no build step is needed.
 */
(function (wp) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var RangeControl = wp.components.RangeControl;
	var SelectControl = wp.components.SelectControl;
	var ServerSideRender = wp.serverSideRender;
	var useSelect = wp.data.useSelect;

	wp.blocks.registerBlockType('parish-programs/programs', {
		edit: function (props) {
			var attributes = props.attributes;

			var groups = useSelect(function (select) {
				return select('core').getEntityRecords('taxonomy', 'pp_group', {
					per_page: -1,
					hide_empty: false
				});
			}, []);

			var groupOptions = [ { label: __('All groups', 'parish-programs'), value: '' } ]
				.concat((groups || []).map(function (term) {
					return { label: term.name, value: term.slug };
				}));

			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __('Programs', 'parish-programs') },
						el(TextControl, {
							label: __('Heading', 'parish-programs'),
							help: __('Optional heading shown above the programs.', 'parish-programs'),
							value: attributes.heading,
							onChange: function (value) { props.setAttributes({ heading: value }); }
						}),
						el(RangeControl, {
							label: __('Number of programs', 'parish-programs'),
							help: __('0 shows all published programs.', 'parish-programs'),
							min: 0,
							max: 24,
							value: attributes.count,
							onChange: function (value) { props.setAttributes({ count: value }); }
						}),
						el(SelectControl, {
							label: __('Program group', 'parish-programs'),
							help: __('Only show programs in this group.', 'parish-programs'),
							value: attributes.group,
							options: groupOptions,
							onChange: function (value) { props.setAttributes({ group: value }); }
						}),
						el(SelectControl, {
							label: __('Layout', 'parish-programs'),
							value: attributes.layout,
							options: [
								{ label: __('Grid', 'parish-programs'), value: 'grid' },
								{ label: __('Carousel', 'parish-programs'), value: 'carousel' }
							],
							onChange: function (value) { props.setAttributes({ layout: value }); }
						})
					)
				),
				el(ServerSideRender, {
					block: 'parish-programs/programs',
					attributes: attributes
				})
			);
		},
		save: function () {
			return null;
		}
	});
})(window.wp);
