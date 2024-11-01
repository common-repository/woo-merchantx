<?php

// Gather payment methods for this customervaultid
function merchantx_getPMDetailsByVaultId( $billingid ) {
	$body = [
		'keytext' => sanitize_text_field( MERCHANTX_API_KEY ),
		'report_type' => 'customer_vault',
		'ver' => 2,
		'billing_id' => $billingid,
	];
	$body = http_build_query( $body );
	$args = [
		'headers' => [ 'Content-type' => 'application/x-www-form-urlencoded; charset=utf-8' ],
		'body' => $body
	];
	$response = wp_remote_post( MERCHANTX_QUERY_URL, $args );
	if( is_wp_error( $response ) || empty( $response['body'] ) ) {
		$logger = wc_get_logger();
		$logger->error( 'merchantx_getPMDetailsByVaultId(): ' . print_r( $response, true ), [ 'source' => 'woo-merchantx' ] );
		return 'no results';
	}
	$xml = simplexml_load_string( $response['body'], 'SimpleXMLElement', LIBXML_NOCDATA );
	$json = json_encode( $xml );
	$full_response = json_decode( $json, true );
	//$logger = wc_get_logger(); $logger->debug( 'merchantx_getPMDetailsByVaultId(): ' . print_r( $response, true ), [ 'source' => 'woo-merchantx' ] );
	return $full_response['customer_vault'];
}

function merchantx_getCardType( $number ) {
	if( substr( $number, 0 ) == 4 ) { return 'Visa'; }
	elseif( substr( $number, 0, 2 ) == 37 || substr( $number, 0, 2 ) == 32 ) { return 'Amex'; }
	elseif( substr( $number, 0 ) == 6 ) { return 'Discover'; }
	elseif( substr( $number, 0 ) == 5 ) { return 'Mastercard'; }
	return 'Unknown';
}

function merchantx_getResultCodeText( $code ) {
	$codes = [
		1 => __( 'Transaction was approved', 'woo-merchantx' ),
		100 => __( 'Transaction was approved', 'woo-merchantx' ),
		200 => __( 'Transaction was delined by processor', 'woo-merchantx' ),
		201 => __( 'Do not honor', 'woo-merchantx' ),
		202 => __( 'Insufficient funds', 'woo-merchantx' ),
		203 => __( 'Over limit', 'woo-merchantx' ),
		204 => __( 'Transaction not allowed', 'woo-merchantx' ),
		220 => __( 'Incorrect payment information', 'woo-merchantx' ),
		221 => __( 'No such card issuer', 'woo-merchantx' ),
		222 => __( 'No card number on file with issuer', 'woo-merchantx' ),
		223 => __( 'Expired card', 'woo-merchantx' ),
		224 => __( 'Invalid expiration date', 'woo-merchantx' ),
		225 => __( 'Invalid card security code', 'woo-merchantx' ),
		240 => __( 'Call issuer for further information', 'woo-merchantx' ),
		250 => __( 'Pick up card', 'woo-merchantx' ),
		251 => __( 'Lost card', 'woo-merchantx' ),
		252 => __( 'Stolen card', 'woo-merchantx' ),
		253 => __( 'Fraudulent Card', 'woo-merchantx' ),
		260 => __( 'Declined with further instructions available', 'woo-merchantx' ),
		261 => __( 'Declined-Stop all recurring payments', 'woo-merchantx' ),
		262 => __( 'Declined-Stop this recurring program', 'woo-merchantx' ),
		263 => __( 'Declined-Update cardholder data available', 'woo-merchantx' ),
		264 => __( 'Declined-Retry in a few days', 'woo-merchantx' ),
		300 => __( 'Transaction was rejected by gateway', 'woo-merchantx' ),
		400 => __( 'Transaction error returned by processor', 'woo-merchantx' ),
		410 => __( 'Invalid merchant configuration', 'woo-merchantx' ),
		411 => __( 'Merchant account is inactive', 'woo-merchantx' ),
		420 => __( 'Communication error', 'woo-merchantx' ),
		421 => __( 'Communication error with issuer', 'woo-merchantx' ),
		430 => __( 'Duplicate transaction at processor', 'woo-merchantx' ),
		440 => __( 'Processor format error', 'woo-merchantx' ),
		441 => __( 'Invalid transaction information', 'woo-merchantx' ),
		460 => __( 'Processor feature not available', 'woo-merchantx' ),
		461 => __( 'Unsupported card type', 'woo-merchantx' ),
	];
	return isset( $codes[$code] ) ? $codes[$code] : '';
}

