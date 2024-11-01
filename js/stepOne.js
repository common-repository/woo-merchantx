
// Performs step one of the three step process: Formats non-secure data and retrieves a url
function merchantx_stepOne( data, path ) {
	var items = [];
	for( var x = 0; x < data['itemcount']; x ++ ) {
		var item = {
			productid: data['items'][x]['productid'],
			name: data['items'][x]['name'],
			line_total: data['items'][x]['line_total'],
			qty: data['items'][x]['qty'],
			line_subtotal: data['items'][x]['line_subtotal']
		}
		items.push( item );
	}

	// Determine which action we are taking
	if( data['savepaymentmethod'] == 'N' || ( data['savepaymentmethod'] == 'Y' && data['customervaultid'] == '' ) ) {
		jQuery.ajax( {
			type: 'POST',
			url: frontendajax.ajaxurl,
			async: false,
			data: {
				action: 'merchantx_stepOne',
				thisid: data['thisid'],
				orderid: data['orderid'],
				transactiontype: data['transactiontype'],
				ordertotal: data['ordertotal'],
				ordertax: data['ordertax'],
				ordershipping: data['ordershipping'],
				savepaymentmethod: data['savepaymentmethod'],
				customervaultid: data['customervaultid'],
				user_email: data['user_email'],
				userid: data['userid'],
				last4: data['last4'],
				expiry: data['expiry'],
				billingid: data['billingid'],
				billingfirstname: data['billingfirstname'],
				billinglastname: data['billinglastname'],
				billingaddress1: data['billingaddress1'],
				billingcity: data['billingcity'],
				billingstate: data['billingstate'],
				billingpostcode: data['billingpostcode'],
				billingcountry: data['billingcountry'],
				billingemail: data['billingemail'],
				billingphone: data['billingphone'],
				billingcompany: data['billingcompany'],
				billingaddress2: data['billingaddress2'],
				shippingfirstname: data['shippingfirstname'],
				shippinglastname: data['shippinglastname'],
				shippingaddress1: data['shippingaddress1'],
				shippingcity: data['shippingcity'],
				shippingstate: data['shippingstate'],
				shippingpostcode: data['shippingpostcode'],
				shippingcountry: data['shippingcountry'],
				shippingphone: data['shippingphone'],
				shippingcompany: data['shippingcompany'],
				shippingaddress2: data['shippingaddress2'],
				items: items,
				itemcount: data['itemcount'],
				security: data['security']
			},
			success: function( response ) {

				var formurl = response;

				// If data['billingid'] is not null, that implies a payment method was selected and we don't have to do step 2
				// Skip to step three error returned, force redirect and display error
				if( formurl.indexOf( 'Payment Gateway Failed' ) !== -1 ) {

				// One off pm or saving new pm
				} else if( data['billingid'] == '' ) {
					document.getElementById( 'submitpayment' ).action = formurl.replace( /^\s+|\s+$/gm, '' );
					document.getElementById( 'submitpayment' ).submit();

				// Using saved payment method - most of this one isn't needed
				} else {
					var checkoutURL = '/checkout/';
					var response = formurl.split( '||' );
					var url = response[0];
					var rc = response[1];
					var tid = response[2];
					var ac = response[3];
					var ar = response[4];
					var posturl = url;
					var token = '';
					var token = url.split( '/' );
					token = token[( token.length - 1 )];
					var url = checkoutURL + '?key=woo&order=' + data['orderid'] + '&token-id=' + token
						+ '&rc=' + rc + '&tid=' + tid + '&ac=' + ac + '&ar=' + ar;
					document.getElementById( 'submitpayment' ).action = posturl;
					document.getElementById( 'submitpayment' ).submit();
				}
			},
			error: function ( jqXHR, textStatus, errorThrown ) {
				alert( textStatus + ' ' + errorThrown );
			}
		} );

	// (2) add a billing id to an existing customer vault id
	// Use add-billing first for three step, then process the sale after that has returned
	} else {
		jQuery.ajax( {
			type: 'POST',
			url: frontendajax.ajaxurl,
			async: false,
			data: {
				action: 'merchantx_stepOne_addBilling',
				thisid: data['thisid'],
				orderid: data['orderid'],
				customervaultid: data['customervaultid'],
				billingid: data['billingid'],
				billingfirstname: data['billingfirstname'],
				billinglastname: data['billinglastname'],
				billingaddress1: data['billingaddress1'],
				billingcity: data['billingcity'],
				billingstate: data['billingstate'],
				billingpostcode: data['billingpostcode'],
				billingcountry: data['billingcountry'],
				billingemail: data['billingemail'],
				billingphone: data['billingphone'],
				billingcompany: data['billingcompany'],
				billingaddress2: data['billingaddress2'],
				security: data['security']
			},
			success: function( response ) {
				var formurl = response;
				var exploder = response.split( '--||--' );
				var responseurl = exploder[0];
				var billingid = exploder[1];
				data['billingid'] = billingid;
				

				// Error returned, force redirect and display error
				if( formurl.indexOf( 'Payment Gateway Failed' ) !== -1 ) {

				// If data['billingid'] is not null, that implies a payment method was selected
				// We don't have to do step 2, just skip to step three
				} else if( data['billingid'] == '' ) {
					// console.log( data );
					document.getElementById( 'submitpayment' ).action = formurl.replace( /^\s+|\s+$/gm, '' );
					document.getElementById( 'submitpayment' ).submit();

				// Using saved payment method - most of this one isn't needed
				} else {
					var checkoutURL = '/checkout/';
					var token = responseurl.split( '/' );
					token = token[ ( token.length - 1 ) ];
					var url = checkoutURL + '?key=woo&order=' + data['orderid'] + '&token-id=' + token + '&action=addbilling';
					document.getElementById( 'submitpayment' ).action = responseurl;					
					document.getElementById( 'submitpayment' ).submit();
				}
			},
			error: function ( jqXHR, textStatus, errorThrown ) {
				alert( textStatus + ' ' + errorThrown );
			}
		} );
	}
}
