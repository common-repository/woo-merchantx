<?php

// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) { exit; }

// Gateway Class
class MerchantX_Payment_Gateway extends WC_Payment_Gateway {

	// Setup Function
	public function __construct() {
		global $woocommerce;
		$this->id = 'merchantx';
		$this->icon = null;
		$this->has_fields = false;
		$this->method_title = __( 'MerchantX Gateway For WooCommerce', 'woo-merchantx' );
		$this->init_form_fields();
		$this->init_settings();
		$this->title = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions' );
		$this->enable_for_methods = $this->get_option( 'enable_for_methods', [] );
		$this->transactiontype = sanitize_text_field( $this->get_option( 'transactiontype' ) );
		$this->finalorderstatus = $this->get_option( 'finalorderstatus' );
		$this->redirecturl = $this->get_option( 'redirecturl' );

		// Redirect if token defined - order was previously able to return in the url
		if( isset( $_GET['token-id'] ) && isset( $_GET['order'] ) && ! isset( $_GET['complete'] ) ) {
			
			// Split order by ***
			if( strpos( $_GET['order'], '***' ) !== false ) {
				$details = explode( '***', $_GET['order'] );
				$orderid = $details[0];
				$action = $details[1];
				$thisid = $details[2];
			} else {
				$action = '';
			}
			if( isset( $_GET['action'] ) && $action == 'addbilling' && $thisid == $this->id ) {
				$this->successful_request( '', sanitize_text_field( $_GET['order'] ), $details );

			// Saved payment method
			} elseif( isset( $_GET['rc'] ) && isset( $_GET['tid'] ) ) {
				$detials['ispaymentmethod'] = 'Y';
				$details['rc'] = sanitize_text_field( $_GET['rc'] );
				$details['tid'] = sanitize_text_field( $_GET['tid'] );
				$details['ac'] = sanitize_text_field( $_GET['ac'] );
				$details['ar'] = sanitize_text_field( $_GET['ar'] );
				$this->successful_request(
					sanitize_text_field( $_GET['token-id'] ), sanitize_text_field( $_GET['order'] ), $details
				);
			} else {
				$details['ispaymentmethod'] = 'N';
				$this->successful_request(
					sanitize_text_field( $_GET['token-id'] ), sanitize_text_field( $_GET['order'] ), $details
				);
			}
			wp_die();
		}

		// Hooks
		add_action( 'woocommerce_api_callback', [ $this, 'successful_request' ] );
		add_action( 'woocommerce_receipt_merchantx', [ $this, 'receipt_page' ] );
		add_action( 'woocommerce_confirm_order_merchantx', [ $this, 'confirm_order_page' ] );
		add_action( 'woocommerce_update_options_payment_gateways', [ $this, 'process_admin_options' ] );
		add_action( 'woocommerce_update_options_payment_gateways_merchantx', [ $this, 'process_admin_options' ] );

		// Feature Declaration		
		$this->supports = [ 'refunds', 'tokenization' ];
		// 'products'
		// 'subscriptions'
		// 'subscription_cancellation'
		// 'subscription_suspension'
		// 'subscription_reactivation'
		// 'subscription_amount_changes'
		// 'subscription_date_changes'
		// 'subscription_payment_method_change'
	}

	// Admin Panel Options
	function admin_options() {
		echo sprintf(
			'<h3>%s</h3>',
			__( 'MerchantX Gateway For WooCommerce', 'woo-merchantx' )
		);
		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
	}

	// Initialise Gateway Settings Form Fields
	public function init_form_fields() {
		$this->form_fields = [
			'enabled' => [
				'title' => __( 'Enable/Disable', 'woo-merchantx' ),
				'type' => 'checkbox',
				'label' => __( 'Enable MerchantX Gateway For WooCommerce', 'woo-merchantx' ),
				'default' => 'no',
			],
			'title' => [
				'title' => __( 'Title', 'woo-merchantx' ),
				'type' => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woo-merchantx' ),
				'default' => __( 'MerchantX Gateway For WooCommerce', 'woo-merchantx' ),
				'desc_tip' => true,
			],
			'description' => [
				'title' => __( 'Description', 'woo-merchantx' ),
				'type' => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woo-merchantx' ),
				'desc_tip' => true,
				'default' => '',
			],
			'instructions' => [
				'title' => __( 'Instructions', 'woo-merchantx' ),
				'type' => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page.', 'woo-merchantx' ),
				'desc_tip' => true,
				'default' => '',
			],
			'apikey' => [
				'title' => __( 'API Key', 'woo-merchantx' ),
				'type' => 'text',
				'description' => '',
				'desc_tip' => true,
				'default' => '',
			],
			'savepaymentmethodstoggle' => [
				'title' => __( 'Turn Saved Payment Methods On/Off', 'woo-merchantx' ),
				'type' => 'select',
				'description' => __( 'Allows you to turn saved payment methods on and off.', 'woo-merchantx' ),
				'default' => 'off',
				'desc_tip' => true,
				'options' => [
					'on' => __( 'On', 'woo-merchantx' ),
					'off' => __( 'Off', 'woo-merchantx' ),
				],
			],
			'transactiontype' => [
				'title' => __( 'Transaction Type', 'woo-merchantx' ),
				'type' => 'select',
				'description' => '',
				'default' => '',
				'desc_tip' => true,
				'options' => [
					'auth' => __( 'Authorize Only', 'woo-merchantx' ),
					'sale' => __( 'Authorize & Capture', 'woo-merchantx' ),
				],
			],
			'finalorderstatus' => [
				'title' => __( 'Final Order Status', 'woo-merchantx' ),
				'type' => 'select',
				'description' => __( 'This option allows you to set the final status of an order after it has been processed successfully by the gateway.', 'woo-merchantx' ),
				'default' => 'Processing',
				'desc_tip' => true,
				'options' => [
					'Processing' => __( 'Processing', 'woo-merchantx' ),
					'Ready to Ship' => __( 'Ready to Ship', 'woo-merchantx' ),
					'Completed' => __( 'Completed', 'woo-merchantx' ),
				],
			],
			'redirecturl' => [
				'title' => __( 'Return URL', 'woo-merchantx' ),
				'type' => 'text',
				'description' => __( 'This is the URL the user will be taken to once the sale has been completed. Please enter the full URL of the page. It must be an active page on the same website. If left blank, it will take the buyer to the order received page in their account.', 'woo-merchantx' ),
				'desc_tip' => true,
				'default' => get_site_url() . '/cart/',
			],
		];
	}