function merchantx_getTransactionId( $order_id ) {
	$args = [
		'headers' => [ 'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8' ],
	];
	$url = sprintf(
		'%s?keytext=%s&ver=2&action_type=sale&order_id=%d',
		MERCHANTX_QUERY_URL, MERCHANTX_API_KEY, $order_id
	);
	$response = wp_remote_get( $url, $args );
	if( is_wp_error( $response ) || empty( $response['body'] ) ) {
		$logger = wc_get_logger();
		$logger->error( 'merchantx_getTransactionId(): ' . print_r( $response, true ), [ 'source' => 'woo-merchantx' ] );
		return '';
	}
	$xml = simplexml_load_string( $response['body'], 'SimpleXMLElement', LIBXML_NOCDATA );
	$json = json_encode( $xml );
	$full_response = json_decode( $json, true );
	return isset( $full_response['transaction']['transaction_id'] )
		? sanitize_text_field( $full_response['transaction']['transaction_id'] ) : '';
}

// Validate data
function merchantx_cleanTheData( $data, $datatype = 'none' ) {
	switch( $datatype ) {
		case 'string':
			if( gettype( $data ) != 'string' ) { $data = ''; }
			$data = sanitize_text_field( $data );
			break;
		case 'int':
			if( gettype( $data ) != 'integer' ) { $data = '0'; }
			$data = sanitize_text_field( $data );
			break;
		case 'url':
			if( filter_var( $data, FILTER_VALIDATE_URL ) == false ) { $data = ''; }
			$data = sanitize_text_field( $data );
			break;
		case 'email':
			if( filter_var( $email, FILTER_VALIDATE_EMAIL ) === false ) { $data = ''; }
			$data = sanitize_email( $data );
			break;
	}
	return esc_html( htmlspecialchars( $data ) );
}

function merchantx_pw_load_scripts() {
	wp_enqueue_script( 'merchantx_ajax_custom_script', plugin_dir_url( __FILE__ ) . 'js/stepOne.js', [ 'jquery' ] );
	wp_localize_script( 'merchantx_ajax_custom_script', 'frontendajax', [ 'ajaxurl' => admin_url( 'admin-ajax.php' ) ] );
	wp_enqueue_script( 'merchantx_ajax_custom_script1', plugin_dir_url( __FILE__ ) . 'js/deletePaymentMethod.js', [ 'jquery' ] );
	wp_localize_script( 'merchantx_ajax_custom_script1', 'frontendajax', [ 'ajaxurl' => admin_url( 'admin-ajax.php' ) ] );
}

