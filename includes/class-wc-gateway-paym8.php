<?php
/**
 * PayM8 Payment Gateway
 *
 * Provides a PayM8 Payment Gateway.
 *
 * @class  WC_Gateway_PayM8
 * @package PayM8
 */
class WC_Gateway_PayM8 extends WC_Payment_Gateway {

	/**
	 * Submit payment url
	 *
	 * @var string
	 */
	public $submit_payment_url = '';

	/**
	 * Payment callback url
	 *
	 * @var string
	 */
	public $payment_callback_url = '';

	/**
	 *  Merchant redirect result url
	 *
	 * @var string
	 */
	public $merchant_redirect_result_url = '';

	/**
	 * User ip
	 *
	 * @var string
	 */
	public $user_ip = '';

	/**
	 * Paym8 logo url
	 *
	 * @var string
	 */
	public $paym8_logo_url = '';

	/**
	 * Exclamation markurl
	 *
	 * @var string
	 */
	public $exclamation_mark_url = '';

	/**
	 * Constructor
	 */
	public function __construct() {

		$this->id                   = 'paym8_gateway';
		$this->icon                 = WP_PLUGIN_URL . '/' . plugin_basename( dirname( dirname( __FILE__ ) ) ) . '/assets/images/paym8Logo.png';
		$this->has_fields           = false;
		$this->method_title         = __( 'PayM8 Axis', 'wc-gateway-paym8' );
		$this->method_description   = __( 'Allows payments from PayM8 Axis platform', 'wc-gateway-paym8' );
		$this->paym8_logo_url       = WP_PLUGIN_URL . '/' . plugin_basename( dirname( dirname( __FILE__ ) ) ) . '/assets/images/payM8LoginLettering-withoutAxis.png';
		$this->exclamation_mark_url = WP_PLUGIN_URL . '/' . plugin_basename( dirname( dirname( __FILE__ ) ) ) . '/assets/images/exclamationMark2.png';

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->enabled                       = $this->get_option( 'enabled' );
		$this->username                      = $this->get_option( 'username' );
		$this->password                      = $this->get_option( 'password' );
		$this->mcp                           = $this->get_option( 'mcp' );
		$this->merchant_branch_productnumber = $this->get_option( 'merchant_branch_productnumber' );

		if ( 'yes' == $this->get_option( 'test_environment' ) ) {
			$this->submit_payment_url = 'https://test.paym8online.com/PaymentsService/api/V1/ecommerce/SubmitPaymentRequest';
		} else {
			$this->submit_payment_url = 'https://www.paym8online.com/PaymentsService/api/V1/ecommerce/SubmitPaymentRequest';
		}

		$this->payment_callback_url = trailingslashit( get_site_url() ) . '?wc-api=WC_Gateway_PayM8';

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_paym8_gateway', array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_api_wc_gateway_paym8', array( $this, 'axis_result' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices_env_check' ) );

		add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'paym8_change_thankyou_sub_title' ), 1, 2 );
		add_filter( 'the_title', array( $this, 'paym8_personalize_order_received_title' ), 10, 2 );
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 *
	 * @since 1.0.0
	 */
	public function init_form_fields() {

		$this->form_fields = apply_filters(
			'wc_paym8_form_fields',
			array(

				'enabled'                       => array(
					'title'   => __( 'Enable/Disable', 'wc-gateway-paym8' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable PayM8 Payments', 'wc-gateway-paym8' ),
					'default' => __( 'no', 'wc-gateway-paym8' ),
				),
				'test_environment'              => array(
					'title'   => __( 'Test Environment', 'wc-gateway-paym8' ),
					'type'    => 'checkbox',
					'label'   => __( 'Use Test Environment', 'wc-gateway-paym8' ),
					'default' => __( 'no', 'wc-gateway-paym8' ),
				),
				'username'                      => array(
					'title'       => __( 'Username', 'wc-gateway-paym8' ),
					'type'        => 'text',
					'description' => __( 'This is your API username.', 'wc-gateway-paym8' ),
					'default'     => __( 'Username here', 'wc-gateway-paym8' ),
					'desc_tip'    => true,
				),
				'password'                      => array(
					'title'       => __( 'Password', 'wc-gateway-paym8' ),
					'type'        => 'password',
					'description' => __( 'This is your API password.', 'wc-gateway-paym8' ),
					'default'     => __( 'password here', 'wc-gateway-paym8' ),
					'desc_tip'    => true,
				),
				'merchant_branch_productnumber' => array(
					'title'       => __( 'Merchant Branch Product Number', 'wc-gateway-paym8' ),
					'type'        => 'text',
					'description' => __( 'Your Merchant Branch Product Number provided by the PayM8 sales staff ', 'wc-gateway-paym8' ),
					'default'     => __( 'merchant branch product number here', 'wc-gateway-paym8' ),
					'desc_tip'    => true,
				),
			)
		);
	}

	/**
	 * Admin notices env check
	 * Add a warning banner in the admin section
	 *
	 * @since 1.0.0
	 */
	public function admin_notices_env_check() {
		if ( 'yes' == $this->get_option( 'test_environment' ) ) {
			$this->logger( 'Test enabled!', 'Warning Test Enabled' );
			echo '<div class="notice notice-warning is-dismissible"><p>'
			. esc_html( __( 'PayM8 Payments Gateway Warning: You have the Test Environment enabled in the Payment Settings', 'wc-gateway-paym8' ) )
			. '</p></div>';
		}
	}

	/**
	 * Validate username field
	 * Validates the username field
	 *
	 * Key.
	 *
	 * @param string $key The Key param.
	 *
	 * Value.
	 *
	 * @param string $value The value param.
	 *
	 * @since 1.0.0
	 */
	public function validate_username_field( $key, $value ) {
		if ( ! isset( $value ) || empty( $value ) ) {
			WC_Admin_Settings::add_error( esc_html__( 'The Username field is required', 'wc-gateway-paym8' ) );
		}

		return $value;
	}

	/**
	 * Validate password field
	 * Validates the password field
	 *
	 * Key.
	 *
	 * @param string $key The Key param.
	 *
	 * Value.
	 *
	 * @param string $value The value param.
	 *
	 * @since 1.0.0
	 */
	public function validate_password_field( $key, $value ) {
		if ( ! isset( $value ) || empty( $value ) ) {
			WC_Admin_Settings::add_error( esc_html__( 'The Password field is required', 'wc-gateway-paym8' ) );
		}

		return $value;
	}

	/**
	 * Validate  merchant branch product field
	 * Validates the merchant branch product field
	 *
	 * Key.
	 *
	 * @param string $key The Key param.
	 *
	 * Value.
	 *
	 * @param string $value The value param.
	 *
	 * @since 1.0.0
	 */
	public function validate_merchant_branch_productnumber_field( $key, $value ) {
		if ( ! isset( $value ) || empty( $value ) ) {
			WC_Admin_Settings::add_error( esc_html__( 'The Merchant Branch Product field is required', 'wc-gateway-paym8' ) );
		}

		return $value;
	}

	/**
	 * Paym8 change thankyou sub title
	 * Thank you subtitle
	 *
	 * Text.
	 *
	 * @param string $text The Text param.
	 *
	 * Order.
	 *
	 * @param string $order The Order param.
	 *
	 * @since 1.0.0
	 */
	public function paym8_change_thankyou_sub_title( $text, $order ) {
		if ( isset( $order ) ) {
			$order_status = $order->get_status();
			$first_name   = esc_html( $order->get_billing_first_name() );
			$order_id     = $order->get_order_number();
			if ( $order->has_status( 'completed' ) ) {
				$this->logger( '[On Thank You page] Order Successful, Order Id:' . $order_id, 'Order Success:' );
				$text = "Thank you <strong>{$first_name} </strong>, your payment was successful and your order details are below. ";
				return $text;
			}

			if ( $order->has_status( 'failed' ) ) {
				$this->logger( '[On Thank You page] Order Failed, Order Id:' . $order_id, 'Order Failure:' );
				$text = "Apologies <strong>{$first_name} </strong>, your payment failed, please contact us at with the order details below to find out why and we can re-process your order.";
			}
		}

		return $text;
	}

	/**
	 * Paym8 personalize order received title
	 * Order received title
	 *
	 * Title.
	 *
	 * @param string $title The title param.
	 *
	 * Id.
	 *
	 * @param string $id The id param.
	 *
	 * @since 1.0.0
	 */
	public function paym8_personalize_order_received_title( $title, $id ) {
		if ( is_order_received_page() && get_the_ID() === $id ) {
			global $wp;
			$order_id  = apply_filters( 'woocommerce_thankyou_order_id', absint( $wp->query_vars['order-received'] ) );
			$order_key = apply_filters( 'woocommerce_thankyou_order_key', empty( $_GET['key'] ) ? '' : wc_clean( wp_unslash( $_GET['key'] ) ) );

			if ( $order_id > 0 ) {
				$order = wc_get_order( $order_id );
				if ( $order->get_order_key() != $order_key ) {
					$order = false;
				}
			}

			if ( isset( $order ) ) {
				$order_status = $order->get_status();

				if ( $order->has_status( 'completed' ) ) {
					$this->logger( 'Order Successful, Order Id:' . $order_id, 'Order Success:' );
					$title = 'Payment Successful!';
				}

				if ( $order->has_status( 'failed' ) ) {
					$this->logger( 'Order Failed, Order Id:' . $order_id, 'Order Failure:' );
					$title = 'Payment Failed';
				}
			}
		}

		return $title;
	}

	/**
	 * Axis result
	 * Receive axis result callback
	 *
	 * @since 1.0.0
	 */
	public function axis_result() {
		$raw_post              = file_get_contents( 'php://input' );
		$decoded_callback_data = json_decode( $raw_post );
		$this->logger( wc_print_r( $decoded_callback_data, true ), 'Callback data: ' );

		header( 'HTTP/1.0 200 OK' );
		flush();

		$order = wc_get_order( $decoded_callback_data->OrderId );
		if ( isset( $order ) && ! empty( $order ) ) {
			if ( 'true' == $decoded_callback_data->WasPaymentSuccessful ) {
				wc_reduce_stock_levels( $decoded_callback_data->OrderId );
				WC()->cart->empty_cart();

				$order->update_status( 'completed' );
				$order->save();

				$this->logger( $decoded_callback_data->OrderId, 'Payment was successful for Order Id: ' );
			}

			if ( 'false' == $decoded_callback_data->WasPaymentSuccessful ) {
				$order->update_status( 'failed' );
				$order->save();
				$this->logger( $decoded_callback_data->OrderId, 'Payment failed for Order Id: ' );
			}
		} else {
			$this->logger( "Failed to retrieved order after CallBack from Order Id: {$decoded_callback_data->OrderId}", 'Order Retrieval Failed!: ' );
		}
	}

	/**
	 * Receipt page
	 * Receipt page after order
	 *
	 * Order.
	 *
	 * @param string $order The order param.
	 *
	 * @since 1.0.0
	 */
	public function receipt_page( $order ) {
		echo '<div class="wrapper">
        <a href="https://www.paym8.co.za/" target="_blank"><img src="' . esc_url( $this->paym8_logo_url ) . '"></a>
        <br>
        <br>
        <input class="button" type="button" id="submit_payment"  value="Pay Now">
        </div>   
        <div id="loaderDiv" class=""><div id="containerLoader" class="containe-redirect not-active">
        <div class="child-redirect"><p class="redirect-text">Redirecting to PayM8 for payment...</p></div>
      </div></div>';

		$my_order = wc_get_order( $order );
		$order_id = $my_order->get_Id();
		$this->logger( $order_id, 'New OrderId:' );

		$billing_first_name = $my_order->get_billing_first_name();
		$total_cost_in_cents   = $my_order->get_total() * 100;

		$this->merchant_redirect_result_url = $this->get_return_url( $my_order );
		$this->user_ip                      = $this->get_the_user_ip();

		$channel_detail      = array(
			'channelName' => 'Base24',
			'settings'    => null,
		);
		$submit_payment_body = array(
			'merchantBranchProductNumber' => $this->merchant_branch_productnumber,
			'totalCostInCents'            => $total_cost_in_cents,
			'transactionDescription'      => 'woocommerce',
			'merchantReferenceNumber'     => $order,
			'userHostAddress'             => $this->user_ip,
			'resultRedirectUrl'           => $this->merchant_redirect_result_url,
			'callbackUrl'                 => $this->payment_callback_url,
			'cartItems'                   => null,
			'paymentChannels'             => array( $channel_detail ),
			'merchantClientProfile'       => "mcp_{$billing_first_name}",
			'IsWooCommerceRequest'        => true,
		);

		$args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->username . ':' . $this->password ),
				'Content-Type'  => 'application/json; charset=utf-8',
			),
			'body'    => json_encode( $submit_payment_body ),
			'timeout' => 300,
		);

		$this->logger( wc_print_r( $args, true ), 'Submit Payment Post Data: ' );
		$response = wp_remote_post( $this->submit_payment_url, $args );

		if ( is_wp_error( $response ) ) {
			$this->logger( wc_print_r( $response->get_error_message(), true ), 'Error Msg On PayM8 SubmitPayment Call:' );
			$my_order->add_order_note( 'Error when communicating to PayM8! Error Detail: [' . $response->get_error_message() . '] Please contact PayM8 support at support@paym8.co.za' );

			return '<br><div style="border: 1px dashed grey; padding:10px">
            <p style="float: left;!important"><img src="' . $this->exclamation_mark_url . '"></p>    
            <p><strong>Error</strong></p>
            <br>
            <p>Apologies, an error has occured communicating with PayM8, please refresh this page and try again otherwise if the error persists please contact us to resolve this issue. Thank you</p>
            </div>';
		}

		$response_body_from_axis = wp_remote_retrieve_body( $response );
		$this->logger( wc_print_r( $response_body_from_axis, true ), 'Json Response Body From Axis for Order Id:' . $order_id );

		$http_code = wp_remote_retrieve_response_code( $response, $args );
		$this->logger( $http_code, 'Response Code:' );
		$parsed_response_body_from_axis = json_decode( $response_body_from_axis );

		if ( 200 == $http_code ) {
			$redirect_uri = $parsed_response_body_from_axis->data->redirectUri;

			echo '<script type="text/javascript">
            jQuery(function(){
                if(jQuery(".entry-header").length > 0)
                {
                    jQuery(".entry-header").attr("style", "padding:0!important;");
                }
                jQuery("#submit_payment").click(function(){
                    jQuery("#containerLoader").removeClass("not-active");  
                    jQuery("#loaderDiv").addClass("loading-cover");   
                    window.setTimeout(function() {
                         window.location.href = "' . esc_url( $redirect_uri ) . '";
                      }, 5000);
                  });               
            });
            </script>';
		}

		if ( 500 == $http_code ) {
			$error_array = $parsed_response_body_from_axis->errorItems;
			if ( isset( $error_array ) || ! empty( $error_array ) ) {
				if ( count( $error_array ) > 0 ) {
					$error_description = array_values( $error_array )[0]->description;
					$order_failur_note = "Error when communicating to PayM8! Error Details: [ {$error_description} ] . Please contact PayM8 at support@paym8.co.za";
					$my_order->add_order_note( $order_failur_note );
				}
			}

			echo '<br><div style="border: 1px dashed grey; padding:10px">
                <p style="float: left;!important"><img src="' . esc_url( $this->exclamation_mark_url ) . '"></p>    
                <p><strong>Error</strong></p>
                <p> Apologies, an error has occured communicating with PayM8, please refresh this page and try again otherwise if the error persists please contact us to resolve this issue. Thank you</p>
                </div>';
		}
	}

	/**
	 * Process payment
	 * Process the payment
	 *
	 * Order_id.
	 *
	 * @param string $order_id The order_id param.
	 *
	 * @since 1.0.0
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Logger
	 * Logger data
	 *
	 * Message.
	 *
	 * @param string $message The message param.
	 *
	 * Message_title.
	 *
	 * @param string $message_title The message_title param.
	 *
	 * @since 1.0.0
	 */
	private function logger( $message, $message_title ) {
		$paym8_logger  = wc_get_logger();
		$context       = array( 'source' => 'paym8_gateway' );
		$formatted_msg = '-----------'
		. PHP_EOL . $message_title
		. PHP_EOL . '----------'
		. PHP_EOL . $message
		. PHP_EOL . '----------';

		$paym8_logger->info( $formatted_msg, $context );
	}

	/**
	 * Get the user ip
	 * Get user IP
	 *
	 * @since 1.0.0
	 */
	private function get_the_user_ip() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$this->user_ip = filter_var( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ), FILTER_VALIDATE_IP );
			$this->logger( $this->user_ip, 'User IP - HTTP_CLIENT_IP:' );

			return $this->user_ip;
		}

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$this->user_ip = filter_var( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ), FILTER_VALIDATE_IP );
			$this->logger( $this->user_ip, 'User IP - HTTP_X_FORWARDED_FOR:' );

			return $this->user_ip;
		}

		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$this->user_ip = filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP );
			$this->logger( $this->user_ip, 'User IP - REMOTE_ADDR:' );

			return $this->user_ip;
		}
	}


} // #### end \WC_Gateway_PayM8 class
