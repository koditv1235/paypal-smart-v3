<?php
	//load classes init method
	add_action('init', array('PMProGateway_paypal_smart', 'init'));

	class PMProGateway_paypal_smart extends PMProGateway{

		public $paypal_url;
		function __construct($name) {
			$this->paypal_url = (pmpro_getOption( 'gateway_environment' ) == 'sandbox') ? 'https://api.sandbox.paypal.com/' : 'https://api.paypal.com/';
		}
			
		

		function PMProGateway($gateway = NULL){
			$this->gateway = $gateway;
			return $this->gateway;
		}										

		/**
		 * Run on WP init
		 *
		 * @since 1.8
		 */
		static function init(){
			//make sure paypal smart is a gateway option
			add_filter('pmpro_gateways', array('PMProGateway_paypal_smart', 'pmpro_gateways'));

			//add fields to payment settings
			add_filter('pmpro_payment_options', array('PMProGateway_paypal_smart', 'pmpro_payment_options'));
			add_filter('pmpro_payment_option_fields', array('PMProGateway_paypal_smart', 'pmpro_payment_option_fields'), 10, 2);

			//add some fields to edit user page (Updates)
			// add_action('pmpro_after_membership_level_profile_fields', array('PMProGateway_paypal_smart', 'user_profile_fields'));
			// add_action('profile_update', array('PMProGateway_paypal_smart', 'user_profile_fields_save'));

			//updates cron
			add_action('pmpro_activation', array('PMProGateway_paypal_smart', 'pmpro_activation'));
			add_action('pmpro_deactivation', array('PMProGateway_paypal_smart', 'pmpro_deactivation'));
			add_action('pmpro_cron_paypal_smart_subscription_updates', array('PMProGateway_paypal_smart', 'pmpro_cron_paypal_smart_subscription_updates'));

			//code to add at checkout if paypal smart is the current gateway
			$gateway = pmpro_getOption("gateway");
			if($gateway == "paypal_smart")
			{
				//valodate user name and email
				add_action("wp_ajax_pmpro_check_user", array('PMProGateway_paypalsmart','pmpro_check_user'));
				add_action("wp_ajax_nopriv_pmpro_check_user", array('PMProGateway_paypalsmart','pmpro_check_user'));

				add_filter('pmpro_required_billing_fields', array('PMProGateway_paypalexpress', 'pmpro_required_billing_fields'));
				add_action('pmpro_checkout_preheader', array('PMProGateway_paypal_smart', 'pmpro_checkout_preheader'));
				add_filter('pmpro_checkout_order', array('PMProGateway_paypal_smart', 'pmpro_checkout_order'));
				add_filter('pmpro_billing_order', array('PMProGateway_paypal_smart', 'pmpro_checkout_order'));
				
				add_filter('pmpro_include_billing_address_fields', '__return_false');
				add_filter('pmpro_include_payment_information_fields', '__return_false');
				
				add_filter('pmpro_checkout_default_submit_button', array('PMProGateway_paypal_smart', 'pmpro_checkout_default_submit_button'));
			}
		}

		/**
		 * Make sure paypal smart is in the gateways list
		 *
		 * @since 1.8
		 */
		static function pmpro_gateways($gateways){
			if(empty($gateways['paypal_smart']))
				$gateways['paypal_smart'] = __('PayPal Smart Button', 'pmpro');

			return $gateways;
		}

		/**
		 * Get a list of payment options that the paypal smart gateway needs/supports.
		 *
		 * @since 1.8
		 */
		static function getGatewayOptions(){
			$options = array(
				'sslseal',
				'nuclear_HTTPS',
				'gateway_environment',
				'paypal_client_id',
				'paypal_secret_key',
				'currency',
				'use_ssl',
				'tax_state',
				'tax_rate',
			);

			return $options;
		}

		static function pmpro_check_user(){
			if(username_exists(sanitize_text_field( $_POST['username'] )) || email_exists( sanitize_email( $_POST['bemail'] ) ))
				echo 'FALSE';
			else
				echo 'TRUE';
			wp_die();
		}

		/**
		 * Set payment options for payment settings page.
		 *
		 * @since 1.8
		 */
		static function pmpro_payment_options($options){
			//get paypal smart options
			$paypal_smart_options = PMProGateway_paypal_smart::getGatewayOptions();

			//merge with others.
			$options = array_merge($paypal_smart_options, $options);

			return $options;
		}

		/**
		 * Display fields for paypal smart options.
		 *
		 * @since 1.8
		 */
		static function pmpro_payment_option_fields($values, $gateway){
			?>
			<tr class="pmpro_settings_divider gateway gateway_paypal_smart" <?php if($gateway != "paypal_smart") { ?>style="display: none;"<?php } ?>>
				<td colspan="2">
					<?php _e('PayPal Smart Button Settings', 'pmpro'); ?>
				</td>
			</tr>
			<tr class="gateway gateway_paypal_smart" <?php if($gateway != "paypal_smart") { ?>style="display: none;"<?php } ?>>
				
			<tr class="gateway gateway_paypal_smart" <?php if ( $gateway != "paypal_smart" ) { ?>style="display: none;"<?php } ?>>
				<th scope="row" valign="top">
					<label for="paypal_client_id"><?php _e( 'Publishable Key', 'paid-memberships-pro' ); ?>:</label>
				</th>
				<td>
					<input type="text" id="paypal_client_id" name="paypal_client_id" value="<?php echo esc_attr( $values['paypal_client_id'] ) ?>" class="regular-text code" />
				</td>
			</tr>
			<tr class="gateway gateway_paypal_smart" <?php if ( $gateway != "paypal_smart" ) { ?>style="display: none;"<?php } ?>>
				<th scope="row" valign="top">
					<label for="paypal_secret_key"><?php _e( 'Secret Key', 'paid-memberships-pro' ); ?>:</label>
				</th>
				<td>
					<input type="text" id="paypal_secret_key" name="paypal_secret_key" value="<?php echo esc_attr( $values['paypal_secret_key'] ) ?>" class="regular-text code" />
				</td>
			</tr>
		
			</tr>
			<?php
		}

		static function pmpro_checkout_preheader($morder){
			global $wpdb, $gateway, $pmpro_currency, $pmpro_level;
			$user = wp_get_current_user(); 

			$default_gateway = pmpro_getOption("gateway");

			if(($gateway == "paypal_smart" || $default_gateway == "paypal_smart")) {

				wp_register_script( 'paypalsmart', 'https://www.paypal.com/sdk/js?client-id='.pmpro_getOption('paypal_client_id').'&currency='.$pmpro_currency.'&disable-funding=credit', null, null );
				wp_enqueue_script( 'paypalsmart' );

				$amount = $pmpro_level->initial_payment;
				// $amount_tax = $morder->getTax($amount);
				// $amount = pmpro_round_price((float)$amount + (float)$amount_tax);

				$localize_vars = array(
					'gateway_environment' => pmpro_getOption( 'gateway_environment' ),
					'ajax_url' => admin_url( 'admin-ajax.php'),
					'amount' =>  number_format($amount,2,".",""),
					'isLogin' => (isset($user->ID)) ? true : false
				);
				
				wp_register_script( 'pmpro_paypal_smart', plugins_url( '/pmpro-paypal-smart-gateway/js/pmpro-paypal_smart.js' ), array( 'jquery' ), PMPRO_VERSION );
				wp_localize_script( 'pmpro_paypal_smart', 'pmproPayPalSmart', $localize_vars );
				wp_enqueue_script( 'pmpro_paypal_smart' );

				// wp_register_script( 'jquery_validate', 'https://cdn.jsdelivr.net/npm/jquery-validation@1.19.1/dist/jquery.validate.min.js', null, null );
				// wp_enqueue_script( 'jquery_validate' );

			}
		}

		/**
		 * Filtering orders at checkout.
		 *
		 * @since 1.8
		 */
		static function pmpro_checkout_order($morder){
			// Create a code for the order.
			if ( empty( $morder->code ) ) {
				$morder->code = $morder->getRandomCode();
			}

			return $morder;
		}

		/**
		 * Code to run after checkout
		 *
		 * @since 1.8
		 */
		static function pmpro_after_checkout($user_id, $morder){
		}
		
		/**
		 * Use our own payment fields at checkout. (Remove the name attributes.)		
		 * @since 1.8
		 */
		static function pmpro_checkout_default_submit_button($include){
			global $pmpro_requirebilling, $pmpro_level;
			if(!pmpro_isLevelRecurring($pmpro_level)){
				 ?>

				<div <?php ( ! $pmpro_requirebilling || apply_filters( "pmpro_hide_payment_information_fields", false ) ) ? 'style="display: none;"' : ''; ?> >
					
					<input type="hidden" name="submit-checkout" value="1">
					<input type="hidden" name="intent" value="CAPTURE">
					<input type="hidden" name="javascriptok" value="1">
					<input type="hidden" id="order-id" name="orderID" value="">
			
					<div id="paypal-button-container"></div> <?php
					$sslseal = pmpro_getOption( "sslseal" );  
					if ( ! empty( $sslseal ) ) { ?>
						<div class="<?php echo pmpro_get_element_class( 'pmpro_checkout-fields-display-seal' ); ?>"> <?php 
					} ?>
				</div><?php
			}
			?> 
				
			<?php 
			//don't include the default
			// return false;
		}

		/**
		 * Fields shown on edit user page
		 *
		 * @since 1.8
		 */
		static function user_profile_fields($user){
		}

		/**
		 * Process fields from the edit user page
		 *
		 * @since 1.8
		 */
		static function user_profile_fields_save($user_id){
		}

		/**
		 * Cron activation for subscription updates.
		 *
		 * @since 1.8
		 */
		static function pmpro_activation(){
			wp_schedule_event(time(), 'daily', 'pmpro_cron_paypal_smart_subscription_updates');
		}

		/**
		 * Cron deactivation for subscription updates.
		 *
		 * @since 1.8
		 */
		static function pmpro_deactivation(){
			wp_clear_scheduled_hook('pmpro_cron_paypal_smart_subscription_updates');
		}

		/**
		 * Cron job for subscription updates.
		 *
		 * @since 1.8
		 */
		static function pmpro_cron_paypal_smart_subscription_updates(){
		}


		static function pmpro_required_billing_fields($fields){
			global $current_user, $bemail, $bconfirmemail;

			$remove = array('bfirstname','blastname','baddress1','bcity','bstate','bzipcode','bphone','bcountry','CardType','AccountNumber','ExpirationMonth','ExpirationYear','CVV');

			//if a user is logged in, don't require bemail either
			if ( ! empty( $current_user->user_email ) ) {
				$remove[]      = 'bemail';
				$bemail        = $current_user->user_email;
				$bconfirmemail = $bemail;
			}
			
			foreach ( $remove as $field ) {
				unset( $fields[ $field ] );
			}

        	return $fields;
		}
				
		function process(&$order){
			
			
			//check for initial payment
			if(floatval($order->InitialPayment) == 0){
				//auth first, then process
				if($this->authorize($order)){						
					$this->void($order);										
					if(!pmpro_isLevelTrial($order->membership_level)){
						//subscription will start today with a 1 period trial (initial payment charged separately)
						$order->ProfileStartDate = date("Y-m-d") . "T0:0:0";
						$order->TrialBillingPeriod = $order->BillingPeriod;
						$order->TrialBillingFrequency = $order->BillingFrequency;													
						$order->TrialBillingCycles = 1;
						$order->TrialAmount = 0;
						
						//add a billing cycle to make up for the trial, if applicable
						if(!empty($order->TotalBillingCycles))
							$order->TotalBillingCycles++;
					}
					elseif($order->InitialPayment == 0 && $order->TrialAmount == 0){
						//it has a trial, but the amount is the same as the initial payment, so we can squeeze it in there
						$order->ProfileStartDate = date("Y-m-d") . "T0:0:0";														
						$order->TrialBillingCycles++;
						
						//add a billing cycle to make up for the trial, if applicable
						if($order->TotalBillingCycles)
							$order->TotalBillingCycles++;
					}
					else{
						//add a period to the start date to account for the initial payment
						$order->ProfileStartDate = date("Y-m-d", strtotime("+ " . $order->BillingFrequency . " " . $order->BillingPeriod, current_time("timestamp"))) . "T0:0:0";
					}
					
					$order->ProfileStartDate = apply_filters("pmpro_profile_start_date", $order->ProfileStartDate, $order);
					return $this->subscribe($order);
				}
				else{
					if(empty($order->error))
						$order->error = __("Unknown error: Authorization failed.", "pmpro");
					return false;
				}
			}else{
				//charge first payment		
				if($this->charge($order)){	
					
					return true;
				}	
				
			}
			return false;
		}
		
		/*
			Run an authorization at the gateway.

			Required if supporting recurring subscriptions
			since we'll authorize $1 for subscriptions
			with a $0 initial payment.
		*/
		function authorize(&$order){
			//create a code for the order
			if(empty($order->code))
				$order->code = $order->getRandomCode();
			
			//code to authorize with gateway and test results would go here

			//simulate a successful authorization
			$order->payment_transaction_id = "ZERO-" . $order->code;
			$order->updateStatus("authorized");													
			return true;					
		}
		
		/*
			Void a transaction at the gateway.

			Required if supporting recurring transactions
			as we void the authorization test on subs
			with a $0 initial payment and void the initial
			payment if subscription setup fails.
		*/
		function void(&$order){
			//need a transaction id
			if(empty($order->payment_transaction_id))
				return false;
			
			//code to void an order at the gateway and test results would go here

			//simulate a successful void
			$order->payment_transaction_id = "TEST" . $order->code;
			$order->updateStatus("voided");					
			return true;
		}	
		
		/*
			Make a charge at the gateway.

			Required to charge initial payments.
		*/
		function charge(&$order){
			global $pmpro_currency;

			$result = $this->getAccessToken();  
			
			if(wp_remote_retrieve_response_code($result) == 200){
				$response = json_decode(wp_remote_retrieve_body($result)); 
				
				
				//create a code for the order
				if(empty($order->code))
					$order->code = $order->getRandomCode();
			
	
				$headers = array( 
					'Content-Type'=> 'application/x-www-form-urlencoded',
					'Accept' => 'application/json',
					'Authorization' => 'Bearer '. $response->access_token
				);

				$pload = array(
					'method' => 'GET',
					'timeout' => 30,
					'redirection' => 5,
					'httpversion' => '1.0',
					'blocking' => true,
					'headers' => $headers,
					'cookies' => array()
				);

				$result = wp_remote_post( $this->paypal_url . 'v2/checkout/orders/'.$_POST['orderID'], $pload );

				if(wp_remote_retrieve_response_code($result) == 200){
					$response = json_decode(wp_remote_retrieve_body($result)); 
					
					$amount = $order->InitialPayment;
					
					if($response->status == 'COMPLETED' && $amount == $response->purchase_units[0]->amount->value && strtolower($pmpro_currency) == strtolower($response->purchase_units[0]->amount->currency_code)){
						$order->payment_transaction_id = $_POST['orderID'];
						$order->payment_type = "PayPal Smart Button";
						$order->updateStatus("success");					
						return true;
					}
				}
			}
			return false;
		}
		
		function capturePayment($accessToken){
			
			$post_data = array('grant_type' => 'client_credentials' );

			$headers = array( 
				'Content-Type'=> 'application/x-www-form-urlencoded',
				'Accept' => 'application/json',
				'Authorization' => 'Bearer '. $accessToken
			);

			$pload = array(
				'method' => 'POST',
				'timeout' => 30,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking' => true,
				'headers' => $headers,
				'body' => $post_data,
				'cookies' => array()
			);

			return wp_remote_post( $this->paypal_url . 'v2/checkout/orders/5O190127TN364715T/capture', $pload );

		}

		function getAccessToken(){
			
			$post_data = array('grant_type' => 'client_credentials' );

			$headers = array( 
				'Content-Type'=> 'application/x-www-form-urlencoded',
				'Accept' => 'application/json',
				'Authorization' => 'Basic '. base64_encode(pmpro_getOption('paypal_client_id').':'.pmpro_getOption('paypal_secret_key'))
			);

			$pload = array(
				'method' => 'POST',
				'timeout' => 30,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking' => true,
				'headers' => $headers,
				'body' => $post_data,
				'cookies' => array()
			);

			return wp_remote_post( $this->paypal_url . 'v1/oauth2/token', $pload );

		}


		/*
			Setup a subscription at the gateway.

			Required if supporting recurring subscriptions.
		*/
		function subscribe(&$order){
			//create a code for the order
			if(empty($order->code))
				$order->code = $order->getRandomCode();
			
			//filter order before subscription. use with care.
			$order = apply_filters("pmpro_subscribe_order", $order, $this);
			
			//code to setup a recurring subscription with the gateway and test results would go here

			//simulate a successful subscription processing
			$order->status = "success";		
			$order->subscription_transaction_id = "TEST" . $order->code;				
			return true;
		}	
		
		/*
			Update billing at the gateway.

			Required if supporting recurring subscriptions and
			processing credit cards on site.
		*/
		function update(&$order){
			//code to update billing info on a recurring subscription at the gateway and test results would go here

			//simulate a successful billing update
			return true;
		}
		
		/*
			Cancel a subscription at the gateway.

			Required if supporting recurring subscriptions.
		*/
		function cancel(&$order){
			//require a subscription id
			if(empty($order->subscription_transaction_id))
				return false;
			
			//code to cancel a subscription at the gateway and test results would go here

			//simulate a successful cancel			
			$order->updateStatus("cancelled");					
			return true;
		}	
		
		/*
			Get subscription status at the gateway.

			Optional if you have code that needs this or
			want to support addons that use this.
		*/
		function getSubscriptionStatus(&$order){
			//require a subscription id
			if(empty($order->subscription_transaction_id))
				return false;
			
			//code to get subscription status at the gateway and test results would go here

			//this looks different for each gateway, but generally an array of some sort
			return array();
		}

		/*
			Get transaction status at the gateway.

			Optional if you have code that needs this or
			want to support addons that use this.
		*/
		function getTransactionStatus(&$order){			
			//code to get transaction status at the gateway and test results would go here

			//this looks different for each gateway, but generally an array of some sort
			return array();
		}
	}