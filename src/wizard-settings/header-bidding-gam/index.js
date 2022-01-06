/**
 * Header Bidding Settings Hooks.
 */

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Fragment, useState } from '@wordpress/element';

/**
 * Newspack dependencies.
 */
import { ActionCard } from 'newspack-components';

/**
 * Internal dependencies.
 */
import './style.scss';

const HeaderBiddingGAM = () => {
	const [ isLoading ] = useState( true );
	return (
		<ActionCard
			className="newspack-header-bidding-gam-action-card"
			title={ __( 'Google Ad Manager Integration', 'newspack-ads' ) }
			description={ () => (
				<Fragment>
					{ isLoading ? (
						__( 'Fetching data...', 'newspack-ads' )
					) : (
						<Fragment>
							<span>Order</span>
						</Fragment>
					) }
				</Fragment>
			) }
			checkbox="unchecked"
			actionText={ __( 'Configure', 'newspack-ads' ) }
			onClick={ () => {} }
		/>
	);
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
