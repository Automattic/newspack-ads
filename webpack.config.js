/**
 **** WARNING: No ES6 modules here. Not transpiled! ****
 */
/* eslint-disable import/no-nodejs-modules */
/* eslint-disable @typescript-eslint/no-var-requires */

/**
 * External dependencies
 */
const fs = require( 'fs' );
const getBaseWebpackConfig = require( 'newspack-scripts/config/getWebpackConfig' );
const path = require( 'path' );

/**
 * Internal variables
 */
const editorSetup = [
	'regenerator-runtime/runtime',
	path.join( __dirname, 'src', 'setup', 'editor' ),
];
const viewSetup = [ 'regenerator-runtime/runtime', path.join( __dirname, 'src', 'setup', 'view' ) ];

function blockScripts( type, inputDir, blocks ) {
	return blocks
		.map( block => path.join( inputDir, 'blocks', block, `${ type }.js` ) )
		.filter( fs.existsSync );
}

const blocksDir = path.join( __dirname, 'src', 'blocks' );
const blocks = fs
	.readdirSync( blocksDir )
	.filter( block => fs.existsSync( path.join( __dirname, 'src', 'blocks', block, 'editor.js' ) ) );

// Helps split up each block into its own folder view script
const viewBlocksScripts = blocks.reduce( ( viewBlocks, block ) => {
	const viewScriptPath = path.join( __dirname, 'src', 'blocks', block, 'view.js' );
	if ( fs.existsSync( viewScriptPath ) ) {
		viewBlocks[ block + '/view' ] = [ ...viewSetup, ...[ viewScriptPath ] ];
	}
	return viewBlocks;
}, {} );

const entry = {
	'suppress-ads': path.join( __dirname, 'src', 'suppress-ads' ),
	frontend: path.join( __dirname, 'src', 'frontend' ),
	'customizer-preview': path.join( __dirname, 'src', 'customizer', 'preview' ),
	'customizer-control': path.join( __dirname, 'src', 'customizer', 'control' ),
	'header-bidding-gam': path.join( __dirname, 'src', 'wizard-settings', 'header-bidding-gam' ),
	prebid: path.join( __dirname, 'src', 'prebid' ),
};

Object.keys( entry ).forEach( key => {
	entry[ key ] = [ 'regenerator-runtime/runtime', entry[ key ] ];
} );

const webpackConfig = getBaseWebpackConfig(
	{ WP: true },
	{
		entry: {
			// Combines all the different blocks into one editor.js script
			editor: [
				...editorSetup,
				...blockScripts( 'editor', path.join( __dirname, 'src' ), blocks ),
			],
			...entry,
			...viewBlocksScripts,
		},
		'output-path': path.join( __dirname, 'dist' ),
	}
);

/**
 * Custom babel config for Prebid.js.
 * https://github.com/prebid/Prebid.js/blob/6.12.0/README.md#usage-as-a-npm-dependency.
 */
webpackConfig.module.rules.push( {
	test: /.js$/,
	include: new RegExp( `\\${ path.sep }prebid\\.js` ),
	use: {
		loader: 'babel-loader',
		options: {
			...require( 'prebid.js/.babelrc.js' ),
			configFile: false,
		},
	},
} );

module.exports = webpackConfig;
