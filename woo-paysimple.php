<?php
/**
 * Plugin Name:       Woocommerce PaySimple Payment Gateway Integration
 * Plugin URI:        
 * Description:       Woocommerce PaySimple Payment Gateway Integration enables you to accept credit card payments securely with your paysimple merchant account.
 * Version:           1.0.6
 * Author:            Multidots
 * Author URI:        http://www.multidots.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woo-paysimple-payment-gateway
 * Domain Path:       /languages
 */
// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}


/**
 * Begins execution of the plugin.
 */
add_action('plugins_loaded', 'init_woo_paysimple_payment_gateway');

function init_woo_paysimple_payment_gateway() {

	/**
     * Tell WooCommerce that PaySimple class exists 
     */
	function add_woo_paysimple_payment_gateway($methods) {
		$methods[] = 'Woo_PaySimple_Payment_Gateway';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'add_woo_paysimple_payment_gateway');
	add_action('woocommerce_checkout_update_order_review', 'woo_simplesetSelectedCountry', 10);
	// check if ajax request
	
	if ( !empty( $_REQUEST['wc-ajax'] ) ) { 
		if(!is_admin() && (($_REQUEST['wc-ajax'])) && 'update_order_review' == $_REQUEST['wc-ajax']) {
			add_filter( 'woocommerce_available_payment_gateways',  'woo_simple_availablePaymentGateways', 10, 1 );
		}
	}
	


	function setSelectedCountry()
	{
		$gdd = sanitize_text_field($_REQUEST['country']);
	}
	
	/**
	 * Function is responsible for unset payment gateway for the other country.
	 *
	 * @param unknown_type $available_gateways
	 * @return unknown
	 */

	function woo_simple_availablePaymentGateways($payment_gateways)
	{
		foreach ($payment_gateways as $gateway) {

			if($gateway->id =='woo_paysimple_payment_gateway' && !in_array(sanitize_text_field($_REQUEST['country']), array('CA','US'))) {
				unset($payment_gateways[$gateway->id]);
			}
		}

		return $payment_gateways;
	}

	if (!class_exists('WC_Payment_Gateway'))
	return;

	/**
     * PaySimple gateway class
     */
	class Woo_PaySimple_Payment_Gateway extends WC_Payment_Gateway {
		private $selected_country;

		/**
         * Constructor
         */
		public function __construct() {
			$this->id = 'woo_paysimple_payment_gateway';
			$this->icon = apply_filters('woocommerce_paysimple_icon', plugins_url('images/cards.png', __FILE__));
			$this->has_fields = true;
			$this->method_title = 'PaySimple';
			$this->method_description = 'PaySimple for WooCommerce authorizes credit card payments and processes them securely with your merchant account.';
			$this->supports = array('products', 'refunds');
			// Load the form fields
			$this->init_form_fields();
			// Load the settings
			$this->init_settings();
			// Get setting values
			$this->title = $this->get_option('title');
			$this->description = $this->get_option('description');
			$this->enabled = $this->get_option('enabled');
			$this->sandbox = $this->get_option('sandbox');
			$this->environment = $this->sandbox == 'no' ? 'production' : 'sandbox';
			$this->paysimple_username = $this->sandbox == 'no' ? $this->get_option('paysimple_username') : $this->get_option('sandbox_paysimple_username');
			$this->paysimple_sharedsecret = $this->sandbox == 'no' ? $this->get_option('paysimple_sharedsecret') : $this->get_option('sandbox_paysimple_sharedsecret');

			$this->debug = isset($this->settings['debug']) ? $this->settings['debug'] : 'no';
			// Hooks

			register_activation_hook(__FILE__, array($this, 'activate_woo_paysimple_payment_gateway'));
			register_deactivation_hook(__FILE__, array($this, 'deactivate_woo_paysimple_payment_gateway'));

			add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
			add_action('admin_notices', array($this, 'checks'));
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			
		}


		/**
         * Admin Panel Options
         */
		public function admin_options() { 
			global $wpdb;  
			$current_user = wp_get_current_user();
			wp_enqueue_script('jquery-ui-dialog');
			if (!get_option('ps_plugin_notice_shown')) {
			 echo '<div id="ps_dialog" title="Basic dialog"><p>Subscribe for latest plugin update and get notified when we update our plugin and launch new products for free! </p> <p><input type="text" id="txt_user_sub_ps" class="regular-text" name="txt_user_sub_ps" value="'.$current_user->user_email.'"></p></div>';
			 
			}	 
            ?>
            <script type="text/javascript">
				jQuery( document ).ready(function() {
					jQuery( "#ps_dialog" ).dialog({
						modal: true, title: 'Subscribe Now', zIndex: 10000, autoOpen: true,
						width: '500', resizable: false,
						position: {my: "center", at:"center", of: window },
						dialogClass: 'dialogButtons',
						buttons: {
							Yes: function () {
								// $(obj).removeAttr('onclick');
								// $(obj).parents('.Parent').remove();
								var email_id = jQuery('#txt_user_sub_ps').val();
								
								var data = {
								'action': 'add_plugin_user_ps',
								'email_id': email_id
								};
								
								// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
								jQuery.post(ajaxurl, data, function(response) {
								jQuery('#ps_dialog').html('<h2>You have been successfully subscribed');
								jQuery(".ui-dialog-buttonpane").remove();
								});
								
								
								},
							No: function () {
								var email_id = jQuery('#txt_user_sub_ps').val();
								
								var data = {
								'action': 'hide_subscribe_ps',
								'email_id': email_id
								};
								
								// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
								jQuery.post(ajaxurl, data, function(response) {
													
								});
								
								jQuery(this).dialog("close");
								
								}
						},
						close: function (event, ui) {
							jQuery(this).remove();
						}
					});
					jQuery("div.dialogButtons .ui-dialog-buttonset button").removeClass('ui-state-default');
					jQuery("div.dialogButtons .ui-dialog-buttonset button").addClass("button-primary woocommerce-save-button");
					jQuery("div.dialogButtons .ui-dialog-buttonpane .ui-button").css("width","80px");
				});
				</script>
				
            <h3><?php _e('PaySimple for WooCommerce', 'woo-paysimple-payment-gateway'); ?></h3>
            <p><?php _e('PaySimple for WooCommerce authorizes credit card payments and processes them securely with your merchant account.', 'woo-paysimple-payment-gateway'); ?></p>
            <table class="form-table">
                <?php esc_html($this->generate_settings_html()); ?>
            </table> <?php
		}
		/**
             * Check if SSL is enabled and notify the user
             */
		public function checks() {
			if ($this->enabled == 'no') {
				return;
			}

			// PHP Version
			if (version_compare(phpversion(), '5.2.1', '<')) {
				echo '<div class="error"><p>' . sprintf(__('Woo PaySimple Payment Gateway Error: PaySimple requires PHP 5.2.1 and above. You are using version %s.', 'woo-paysimple-payment-gateway'), phpversion()) . '</p></div>';
			}

			// Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected
			elseif ('no' == get_option('woocommerce_force_ssl_checkout') && !class_exists('WordPressHTTPS')) {
				echo '<div class="error"><p>' . sprintf(__('Woo PaySimple Payment Gateway is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout may not be secure!', 'woo-paysimple-payment-gateway'), admin_url('admin.php?page=wc-settings&tab=checkout')) . '</p></div>';
			}
		}

		/**
             * Check if this gateway is enabled
             */
		public function is_available() {
			if ('yes' != $this->enabled) {
				return false;
			}

			if (!is_ssl() && 'yes' != $this->sandbox) {
				//	return false;
			}

			return true;
		}

		/**
             * Initialise Gateway Settings Form Fields
             */
		public function init_form_fields() {
			$this->form_fields = array(
			'enabled' => array(
			'title' => __('Enable/Disable', 'woo-paysimple-payment-gateway'),
			'label' => __('Enable Woo PaySimple Payment Gateway', 'woo-paysimple-payment-gateway'),
			'type' => 'checkbox',
			'description' => '',
			'default' => 'no'
			),
			'title' => array(
			'title' => __('Title', 'woo-paysimple-payment-gateway'),
			'type' => 'text',
			'description' => __('This controls the title which the user sees during checkout.', 'woo-paysimple-payment-gateway'),
			'default' => __('Credit card', 'woo-paysimple-payment-gateway'),
			'desc_tip' => true
			),
			'description' => array(
			'title' => __('Description', 'woo-paysimple-payment-gateway'),
			'type' => 'textarea',
			'description' => __('This controls the description which the user sees during checkout.', 'woo-paysimple-payment-gateway'),
			'default' => 'Pay securely with your credit card.',
			'desc_tip' => true
			),
			'sandbox' => array(
			'title' => __('Sandbox', 'woo-paysimple-payment-gateway'),
			'label' => __('Enable Sandbox Mode', 'woo-paysimple-payment-gateway'),
			'type' => 'checkbox',
			'description' => __('Place the payment gateway in sandbox mode using sandbox API keys (real payments will not be taken).', 'woo-paysimple-payment-gateway'),
			'default' => 'yes'
			),
			'sandbox_paysimple_username' => array(
			'title' => __('Sandbox PaySimple Username', 'woo-paysimple-payment-gateway'),
			'type' => 'text',
			'description' => __('Get your PaySimpleUsername from your PaySimple account.', 'woo-paysimple-payment-gateway'),
			'default' => '',
			'desc_tip' => true
			),
			'sandbox_paysimple_sharedsecret' => array(
			'title' => __('Sandbox PaySimple SecretKey', 'woo-paysimple-payment-gateway'),
			'type' => 'textarea',
			'description' => __('Get your PaySimplePassword from your PaySimple account.', 'woo-paysimple-payment-gateway'),
			'default' => '',
			'desc_tip' => true
			),
			'paysimple_username' => array(
			'title' => __('Live PaySimple Username', 'woo-paysimple-payment-gateway'),
			'type' => 'text',
			'description' => __('Get your PaySimpleUsername from your PaySimple account.', 'woo-paysimple-payment-gateway'),
			'default' => '',
			'desc_tip' => true
			),
			'paysimple_sharedsecret' => array(
			'title' => __('Live PaySimple SecretKey', 'woo-paysimple-payment-gateway'),
			'type' => 'textarea',
			'description' => __('Get your PaySimplePassword from your PaySimple account.', 'woo-paysimple-payment-gateway'),
			'default' => '',
			'desc_tip' => true
			),

			'debug' => array(
			'title' => __('Debug', 'woo-paysimple-payment-gateway'),
			'type' => 'checkbox',
			'label' => __('Enable logging <code>/wp-content/uploads/wc-logs/woo-paysimple{tag}.log</code>', 'woo-paysimple-payment-gateway'),
			'default' => 'no'
			),
			);
		}

		/**
             * Initialise Credit Card Payment Form Fields
             */
		public function payment_fields() { 
                ?>
            <style type="text/css">
                .blockUI.blockOverlay {
                    position: initial !important;
                }
            </style>    
            <fieldset id="paysimple-cc-form">
                <p class="form-row form-row-wide">
                    <label for="paysimple-card-number"><?php echo __('Card Number', 'woo-paysimple-payment-gateway') ?> <span class="required">*</span></label>
                    <input type="text" data-encrypted-name="paysimple-card-number" placeholder="" autocomplete="off" maxlength="20" class="input-text wc-credit-card-form-card-number" id="woo-paysimple-payment-gateway-card-number" name='woo-paysimple-payment-gateway-card-number'>
                </p>

                <p class="form-row form-row-first paysimple-card-expiry">
                    <label for="paysimple-card-expiry-month"><?php echo __('Expiry', 'woo-paysimple-payment-gateway') ?> <span class="required">*</span></label>
                    <select name="woo-paysimple-payment-gateway-card-expiry-month" id="woo-paysimple-payment-gateway-card-expiry-month" class="input-text">
                        <option value=""><?php _e('Month', 'woo-paysimple-payment-gateway') ?></option>
                        <option value='01'>01</option>
                        <option value='02'>02</option>
                        <option value='03'>03</option>
                        <option value='04'>04</option>
                        <option value='05'>05</option>
                        <option value='06'>06</option>
                        <option value='07'>07</option>
                        <option value='08'>08</option>
                        <option value='09'>09</option>
                        <option value='10'>10</option>
                        <option value='11'>11</option>
                        <option value='12'>12</option>  
                    </select>

                    <select name="woo-paysimple-payment-gateway-card-expiry-year" id="woo-paysimple-payment-gateway-card-expiry-year" class="input-text">
                        <option value=""><?php _e('Year', 'woo-paysimple-payment-gateway') ?></option><?php
                        for ($iYear = date('Y'); $iYear < date('Y') + 21; $iYear++) {
                        	echo '<option value="' . $iYear . '">' . $iYear . '</option>';
                        }
            ?>
                    </select>
                </p>

                <p class="form-row form-row-last">
                    <label for="paysimple-card-cvc"><?php echo __('Card Code', 'woo-paysimple-payment-gateway') ?> <span class="required">*</span></label>
                    <input type="text" data-encrypted-name="paysimple-card-cvc" placeholder="CVC" autocomplete="off" class="input-text wc-credit-card-form-card-cvc" name ='woo-paysimple-payment-gateway-card-cvc' id="woo-paysimple-payment-gateway-card-cvc">
                </p>
            </fieldset>
            <?php
		}

		/**
         * Outputs style used for Woo PaySimple Payment Gateway Payment fields
         * Outputs scripts used for Woo PaySimple Payment Gateway
         */
		public function payment_scripts() {
			if (!is_checkout() || !$this->is_available()) {
				return;
			}
		}

		/**
         * Process the payment
         */
		public function process_payment($order_id) {
			require_once( 'includes/woo-paysimple-functions.php' );

			$obj = new Woo_PaySimple_Functions();
			global $woocommerce;
			$order = new WC_Order($order_id);
			$order_id = $order->id;

			global $current_user;
			get_currentuserinfo();
			$data = array();
			$data['environment'] = isset($this->environment) ? $this->environment: '' ;
			$data['paysimple_username'] = isset($this->paysimple_username) ? $this->paysimple_username : '' ;
			$data['paysimple_sharedsecret'] = isset($this->paysimple_sharedsecret) ? $this->paysimple_sharedsecret : '';

			if(is_user_logged_in()) {
				global $current_user;
				get_currentuserinfo();
				$paysimple_accountid = get_user_meta($current_user->ID,'_woo_paysimple_accountid',true);

			}
			if (empty($paysimple_accountid)) {
				$userData = $obj->createCustomer($data);
				$card_detail = array();
				$add_card = array();
				$card_number = intval($_POST['woo-paysimple-payment-gateway-card-number']);
				$card_exp_month = intval($_POST['woo-paysimple-payment-gateway-card-expiry-month']);
				$card_exp_year = intval($_POST['woo-paysimple-payment-gateway-card-expiry-year']);
				$add_card['card_number'] = isset($card_number) ? $card_number :'';
				$add_card['card_exp_month'] = isset($card_exp_month) ? $card_exp_month : '';
				$add_card['card_exp_year'] = isset($card_exp_year) ? $card_exp_year : '';
				$add_card['paysimple_customer_id'] = $userData->Response->Id;
				if (isset($_POST['ship_to_different_address']) && !empty($_POST['ship_to_different_address'])) {
					$add_card['zipcode'] = sanitize_text_field($_POST['shipping_postcode']);
				}else{
					$add_card['zipcode'] = sanitize_text_field($_POST['billing_postcode']);
				}

				$accountData = $obj->addCreditCardAccount($add_card);
				if(is_user_logged_in()) {
					global $current_user;
					get_currentuserinfo();
					add_user_meta($current_user->ID, '_woo_paysimple_customerid', $userData->Response->Id);
				}

			}else {

				$get_current_cusomerid = get_user_meta($current_user->ID,'_woo_paysimple_customerid',true);
				if (isset($get_current_cusomerid) && !empty($get_current_cusomerid)) {
					$customerid = $get_current_cusomerid;
				}

				$card_detail = array();
				$add_card = array();
				$card_number = intval($_POST['woo-paysimple-payment-gateway-card-number']);
				$card_exp_month = intval($_POST['woo-paysimple-payment-gateway-card-expiry-month']);
				$card_exp_year = intval($_POST['woo-paysimple-payment-gateway-card-expiry-year']);
				$add_card['card_number'] = isset($card_number) ? $card_number :'';
				$add_card['card_exp_month'] = isset($card_exp_month) ? $card_exp_month : '';
				$add_card['card_exp_year'] = isset($card_exp_year) ? $card_exp_year : '';
				
				$add_card['paysimple_customer_id'] = isset($customerid) ? $customerid : '';
				if (isset($_POST['ship_to_different_address']) && !empty($_POST['ship_to_different_address'])) {
					$add_card['zipcode'] = sanitize_text_field($_POST['shipping_postcode']);
				}else{
					$add_card['zipcode'] = sanitize_text_field($_POST['billing_postcode']);
				}

				$accountData = $obj->addCreditCardAccount($add_card);
			}


			if (isset($accountData->Response->Id) && !empty($accountData->Response->Id)) {
				update_option('woo_simplepay_temp_id',$accountData->Response->Id);
			}

			if (isset($accountData->Response->Id) && !empty($accountData->Response->Id)) {
				if (is_user_logged_in()) {
					global $current_user;
					get_currentuserinfo();
					add_user_meta($current_user->ID, '_woo_paysimple_accountid', $accountData->Response->Id);
				}
			}


			if (!is_user_logged_in()) {
				$get_temp_id = get_option('woo_simplepay_temp_id',true);
				if (isset($get_temp_id) && !empty($get_temp_id)) {
					$accountid = $get_temp_id;
				}
			}else if(is_user_logged_in()) {

				$accountid = get_user_meta($current_user->ID,'_woo_paysimple_accountid',true);

			}
			$pay_params = array();
			$pay_params['AccountId'] = isset($accountid) ? $accountid : '';
			$pay_params['InvoiceId'] = NULL;
			$pay_params['Amount'] = isset($order->order_total) ? $order->order_total : '';
			$pay_params['IsDebit'] = false;
			$pay_params['InvoiceNumber'] = NULL;
			$pay_params['OrderId'] = isset($order->id) ? $order->id : '';

			$result = $obj->createPayment($pay_params);

			if ($result->Meta->HttpStatusCode == 201) {

				// Payment complete
				$order->payment_complete($result->Response->Id);

				// Add order note
				$order->add_order_note(sprintf(__('%s payment approved! Trnsaction ID: %s', 'woo-paysimple-payment-gateway'), $this->title, $result->Response->Id));
				$order_comment = sanitize_text_field($_POST['order_comments']);
				$checkout_note = array(
				'ID' => $order_id,
				'post_excerpt' => isset($order_comment) ? $order_comment : '',
				);
				wp_update_post($checkout_note);

				$result_transaction_id = isset($result->Response->Id) ? $result->Response->Id : '';
				update_post_meta($order_id, 'wc_paysimple_gateway_transaction_id', $result_transaction_id);

				if (is_user_logged_in()) {
					$userLogined = wp_get_current_user();
					update_post_meta($order_id, '_billing_email', isset($userLogined->user_email) ? $userLogined->user_email : '');
					update_post_meta($order_id, '_customer_user', isset($userLogined->ID) ? $userLogined->ID : '');
				} else {

					$payermail = sanitize_text_field($_POST['billing_email']);
					update_post_meta($order_id, '_billing_email', $payermail );

				}


				$trn_bill_fname = isset($result->Response->CustomerFirstName) ? $result->Response->CustomerFirstName : '';
				$trn_bill_lname = isset($result->Response->CustomerLastName) ? $result->Response->CustomerLastName : '';

				$fullname = $trn_bill_fname . ' ' . $trn_bill_lname;

				$this->add_log(print_r($result, true));
				// Remove cart
				WC()->cart->empty_cart();
				// Return thank you page redirect
				if (is_ajax()) {
					$result = array(
					'redirect' => $this->get_return_url($order),
					'result' => 'success'
					);
					echo json_encode($result);
					exit;
				} else {
					exit;
				}
			} else if ($result->transaction) {
				$order->add_order_note(sprintf(__('%s payment declined.<br />Error: %s<br />Code: %s', 'woo-paysimple-payment-gateway'), $this->title, 'Failed', $result->Response->Id));
				$this->add_log(print_r($result, true));
			} else {
				foreach (($result->errors->deepAll()) as $error) {
					wc_add_notice("Validation error - " . $error->message, 'error');
				}
				return array(
				'result' => 'fail',
				'redirect' => ''
				);
				$this->add_log($error->message);
			}
		}

		//  Process a refund if supported
		public function process_refund($order_id, $amount = null, $reason = '') {

			require_once( 'includes/woo-paysimple-functions.php' );

			$obj_simple = new Woo_PaySimple_Functions();
			$order = new WC_Order($order_id);

			if ($amount < $order->get_total()) {
				return new WP_Error('wc_paysimple_gateway_refund-error', 'Partial refund is not supported please enter full order total amount');
			}
			$data = array();

			$data['environment'] = isset($this->environment) ? $this->environment : '';
			$data['paysimple_username'] = isset($this->paysimple_username) ? $this->paysimple_username : '';
			$data['paysimple_sharedsecret'] = isset($this->paysimple_sharedsecret) ? $this->paysimple_sharedsecret : '';
			$transation_id = get_post_meta($order_id, 'wc_paysimple_gateway_transaction_id', true);

			$result = $obj_simple->refundPayment($transation_id);

			if ($result->Meta->HttpStatusCode == 200) {

				$this->add_log(print_r($result, true));
				$max_remaining_refund = wc_format_decimal($order->get_total() - $amount);

				$order->update_status('refunded');
				$order->add_order_note(sprintf(__('%s payment refunded! Trnsaction ID: %s', 'woo-paysimple-payment-gateway'), $this->title, $result->Response->Id));
				$this->add_log(print_r($result, true));

				if (ob_get_length())
				ob_end_clean();
				return true;
			}else {
				$wc_message = apply_filters('woo_paysimple_refund_message', $result->Meta->Errors->ErrorMessages[0]->Message, $result);
				$this->add_log(print_r($result, true));

				return new WP_Error('wc_paysimple_gateway_refund-error', $wc_message);
			}
		}

		/**
         * Use WooCommerce logger if debug is enabled.
         */
		function add_log($message) {
			if ($this->debug == 'yes') {
				if (empty($this->log))
				$this->log = new WC_Logger();
				$this->log->add('woo_paysimple_payment_gateway', $message);
			}
		}

		/**
         * Run when plugin is activated
         */
		public function activate_woo_paysimple_payment_gateway() {
				set_transient( '_welcome_screen_paysimple_payment_activation_redirect_data', true, 30 );
		}

		/**
         * Run when plugin is deactivate
         */
		public function deactivate_woo_paysimple_payment_gateway() {
			
		}


	}
	
	
    add_action('admin_init', 'welcome_woo_paysimple_payment_screen_do_activation_redirect');
    add_action('admin_menu', 'welcome_pages_screen_woo_paysimple_payment');
    add_action('woocommerce_paysimple_payment_about','woocommerce_paysimple_payment_about');
    add_action( 'admin_menu','adjust_the_wp_menu_woo_paysimple', 999 );
    
    function welcome_woo_paysimple_payment_screen_do_activation_redirect(){ 
    	if (!get_transient('_welcome_screen_paysimple_payment_activation_redirect_data')) {
			return;
		}
		
		// Delete the redirect transient
		delete_transient('_welcome_screen_paysimple_payment_activation_redirect_data');

		// if activating from network, or bulk
		if (is_network_admin() || isset($_GET['activate-multi'])) {
			return;
		}
		// Redirect to extra cost welcome  page
		wp_safe_redirect(add_query_arg(array('page' => 'woocommerce_paysimple_payment&tab=about'), admin_url('index.php')));
    }
    
    function welcome_pages_screen_woo_paysimple_payment(){
    	add_dashboard_page(
		'Woocommerce PaySimple Payment Gateway Integration Dashboard', 'Woocommerce PaySimple Payment Gateway Integration Dashboard', 'read', 'woocommerce_paysimple_payment', 'welcome_screen_content_woocommerce_paysimple_payment'
		);
    	
    }	
    
    function welcome_screen_content_woocommerce_paysimple_payment(){
        ?>
        
         <div class="wrap about-wrap">
            <h1 style="font-size: 2.1em;"><?php printf(__('Welcome to Woocommerce PaySimple Payment Gateway Integration', 'analytics-for-woocommerce-by-customerio')); ?></h1>

            <div class="about-text woocommerce-about-text">
        <?php
        $message = '';
        printf(__('%s Woocommerce PaySimple Payment Gateway Integration enables you to accept credit card payments securely with your paysimple merchant account.', 'analytics-for-woocommerce-by-customerio'), $message, '1.0.2');
        ?>
                <img class="version_logo_img" src="<?php echo plugin_dir_url(__FILE__) . 'images/woo_paysimple.png'; ?>">
            </div>

        <?php
        $setting_tabs_wc = apply_filters('woocustomer_io_setting_tab', array("about" => "Overview", "other_plugins" => "Checkout our other plugins"));
        $current_tab_wc = (isset($_GET['tab'])) ? $_GET['tab'] : 'general';
        $aboutpage = isset($_GET['page'])
        ?>
            <h2 id="woo-extra-cost-tab-wrapper" class="nav-tab-wrapper">
            <?php
            foreach ($setting_tabs_wc as $name => $label)
            echo '<a  href="' . home_url('wp-admin/index.php?page=woocommerce_paysimple_payment&tab=' . $name) . '" class="nav-tab ' . ( $current_tab_wc == $name ? 'nav-tab-active' : '' ) . '">' . $label . '</a>';
            ?>
            </h2>

                <?php
                foreach ($setting_tabs_wc as $setting_tabkey_wc => $setting_tabvalue) {
                	switch ($setting_tabkey_wc) {
                		case $current_tab_wc:
                			do_action('woocommerce_paysimple_payment_' . $current_tab_wc);
                			break;
                	}
                }
                ?>
            <hr />
            <div class="return-to-dashboard">
                <a href="<?php echo home_url('/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woo_paysimple_payment_gateway'); ?>"><?php _e('Go to Woocommerce PaySimple Payment Gateway Integration Settings', 'analytics-for-woocommerce-by-customerio'); ?></a>
            </div>
        </div>
	<?php }
	
	
	
	function woocommerce_paysimple_payment_about() {
		//do_action('my_own');
		$current_user = wp_get_current_user();
    	?>
        <div class="changelog">
            </br>
           	<style type="text/css">
				p.woocustomer_io_overview {max-width: 100% !important;margin-left: auto;margin-right: auto;font-size: 15px;line-height: 1.5;}
				.woocustomer_io_ul ul li {margin-left: 3%;list-style: initial;line-height: 23px;}
			</style>  
            <div class="changelog about-integrations">
                <div class="wc-feature feature-section col three-col">
                    <div>
                    
                    <p class="woocustomer_io_overview"><?php _e('Woocommerce PaySimple Payment Gateway Integration authorizes credit card payments and processes them securely with your merchant account.', 'analytics-for-woocommerce-by-customerio'); ?></p>
                        
                         <p class="woocustomer_io_overview"><strong>Plugin Functionality: </strong></p> 
                        <div class="woocustomer_io_ul">
                        	<ul>
								<li>Easy to install and configure.</li>
								<li>Compatible with WordPress/Woocommerce plugins.</li>
								<li>You don't need any extra plugins or scripts to process the Transaction.</li>
								<li>Accepts all major credit cards and Refunds functionality available.</li>
								<li>Tested this plugin on the PaySimple sandbox (test) servers to ensure your customers don't have problems paying you.</li>
								
							</ul>
                        </div>
                        
                    </div>
                    
                </div>
            </div>
        </div>
        
        
        <?php	
    	global $wpdb;
    	$current_user =  wp_get_current_user();
		wp_enqueue_script('jquery-ui-dialog');
		if (!get_option('ps_plugin_notice_shown')) {
		echo '<div id="ps_dialog" title="Basic dialog"><p>Subscribe for latest plugin update and get notified when we update our plugin and launch new products for free! </p> <p><input type="text" id="txt_user_sub_ps" class="regular-text" name="txt_user_sub_ps" value="'.$current_user->user_email.'"></p></div>';
		}	 
        ?>
        <script type="text/javascript">
			jQuery( document ).ready(function() {
			});
			</script>
			<?php
      }
    
    function adjust_the_wp_menu_woo_paysimple(){ 
    	remove_submenu_page('index.php', 'woocommerce_paysimple_payment');
    }
    
    add_action( 'wp_ajax_hide_subscribe_ps', 'hide_subscribe_psfn' );
	
	function hide_subscribe_psfn() { 
		global $wpdb;	
		$email_id= $_POST['email_id'];
		update_option('ps_plugin_notice_shown', 'true');
	}
	
}
	
	/**Custom pointer hook**/
	add_action('admin_print_footer_scripts', 'custom_psfn_pointers_footer');
	add_action('admin_enqueue_scripts', 'enqueue_styles');
	add_action('admin_enqueue_scripts', 'enqueue_scripts');
	
	function enqueue_styles() { 
		wp_enqueue_style( 'wp-pointer' );
		?>
		<style>
		#awc_dialog {width:500px; font-size:15px; font-weight:bold;}
		#awc_dialog p {font-size:15px; font-weight:bold;}
		.free_plugin {margin-bottom: 20px;}
		.paid_plugin {margin-bottom: 20px;}
		.paid_plugin h3 {border-bottom: 1px solid #ccc;padding-bottom: 20px;}
		.free_plugin h3 {padding-bottom: 20px;border-bottom: 1px solid #ccc;}
		
		.paid_plugin {
		    margin-bottom: 20px;
		}
		
		.paid_plugin h3 {
		    border-bottom: 1px solid #ccc;
		    padding-bottom: 20px;
		}
		
		.free_plugin h3 {
		    padding-bottom: 20px;
		    border-bottom: 1px solid #ccc;
		}
		
		.plug-containter {
		    width: 100%;
		    display: inline-block;
		    margin-left: 20px;
		}
		.plug-containter .contain-section {
		    width: 25%;
		    display: inline-block;
		    margin-top: 30px;
		}
		.plug-containter .contain-section .contain-img {
		    width: 30%;
		    display: inline-block;
		}
		.plug-containter .contain-section .contain-title {
		    width: 50%;
		    display: inline-block;
		    vertical-align: middle;
		    margin-left: 10px;
		}
		.plug-containter .contain-section .contain-title a {
		    text-decoration: none;
		    line-height: 20px;
		    font-weight: bold;
		}
		.version_logo_img {
		    position: absolute;
		    right: 0;
		    top: 0;
		}
		</style>
	<?php	
	}
	
	function enqueue_scripts() { 
		wp_enqueue_script( 'wp-pointer' );
		wp_enqueue_style('wp-jquery-ui-dialog');
	}
	
	function custom_psfn_pointers_footer(){
		
		$admin_pointers = custom_psfn_pointers_admin_pointers();
		?>
		    <script type="text/javascript">
		        /* <![CDATA[ */
		        ( function($) {
		            <?php
		            foreach ( $admin_pointers as $pointer => $array ) {
		               if ( $array['active'] ) {
		                  ?>
		            $( '<?php echo $array['anchor_id']; ?>' ).pointer( {
		                content: '<?php echo $array['content']; ?>',
		                position: {
		                    edge: '<?php echo $array['edge']; ?>',
		                    align: '<?php echo $array['align']; ?>'
		                },
		                close: function() {
		                    $.post( ajaxurl, {
		                        pointer: '<?php echo $pointer; ?>',
		                        action: 'dismiss-wp-pointer'
		                    } );
		                }
		            } ).pointer( 'open' );
		            <?php
		         }
		      }
		      ?>
		        } )(jQuery);
		        /* ]]> */
		    </script>
		<?php
	}
	
	function custom_psfn_pointers_admin_pointers(){ 
		$dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
	    $version = '1_0'; // replace all periods in 1.0 with an underscore
	    $prefix = 'custom_psfn_pointers' . $version . '_';
	
	    $new_pointer_content = '<h3>' . __( 'Welcome to Woocommerce PaySimple Payment Gateway Integration' ) . '</h3>';
	    $new_pointer_content .= '<p>' . __( 'Woocommerce PaySimple Payment Gateway Integration enables you to accept credit card payments securely with your paysimple merchant account.' ) . '</p>';
	
	    return array(
	        $prefix . 'psfn_notice_view' => array(
	            'content' => $new_pointer_content,
	            'anchor_id' => '#adminmenu',
	            'edge' => 'left',
	            'align' => 'left',
	            'active' => ( ! in_array( $prefix . 'psfn_notice_view', $dismissed ) )
	        )
	    );
	
	}