	// Displays description
	function payment_fields() {
		if( ! empty( $this->description ) ) {
			echo wpautop( wp_kses_post( $this->description ) );
		}
	}

	// Process the payment and return the result
	function process_payment( $order_id ) {
		global $woocommerce;
		$woo_version = class_exists( 'WooCommerce' ) ? $woocommerce->version : 'old';
		$order = new WC_Order( $order_id );
		$order_key = $order->get_order_key();  

		// Get redirect url based on the version
		$redirect = ( $woo_version == 'old' )
			? add_query_arg(
				'order', $order->get_id(), add_query_arg(
					'key', $order_key, get_permalink( get_option( 'woocommerce_pay_page_id' ) )
				)
			) : $order->get_checkout_payment_url( true );

		// Return
		return [ 'result' => 'success', 'redirect' => $redirect ];
	}

	function receipt_page( $order_id ) {
		global $woocommerce;
		$order = new WC_Order( $order_id );
		wp_enqueue_script( 'backThatUp', plugin_dir_url( __FILE__ ) . '/js/backToCheckout.js' );

		// Logged in
		if( get_current_user_id() > 0 ) {
			$user = get_userdata( get_current_user_id() );
			$user_email = sanitize_email( $user->user_email );
			$userid = sanitize_text_field( $user->user_id );
		} else {
			$user_email = '';
			$userid = '';
		}
		$customervaultid = '';

		// Get settings
		$this->init_settings();

		// Check for ssl
		if( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] != 'off' ) { $isssl = 'Y'; }
		else { $isssl = 'N'; }
		
		$order_shipping = get_post_meta( $order->get_id(), '_order_shipping', true );
		$order_discount = get_post_meta( $order->get_id(), '_cart_discount', true );
		$order_discount_tax = get_post_meta( $order->get_id(), '_cart_discount_tax', true );
		$order_tax = get_post_meta( $order->get_id(), '_order_tax', true );
		$order_shipping_tax = get_post_meta( $order->get_id(), '_order_shipping_tax', true );
		$order_total = get_post_meta( $order->get_id(), '_order_total', true );

		// Fix for having product names with double quotes - thanks to abirchler for pointing this one out
		$data = [
			'thisid' => merchantx_cleanTheData( $this->id ),
			'orderid' => merchantx_cleanTheData( $order_id, 'integer' ),
			'transactiontype' => merchantx_cleanTheData( $this->transactiontype, 'string' ),
			'user_email' => sanitize_email( $user_email ),
			'userid' => sanitize_text_field( get_current_user_id() ),
			'ordertotal' => $order_total,
			'ordertax' => $order_tax,
			'ordershipping' => $order_shipping,
			'billingfirstname' => merchantx_cleanTheData( $order->get_billing_first_name(), 'string' ), 
			'billinglastname' => merchantx_cleanTheData( $order->get_billing_last_name(), 'string' ), 
			'billingaddress1' => merchantx_cleanTheData( $order->get_billing_address_1(), 'string' ), 
			'billingcity' => merchantx_cleanTheData( $order->get_billing_city(), 'string' ), 
			'billingstate' => merchantx_cleanTheData( $order->get_billing_state(), 'string') ,
			'billingpostcode' => merchantx_cleanTheData( $order->get_billing_postcode(), 'string' ),
			'billingcountry' => merchantx_cleanTheData( $order->get_billing_country(), 'string' ),
			'billingemail' => sanitize_email( $order->get_billing_email() ),
			'billingphone' => sanitize_text_field( $order->get_billing_phone() ),
			'billingcompany' => merchantx_cleanTheData( $order->get_billing_company(), 'string' ),
			'billingaddress2' => merchantx_cleanTheData( $order->get_billing_address_2(), 'string' ),
			'shippingfirstname' => merchantx_cleanTheData( $order->get_shipping_first_name(), 'string' ),
			'shippinglastname' => merchantx_cleanTheData( $order->get_shipping_last_name(), 'string' ),
			'shippingaddress1' => merchantx_cleanTheData( $order->get_shipping_address_1(), 'string' ),
			'shippingcity' => merchantx_cleanTheData( $order->get_shipping_city(), 'string' ),
			'shippingstate' => merchantx_cleanTheData( $order->get_shipping_state(), 'string' ),
			'shippingpostcode' => sanitize_text_field( $order->get_shipping_postcode() ),
			'shippingcountry' => merchantx_cleanTheData( $order->get_shipping_country(), 'string' ),
			'shippingphone' => sanitize_text_field( $order->get_billing_phone() ),
			'shippingcompany' => merchantx_cleanTheData( $order->get_shipping_company(), 'string' ),
			'shippingaddress2' => merchantx_cleanTheData( $order->get_shipping_address_2(), 'string' ),
			'security' => wp_create_nonce( 'checkout-nonce' ),
		];
		
