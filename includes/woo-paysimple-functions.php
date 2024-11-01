<?php 

class Woo_PaySimple_Functions {

	public function __construct()
	{

	}

	/**
 * Creates a Payment record when provided with a Payment object.
 * This is a one-time payment that will be created on the current date for the Customer with the specified Account Id.
 * 
 * @param array $data
 * @return boolean|array
 */
	public function createCustomer($data) {

		$obj_simple->paysimple_username;
		$params = array();
		$params = array(
		'FirstName' => isset($_POST['billing_first_name']) ? sanitize_text_field( $_POST['billing_first_name'] ) : '',
		'LastName' =>isset($_POST['billing_last_name']) ? sanitize_text_field ( $_POST['billing_last_name'] ) : '',
		'Company' => isset($_POST['billing_company']) ? sanitize_text_field ( $_POST['billing_company'] ) : '',
		'BillingAddress' => array(
		'StreetAddress1' => isset($_POST['billing_address_1']) ? sanitize_text_field ( $_POST['billing_address_1'] ) : '' ,
		'StreetAddress2' => isset($_POST['billing_address_2']) ? sanitize_text_field ( $_POST['billing_address_2'] ) : '' ,
		'City' => isset($_POST['billing_city']) ? sanitize_text_field( $_POST['billing_city'] ) : '' ,
		'Country' => isset($_POST['billing_country']) ? sanitize_text_field( $_POST['billing_country'] ) : '',
		'StateCode' => isset($_POST['billing_state']) ? sanitize_text_field ( $_POST['billing_state'] ) : '',
		'ZipCode' => isset($_POST['billing_postcode']) ? sanitize_text_field ( $_POST['billing_postcode'] ) : '',
		),
		'ShippingSameAsBilling' => true,
		'Email' =>  isset($_POST['billing_email']) ? sanitize_text_field ( $_POST['billing_email'] ) :'',
		//'Phone' => $data['Customer']['phone'],
		//'Phone' => isset($_POST['billing_phone']) ? intval($_POST['billing_phone']) : '0',
		);

		if (isset($_POST['ship_to_different_address']) && !empty($_POST['ship_to_different_address'])) {
			// their shipping is not the same as their billing

			$params['ShippingSameAsBilling'] = false;
			$params['ShippingAddress'] = array(
			'StreetAddress1' => isset($_POST['shipping_address_1']) ? sanitize_text_field ( $_POST['shipping_address_1'] ) : '' ,
			'StreetAddress2' =>isset($_POST['shipping_address_2']) ? sanitize_text_field ( $_POST['shipping_address_2'] ) : '' ,
			'City' => isset($_POST['shipping_city']) ? sanitize_text_field ( $_POST['shipping_city'] ) : '' ,
			'StateCode' => isset($_POST['shipping_state']) ? sanitize_text_field ( $_POST['shipping_state'] ) : '',
			'ZipCode' => isset($_POST['shipping_postcode']) ? sanitize_text_field ( $_POST['shipping_postcode'] ) : '0',
			);
		}
		return $this->_sendRequest('POST', '/customer', $params);
	}