function merchantx_stepOne_addBilling() {
	$security = sanitize_text_field( $_POST['security'] );
	check_ajax_referer( 'checkout-nonce', 'security', false );
	$thisid = sanitize_text_field( $_POST['thisid'] );
	$orderid = sanitize_text_field( $_POST['orderid'] );
	$transactiontype = sanitize_text_field( $_POST['transactiontype'] );
	$ordertotal = sanitize_text_field( $_POST['ordertotal'] );
	$ordertax = sanitize_text_field( $_POST['ordertax'] );
	$ordershipping = sanitize_text_field( $_POST['ordershipping'] );
	$savepaymentmethod = sanitize_text_field( $_POST['savepaymentmethod'] );
	$customervaultid = sanitize_text_field( $_POST['customervaultid'] );
	$user_email = sanitize_email( $_POST['user_email'] );
	$userid = sanitize_text_field( $_POST['userid'] );
	$last4 = sanitize_text_field( $_POST['last4'] );
	$expiry = sanitize_text_field( $_POST['expiry'] );
	$billingid = sanitize_text_field( $_POST['billingid'] );

	// Get the saved payment tokens from WC
	$payment_tokens = WC_Payment_Tokens::get_customer_tokens( get_current_user_id() );

	$token = [];
	foreach( $payment_tokens as $pt ) {
		$billingid = $pt->get_token();

		// Query to get the rest of the details for this billing id
		$paymentmethod = merchantx_getPMDetailsByVaultId( $billingid );
		$customervaultid = $paymentmethod['customer']['@attributes']['id'];
		$thispm = [
			'tokenid' => $tokenid,
			'internalid' => $pt->get_id(),
			'billingid' => $billingid,
			'customervaultid' => $paymentmethod['customer']['@attributes']['id'],
			'ccnumber' => $paymentmethod['customer']['billing']['cc_number'],
			'ccexp' => substr_replace( $paymentmethod['customer']['billing']['cc_exp'], '/', 2, 0 ),
			'cardtype' => $pt->get_card_type(),
		];
		array_push( $paymentmethods, $thispm );
	}

	// Now that we have all the payment methods, we need to find the ones that match just the selected vault id
	$usedbillingids = [];
	for( $x = 0 ;	$x < count( $paymentmethods ); $x ++ ) {
		if( $paymentmethods[$x]['customervaultid'] == $customervaultid ) {
			array_push( $usedbillingids, $paymentmethods[$x]['billingid'] );
		}
	}

	// Come up with a unique billing id
	$fail = 'Y';
	$length = 10;
	while( $fail == 'Y' ) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen( $characters );
		$randomString = '';
		for( $i = 0 ; $i < $length; $i ++ ) {
			$randomString .= $characters[rand( 0, $charactersLength - 1 )];
		}
		if( ! in_array( $randomString, $usedbillingids ) ) {
			$newbillingid = $randomString;
			$billingid = $randomString;
			$fail = 'N';
		}
	}

	$billingfirstname = sanitize_text_field( $_POST['billingfirstname'] );
	$billinglastname = sanitize_text_field( $_POST['billinglastname'] );
	$billingaddress1 = sanitize_text_field( $_POST['billingaddress1'] );
	$billingcity = sanitize_text_field( $_POST['billingcity'] );
	$billingstate = sanitize_text_field( $_POST['billingstate'] );
	$billingpostcode = sanitize_text_field( $_POST['billingpostcode'] );
	$billingcountry = sanitize_text_field( $_POST['billingcountry'] );
	$billingemail = sanitize_email( $_POST['billingemail'] );
	$billingphone = sanitize_text_field( $_POST['billingphone'] );
	$billingcompany = sanitize_text_field( $_POST['billingcompany'] );
	$billingaddress2 = sanitize_text_field( $_POST['billingaddress2'] );
	$shippingfirstname = sanitize_text_field( $_POST['shippingfirstname'] );
	$shippinglastname = sanitize_text_field( $_POST['shippinglastname'] );
	$shippingaddress1 = sanitize_text_field( $_POST['shippingaddress1'] );
	$shippingcity = sanitize_text_field( $_POST['shippingcity'] );
	$shippingstate = sanitize_text_field( $_POST['shippingstate'] );
	$shippingpostcode = sanitize_text_field( $_POST['shippingpostcode'] );
	$shippingcountry = sanitize_text_field( $_POST['shippingcountry'] );
	$shippingphone = sanitize_text_field( $_POST['shippingphone'] );
	$shippingcompany = sanitize_text_field( $_POST['shippingcompany'] );
	$shippingaddress2 = sanitize_text_field( $_POST['shippingaddress2'] );

	// Can't accept a url with a query string in it
	$referrer = explode( '?', $_SERVER['HTTP_REFERER'] );
	$thisreferrer = sprintf(
		'%s?order=%s***action=addbilling***plugin=%s',
		$referrer[0], $orderid, $thisid
	);

	// Build item list
	$items = [];
	for( $x = 0; $x < $_POST['itemcount']; $x ++ ) {
		$item = [
			'product_id' => sanitize_text_field( $_POST['items'][$x]['productid'] ),
			'name' => sanitize_text_field( stripslashes( $_POST['items'][$x]['name'] ) ),
			'line_total' => sanitize_text_field( ( $_POST['items'][$x]['line_total'] / $_POST['items'][$x]['qty'] ) ),
			'qty' => sanitize_text_field( $_POST['items'][$x]['qty'] ),
			'line_subtotal' => sanitize_text_field( $_POST['items'][$x]['line_subtotal'] )
		];
		array_push( $items, $item );
	}

	// Add billing
	$body = sprintf(
		'
			<add-billing>
				<api-key>%s</api-key>
				<redirect-url>%s</redirect-url>
				<customer-vault-id>%s</customer-vault-id>
				<billing>
					<billing-id>%s</billing-id>
					<email>%s</email>
					<first-name>%s</first-name>
					<last-name>%s</last-name>
					<address1>%s</address1>
					<city>%s</city>
					<state>%s</state>
					<postal>%s</postal>
					<country>%s</country>
				</billing>
			</add-billing>
		',
		MERCHANTX_API_KEY,
		$thisreferrer,
		$customervaultid,
		$billingid,
		$billingemail,
		$billingfirstname,
		$billinglastname,
		$billingaddress1,
		$billingcity,
		$billingstate,
		$billingpostcode,
		$billingcountry
	);

	// Send
	$args = [
		'headers' => [ 'Content-Type' => 'text/xml; charset=utf-8' ],
		'body' => preg_replace( '/[\t\n\r]/', '', $body )
	];
	$response = wp_remote_post( MERCHANTX_3STEP_URL, $args );
	if( is_wp_error( $response ) || empty( $response['body'] ) ) {
		$logger = wc_get_logger();
		$logger->error( 'merchantx_stepOne_addBilling(): ' . print_r( $response, true ), [ 'source' => 'woo-merchantx' ] );
		return '';
	}
	$xml = simplexml_load_string( $response['body'], 'SimpleXMLElement', LIBXML_NOCDATA );
	$json = json_encode( $xml );
	$full_response = json_decode( $json, true ); 

	// If successful, submit order thru the direct post now that we have the billing id/token
	echo sprintf( '%s--||--%s', $full_response['form-url'], $billingid );
	wp_die();
}

