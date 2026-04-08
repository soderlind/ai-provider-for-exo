import React from 'react';

window.wp = {
	apiFetch: vi.fn( () => Promise.resolve( {} ) ),
	element: {
		useState: React.useState,
		useEffect: React.useEffect,
		useCallback: React.useCallback,
		createElement: React.createElement,
	},
	i18n: { __: vi.fn( ( str ) => str ) },
	components: {
		Button( { children, ...props } ) {
			return React.createElement( 'button', props, children );
		},
		TextControl( props ) {
			return React.createElement( 'input', {
				type: 'text',
				value: props.value || '',
				onChange: ( e ) => props.onChange?.( e.target.value ),
			} );
		},
	},
};