	/**
	 * Prepares and sends your request to the API servers
	 * 
	 * @param string $method POST | GET | UPDATE | DELETE
	 * @param string $action PaySimple API endpoint
	 * @param array $data A PaySimple API Request Body packet as an array
	 * @return boolean|array Returns Exception/FALSE or the "Response" array
   */
	public function _sendRequest($method, $action, $data = NULL) {
		$conf_settings = get_option('woocommerce_woo_paysimple_payment_gateway_settings',true);
		$mode = $conf_settings['sandbox'];
		$environment = $mode == 'no' ? 'production' : 'sandbox';
		$paysimple_username = $mode == 'no' ?  $conf_settings['paysimple_username'] :  sanitize_text_field( $conf_settings['sandbox_paysimple_username'] );
		$paysimple_sharedsecret = $mode == 'no' ?  $conf_settings['paysimple_sharedsecret'] : sanitize_text_field( $conf_settings['sandbox_paysimple_sharedsecret'] );
		if ($mode == 'yes') {
			$endpoint = 'https://sandbox-api.paysimple.com/v4';
		} else {
			$endpoint = 'https://api.paysimple.com/v4';
		}
		$userName = $paysimple_username;
		$superSecretCode = $paysimple_sharedsecret;
		$timestamp = gmdate("c");
		$hmac = hash_hmac("sha256", $timestamp, $superSecretCode, true); //note the raw output parameter
		$hmac = base64_encode($hmac);
		$auth = "Authorization: PSSERVER AccessId = $userName; Timestamp = $timestamp; Signature = $hmac";
		$url = $endpoint.$action;
		$data = $data; //array('FirstName' => 'Ross','LastName' => 'Person','Email' => 'testperson@abcco.com', 'php_master' => true);
		$post_args = json_encode($data);
		$headers = array($auth, "Content-Type: application/json; charset=utf-8");

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_POST, TRUE);
		if ($method == 'PUT') {
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
		}
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $post_args);
		$result = curl_exec($curl);
		$responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$result = json_decode($result);

		curl_close($curl);
		return $result;
	}



	/**
	 * Creates a Credit Card Account record when provided with a Credit Card Account object
	 * 
	 * @param array $data
	 * @return boolean|array
   */
	public function addCreditCardAccount($data, $IsDefault = false) {


		$params = array(
		'Id' => 0,
		'IsDefault' => $IsDefault,
		'Issuer' => $this->cardType($data['card_number']),
		'CreditCardNumber' => sanitize_text_field( $data['card_number'] ),
		'ExpirationDate' => sanitize_text_field( $data['card_exp_month'] ) . '/' . sanitize_text_field ( $data['card_exp_year'] ),
		'CustomerId' => sanitize_text_field ( $data['paysimple_customer_id'] ),
		'BillingZipCode' => sanitize_text_field ( $data['zipcode'] ),
		);
		return $this->_sendRequest('POST', '/account/creditcard', $params);
	}


	/**
	 * Creates a Payment record when provided with a Payment object.
	 * This is a one-time payment that will be created on the current date for the Customer with the specified Account Id.
	 * 
	 * @param array $data
	 * @param array $data
 	 * @return boolean|array
 	 */

	public function createPayment($data) {
		$params = array(
		'AccountId' => $data['AccountId'],
		'InvoiceId' => NULL,
		'Amount' => $data['Amount'],
		'IsDebit' => false, // IsDebit indicates whether this Payment is a refund.
		'InvoiceNumber' => $data['OrderId'],
		'OrderId' => $data['OrderId'],
		'Id' => 0
		);

		return $this->_sendRequest('POST', '/payment', $params);
	}

	/**
	 * Creates a Payment record when provided with a Payment object.
	 * This is a one-time payment that will be created on the current date for the Customer with the specified Account Id.
	 * 
	 * @param array $data
	 * @return boolean|array
	 */
	public function refundPayment($PaymentId) {
		return $this->_sendRequest('PUT', '/payment/'.$PaymentId.'/reverse');
	}

	/**
	 * Function is responsible for verify card type
	 *
	 * @param unknown_type $number
	 * @return unknown
	 */
	public function cardType($number)
	{
		$number=preg_replace('/[^\d]/','',$number);
		if (preg_match('/^3[47][0-9]{13}$/',$number))
		{
			return 14; //amex
		}

		elseif (preg_match('/^6(?:011|5[0-9][0-9])[0-9]{12}$/',$number))
		{
			return 15;//discover
		}

		elseif (preg_match('/^5[1-5][0-9]{14}$/',$number))
		{
			return 13; //master
		}
		elseif (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/',$number))
		{
			return 12;//visa
		}
		else
		{
			return 'Unknown';
		}
	}
}