function merchantx_stepOne() {	 
	global $woocommerce;
	$security = sanitize_text_field( $_POST['security'] );
	check_ajax_referer( 'checkout-nonce', 'security', false );

	// Catch variables passed in and define them
	$orderid = sanitize_text_field( $_POST['orderid'] );
	$transactiontype = sanitize_text_field( $_POST['transactiontype'] );
	$ordertotal = sanitize_text_field( $_POST['ordertotal'] );
	$ordertax = sanitize_text_field( $_POST['ordertax'] );
	$ordershipping = sanitize_text_field( $_POST['ordershipping'] );
	$savepaymentmethod = sanitize_text_field( $_POST['savepaymentmethod'] );
	$customervaultid = sanitize_text_field( $_POST['customervaultid'] );
	$user_email = sanitize_email( $_POST['user_email'] );
	$userid = sanitize_text_field( $_POST['userid'] );
	$last4 = sanitize_text_field( $_POST['last4'] );
	$expiry = sanitize_text_field( $_POST['expiry'] );
	$billingid = sanitize_text_field( $_POST['billingid'] );
	$billingfirstname = sanitize_text_field( $_POST['billingfirstname'] );
	$billinglastname = sanitize_text_field( $_POST['billinglastname'] );
	$billingaddress1 = sanitize_text_field( $_POST['billingaddress1'] );
	$billingcity = sanitize_text_field( $_POST['billingcity'] );
	$billingstate = sanitize_text_field( $_POST['billingstate'] );
	$billingpostcode = sanitize_text_field( $_POST['billingpostcode'] );
	$billingcountry = sanitize_text_field( $_POST['billingcountry'] );
	$billingemail = sanitize_email( $_POST['billingemail'] );
	$billingphone = sanitize_text_field( $_POST['billingphone'] );
	$billingcompany = sanitize_text_field( $_POST['billingcompany'] );
	$billingaddress2 = sanitize_text_field( $_POST['billingaddress2'] );
	$shippingfirstname = sanitize_text_field( $_POST['shippingfirstname'] );
	$shippinglastname = sanitize_text_field( $_POST['shippinglastname'] );
	$shippingaddress1 = sanitize_text_field( $_POST['shippingaddress1'] );
	$shippingcity = sanitize_text_field( $_POST['shippingcity'] );
	$shippingstate = sanitize_text_field( $_POST['shippingstate'] );
	$shippingpostcode = sanitize_text_field( $_POST['shippingpostcode'] );
	$shippingcountry = sanitize_text_field( $_POST['shippingcountry'] );
	$shippingphone = sanitize_text_field( $_POST['shippingphone'] );
	$shippingcompany = sanitize_text_field( $_POST['shippingcompany'] );
	$shippingaddress2 = sanitize_text_field( $_POST['shippingaddress2'] );

	// Can't accept a url with a query string in it
	$referrer = explode( '?', $_SERVER['HTTP_REFERER'] );
	$referrer = $referrer[0];
	$referrer .= '?order=' . $orderid;

	// Build item list
	$items = [];
	for( $x = 0; $x < $_POST['itemcount']; $x ++ ) {
		$item = [
			'product_id' => sanitize_text_field( $_POST['items'][$x]['productid'] ),
			'name' => sanitize_text_field( stripslashes($_POST['items'][$x]['name'] ) ),
			'line_total' => sanitize_text_field( ( $_POST['items'][$x]['line_total'] / $_POST['items'][$x]['qty'] ) ),
			'qty' => sanitize_text_field( $_POST['items'][$x]['qty'] ),
			'line_subtotal' => sanitize_text_field( $_POST['items'][$x]['line_subtotal'] )
		];
		array_push( $items, $item );
	}

	// Implies user selected a previously existing payment method (billing id)
	if( $billingid != '' ) {
		$args = [
			'billingid' => $billingid,
			'billingfirstname' => $billingfirstname,
			'billinglastname' => $billinglastname,
			'billingaddress1' => $billingaddress1,
			'billingaddress2' => $billingaddress2,
			'billingcity' => $billingcity,
			'billingstate' => $billingstate,
			'billingpostcode' => $billingpostcode,
			'billingcountry' => $billingcountry,
			'billingemail' => $billingemail,
			'billingphone' => $billingphone,
			'billingcompany' => $billingcompany,
			'shippingfirstname' => $shippingfirstname,
			'shippinglastname' => $shippinglastname,
			'shippingaddress1' => $shippingaddress1,
			'shippingaddress2' => $shippingaddress2,
			'shippingcity' => $shippingcity,
			'shippingstate' => $shippingstate,
			'shippingpostcode' => $shippingpostcode,
			'shippingcountry' => $shippingcountry,
			'shippingphone' => $shippingphone,
			'shippingcompany' => $shippingcompany,
		];
		$addresses = merchantx_build_xml_addresses( $args );
		$products = '';
		foreach( $items as $item ) { $products .= merchantx_build_xml_product( $item ); }
		$body = sprintf(
			'
				<%s>
					<api-key>%s</api-key>
					<redirect-url>%s</redirect-url>
					<amount>%s</amount>
					<ip-address>%s</ip-address>
					<currency>USD</currency>
					<order-id>%s</order-id>
					<order-description>Online Order</order-description>
					<tax-amount>%s</tax-amount>
					<shipping-amount>%s</shipping-amount>
					<customer-vault-id>%s</customer-vault-id>
					%s
					%s
				</%s>
			',
			$transactiontype,
			MERCHANTX_API_KEY,
			$referrer,
			$ordertotal,
			$_SERVER['REMOTE_ADDR'],
			$orderid,
			$ordertax,
			$ordershipping,
			$customervaultid,
			$addresses,
			$products,
			$transactiontype
		);

		// Send
		$args = [
			'headers' => [ 'Content-Type' => 'text/xml; charset=utf-8' ],
			'body' => preg_replace( '/[\t\n\r]/', '', $body )
		];
		$response = wp_remote_post( MERCHANTX_3STEP_URL, $args );
		if( is_wp_error( $response ) || empty( $response['body'] ) ) {
			$logger = wc_get_logger();
			$logger->error( 'merchantx_stepOne() 1: ' . print_r( $response, true ), [ 'source' => 'woo-merchantx' ] );
			return '';
		}
		$xml = simplexml_load_string( $response['body'], 'SimpleXMLElement', LIBXML_NOCDATA );
		$json = json_encode( $xml );
		$full_response = json_decode( $json, true );

		if( 1 == 2 ) {
		   // $error_message = $response->get_error_message();
		   // echo 'Something went wrong: ' . $error_message;
		} else {		
			$formURL = $full_response['form-url'];
			$rc = $full_response['result'];
			$tid = $full_response['transaction-id'];
			$ac = $full_response['authorization-code'];
			$ar = $full_response['avs-result'];
			echo sprintf( '%s||%s||%s||%s||%s', $formURL, $rc, $tid, $ac, $ar );
			wp_die();
		}	  
		wp_die();

	// Save a new payment method to an existing vault
	} elseif( $savepaymentmethod == 'Y' ) {
		
		// Get the saved payment tokens from WC
		$payment_tokens = WC_Payment_Tokens::get_customer_tokens( get_current_user_id() );
		$token = [];
		foreach( $payment_tokens as $pt ) {
			$billingid = $pt->get_token();

			// Query to get the rest of the details for this billing id
			$paymentmethod = merchantx_getPMDetailsByVaultId( $billingid );
			$customervaultid = $paymentmethod['customer']['@attributes']['id'];
			$thispm = [];
			$thispm['tokenid'] = $tokenid;
			$thispm['internalid'] = $pt->get_id();
			$thispm['billingid'] = $billingid;
			$thispm['customervaultid'] = $paymentmethod['customer']['@attributes']['id'];
			$thispm['ccnumber'] = $paymentmethod['customer']['billing']['cc_number'];
			$thispm['ccexp'] = substr_replace( $paymentmethod['customer']['billing']['cc_exp'], '/', 2, 0 );
			$thispm['cardtype'] = $pt->get_card_type();
			array_push( $paymentmethods, $thispm );
		}

		// Now that we have all the payment methods, we need to find the ones that match just the selected vault id
		$usedbillingids = [];
		for( $x = 0 ;	$x < count( $paymentmethods ) ;	 $x++ ) {
			if( $paymentmethods[$x]['customervaultid'] == $customervaultid ) {
				array_push( $usedbillingids, $paymentmethods[$x]['billingid'] );
			}
		}

		// Come up with a unique billing id
		$fail = 'Y';
		$length = 10;
		while( $fail == 'Y' ) {
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$charactersLength = strlen( $characters );
			$randomString = '';
			for( $i = 0 ;	$i < $length ;	$i++ ) {
				$randomString .= $characters[rand( 0, $charactersLength - 1 )];
			}
			if( ! in_array( $randomString, $usedbillingids ) ) {
				$newbillingid = $randomString;
				$billingid = $randomString;
				$fail = 'N';
			}
		}

		// No existing customer vaults, must create a new one with a billing id
		if( $customervaultid == '' ) {
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$charactersLength = strlen( $characters );
			$randomString = '';
			for( $i = 0 ;	$i < $length ;	$i++ ) {
				$randomString .= $characters[rand( 0, $charactersLength - 1 )];
			}

			// Implies there is no customer vault, need to create a new one
			$customervaultid = $randomString;
			$args = [
				'billingid' => $billingid,
				'billingfirstname' => $billingfirstname,
				'billinglastname' => $billinglastname,
				'billingaddress1' => $billingaddress1,
				'billingaddress2' => $billingaddress2,
				'billingcity' => $billingcity,
				'billingstate' => $billingstate,
				'billingpostcode' => $billingpostcode,
				'billingcountry' => $billingcountry,
				'billingemail' => $billingemail,
				'billingphone' => $billingphone,
				'billingcompany' => $billingcompany,
				'shippingfirstname' => $shippingfirstname,
				'shippinglastname' => $shippinglastname,
				'shippingaddress1' => $shippingaddress1,
				'shippingaddress2' => $shippingaddress2,
				'shippingcity' => $shippingcity,
				'shippingstate' => $shippingstate,
				'shippingpostcode' => $shippingpostcode,
				'shippingcountry' => $shippingcountry,
				'shippingphone' => $shippingphone,
				'shippingcompany' => $shippingcompany,
			];
			$addresses = merchantx_build_xml_addresses( $args );
			$products = '';
			foreach( $items as $item ) { $products .= merchantx_build_xml_product( $item ); }
			$body = sprintf(
				'
					<%s>
						<api-key>%s</api-key>
						<redirect-url>%s</redirect-url>
						<amount>%s</amount>
						<ip-address>%s</ip-address>
						<currency>USD</currency>
						<order-id>%s</order-id>
						<order-description>Online Order</order-description>
						<tax-amount>%s</tax-amount>
						<shipping-amount>%s</shipping-amount>
						%s
						%s
						<add-customer>
							<customer-vault-id>%s</customer-vault-id>
						</add-customer>
					</%s>
				',
				$transactiontype,
				MERCHANTX_API_KEY,
				$referrer,
				$ordertotal,
				$_SERVER['REMOTE_ADDR'],
				$orderid,
				$ordertax,
				$ordershipping,
				$addresses,
				$products,
				$customervaultid,
				$transactiontype
			);

			// Send
			$args = [
				'headers' => [ 'Content-type' => 'text/xml; charset=utf-8' ],
				'body' => preg_replace( '/[\t\n\r]/', '', $body ),
			];
			$response = wp_remote_post( MERCHANTX_3STEP_URL, $args );
			if( is_wp_error( $response ) || empty( $response['body'] ) ) {
				$logger = wc_get_logger();
				$logger->error( 'merchantx_stepOne() 2: ' . print_r( $response, true ), [ 'source' => 'woo-merchantx' ] );
				return '';
			}
			$xml = simplexml_load_string( $response['body'], 'SimpleXMLElement', LIBXML_NOCDATA );
			$json = json_encode( $xml );
			$full_response = json_decode( $json, true );

			// Using an existing vault id seems to add another pm with the same billing id
			// For now, we will be generating a random string for each vault and using that
			if( $full_response ) {

				// Save token/payment method to woo
				$token = new WC_Payment_Token_CC();
				$token->set_token( $billingid );
				$token->set_gateway_id( '' );
				$token->set_user_id( $userid );
				$token->set_last4( $last4 );
				$token->set_expiry_month( substr( $expiry, 0, 2 ) );
				$token->set_expiry_year( '20' . substr( $expiry, -2 ) );
				$token->set_card_type( 'unknown' );
				$token->save();
				echo $full_response['form-url'] ;
			}

		// Customer vault exists, add payment method to this vault
		// At some point, we will have to check to total number of billing ids per vault
		} else {
			$body = sprintf(
				'
					<add-billing>
						<api-key>%s</api-key>
						<redirect-url>%s</redirect-url>
						<customer-vault-id>%s</customer-vault-id>
						<billing>
							<billing-id>%s</billing-id>
							<email>%s</email>
							<first-name>%s</first-name>
							<last-name>%s</last-name>
							<address1>%s</address1>
							<address2 />
							<city>%s</city>
							<state>%s</state>
							<postal>%s</postal>
							<country>%s</country>
							<company>%s</company>
						</billing>
					</add-billing>
				',
				MERCHANTX_API_KEY,
				$referrer,
				$customervaultid,
				$billingid,
				$billingemail,
				$billingfirstname,
				$billinglastname,
				$billingaddress1,
				$billingcity,
				$billingstate,
				$billingpostcode,
				$billingcountry,
				$billingcompany
			);

			// Send
			$args = [
				'headers' => [ 'Content-Type' => 'text/xml; charset=utf-8' ],
				'body' => preg_replace( '/[\t\n\r]/', '', $body )
			];
			$response = wp_remote_post( MERCHANTX_3STEP_URL, $args );
			if( is_wp_error( $response ) || empty( $response['body'] ) ) {
				$logger = wc_get_logger();
				$logger->error( 'merchantx_stepOne() 3: ' . print_r( $response, true ), [ 'source' => 'woo-merchantx' ] );
				return '';
			}
			$xml = simplexml_load_string( $response['body'], 'SimpleXMLElement', LIBXML_NOCDATA );
			$json = json_encode( $xml );
			$full_response = json_decode( $json, true );
			echo $full_response['form-url'];
		}

	// Implies one time sale, do not save the payment method for later
	} else {
		$args = [
			'billingid' => '',
			'billingfirstname' => $billingfirstname,
			'billinglastname' => $billinglastname,
			'billingaddress1' => $billingaddress1,
			'billingaddress2' => $billingaddress2,
			'billingcity' => $billingcity,
			'billingstate' => $billingstate,
			'billingpostcode' => $billingpostcode,
			'billingcountry' => $billingcountry,
			'billingemail' => $billingemail,
			'billingphone' => $billingphone,
			'billingcompany' => $billingcompany,
			'shippingfirstname' => $shippingfirstname,
			'shippinglastname' => $shippinglastname,
			'shippingaddress1' => $shippingaddress1,
			'shippingaddress2' => $shippingaddress2,
			'shippingcity' => $shippingcity,
			'shippingstate' => $shippingstate,
			'shippingpostcode' => $shippingpostcode,
			'shippingcountry' => $shippingcountry,
			'shippingphone' => $shippingphone,
			'shippingcompany' => $shippingcompany,
		];
		$addresses = merchantx_build_xml_addresses( $args );
		$products = '';
		foreach( $items as $item ) { $products .= merchantx_build_xml_product( $item ); }
		$body = sprintf(
			'
				<?xml version="1.0" encoding="UTF-8" standalone="no" ?>
				<%s>
					<api-key>%s</api-key>
					<redirect-url>%s</redirect-url>
					<amount>%s</amount>
					<ip-address>%s</ip-address>
					<currency>USD</currency>
					<order-id>%s</order-id>
					<order-description>Online Order</order-description>
					<tax-amount>%s</tax-amount>
					<shipping-amount>%s</shipping-amount>
					%s
					%s
				</%s>
			',
			$transactiontype,
			MERCHANTX_API_KEY,
			$referrer,
			$ordertotal,
			$_SERVER['REMOTE_ADDR'],
			$orderid,
			$ordertax,
			$ordershipping,
			$addresses,
			$products,
			$transactiontype
		);

		// Send
		$args = [
			'headers' => [ 'Content-Type' => 'text/xml; charset="UTF-8"' ],
			'body' => preg_replace( '/[\t\n\r]/', '', $body )
		];
		$response = wp_remote_post( MERCHANTX_3STEP_URL, $args );
		if( is_wp_error( $response ) || empty( $response['body'] ) ) {
			$logger = wc_get_logger();
			$logger->error( 'merchantx_stepOne() 4: ' . print_r( $response, true ), [ 'source' => 'woo-merchantx' ] );
			return '';
		}
		$xml = simplexml_load_string( $response['body'], 'SimpleXMLElement', LIBXML_NOCDATA );
		$json = json_encode( $xml );
		$full_response = json_decode( $json, true );
		echo isset( $full_response['form-url'] ) ? $full_response['form-url'] : '';
	}
	wp_die();
}