		// Loop through items, build array
		$items = $order->get_items();
		$y = 0;
		foreach( array_values( $items ) as $x => $item ) {
			$data['items'][$x] = [
				'productid' => $item['product_id'],
				'name' => htmlspecialchars( $item['name'] ),
				'line_total' => $item['line_total'], 
				'qty' => $item['qty'],
				'line_subtotal' => $item['line_subtotal']
			];
			$y ++;
		}

?>
<style type="text/css">
	ul.paymentmethods {
		list-style: none;
		margin-left: 0;
	}
	ul.paymentmethods li, #pmtable {
		width: 100%;
		padding: 3px;
		margin-bottom: 5px;
		border: 1px solid #CCCCCC;
		background-color: #FDFDFD;
	}
	ul.paymentmethods li.active, #pmtable.active {
		border: 3px solid #333333;
	}
	ul.paymentmethods li label {
		width: 100%;
		display: block;
	}
	.cc {
		width: 64px;
		height: 40px;
		float: right;
		background-image: url('<?php echo plugins_url( 'img/icon_cc_blank.png', __FILE__ ); ?>' );
		background-repeat: no-repeat;
		background-size: contain;
		background-position: center center;
	}
	.cc.Visa {
		background-image: url( '<?php echo plugins_url( 'img/icon_cc_visa.png', __FILE__ ); ?>' );
	}
	.cc.Mastercard {
		background-image: url( '<?php echo plugins_url( 'img/icon_cc_mastercard.png', __FILE__ ); ?>' );
	}
	.cc.Discover {
		background-image: url( '<?php echo plugins_url( 'img/icon_cc_discover.png', __FILE__ ); ?>' );
	}
	.cc.Amex {
		background-image: url( '<?php echo plugins_url( 'img/icon_cc_amex.png', __FILE__ ); ?>' );
	}
</style>
<script type="text/javascript">
	function merchantx_arrangeData() {
		var data = JSON.parse( '<?php echo json_encode( $data ); ?>' );
		var pmselected = 'N';
		var radios = document.getElementsByName( 'paymentmethod' );
		for( var i = 0, len = radios.length; i < len; i ++ ) {
			if( radios[i].checked ) { pmselected = 'Y'; }
		}		
		if( pmselected == 'N' ) {
			alert( '<?php _e( 'Please select a payment method or create a new one.', 'woo-merchantx' ); ?>' );
			document.getElementById( 'backbutton' ).disabled = false;
			document.getElementById( 'submitbutton' ).disabled = false;
			document.getElementById( 'spinner' ).style.display = 'none';
			return false;
		}
		document.getElementById( 'backbutton' ).disabled = true;
		document.getElementById( 'submitbutton' ).disabled = true;
		document.getElementById( 'spinner' ).style.display = 'block';
		var elementexists = document.getElementById( 'savepaymentmethod' );
		var savepaymentmethod = ( elementexists && document.getElementById( 'savepaymentmethod' ).checked )
			? 'Y' : 'N';
		var billingid = ( document.getElementById( 'paymentmethodid' ).value != '' )
			? document.getElementById( 'paymentmethodid' ).value : '';
		var customervaultid = document.getElementById( 'customervaultid' ).value;
		var last4 = document.getElementById( 'billingccnumber' ).value.slice( -4 );
		var expiry = document.getElementById( 'billingccexp' ).value;
		data['action'] = ( billingid != '' ) ? 'merchantx_stepOne' : 'merchantx_stepOne_addBilling';
		data['savepaymentmethod'] = savepaymentmethod;
		data['customervaultid'] = customervaultid;
		data['billingid'] = billingid;
		data['last4'] = last4;
		data['expiry'] = expiry;
		data['itemcount'] = <?php echo $y; ?>;
		return merchantx_stepOne( data, '<?php echo plugin_dir_url( __FILE__ ); ?>' );
	}

	// Using the event, make sure the list item is being clicked on, not the delete link
	function merchantx_toggleState( id, vaultid, total, e ) {
		// console.log( e.target.nodeName );
		if( e.target.nodeName == 'INPUT' ) {
			document.getElementById( 'paymentmethodid' ).value = '';
			for( var x = 1; x <= total; x ++ ) {
				document.getElementById( 'paymentmethodli' + x ).className = '';
			}
			document.getElementById( 'pmtable' ).className = '';
			document.getElementById( 'paymentmethodnew' ).className = '';
			if( id != 'new' ) {
				document.getElementById( 'paymentmethodli' + id ).className = 'active';
				document.getElementById( 'paymentmethodid' ).value
					= document.getElementById( 'paymentmethod' + id ).value;
				document.getElementById( 'customervaultid' ).value = vaultid;
			} else {
				document.getElementById( 'paymentmethodnew' ).checked = true;
				document.getElementById( 'pmtable' ).className = 'active';
			}
		}
	}

	function merchantx_cc_validate() {

		// Disable form fields
		document.getElementById( 'backbutton' ).disabled = true;
		document.getElementById( 'submitbutton' ).disabled = true;
		document.getElementById( 'spinner' ).style.display = 'block';
		
		// Only do this if the new cc number option is checked
		if( document.getElementById( 'paymentmethodnew' ).checked ) {
			var ccnumber = document.getElementById( 'billingccnumber' ).value;
			var ccexp = document.getElementById( 'billingccexpmonth' ).value
				+ '/' + document.getElementById( 'billingccexpyear' ).value;
			var error = '';

			// Validate ccnumber, remove all spaces and check for non-numeric chars.
			// If it fails, show an alert.  otherwise, the gateway will handle a failed number too
			var test_ccnumber = ccnumber.replace( / -/g,'' );
			if( isNaN( test_ccnumber ) == true ) {
				error += '- <?php _e( 'Not a valid credit card number.', 'woo-merchantx' ); ?>' + "\n";
			}
			if( test_ccnumber == '' ) {
				error += '- <?php _e( 'Credit card number must not be blank.', 'woo-merchantx' ); ?>' + "\n";
			}

			// Check expiration date
			if(
				document.getElementById( 'billingccexpmonth' ).value != ''
				&& document.getElementById( 'billingccexpmonth' ).value != ''
			) {
				document.getElementById( 'billingccexp' ).value
					= document.getElementById( 'billingccexpmonth' ).value
					+ document.getElementById('billingccexpyear' ).value;
			} else {
				error += '- <?php _e( 'Valid Expiration Date.', 'woo-merchantx' ); ?>';
			}
			if( error != '' ) {
				alert( '<?php _e( 'Please make sure the following are correct:', 'woo-merchantx' ); ?>' + "\n" + error );
				document.getElementById( 'backbutton' ).disabled = false;
				document.getElementById( 'submitbutton' ).disabled = false;
				document.getElementById( 'spinner' ).style.display = 'none';
				return false;
			} else {
				merchantx_arrangeData();
			}

		// Implies user is selecting a saved pm
		} else {
			merchantx_arrangeData();
		}
	}
