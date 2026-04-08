/**
 * exo — Connector for the WP 7 Connectors page.
 *
 * ESM script module. Only @wordpress/connectors is a real module import.
 * Classic-script packages are accessed via window.wp.* globals.
 */

import {
	__experimentalRegisterConnector as registerConnector,
	__experimentalConnectorItem as ConnectorItem,
} from '@wordpress/connectors';

const apiFetch = window.wp.apiFetch;
const { useState, useEffect, useCallback, createElement } = window.wp.element;
const { __ } = window.wp.i18n;
const { Button, TextControl } = window.wp.components;

const el = createElement;

const API_KEY_OPTION    = 'connectors_ai_exo_api_key';
const ENDPOINT_OPTION   = 'connectors_ai_exo_endpoint';
const MODEL_NAME_OPTION = 'connectors_ai_exo_model_name';

const ALL_OPTIONS = [
	API_KEY_OPTION,
	ENDPOINT_OPTION,
	MODEL_NAME_OPTION,
].join( ',' );

function useExoSettings() {
	const [ isLoading, setIsLoading ]     = useState( true );
	const [ apiKey, setApiKey ]           = useState( '' );
	const [ endpoint, setEndpoint ]       = useState( '' );
	const [ modelName, setModelName ]     = useState( '' );

	const isConnected = ! isLoading && endpoint !== '';

	const loadSettings = useCallback( async () => {
		try {
			const data = await apiFetch( {
				path: `/wp/v2/settings?_fields=${ ALL_OPTIONS }`,
			} );
			setApiKey( data[ API_KEY_OPTION ] || '' );
			setEndpoint( data[ ENDPOINT_OPTION ] || 'http://localhost:52415' );
			setModelName( data[ MODEL_NAME_OPTION ] || '' );
		} catch {
			// Silently fail — settings will show defaults.
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		loadSettings();
	}, [ loadSettings ] );

	const saveSettings = useCallback( async ( newApiKey, newEndpoint ) => {
		const result = await apiFetch( {
			path: `/wp/v2/settings?_fields=${ ALL_OPTIONS }`,
			method: 'POST',
			data: {
				[ API_KEY_OPTION ]: newApiKey,
				[ ENDPOINT_OPTION ]: newEndpoint,
			},
		} );
		setApiKey( result[ API_KEY_OPTION ] || '' );
		setEndpoint( result[ ENDPOINT_OPTION ] || '' );
	}, [] );

	const removeApiKey = useCallback( async () => {
		await apiFetch( {
			path: `/wp/v2/settings?_fields=${ API_KEY_OPTION }`,
			method: 'POST',
			data: { [ API_KEY_OPTION ]: '' },
		} );
		setApiKey( '' );
	}, [] );

	const detectModels = useCallback( async ( detectEndpoint, detectApiKey ) => {
		const result = await apiFetch( {
			path: '/exo/v1/detect',
			method: 'POST',
			data: {
				endpoint: detectEndpoint,
				api_key: detectApiKey,
			},
		} );

		if ( result.model_name ) {
			setModelName( result.model_name );
		}

		return result;
	}, [] );

	return {
		isLoading,
		isConnected,
		apiKey,
		setApiKey,
		endpoint,
		setEndpoint,
		modelName,
		setModelName,
		saveSettings,
		removeApiKey,
		detectModels,
	};
}

function ExoConnector( { slug, name, description, logo } ) {
	const {
		isLoading,
		isConnected,
		apiKey,
		setApiKey,
		endpoint,
		setEndpoint,
		modelName,
		setModelName,
		saveSettings,
		removeApiKey,
		detectModels,
	} = useExoSettings();

	const [ isExpanded, setIsExpanded ]       = useState( false );
	const [ isDetecting, setIsDetecting ]     = useState( false );
	const [ statusMessage, setStatusMessage ] = useState( '' );
	const [ statusType, setStatusType ]       = useState( '' );
	const [ isReplacingApiKey, setIsReplacingApiKey ] = useState( false );

	const hasSavedApiKey = apiKey.startsWith( '\u2022' );
	const canUseStoredApiKey = hasSavedApiKey && ! isReplacingApiKey;

	const handleConnect = async () => {
		setIsDetecting( true );
		setStatusMessage( '' );
		try {
			if ( ! canUseStoredApiKey ) {
				await saveSettings( apiKey, endpoint );
			} else {
				await saveSettings( apiKey, endpoint );
			}
			const result = await detectModels( endpoint, apiKey );
			setIsReplacingApiKey( false );
			const count = result.count || 0;
			const catalogCount = result.catalog_count || 0;
			setStatusMessage(
				count
					? /* translators: 1: active model count, 2: catalog model count */
					  __( 'Connected — ' + count + ' active model(s) of ' + catalogCount + ' in catalog.', 'ai-provider-for-exo' )
					: catalogCount
						? __( 'Connected but no active models. Start a model in exo first.', 'ai-provider-for-exo' )
						: __( 'Connected but no models detected.', 'ai-provider-for-exo' )
			);
			setStatusType( count ? 'success' : 'error' );
		} catch ( e ) {
			setStatusMessage(
				e.message || __( 'Detection failed. Check endpoint and API key.', 'ai-provider-for-exo' )
			);
			setStatusType( 'error' );
		} finally {
			setIsDetecting( false );
		}
	};

	const handleRedetect = async () => {
		setIsDetecting( true );
		setStatusMessage( '' );
		try {
			const result = await detectModels( endpoint, apiKey );
			const count = result.count || 0;
			const catalogCount = result.catalog_count || 0;
			setStatusMessage(
				count
					? __( 'Refreshed — ' + count + ' active model(s) of ' + catalogCount + ' in catalog.', 'ai-provider-for-exo' )
					: catalogCount
						? __( 'No active models. Start a model in exo first.', 'ai-provider-for-exo' )
						: __( 'No models detected.', 'ai-provider-for-exo' )
			);
			setStatusType( count ? 'success' : 'error' );
		} catch ( e ) {
			setStatusMessage(
				e.message || __( 'Detection failed.', 'ai-provider-for-exo' )
			);
			setStatusType( 'error' );
		} finally {
			setIsDetecting( false );
		}
	};

	if ( isLoading ) {
		return el( ConnectorItem, {
			logo: logo || el( ExoIcon ),
			name,
			description,
			actionArea: el( 'span', { className: 'spinner is-active' } ),
		} );
	}

	const buttonLabel = isConnected
		? __( 'Edit', 'ai-provider-for-exo' )
		: __( 'Set Up', 'ai-provider-for-exo' );

	const actionButton = el( Button, {
		variant: isConnected ? 'tertiary' : 'secondary',
		size: isConnected ? undefined : 'compact',
		onClick: () => setIsExpanded( ! isExpanded ),
		'aria-expanded': isExpanded,
	}, buttonLabel );

	// Model names from comma-separated string.
	const detectedModels = modelName ? modelName.split( ',' ).map( ( s ) => s.trim() ).filter( Boolean ) : [];

	const settingsPanel = isExpanded && el( 'div', null,
		el( TextControl, {
			label: __( 'Endpoint URL', 'ai-provider-for-exo' ),
			value: endpoint,
			onChange: ( value ) => {
				setStatusMessage( '' );
				setEndpoint( value );
			},
			placeholder: 'http://localhost:52415',
			help: __( 'Your exo API endpoint. Default: http://localhost:52415', 'ai-provider-for-exo' ),
			__next40pxDefaultSize: true,
		} ),

		el( TextControl, {
			label: __( 'API Key (optional)', 'ai-provider-for-exo' ),
			value: apiKey,
			onChange: ( value ) => {
				setStatusMessage( '' );
				setApiKey( value );
			},
			placeholder: __( 'Leave empty if not required', 'ai-provider-for-exo' ),
			help: hasSavedApiKey && ! isReplacingApiKey
				? __( 'Your API key is stored securely.', 'ai-provider-for-exo' )
				: __( 'Only needed if your exo cluster requires authentication.', 'ai-provider-for-exo' ),
			disabled: isDetecting || ( hasSavedApiKey && ! isReplacingApiKey ),
			__next40pxDefaultSize: true,
		} ),

		hasSavedApiKey && ! isReplacingApiKey && el( Button, {
			variant: 'link',
			isDestructive: true,
			onClick: async () => {
				await removeApiKey();
				setIsReplacingApiKey( true );
				setStatusMessage( '' );
			},
		}, __( 'Remove and replace', 'ai-provider-for-exo' ) ),

		// Connect / Save & Re-detect button.
		el( 'div', {
			style: { marginTop: 12, display: 'flex', alignItems: 'center', gap: 12 },
		},
			el( Button, {
				variant: 'primary',
				__next40pxDefaultSize: true,
				onClick: handleConnect,
				isBusy: isDetecting,
				disabled: isDetecting || ! endpoint,
			}, detectedModels.length
				? __( 'Save & Re-detect', 'ai-provider-for-exo' )
				: __( 'Connect & Detect', 'ai-provider-for-exo' )
			),
			statusMessage && el( 'span', {
				style: {
					fontSize: '13px',
					color: statusType === 'error' ? '#cc1818' : '#00a32a',
				},
			}, statusMessage ),
		),

		// Detected Models panel (read-only).
		detectedModels.length > 0 && el( 'div', {
			style: {
				marginTop: 20,
				padding: '16px 20px',
				background: '#f6f7f7',
				borderRadius: 8,
				border: '1px solid #e0e0e0',
			},
		},
			el( 'div', {
				style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 },
			},
				el( 'span', { style: { fontWeight: 600, fontSize: '13px' } },
					__( 'Active Models', 'ai-provider-for-exo' )
				),
				el( Button, {
					variant: 'tertiary',
					size: 'compact',
					onClick: handleRedetect,
					isBusy: isDetecting,
					disabled: isDetecting || ! endpoint,
				}, __( 'Refresh', 'ai-provider-for-exo' ) ),
			),
			el( 'div', { style: { display: 'flex', flexWrap: 'wrap', gap: 6 } },
				...detectedModels.map( ( model ) =>
					el( 'code', {
						key: model,
						style: {
							padding: '2px 8px',
							borderRadius: '4px',
							background: '#e8e8e8',
							fontSize: '12px',
						},
					}, model )
				),
			),
		),
	);

	return el( ConnectorItem, {
		logo: logo || el( ExoIcon ),
		name,
		description,
		actionArea: actionButton,
	}, settingsPanel );
}

function ExoIcon() {
	return el( 'svg', {
		width: 40,
		height: 40,
		viewBox: '0 0 24 24',
		xmlns: 'http://www.w3.org/2000/svg',
		'aria-hidden': 'true',
	},
		el( 'path', {
			fill: '#6366f1',
			d: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z',
		} ),
	);
}

registerConnector( 'ai_provider/exo', {
	name: __( 'exo', 'ai-provider-for-exo' ),
	description: __( 'Connect to exo — run frontier AI models locally on your device cluster.', 'ai-provider-for-exo' ),
	render: ExoConnector,
} );
