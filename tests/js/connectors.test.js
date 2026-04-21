import { describe, it, expect, vi, beforeEach } from 'vitest';
import { __experimentalRegisterConnector as registerConnector } from '@wordpress/connectors';

describe( 'exo connector', () => {
	beforeEach( () => {
		vi.clearAllMocks();
	} );

	it( 'registers the connector module', async () => {
		await import( '../../src/js/connectors.js' );
		expect( registerConnector ).toHaveBeenCalledWith(
			'ai_provider/aiprfoex',
			expect.objectContaining( {
				name: 'exo',
				description: expect.any( String ),
				render: expect.any( Function ),
			} )
		);
	} );
} );
