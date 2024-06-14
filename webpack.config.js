/**
 **** WARNING: No ES6 modules here. Not transpiled! ****
 */
/* eslint-disable import/no-nodejs-modules */
/* eslint-disable @typescript-eslint/no-var-requires */

/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );
const getBaseWebpackConfig = require( 'newspack-scripts/config/getWebpackConfig' );
const loader = require.resolve( 'newspack-scripts/node_modules/babel-loader' );

/**
 * Internal variables
 */
const editorSetup = path.join( __dirname, 'src', 'setup', 'editor' );
const viewSetup = path.join( __dirname, 'src', 'setup', 'view' );
const frontend = path.join( __dirname, 'src', 'frontend' );
const customizerPreview = path.join( __dirname, 'src', 'customizer', 'preview' );
const customizerControl = path.join( __dirname, 'src', 'customizer', 'control' );
const headerBiddingGAM = path.join( __dirname, 'src', 'wizard-settings', 'header-bidding-gam' );
const prebid = path.join( __dirname, 'src', 'prebid' );

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
		viewBlocks[ block + '/view' ] = [ viewSetup, ...[ viewScriptPath ] ];
	}
	return viewBlocks;
}, {} );

// Combines all the different blocks into one editor.js script
const editorScript = [
	editorSetup,
	...blockScripts( 'editor', path.join( __dirname, 'src' ), blocks ),
];

const suppressAdsScript = path.join( __dirname, 'src', 'suppress-ads' );

const webpackConfig = getBaseWebpackConfig( {
	entry: {
		editor: editorScript,
		...viewBlocksScripts,
		'suppress-ads': suppressAdsScript,
		frontend,
		'customizer-preview': customizerPreview,
		'customizer-control': customizerControl,
		'header-bidding-gam': headerBiddingGAM,
		prebid,
	},
} );

/**
 * Custom babel config for Prebid.js.
 * https://github.com/prebid/Prebid.js/blob/6.12.0/README.md#usage-as-a-npm-dependency.
 */
webpackConfig.module.rules.push( {
	test: /.js$/,
	include: new RegExp( `\\${ path.sep }prebid\\.js` ),
	use: {
		loader,
		options: {
			...require( 'prebid.js/.babelrc.js' ),
			configFile: false,
		},
	},
} );

module.exports = webpackConfig;
