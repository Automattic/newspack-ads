/**
 * Header Bidding Settings Hooks.
 */

/**
 * WordPress dependencies.
 */
import { addFilter } from '@wordpress/hooks';
import { sprintf, __, _n } from '@wordpress/i18n';
import { Fragment, useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Path, SVG } from '@wordpress/components';
import { link, pencil } from '@wordpress/icons';

const archive = (
	<SVG xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
		<Path d="m15.976 14.139-3.988 3.418L8 14.14 8.976 13l2.274 1.949V10.5h1.5v4.429L15 13l.976 1.139Z" />
		<Path
			clipRule="evenodd"
			d="M4 9.232A2 2 0 0 1 3 7.5V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1.5a2 2 0 0 1-1 1.732V18a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9.232ZM5 5.5h14a.5.5 0 0 1 .5.5v1.5a.5.5 0 0 1-.5.5H5a.5.5 0 0 1-.5-.5V6a.5.5 0 0 1 .5-.5Zm.5 4V18a.5.5 0 0 0 .5.5h12a.5.5 0 0 0 .5-.5V9.5h-13Z"
			fillRule="evenodd"
		/>
	</SVG>
);

/**
 * Newspack dependencies.
 */
import { ActionCard, Card, Modal, Button } from 'newspack-components';

/**
 * Internal dependencies.
 */
import './style.scss';
import Order from './order';

const { network_code } = window.newspack_ads_bidding_gam;

const getOrderUrl = orderId => {
	return `https://admanager.google.com/${ network_code }#delivery/order/order_overview/order_id=${ orderId }`;
};

const HeaderBiddingGAM = () => {
	const [ inFlight, setInFlight ] = useState( true );
	const [ isManaging, setIsManaging ] = useState( false );
	const [ editingOrder, setEditingOrder ] = useState( false );
	const [ orderName, setOrderName ] = useState( 'Newspack Header Bidding' );
	const [ orders, setOrders ] = useState( null );
	const [ error, setError ] = useState( null );
	const fetchOrders = async () => {
		setInFlight( true );
		let data;
		try {
			data = await apiFetch( {
				path: '/newspack-ads/v1/bidding/gam/orders',
				method: 'GET',
			} );
			setOrders( data );
			setError( null );
		} catch ( err ) {
			setError( err );
		} finally {
			setInFlight( false );
		}
		return data;
	};
	const getActiveOrders = () => {
		return orders?.filter( order => order.is_archived === false ) || [];
	};
	const archiveOrder = async orderId => {
		return await apiFetch( {
			path: '/newspack-ads/v1/bidding/gam/order',
			method: 'DELETE',
			data: {
				id: orderId,
			},
		} );
	};
	const canConfigure = () => {
		return ! inFlight && ! isManaging && error?.data?.status !== '500';
	};
	const getMissingOrderMessage = () => {
		return inFlight
			? __( 'Loading…', 'newspack-ads' )
			: __( 'Missing order configuration', 'newspack-ads' );
	};

	useEffect( async () => {
		await fetchOrders();
	}, [] );

	useEffect( () => {
		if ( orders?.length ) {
			setOrderName( `Newspack Header Bidding v${ orders.length + 1 }` );
		} else {
			setOrderName( 'Newspack Header Bidding' );
		}
	}, [ orders ] );

	useEffect( () => {
		if ( isManaging || editingOrder ) {
			window.onbeforeunload = () => {
				return __( 'Are you sure you want to leave this page?', 'newspack-ads' );
			};
		} else {
			window.onbeforeunload = null;
		}
	}, [ isManaging, editingOrder ] );

	const activeOrders = getActiveOrders();

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
							<span className="newspack-ads__header-bidding-gam__order-description">
								{ activeOrders.length
									? sprintf(
											// Translators: Number of line items in the order.
											_n(
												'There is %s available order.',
												'There are %s available orders.',
												activeOrders.length,
												'newspack-ads'
											),
											activeOrders.length
									  )
									: getMissingOrderMessage() }
							</span>
						) }
					</Fragment>
				) }
				actionText={ canConfigure() ? __( 'Manage Orders', 'newspack-ads' ) : null }
				onClick={ () => setIsManaging( true ) }
			/>
			{ isManaging && ! editingOrder && (
				<Modal
					title={ __( 'Manage Orders', 'newspack-ads' ) }
					onRequestClose={ () => ! inFlight && setIsManaging( false ) }
				>
					{ activeOrders.length && (
						<>
							<Card noBorder>
								{ activeOrders.map( order => (
									<ActionCard
										key={ order.id }
										title={ order.name }
										badge={ order.status }
										description={ () => (
											<span>
												{
													// Translators: the bidder revenue share for this order.
													sprintf( __( 'Bidder Revenue Share: %1$d%%', 'newspack-ads' ), 0 )
												}{ ' ' }
												|{ ' ' }
												{ sprintf(
													// Translators: comma-separated list of adapters for the order or "any" if undefined.
													__( 'Adapters: %s', 'newspack-ads' ),
													__( 'any', 'newspack-ads' )
												) }
											</span>
										) }
										actionText={
											<div className="flex items-center">
												{ order.status === 'DRAFT' && (
													<Button
														onClick={ async () => {
															setInFlight( true );
															await archiveOrder( order.id );
															setInFlight( false );
															await fetchOrders();
														} }
														icon={ archive }
														label={ __( 'Archive order', 'newspack-ads' ) }
														isQuaternary={ true }
														isSmall={ true }
														tooltipPosition="bottom center"
														disabled={ inFlight }
													/>
												) }
												<Button
													onClick={ async () => {
														setEditingOrder( order.id );
													} }
													icon={ pencil }
													label={ __( 'Edit order', 'newspack-ads' ) }
													isQuaternary={ true }
													isSmall={ true }
													tooltipPosition="bottom center"
													disabled={ inFlight }
												/>
												<Button
													href={ getOrderUrl( order.id ) }
													target="_blank"
													rel="external noreferrer noopener"
													icon={ link }
													label={ __( 'GAM Dashboard', 'newspack-ads' ) }
													isQuaternary={ true }
													isSmall={ true }
													tooltipPosition="bottom center"
												/>
											</div>
										}
										className="mv0"
										isSmall
									/>
								) ) }
							</Card>
							<h3>{ __( 'Create new order', 'newspack-ads' ) }</h3>
						</>
					) }
					<Order
						onUnrecoverable={ async () => {
							await fetchOrders();
						} }
						onCreate={ async () => {
							await fetchOrders();
							setIsManaging( false );
						} }
						onCancel={ () => setIsManaging( false ) }
						name={ orderName }
					/>
				</Modal>
			) }
			{ editingOrder && (
				<Modal
					title={ __( 'Edit Order', 'newspack-ads' ) }
					onRequestClose={ () => setEditingOrder( false ) }
				>
					<Order
						onUnrecoverable={ async () => {
							await fetchOrders();
						} }
						onCreate={ async () => {
							await fetchOrders();
							setEditingOrder( false );
						} }
						orderId={ editingOrder }
						onCancel={ () => setEditingOrder( false ) }
					/>
				</Modal>
			) }
		</Fragment>
	);
};

addFilter(
	'newspack.settingSection.bidding.beforeControls',
	'newspack-ads/header-bidding-gam',
	( AfterControls, props ) => {
		if ( props.sectionKey === 'bidding' ) {
			return <HeaderBiddingGAM { ...props } />;
		}
		return AfterControls;
	}
);