function merchantx_deletePaymentMethod() {	 
	$security = $_POST['security'];
	check_ajax_referer( 'delete-pm-nonce', 'security', false );

	// Delete customer vault
	$vaultid = merchantx_cleanTheData( $_POST['vaultid'], 'integer' );
	$billingid = merchantx_cleanTheData( $_POST['billingid'], 'integer' );
	$tokenid = merchantx_cleanTheData( $_POST['tokenid'], 'string' );
	
	// Delete local token reference
	WC_Payment_Tokens::delete( $tokenid );
	$body = sprintf(
		'
			<delete-billing>
				<api-key>%s</api-key>
				<customer-vault-id>%s</customer-vault-id>
				<billing>
					<billing-id>%s</billing-id>
				</billing>
			</delete-billing>
		',
		MERCHANTX_API_KEY, $vaultid, $billingid
	);

	// Send
	$args = [
		'headers' => [ 'Content-type' => 'text/xml; charset=utf-8' ],
		'body' => preg_replace( '/[\t\n\r]/', '', $body )
	];
	$response = wp_remote_post( MERCHANTX_3STEP_URL, $args );
	if( is_wp_error( $response ) || empty( $response['body'] ) ) {
		$logger = wc_get_logger();
		$logger->error( 'merchantx_deletePaymentMethod() 1: ' . print_r( $response, true ), [ 'source' => 'woo-merchantx' ] );
		return '';
	}
	$xml = simplexml_load_string( $response['body'], 'SimpleXMLElement', LIBXML_NOCDATA );
	$json = json_encode( $xml );
	$full_response = json_decode( $json, true );	

	// Deleting the single billing id failed due to it being the last one in the vault.	now delete the vault.
	$resultid = isset( $full_response['result'] ) ? $full_response['result'] : '';
	if( $resultid == 3 ) {
		$body = sprintf(
			'
				<delete-customer>
					<api-key>%s</api-key>
					<customer-vault-id>%s</customer-vault-id>
				</delete-customer>
			',
			MERCHANTX_API_KEY, $vaultid
		);

		// Send
		$args = [
			'headers' => [ 'Content-Type' => 'text/xml; charset=utf-8' ],
			'body' => preg_replace( '/[\t\n\r]/', '', $body )
		];
		$response = wp_remote_post( MERCHANTX_3STEP_URL, $args );
		if( is_wp_error( $response ) || empty( $response['body'] ) ) {
			$logger = wc_get_logger();
			$logger->error( 'merchantx_deletePaymentMethod() 2: ' . print_r( $response, true ), [ 'source' => 'woo-merchantx' ] );
			return 1;
		}
		$xml = simplexml_load_string( $response['body'], 'SimpleXMLElement', LIBXML_NOCDATA );
		$json = json_encode( $xml );
		$full_response = json_decode( $json, true );

		if( isset( $full_response['result-code'] ) && $full_response['result-code'] != 100 ) {
		} else {
			return 1;
		}
	}

	// Output
	echo $resultid;
}

