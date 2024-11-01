
function merchantx_deletePM( id, vaultid, position, e, nonce, tokenid ) {

	// Using the event, make sure the delete link is being clicked on, not the list item
	if( e.target.nodeName == 'A' ) {
		document.getElementById( 'paymentmethodli' + position).style.backgroundColor = '#CCCCCC';
		jQuery.ajax( {
			type: 'POST',
			url: frontendajax.ajaxurl,
			async: false,
			data: {
				action: 'merchantx_deletePaymentMethod',
				vaultid: vaultid,
				billingid: id,
				security: nonce,
				tokenid: tokenid
			},
			success: function( response ) {
				var responseid = response.replace( /^\s+|\s+$/gm, '' );
				if( responseid > 3 ) {
					responseid = responseid / 10;
				}

				// Implies it was a good delete, either of the billingid or the vault
				// Hide/remove the payment method from the ui
				if( responseid == 1 || responseid == 3 ) {
					document.getElementById( 'paymentmethodli' + position).style.display = 'none';
					if (document.getElementById( 'paymentmethodid' ).value == id ) {
						document.getElementById( 'paymentmethodid' ).value = '';
						document.getElementById( 'customervaultid' ).value = '';
					}
				}
			},
			error: function ( jqXHR, textStatus, errorThrown ) {
				document.getElementById( 'paymentmethodli' + position ).style.backgroundColor = '#FFFFFF';
				alert( textStatus + ' ' + errorThrown );
			}
		} );
	}
}
