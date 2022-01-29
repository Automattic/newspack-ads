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

/**
 * Newspack dependencies.
 */
import {
	ActionCard,
	Card,
	Notice,
	Modal,
	TextControl,
	Button,
	ProgressBar,
} from 'newspack-components';

/**
 * Internal dependencies.
 */
import './style.scss';

const { network_code, lica_batch_size } = window.newspack_ads_bidding_gam;

const getOrderUrl = orderId => {
	return `https://admanager.google.com/${ network_code }#delivery/order/order_overview/order_id=${ orderId }`;
};

const HeaderBiddingGAM = () => {
	const [ inFlight, setInFlight ] = useState( true );
	const [ isCreating, setIsCreating ] = useState( false );
	const [ initialOrder, setInitialOrder ] = useState( null );
	const [ orderName, setOrderName ] = useState( 'Newspack Header Bidding' );
	const [ order, setOrder ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ unrecoverable, setUnrecoverable ] = useState( null );
	const [ step, setStep ] = useState( 0 );
	const [ totalBatches, setTotalBatches ] = useState( 1 );
	const [ totalSteps, setTotalSteps ] = useState( 4 );
	const fetchOrder = async () => {
		setInFlight( true );
		let data;
		try {
			data = await apiFetch( {
				path: '/newspack-ads/v1/bidding/gam/order',
				method: 'GET',
			} );
			if ( data.line_item_ids?.length ) {
				const licaConfig = await fetchLicaConfig();
				const batches = Math.ceil( licaConfig.length / lica_batch_size );
				setTotalBatches( batches );
				setTotalSteps( 3 + batches );
			}
			setOrder( data );
			setError( null );
		} catch ( err ) {
			setError( err );
		} finally {
			setInFlight( false );
		}
		return data;
	};
	const updateOrderName = async () => {
		setInFlight( true );
		let data;
		try {
			data = await apiFetch( {
				path: '/newspack-ads/v1/bidding/gam/order_count',
				method: 'GET',
			} );
		} catch {
			// Ignore errors.
		} finally {
			if ( data?.count ) {
				setOrderName( `Newspack Header Bidding v${ data.count + 1 }` );
			} else {
				setOrderName( 'Newspack Header Bidding' );
			}
			setInFlight( false );
		}
	};
	const archiveOrder = async () => {
		return await apiFetch( {
			path: '/newspack-ads/v1/bidding/gam/order',
			method: 'DELETE',
		} );
	};
	const fetchLicaConfig = async () => {
		const licaConfig = await apiFetch( { path: '/newspack-ads/v1/bidding/gam/lica_config' } );
		return licaConfig;
	};
	const createType = async ( type, batch = 0 ) => {
		return await apiFetch( {
			path: '/newspack-ads/v1/bidding/gam/create',
			method: 'POST',
			data: {
				type,
				name: orderName,
				batch,
			},
		} );
	};
	const create = async () => {
		setError( null );
		setUnrecoverable( null );
		setInFlight( true );
		let pendingOrder = { ...order };
		try {
			if ( ! pendingOrder || ! pendingOrder.order_id ) {
				setStep( 1 );
				pendingOrder = await createType( 'order' );
				setOrder( pendingOrder );
			}
			if ( ! pendingOrder?.line_item_ids?.length ) {
				setStep( 2 );
				pendingOrder = await createType( 'line_items' );
				setOrder( pendingOrder );
			}
			const licaConfig = await fetchLicaConfig();
			const batches = Math.ceil( licaConfig.length / lica_batch_size );
			setTotalBatches( batches );
			setTotalSteps( 3 + batches );
			const start = pendingOrder?.lica_batch_count || 0;
			if ( batches > start ) {
				for ( let i = start; i < batches; i++ ) {
					const batch = i + 1;
					setStep( 2 + batch );
					pendingOrder = await createType( 'creatives', batch );
					setOrder( pendingOrder );
				}
			}
			setStep( 3 + batches );
			await fetchOrder();
			setIsCreating( false );
		} catch ( err ) {
			if ( initialOrder?.order_id ) {
				try {
					await archiveOrder();
					await updateOrderName();
				} catch {
					// Ignore errors while archiving unrecoverable order.
				}
				setUnrecoverable( err );
			} else {
				// Set as initial order so it fails unrecoverably on next attempt.
				if ( pendingOrder?.order_id ) {
					setInitialOrder( pendingOrder );
				}
				setError( err );
			}
		} finally {
			setStep( 0 );
			setInFlight( false );
		}
	};
	const getStepName = () => {
		switch ( step ) {
			case 0:
				return '';
			case 1:
				return __( 'Creating Order…', 'newspack-ads' );
			case 2:
				return __( 'Creating Line Items…', 'newspack-ads' );
			default:
				return __( 'Associating Creatives…', 'newspack-ads' );
		}
	};
	const isValid = () => {
		return (
			order &&
			order.order_id &&
			order.line_item_ids?.length &&
			order.lica_batch_count === totalBatches
		);
	};
	const canConfigure = () => {
		return ! inFlight && ! isCreating && ! isValid() && error?.data?.status !== '500';
	};
	const getMissingOrderMessage = () => {
		return inFlight
			? __( 'Loading…', 'newspack-ads' )
			: __( 'Missing order configuration', 'newspack-ads' );
	};
	useEffect( async () => {
		const result = await fetchOrder();
		await updateOrderName();
		setInitialOrder( result );
	}, [] );
	useEffect( () => {
		if ( unrecoverable ) {
			setOrder( null );
			setInitialOrder( null );
		}
	}, [ unrecoverable ] );
	useEffect( () => {
		if ( isCreating ) {
			window.onbeforeunload = () => {
				return __(
					'Are you sure you want to leave this page? Header bidding setup is incomplete.',
					'newspack'
				);
			};
		} else {
			window.onbeforeunload = null;
		}
	}, [ isCreating ] );
	const stepName = getStepName();
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
							<div className="newspack-ads__header-bidding-gam__order-description">
								{ order?.order_id ? (
									<span>
										{ __( 'Order:', 'newspack-ads' ) }{ ' ' }
										<a
											href={ getOrderUrl( order.order_id ) }
											target="_blank"
											rel="noopener noreferrer"
										>
											{ order.order_name }
										</a>
										{ ', ' }
										{ order?.line_item_ids
											? sprintf(
													// Translators: Number of line items in the order.
													_n(
														'containing %s line item',
														'containing %s line items',
														order.line_item_ids.length,
														'newspack-ads'
													),
													order.line_item_ids.length
											  )
											: __( 'missing line items configuration' ) }
										.
									</span>
								) : (
									getMissingOrderMessage()
								) }
							</div>
						) }
					</Fragment>
				) }
				checkbox={ isValid() ? 'checked' : 'unchecked' }
				actionText={ canConfigure() ? __( 'Configure', 'newspack-ads' ) : null }
				onClick={ () => setIsCreating( true ) }
			/>
			{ isCreating && (
				<Modal
					title={ __( 'Create Order', 'newspack-ads' ) }
					onRequestClose={ () => ! inFlight && setIsCreating( false ) }
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
					{ unrecoverable && (
						<Notice
							isError
							noticeText={ __(
								'We were unable to fix the issues with this order and have archived it. Please create a new order below.',
								'newspack-ads'
							) }
						/>
					) }
					<TextControl
						label={ __( 'Order name', 'newspack-ads' ) }
						disabled={ inFlight || order?.order_name }
						value={ order?.order_name ? order.order_name : orderName }
						onChange={ value => setOrderName( value ) }
					/>
					{ ! inFlight && order?.order_id && ! order?.line_item_ids?.length && (
						<Notice
							isWarning
							noticeText={ __( "Order exists but it's missing its line items.", 'newspack-ads' ) }
						/>
					) }
					{ ! inFlight &&
						order?.order_id &&
						order?.line_item_ids?.length &&
						totalBatches > ( order?.lica_batch_count || 0 ) && (
							<Notice
								isWarning
								noticeText={ __(
									'Order and line items exist, but are missing creative associations.',
									'newspack-ads'
								) }
							/>
						) }
					{ step && stepName ? (
						<Fragment>
							<Notice
								isWarning
								noticeText={ __(
									'This may take up to 15 minutes, please do not close the window.',
									'newspack-ads'
								) }
							/>
							<ProgressBar completed={ step } total={ totalSteps } label={ stepName } />
						</Fragment>
					) : null }
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
						<Button isPrimary disabled={ ! orderName || inFlight } onClick={ create }>
							{ initialOrder?.order_id
								? __( 'Fix issues', 'newspack-ads' )
								: __( 'Create Order', 'newspack-ads' ) }
						</Button>
					</Card>
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
