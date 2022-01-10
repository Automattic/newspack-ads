/**
 * Header Bidding Settings Hooks.
 */

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Fragment, useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Newspack dependencies.
 */
import { ActionCard, Card, Notice, Modal, TextControl, Button } from 'newspack-components';

/**
 * Internal dependencies.
 */
import './style.scss';

const HeaderBiddingGAM = () => {
	const [ inFlight, setInFlight ] = useState( true );
	const [ isCreating, setIsCreating ] = useState( false );
	const [ orderName, setOrderName ] = useState( 'Newpack Header Bidding - Dense' );
	const [ order, setOrder ] = useState( null );
	const [ error, setError ] = useState( null );
	const fetchOrder = async ( create = false ) => {
		setInFlight( true );
		try {
			const data = await apiFetch( {
				path: '/newspack-ads/v1/bidding/gam/order',
				method: create ? 'POST' : 'GET',
				data: create ? { name: orderName } : null,
			} );
			setOrder( data );
			setError( null );
		} catch ( err ) {
			setError( err );
		} finally {
			setInFlight( false );
		}
	};
	useEffect(() => {
		fetchOrder();
	}, []);
	return (
		<Fragment>
			<ActionCard
				className="newspack-header-bidding-gam-action-card"
				title={ __( 'Google Ad Manager Integration', 'newspack-ads' ) }
				description={ () => (
					<Fragment>
						{ error ? (
							error.message
						) : (
							<Fragment>
								<span>{ JSON.stringify( order ) }</span>
							</Fragment>
						) }
					</Fragment>
				) }
				checkbox="unchecked"
				actionText={ ! order || error ? __( 'Configure', 'newspack-ads' ) : null }
				onClick={ () => setIsCreating( true ) }
			/>
			{ isCreating && (
				<Modal
					title={ __( 'Create Order', 'newspack-ads' ) }
					onRequestClose={ () => setIsCreating( false ) }
				>
					<p>
						{ __(
							'Create the order and line items on your Google Ad Manager network according to the pre-defined price bucket settings.',
							'newspack-ads'
						) }
					</p>
					{ error && error.data?.status !== '404' && (
						<Notice isError noticeText={ error.message } />
					) }
					<TextControl
						label={ __( 'Order name', 'newspack-ads' ) }
						disabled={ inFlight }
						value={ orderName }
						onChange={ value => setOrderName( value ) }
					/>
					<Card buttonsCard noBorder className="justify-end">
						<Button
							isSecondary
							disabled={ inFlight }
							onClick={ () => {
								setIsCreating( false );
							} }
						>
							{ __( 'Cancel', 'newspack-ads' ) }
						</Button>
						<Button
							isPrimary
							disabled={ ! orderName || inFlight }
							onClick={ async () => {
								// TODO: Implement line items creation before testing order creation.
								await fetchOrder();
								setIsCreating( false );
							} }
						>
							{ __( 'Create Order', 'newspack-ads' ) }
						</Button>
					</Card>
				</Modal>
			) }
		</Fragment>
	);
};

wp.hooks.addFilter(
	'newspack.settingSection.bidding.beforeControls',
	'newspack-ads/header-bidding-gam',
	( AfterControls, props ) => {
		if ( props.sectionKey === 'bidding' ) {
			return <HeaderBiddingGAM { ...props } />;
		}
		return AfterControls;
	}
);