function merchantx_build_xml_product( $item ) {
	return sprintf(
		'
			<product>
				<product-code>%s</product-code>
				<description>%s</description>
				<commodity-code></commodity-code>
				<unit-of-measure></unit-of-measure>
				<unit-cost>%s</unit-cost>
				<quantity>%s</quantity>
				<total-amount>%s</total-amount>
				<tax-amount></tax-amount>
				<tax-rate>1.00</tax-rate>
				<discount-amount></discount-amount>
				<discount-rate></discount-rate>
				<tax-type></tax-type>
				<alternate-tax-id></alternate-tax-id>
			</product>
		',
		$item['product_id'],
		$item['name'],
		round( $item['line_total'], 2 ),
		round( $item['qty'] ),
		round( $item['line_subtotal'], 2 )
	);
}

function merchantx_build_xml_addresses( $args ) {
	extract( $args );
	return sprintf(
		'
			<billing>
				<billing-id>%s</billing-id>
				<first-name>%s</first-name>
				<last-name>%s</last-name>
				<address1>%s</address1>
				<address2>%s</address2>
				<city>%s</city>
				<state>%s</state>
				<postal>%s</postal>
				<country>%s</country>
				<email>%s</email>
				<phone>%s</phone>
				<company>%s</company>
				<fax></fax>
			</billing>
			<shipping>
				<first-name>%s</first-name>
				<last-name>%s</last-name>
				<address1>%s</address1>
				<address2>%s</address2>
				<city>%s</city>
				<state>%s</state>
				<postal>%s</postal>
				<country>%s</country>
				<phone>%s</phone>
				<company>%s</company>
			</shipping>
		',
		$billingid,
		$billingfirstname,
		$billinglastname,
		$billingaddress1,
		$billingaddress2,
		$billingcity,
		$billingstate,
		$billingpostcode,
		$billingcountry,
		$billingemail,
		$billingphone,
		$billingcompany,
		$shippingfirstname,
		$shippinglastname,
		$shippingaddress1,
		$shippingaddress2,
		$shippingcity,
		$shippingstate,
		$shippingpostcode,
		$shippingcountry,
		$shippingphone,
		$shippingcompany
	);
}
