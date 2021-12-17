/**
 * Header Bidding Settings Hooks.
 */

const HeaderBiddingGAM = () => {
	return null;
};

wp.hooks.addFilter(
	'newspack.settingSection.bidding.afterControls',
	'newspack-ads/header-bidding-gam',
	( AfterControls, props ) => {
		if ( props.sectionKey === 'bidding' ) {
			return <HeaderBiddingGAM { ...props } />;
		}
		return AfterControls;
	}
);
