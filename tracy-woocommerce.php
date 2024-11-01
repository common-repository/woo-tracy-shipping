<?php
 
/**
 * Plugin Name: Woo Tracy Shipping
 * Plugin URI: http://tracy.com/Integrations
 * Description: Tracy Shipping Method for WooCommerce
 * Version: 1.0.0
 * Author: Greencode
 * Author URI: http://www.greencode.com
 * License: GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Domain Path: /lang
 * Text Domain: tracy for WooCommerce
 */
 
if ( ! defined( 'WPINC' ) ) { 
    die; 
}
 
/*
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
 
    function tracy_Shipping_Method() {
        if ( ! class_exists( 'Tracy_Shipping_Method' ) ) {
            class Tracy_Shipping_Method extends WC_Shipping_Method {
                /**
                 * Constructor for your shipping class
                 *
                 * @access public
                 * @return void
                 */
                public function __construct() {
                    $this->id                 = 'tracy'; 
                    $this->method_title       = __( 'Woo Tracy Shipping', 'tracy' );  
                    $this->method_description = __( 'Tracy Shipping Method for Woocommerce', 'tracy' ); 
 
                    // Availability & Countries
                    $this->availability = 'including';
                    $this->countries = array(
						'AR' // Argentina
                        );
                    $this->init(); 
                }
 
                /**
                 * Init your settings
                 *
                 * @access public
                 * @return void
                 */
                function init() {
                    // Load the settings API
                    $this->init_form_fields(); 
                    $this->init_settings(); 
 
                    // Save settings in admin if you have any defined
                    add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
                }
 
                /**
                 * Define settings field for this shipping
                 * @return void 
                 */
                function init_form_fields() { 
 
                    $this->form_fields = array(
 
                     'api-key' => array(
                          'title' => __( 'API Key', 'tracy' ),
                          'type' => 'text',
						  'placeholder' => 'Ingrese API Key',
                          'default' => ''
                          ),
 
                     'api-secret' => array(
                        'title' => __( 'API Secret', 'tracy' ),
                          'type' => 'text',
						  'placeholder' => 'Ingrese API Secret',
                          'default' => ''
                          ),
					 'api-url' => array(
                        'title' => __( 'API URL', 'tracy' ),
                          'type' => 'text',
						  'placeholder' => 'API URL',
						  'description' => 'Default: http://admin.tracy.io/orders/',
                          'default' => 'http://admin.tracy.io/orders/'
                          ) 
                     ); 
                }	
				
 
                /**
                 * This function is used to calculate the shipping cost. Within this function we can check for weights, dimensions and other parameters.
                 *
                 * @access public
                 * @param mixed $package
                 * @return void
                 */
                public function calculate_shipping( $package = array()) { 
								
					$apiKey = isset( $this->settings['api-key'] ) ? $this->settings['api-key'] : '';
					$apiSecret = isset( $this->settings['api-secret'] ) ? $this->settings['api-secret'] : '';
					$apiUrl = isset( $this->settings['api-url'] ) ? $this->settings['api-url'] : 'http://admin.tracy.io/orders/';
					
					WC()->customer->set_shipping_address($_REQUEST['calc_shipping_address']);
					
					$query = '?';
					if($apiKey != '')
						$query = $query.'api_key='.$apiKey;
					if($apiSecret != '')
						$query = $query.'&api_secret='.$apiSecret;
					
					$products = '[';
					foreach ( $package['contents'] as $item_id => $values ) 
                    { 
                        $_product = $values['data']; 						
						$products .= '{"quantity":"'.$values['quantity'].'","unit":"Units","product":"'.$_product->name.'","weight":"'.$_product->get_weight().'","weight_vol":"'.''.'"}';
                    }
					$products .=']';
					
					$body = '{"delivery_address":"'.$_REQUEST['calc_shipping_address'].'","delivery_cp":"'.$package["destination"]["postcode"].'","delivery_province":"'.$package["destination"]["state"].'","delivery_partido":"","delivery_locality":"'.$package["destination"]["city"].'","products":'.$products.'}';
									
					$args = array(
						'body' => $body,
						'timeout' => '5',
						'redirection' => '5',
						'httpversion' => '1.0',
						'blocking' => true,
						'headers' => array(),
						'cookies' => array()
					);
					
					$response = wp_remote_post( $apiUrl.'/search_couriers/'.$query, $args );
					
					if ( is_wp_error( $response ) ) {
						$error_message = $response->get_error_message();
					    debug_to_console("Something went wrong:". $error_message);
					} else {
						$data = json_decode($response['body'], true);
					
						foreach($data as $values)
						{					
								$rate = array(
									'id' => $values['shipper_company_uuid'],
									'label' => $values['shipper_company_name'],
									'cost' => $values['shipping_cost']
								);
								$this->add_rate( $rate );
						}
					}	  
                }							
            }
        }
    }
 
    add_action( 'woocommerce_shipping_init', 'tracy_shipping_method' );
 
    function add_tracy_shipping_method( $methods ) {
        $methods[] = 'Tracy_Shipping_Method';
        return $methods;
    }
 
    add_filter( 'woocommerce_shipping_methods', 'add_tracy_shipping_method' );
	
	add_action( 'woocommerce_checkout_order_processed', 'tracy_send_confirmation_order_notification', 1, 1 );
	
	function tracy_send_confirmation_order_notification( $order_id ) {
		 	    
				$tsm = new Tracy_Shipping_Method();
				
				$apiKey =  isset( $tsm->settings['api-key'] ) ? $tsm->settings['api-key'] : '';
				$apiSecret = isset( $tsm->settings['api-secret'] ) ? $tsm->settings['api-secret'] : '';
				$apiUrl = isset( $tsm->settings['api-url'] ) ? $tsm->settings['api-url'] : 'http://admin.tracy.io/orders/';
				
				$order = new WC_Order($order_id); 
				
				global $woocommerce;
				$items = $woocommerce->cart->get_cart();

				$products = '[';
				foreach($items as $cart_item) { 					
					$products .= '{"quantity":"'.$cart_item['quantity'].'","unit":"Units","product":"'.$cart_item['data']->get_title().'","weight":"'.$cart_item['data']->get_weight().'","weight_vol":"'.''.'"}';
				} 
				$products .=']';
				
				$delivery_address = isset($order->shipping_address_1) ? $order->shipping_address_1 : $order->billing_address_1;
				$delivery_cp = isset($order->shipping_postcode) ? $order->shipping_postcode : $order->billing_postcode;
				$delivery_province = isset($order->shipping_state) ? $order->shipping_state : $order->billing_state;
				$delivery_locality = isset($order->shipping_city) ? $order->shipping_city : $order->billing_city;
				
				$body = '{"group_ref":"",
						  "shipper_company_uuid":"'.$_POST['shipping_method'][0].'",
						  "delivery_address":"'.$delivery_address.'",
						  "delivery_cp":"'.$delivery_cp.'",
						  "delivery_province":"'.$delivery_province.'",
						  "delivery_partido":"",
						  "delivery_locality":"'.$delivery_locality.'",
						  "client_email":"'.$order->billing_email.'",
						  "client_cel":"'.$order->billing_phone.'",
						  "contact_first_name":"'.$order->shipping_first_name.' '.$order->shipping_last_name.'",
						  "contact_cel":"'.$order->shipping_phone.'",
						  "products":'.$products.'}';
									
				$args = array(
					'body' => $body,
					'timeout' => '5',
					'redirection' => '5',
					'httpversion' => '1.0',
					'blocking' => true,
					'headers' => array(),
					'cookies' => array()
				);
				
				$query = '?';
				if($apiKey != '')
				    $query = $query.'api_key='.$apiKey;
				if($apiSecret != ''){
					 if($query != '?')
						$query = $query.'&';
					 $query = $query.'api_secret='.$apiSecret;
				}
					
				$response = wp_remote_post( $apiUrl.'/confirm_order/'.$query, $args );
					
				if ( is_wp_error( $response ) ) {
					$error_message = $response->get_error_message();
					debug_to_console("Something went wrong:". $error_message);
				} else {
					debug_to_console("Solicitud de Tracy creada.");
				}
	}

	function add_address_to_form_fields( $woocommerce_shipping_calculator_enable_city )
	{
		$woocommerce_shipping_calculator_enable_city = TRUE;
		?>
		<p class="form-row form-row-wide" id="calc_shipping_address_field">
			<input type="text" class="input-text" value="<?php echo esc_attr( WC()->customer->get_shipping_address()); ?>" placeholder="<?php esc_attr_e( 'Address', 'woocommerce' ); ?>" name="calc_shipping_address" id="calc_shipping_address" />
		</p>
		<?php
		return $woocommerce_shipping_calculator_enable_city;
	}	
	add_filter( 'woocommerce_shipping_calculator_enable_postcode', 'add_address_to_form_fields' );
	
	function debug_to_console( $data ) {
		$output = $data;
		if ( is_array( $output ) )
			$output = implode( ',', $output);

		echo "<script>console.log( 'Debug Objects: " . $output . "' );</script>";
	}
}