</script>

		<?php
		if( MERCHANTX_API_KEY != '' ) {
			echo sprintf(
				'
					<h3>%s</h3>
					<form name="submitpayment" id="submitpayment" action="" method="POST">
				',
				__( 'Pay via the MerchantX Payment Gateway', 'woo-merchantx' )
			);

			$paymentmethods = [];

			// Get the saved payment tokens from WC
			if( is_user_logged_in() && $this->settings['savepaymentmethodstoggle'] == 'on' ) {
				$payment_tokens = WC_Payment_Tokens::get_customer_tokens( get_current_user_id() );

				$token = [];
				foreach( $payment_tokens as $pt ) {
					$billingid = $pt->get_token();

					// Query to get the rest of the details for this billing id
					$paymentmethod = merchantx_getPMDetailsByVaultId( $billingid );
					$customervaultid = isset( $paymentmethod['customer']['@attributes']['id'] )
						? $paymentmethod['customer']['@attributes']['id'] : '';
					$thispm = [
						'tokenid' => '', //$tokenid,
						'internalid' => $pt->get_id(),
						'billingid' => $billingid,
						'customervaultid' => $customervaultid,
						'ccnumber' => $paymentmethod['customer']['billing']['cc_number'],
						'ccexp' => substr_replace( $paymentmethod['customer']['billing']['cc_exp'], '/', 2, 0 ),
						'cardtype' => $pt->get_card_type(),
					];
					array_push( $paymentmethods, $thispm );
				}

				if( count( $paymentmethods ) > 0 ) {
					echo sprintf(
						'
							<h4>%s</h3>
							<ul class="paymentmethods">
						',
						__( 'Saved Payment Methods', 'woo-merchantx' )
					);
					$nonce = wp_create_nonce( 'delete-pm-nonce' );
					for( $x = 0 ;	$x < count( $paymentmethods ) ;	 $x ++ ) {
						$y = $x + 1;
						if( $customervaultid == '' ) {
							$customervaultid = $paymentmethods[$x]['customervaultid'];
						}
						echo sprintf(
							'
								<li style="margin-bottom:5px;padding:10px;" id="paymentmethodli%d" onclick="merchantx_toggleState( %d, \'%s\', %d, event );">
									<input type="radio" name="paymentmethod" id="paymentmethod%d" value="%s" style="float:left;margin-right:15px;display:none;">
									<label for="paymentmethod%d">
										<div class="cc %s"></div>
										<div style="float:left;">
											<b>Card ending in %s</b><br>
											<em>Expires %s</em> |
											<a href="#" onclick="merchantx_deletePM( \'%s\', \'%s\', %d, event, \'%s\', \'%s\' ); return false;">%s</a>
											<br clear="all">
										</div>
										<br clear="all">
									</label>
								</li>
							',
							$y,
							$y,
							esc_html( $paymentmethods[$x]['customervaultid'] ),
							count( $paymentmethods ),
							$y,
							esc_html( $paymentmethods[$x]['billingid'] ),
							$y,
							merchantx_getCardType( $paymentmethods[$x]['ccnumber'] ),
							esc_html( $paymentmethods[$x]['ccnumber'] ),
							esc_html( $paymentmethods[$x]['ccexp'] ),
							esc_html( $paymentmethods[$x]['billingid'] ),
							esc_html( $paymentmethods[$x]['customervaultid'] ),
							$y,
							wp_create_nonce( 'delete_pm' . $paymentmethods[$x]['billingid'] ),
							esc_html( $paymentmethods[$x]['internalid'] ),
							__( 'Delete', 'woo-merchantx' )
						);
					}
					echo  '</ul>';
				}
			}

			// Expiration Years
			$exp_years = '';
			for( $x = date( 'y' ); $x <= ( date( 'y') + 15 ); $x ++ ) {
				$dsp = ( $x < 10 ) ? 0 : '';
				$exp_years .= sprintf( '<option value="%s%s">20%s%s</option>', $dsp, $x, $dsp, $x );
			}

			// Action Buttons
			$buttons_html = sprintf(
				'
					<input type ="button" id="backbutton" value="Back to Cart" style="float:left;"
						onclick="merchantx_backToCheckout( \'%s\', \'\' );" />
					<input type="button" id="submitbutton" value="Submit" style="float:left;margin-left:15px;"
						onclick="merchantx_cc_validate();" />
					<img src="%s" id="spinner" style="display:none;float:left;padding-top:10px;margin-left:15px;" />
				',
				$order_id,
				plugins_url( 'img/spinner.gif', __FILE__ )
			);

			echo sprintf(
				'
					<div style="display:none;">
						<input type="text" name="paymentmethodid" id="paymentmethodid" value="" class="large"><br />
						<input type="text" name="customervaultid" id="customervaultid" value="%s" class="large"><br />
					</div>
					%s
					<div id="timeoutdsp" class="woocommerce" style="display:none;">
						<ul class="woocommerce-info" style="list-style:none;">
							<li>%s %s</li>
						</ul>
					</div>
					<label for="paymentmethodnew" onclick="merchantx_toggleState( \'new\', \'%s\', %s, event );">
					<table id="pmtable">
						<tr>
							<td colspan="2" style="display:none;">
								<input type="radio" name="paymentmethod" id="paymentmethodnew" value="new"
									onclick="merchantx_toggleState( \'new\', \'%s\', %s, event );">
							</td>
						</tr>
						<tr>
							<td>%s</td>
							<td>
								<input type ="text" name="billing-cc-number" id="billingccnumber" value="" size="16" maxlength="16"
									onclick="merchantx_toggleState( \'new\', \'%s\', %s, event );">
							</td>
						</tr>
						<tr>
							<td>%s</td>
							<td>
								<select name="billing-cc-exp-month" id="billingccexpmonth"
									onclick="merchantx_toggleState( \'new\', \'%s\', %s, event );">
									<option value="">---</option>
									<option value="01">Jan</option>
									<option value="02">Feb</option>
									<option value="03">Mar</option>
									<option value="04">Apr</option>
									<option value="05">May</option>
									<option value="06">Jun</option>
									<option value="07">Jul</option>
									<option value="08">Aug</option>
									<option value="09">Sep</option>
									<option value="10">Oct</option>
									<option value="11">Nov</option>
									<option value="12">Dec</option>
								</select>
								/
								<select name="billing-cc-exp-year" id="billingccexpyear"
									onclick="merchantx_toggleState( \'new\', \'%s\', %s, event );">
									<option value="">----</option>
									%s
								</select>
								<input type="hidden" name="billing-cc-exp" id="billingccexp" value="">
							</td>
						</tr>
						<tr>
							<td>%s</td>
							<td>
								<input type ="text" name="cvv" id="cvv" value="" size="4" maxlength="4"
									onclick="merchantx_toggleState( \'new\', \'%s\', %s, event );">
							</td>
						</tr>
						%s
					</table>
				</label>
				<br clear="all">
				<div id="buttons" onload="checkJS()">%s %s</div>
				<script type="text/javascript">
					function checkJS() {
						var buttons = \'%s\';
						document.getElementById( \'buttons\' ).innerHTML = buttons;
					}
					checkJS();
				</script>	
				</form>
				<form name="submitpaymentmethod" id="submitpaymentmethod" method="post" action=""></form>
				',
				esc_html( $customervaultid ),
				( is_user_logged_in() && $this->settings['savepaymentmethodstoggle'] == 'on' )
					? sprintf( '<h4>%s</h4>', __( 'New Payment Method', 'woo-merchantx' ) )
					: sprintf( '<h4>%s</h4>', __( 'Please Enter Your Payment Information Below', 'woo-merchantx' ) ),
				__( 'Your checkout has been sitting still for a while.', 'woo-merchantx' ),
				__( 'Please submit your payment or we\'ll take you back to your cart contents in a few minutes.', 'woo-merchantx' ),
				esc_html( $customervaultid ),
				count( $paymentmethods ),
				esc_html( $customervaultid ),
				count( $paymentmethods ),
				__( 'Credit Card Number', 'woo-merchantx' ),
				esc_html( $customervaultid ),
				count( $paymentmethods ),
				__( 'Expiration Date', 'woo-merchantx' ),
				esc_html( $customervaultid ),
				count( $paymentmethods ),
				esc_html( $customervaultid ),
				count( $paymentmethods ),
				$exp_years,
				__( 'CVV', 'woo-merchantx' ),
				esc_html( $customervaultid ),
				count( $paymentmethods ),
				( is_user_logged_in() && $this->settings['savepaymentmethodstoggle'] == 'on' )
					? sprintf(
					'
						<tr>
							<td colspan="2">
								<label for="savepaymentmethod">
									<input type="checkbox" name="savepaymentmethod" id="savepaymentmethod" value="Y"> %s
								</label>
							</td>
						</tr>
					',
					__( 'Save this payment method for later?', 'woo-merchantx' )
				) : '',
				__( 'JavaScript has been disabled in this browser.', 'woo-merchantx' ),
				__( 'Please enable it or update your browser to place this order.', 'woo-merchantx' ),
				str_replace( "'", "\\'", preg_replace( '/[\t\n\r]/', '', $buttons_html ) )
			);
		} else {
			echo sprintf(
				'
					<div style="color: red; font-size:16px; border:1px solid red; border-radius:10px; background-color:#FDFDFD; padding:15px;"><b>%s</b><br>%s</div>
				',
				__( 'Checkout is not available at this time.', 'woo-merchantx' ),
				__( 'Please try again later.', 'woo-merchantx' )
			);
		}
	}

	// Successful Payment
	function successful_request( $tokenid, $orderid, $details = [] ) {
		global $woocommerce;

		// Check to see if the order var contains an action		  
		if( strpos( $orderid, '***' ) ) {
			$splitme = explode( '***', $orderid );
			$orderid = $splitme[0];
			$splitme_action = explode( '=', $splitme[1] );
			$action = $splitme_action[1];
		}

		$order = new WC_Order( $orderid );

		// Define redirect url.	pull from settigns value or use default
		$redirecturl = $this->redirecturl;
		if( $redirecturl == '' ) {
			$redirecturl = '/checkout/order-received/' . $orderid . '/?key=' . $order->get_order_key();
		}
		$redirecturl_dev = $order->get_checkout_order_received_url();
		$redirecturl = $order->get_checkout_order_received_url();
		if( ! is_array( $details ) ) { $details = []; }

		// Sale action
		if( $action == 'addbilling' ) {
			$order = new WC_Order( $orderid );
			// $order_data = $order->get_data();
			$order_total = $order->get_total();
			$payment_status = 'OK';
			$token = sanitize_text_field($_GET['token-id']);
			$oncomplete_action = $this->finalorderstatus;

			// Check order status
			if( $order->get_status() != 'Completed' ) {
				if( $details['ispaymentmethod'] == 'N' ) {
					$body = sprintf(
						'
							<complete-action> 
								<api-key>%s</api-key>
								<token-id>%s</token-id>
							</complete-action>
						',
						MERCHANTX_API_KEY, $token
					);

					// Send
					$args = [
						'headers' => [ 'Content-type' => 'text/xml; charset=utf-8' ],
						'body' => preg_replace( '/[\t\n\r]/', '', $body )
					];
					$response = wp_remote_post( MERCHANTX_3STEP_URL, $args );
					if( is_wp_error( $response ) || empty( $response['body'] ) ) {
						$logger = wc_get_logger();
						$logger->error( 'successful_request() 1: ' . print_r( $response, true ), [ 'source' => 'woo-merchantx' ] );
						return false;
					}
					$xml = simplexml_load_string( $response['body'], 'SimpleXMLElement', LIBXML_NOCDATA );
					$json = json_encode( $xml );
					$result = json_decode( $json, true );	 

					// Need to have the correct values coming back
					if( $result['result'] != 1 ) {
						$payment_status = 'failure';
						$dsp_error = explode( 'REFID', $result['result-text'] );
						$dsp_error = $dsp_error[0];
						$dsp_error = $result['result-text'];

						// Friendly errors as needed
						if( $dsp_error == 'DECLINE' ) {
							$dsp_error = __( 'Your card has been declined', 'woo-merchantx' );
						}

						// Add extra info, if available
						if( merchantx_getResultCodeText( $result['result-code'] ) != '' ) {
							$dsp_error.= ' (' . merchantx_getResultCodeText( $result['result-code'] ) . ')';
						}

						// Display confirmation message
						wc_add_notice(
							sprintf( __( 'There was a problem with your order: %s', 'woo-merchantx' ), $dsp_error ),
							'error'
						);
						$order->update_status( 'failed', $error_message . ', ' . $dsp_error );

						// Exit & go back to the cart to display the error
						wp_safe_redirect( $redirecturl );
						exit;
					} else {
						$userid = get_current_user_id();
						$customerid = $result['customer-id'];
						$customervaultid = $result['customer-vault-id'];
						$billingid = $result['billing']['billing-id'];
						$ccnumber = $result['billing']['cc-number'];
						$ccexp = $result['billing']['cc-exp'];
						$cctype = merchantx_getCardType( $ccnumber );
						$last4 = substr( $ccnumber, -4 );

						// New Token
						$token = new WC_Payment_Token_CC();
						$token->set_token( $billingid );
						$token->set_gateway_id( '' );
						$token->set_user_id( $userid );
						$token->set_last4( $last4 );
						$token->set_expiry_month(substr( $ccexp, 0, 2 ) );
						$token->set_expiry_year( '20' . substr( $ccexp, -2 ) );
						$token->set_card_type( $cctype );
						$token->save();	   

						// Send order to gateway
						$products = '';
						$items = $order->get_items();
						foreach( $items as $item ) {
							$products .= merchantx_build_xml_product( $item );
						}
						$body = sprintf(
							'
								<sale> 
									<api-key>%s</api-key>
									<amount>%s</amount>
									<customer-vault-id>%s</customer-vault-id>
									<order-id>%s</order-id>
									<billing>
										<billing-id>%s</billing-id>
									</billing>
									%s
								</sale>
							',
							MERCHANTX_API_KEY, $order_total, $customervaultid, $orderid, $billingid, $products
						);

						// Send
						$args = [
							'headers' => [ 'Content-type' => 'text/xml; charset=utf-8' ],
							'body' => preg_replace( '/[\t\n\r]/', '', $body )
						];
						$response = wp_remote_post( MERCHANTX_3STEP_URL, $args );
						if( is_wp_error( $response ) || empty( $response['body'] ) ) {
							$logger = wc_get_logger();
							$logger->error(
								'successful_request() 2: ' . print_r( $response, true ), [ 'source' => 'woo-merchantx' ]
							);
							return false;
						}
						$xml = simplexml_load_string( $response['body'], 'SimpleXMLElement', LIBXML_NOCDATA );
						$json = json_encode( $xml );
						$result = json_decode( $json, true );	 

						// Update subscription status?
						$payment_status = 'OK';
					}
				} else {
					$payment_status = 'OK';
					$result['result-code'] = sanitize_text_field( $details['rc'] );
					$result['transaction-id'] = sanitize_text_field( $details['tid'] );
					$result['authorization-code'] = sanitize_text_field( $details['ac'] );
					$result['avs-result'] = sanitize_text_field( $details['ar'] );
				}

				// Finalize order
				if( $payment_status == 'OK' ) {
					$resultCodeText = merchantx_getResultCodeText( $result['result-code'] );

					// Payment has been successful
					$order->add_order_note( __( 'MerchantX Gateway payment completed.', 'woo-merchantx' ) );

					// Add helpful note
					$note = '
						Transaction ID: ' . sanitize_text_field( $result['transaction-id'] ) . '
						Result Code Text: ' . sanitize_text_field( $resultCodeText ) . ' (Code: ' . $result['result-code'] . ')
						Authorization Code: ' . sanitize_text_field( $result['authorization-code'] ) . '
						Address Match: ' . sanitize_text_field( $result['avs-result'] ) . '
					';
					$order->add_order_note( $note );

					// Mark as paid/payment complete
					$order->payment_complete( $token );

					// Empty cart
					$woocommerce->cart->empty_cart();

					// Only flag as completed if the settings tell us to do so
					if( $oncomplete_action == 'Completed' ) {
						$order->update_status( 'completed', __( 'Successful payment by the MerchantX Gateway', 'woo-merchantx' ) );

					// Flag the order as completed in the eyes of woo
					} elseif ($oncomplete_action == 'Ready to Ship') {
						$order->update_status( 'ready-to-ship', __( 'Successful payment by the MerchantX Gateway', 'woo-merchantx' ) );
					}
					
					// If woocommerce_thankyou exists and the settings checkbox is checked, run it
					if( has_action( 'woocommerce_thankyou' ) ) {
						do_action( 'woocommerce_thankyou', $orderid );
					}

					// Display confirmation message
					wc_add_notice( __( 'Your order is complete! Thank you!', 'woo-merchantx' ), 'success' );

					// Redirect to the empty cart w/ display message
					wp_safe_redirect($redirecturl);
					exit;

				// Payment fails
				} else {
					$note = sprintf(
						__( 'Result Code Text: %s (Code: %s)', 'woo-merchantx' ),
						$resultCodeText, $result['result-code']
					);
					$order->add_order_note( $note );
					wc_add_notice( sprintf( __( 'Payment Failed: %s', 'woo-merchantx' ), $dsp_error ), 'error' );
					return false;
				}
			}

		// Sale action
		} else {
			$order = new WC_Order( $orderid );
			$payment_status = 'OK';
			$token = sanitize_text_field( $_GET['token-id'] );
			$oncomplete_action = $this->finalorderstatus;
			
			// Check order status
			if( $order->get_status() != 'Completed' ) {
				if( $details['ispaymentmethod'] == 'N' ) {
					$body = sprintf(
						'
							<complete-action> 
								<api-key>%s</api-key>
								<token-id>%s</token-id>
							</complete-action>
						',
						MERCHANTX_API_KEY, $token
					);
					$args = [
						'headers' => [ 'Content-Type' => 'text/xml; charset=utf-8' ],
						'body' => preg_replace( '/[\t\n\r]/', '', $body )
					];
					$response = wp_remote_post( MERCHANTX_3STEP_URL, $args );
					if( is_wp_error( $response ) ) {
						$logger = wc_get_logger();
						$logger->error( 'successful_request() 3: ' . print_r( $response, true ), [ 'source' => 'woo-merchantx' ] );
						return false;
					}
					if( is_object( ( $response ) ) ) {
						$response = $response->errors;
						$result['result'] = 'Failed Transaction';
						$result['result-text'] = $response['http_request_failed'][0];
						$result['result-code'] = '';
					} else {
						$xml = simplexml_load_string( $response['body'], 'SimpleXMLElement', LIBXML_NOCDATA );
						$json = json_encode( $xml );
						$result = json_decode( $json, true );	 
					}

					// Failure
					if( $result['result'] != 1 ) {
						$payment_status = 'failure';

						// Split error message to hide REFID: 
						$dsp_error = explode( 'REFID', $result['result-text'] );
						$dsp_error = $dsp_error[0];
						$dsp_error = $result['result-text'];

						// Friendly errors as needed
						if( $dsp_error == 'DECLINE' ) {
							$dsp_error = 'Your card has been declined';
						}

						// Add extra info, if available
						if( merchantx_getResultCodeText( $result['result-code'] ) != '' ) {
							$dsp_error .= ' (' . merchantx_getResultCodeText( $result['result-code'] ) . ')';
						}

						// Display confirmation message
						wc_add_notice( sprintf( __( 'There was a problem with your order: %s', 'woo-merchantx' ), $dsp_error ), 'error' );

						$order->update_status( 'failed', $error_message . ', ' . $dsp_error );

						// Die & go back to the cart to display the error
						wp_safe_redirect( $redirecturl );
						exit;

					// Success
					} else {
						$payment_status = 'OK';
					}

				} else {
					$payment_status = 'OK';
					$result['result-code'] = sanitize_text_field( $details['rc'] );
					$result['transaction-id'] = sanitize_text_field( $details['tid'] );
					$result['authorization-code'] = sanitize_text_field( $details['ac'] );
					$result['avs-result'] = sanitize_text_field( $details['ar'] );
				}

				// Finalize order
				if( $payment_status == 'OK') {			  
					$resultCodeText = merchantx_getResultCodeText( $result['result-code'] );

					// Payment has been successful
					$order->add_order_note( __( 'MerchantX Gateway payment completed.', 'woo-merchantx' ) );

					// Add helpful note
					$note = '
						Transaction ID: ' . sanitize_text_field( $result['transaction-id'] ) . '
						Result Code Text: ' . sanitize_text_field( $resultCodeText ) . ' (Code: ' . $result['result-code'] . ')
						Authorization Code: ' . sanitize_text_field( $result['authorization-code'] ) . '
						Address Match: ' . sanitize_text_field( $result['avs-result'] ) . '
					';
					$order->add_order_note( trim( $note ) );

					// Mark as paid/payment complete
					$order->payment_complete( $token );
					
					// Empty cart
					$woocommerce->cart->empty_cart();

					// Only flag as completed if the settings tell us to do so
					if( $oncomplete_action == 'Completed' ) {
						$order->update_status(
							'completed', __( 'Successful payment by the MerchantX Gateway.', 'woo-merchantx' )
						);

					// Flag the order as completed in the eyes of woo
					} elseif( $oncomplete_action == 'Ready to Ship' ) {
						$order->update_status(
							'ready-to-ship', __( 'Successful payment by the MerchantX Gateway.', 'woo-merchantx' )
						);
					}

					// If woocommerce_thankyou exists and the settings checkbox is checked, run it
					if( has_action( 'woocommerce_thankyou' ) ) {
						do_action( 'woocommerce_thankyou', $orderid );
					}

					// Display confirmation message
					wc_add_notice( __( 'Your order is complete! Thank you!', 'woo-merchantx' ), 'success' );

					// Redirect to the empty cart w/ display 
					wp_safe_redirect( $redirecturl );
					exit;

				// If payment fails, add helpful note
				} else {
					$note = sprintf(
						__( 'Result Code Text: %s (Code: %s)', 'woo-merchantx' ),
						$resultCodeText, $result['result-code']
					);
					$order->add_order_note( $note );
					wc_add_notice(
						sprintf( __( 'Payment Failed: %s', 'woo-merchantx' ), $dsp_error ), 'error'
					);
					return false;
				}
			}
		}
	}

	// Output for the order received page
	function thankyou() {
		echo $this->instructions ? wpautop( $this->instructions ) : '';
	}

	// Refund
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$isssl = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] != 'off' ) ? 'Y' : 'N';

		// Refund $amount for the order with ID $order_id
		$order = new WC_Order( $order_id ); 
		$total = $order->get_total();
		$transactionid = merchantx_getTransactionId( $order_id );
		$isrefunded = 'N';

		// If amount to refund is the same as the total of the order, try to void it first
		if( $amount == $total && $transactionid != '' ) {	
			$body = sprintf(
				'
					<void> 
						<api-key>%s</api-key>
						<transaction-id>%s</transaction-id>
					</void>
				',
				MERCHANTX_API_KEY, $transactionid
			);

			// Send
			$args = [
				'headers' => [ 'Content-Type' => 'text/xml; charset=utf-8' ],
				'body' => preg_replace( '/[\t\n\r]/', '', $body )
			];
			$response = wp_remote_post( MERCHANTX_3STEP_URL, $args );
			if( is_wp_error( $response ) || empty( $response['body'] ) ) {
				$logger = wc_get_logger();
				$logger->error( 'process_refund() 1: ' . print_r( $response, true ), [ 'source' => 'woo-merchantx' ] );
				return false;
			}
			$xml = simplexml_load_string( response['body'], 'SimpleXMLElement', LIBXML_NOCDATA );
			$json = json_encode( $xml );
			$result = json_decode( $json, true );

			$result_id = $result['result'];
			$result_text = $result['result-text'];
			$result_code = $result['result-code'];
			if( $result_id == 1 ) {
				$isrefunded = 'Y';

				// Note posted to woocommerce cart details
				$note = __( 'This transaction was refunded in full.', 'woo-merchantx' );
			}
		}

		// Refund the transaction for the specified amount	
		if( $isrefunded == 'N' ) {
			$body = sprintf(
				'
					<refund> 
						<api-key>%s</api-key>
						<transaction-id>%s</transaction-id>
						<amount>%s</amount>
					</refund>
				',
				MERCHANTX_API_KEY, $transactionid, $amount
			);

			// Send
			$args = [
				'headers' => [ 'Content-Type' => 'text/xml; charset=utf-8' ],
				'body' => preg_replace( '/[\t\n\r]/', '', $body )
			];
			$response = wp_remote_post( MERCHANTX_3STEP_URL, $args );
			if( is_wp_error( $response ) || empty( $response['body'] ) ) {
				$logger = wc_get_logger();
				$logger->error( 'process_refund() 2: ' . print_r( $response, true ), [ 'source' => 'woo-merchantx' ] );
				return false;
			}
			$xml = simplexml_load_string( $response['body'], 'SimpleXMLElement', LIBXML_NOCDATA );
			$json = json_encode( $xml );
			$result = json_decode( $json, true );

			$result_id = $result['result'];
			$result_text = $result['result-text'];
			$result_code = $result['result-code'];

			if( $result_id == 1 ) {
				$note = ( $amount == $total )
					? __( 'This transaction was refunded in full.', 'woo-merchantx' )
					: sprintf( __( '$%s of this transaction was refunded.', 'woo-merchantx' ), $amount );
				$isrefunded = 'Y';
			} else {
				$note = sprintf( __( 'There was an error in posting the refund to this order: %s', 'woo-merchantx' ), $result_text );
			}
		}

		// Add helpful note
		$order->add_order_note( $note );

		// Return
		return ( $isrefunded == 'Y' ) ? true : false;
	}

// End Gateway Class
}
