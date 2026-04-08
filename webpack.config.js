const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

const plugins = defaultConfig.plugins.filter(
	( plugin ) =>
		plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
);

module.exports = {
	...defaultConfig,
	entry: {
		connectors: path.resolve( process.cwd(), 'src/js', 'connectors.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( process.cwd(), 'build' ),
		module: true,
		chunkFormat: 'module',
		library: { type: 'module' },
	},
	experiments: { ...defaultConfig.experiments, outputModule: true },
	externalsType: 'module',
	externals: {
		'@wordpress/connectors': '@wordpress/connectors',
	},
	plugins,
};
