<?php
/**
 * Plugin Name: WooCommerce Cointopay
 * Description: Extends WooCommerce with crypto payments gateway.
 * Version: 1.3
 * Author: Cointopay, Therightsw en ikke
 *
 * @package  WooCommerce
 * @author   Cointopay <info@cointopay.com>
 * @link     cointopay.com
 */
if ( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
require_once ABSPATH . 'wp-content/plugins/woocommerce/woocommerce.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    add_filter( 'woocommerce_payment_gateways', function( $methods )
    {
        $methods[] = 'cointopay';
        return $methods;
    } );
    add_action( 'plugins_loaded', 'woocommerce_cointopay_init', 0 );
    function woocommerce_cointopay_init()
    {
        if ( class_exists( 'WC_Payment_Gateway' ) === false ) {
            return;
        }
    }
    class Cointopay extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id   = 'cointopay';
            $this->icon = plugins_url( 'assets/images/crypto.png', __FILE__ );
            $this->init_form_fields();
            $this->init_settings();
            $this->title          = $this->get_option( 'title' );
            $this->description    = $this->get_option( 'description' );
            $this->altcoinid      = $this->get_option( 'altcoinid' );
            $this->merchantid     = $this->get_option( 'merchantid' );
            $this->apikey         = '1';
            $this->secret         = $this->get_option( 'secret' );
            $this->msg['message'] = '';
            $this->msg['class']   = '';
            add_action( 'init', array(
                 &$this,
                'check_cointopay_response' 
            ) );
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
                 &$this,
                'process_admin_options' 
            ) );
            add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array(
                 &$this,
                'check_cointopay_response' 
            ) );
            // Valid for use.
            if ( !empty( $this->settings['enabled'] ) && !empty( $this->apikey ) && !empty( $this->secret ) ) {
                $this->enabled = 'yes';
            } else {
                $this->enabled = 'no';
            }
            if ( empty( $this->apikey ) ) {
                add_action( 'admin_notices', array(
                     &$this,
                    'apikey_missingmessage' 
                ) );
            }
            if ( empty( $this->secret ) ) {
                add_action( 'admin_notices', array(
                     &$this,
                    'secret_missingmessage' 
                ) );
            }
        }
        public function init_form_fields()
        {
            $this->form_fields = array(
                 'enabled' => array(
                     'title' => __( 'Enable/Disable', 'Cointopay' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable Cointopay', 'Cointopay' ),
                    'default' => 'yes' 
                ),
                'title' => array(
                     'title' => __( 'Title', 'Cointopay' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title the user can see during checkout.', 'Cointopay' ),
                    'default' => __( 'Cointopay', 'Cointopay' ) 
                ),
                'description' => array(
                     'title' => __( 'Description', 'Cointopay' ),
                    'type' => 'textarea',
                    'description' => __( 'This controls the title the user can see during checkout.', 'Cointopay' ),
                    'default' => __( 'You will be redirected to cointopay.com to complete your purchase.', 'Cointopay' ) 
                ),
                'merchantid' => array(
                     'title' => __( 'Your MerchantID', 'Cointopay' ),
                    'type' => 'text',
                    'description' => __( 'Please enter your Cointopay Merchant ID, You can get this information in: <a href="' . esc_url( 'https://cointopay.com' ) . '" target="_blank">Cointopay Account</a>.', 'Cointopay' ),
                    'default' => '' 
                ),
                'altcoinid' => array(
                     'title' => __( 'Default Checkout AltCoinID', 'Cointopay' ),
                    'type' => 'text',
                    'description' => __( 'Please enter your Preferred AltCoinID (1 for bitcoin), You can get this information in: <a href="' . esc_url( 'https://cointopay.com' ) . '" target="_blank">Cointopay Account</a>.', 'Cointopay' ),
                    'default' => '1' 
                ),
                'secret' => array(
                     'title' => __( 'SecurityCode', 'Cointopay' ),
                    'type' => 'password',
                    'description' => __( 'Please enter your Cointopay SecurityCode, You can get this information in: <a href="' . esc_url( 'https://cointopay.com' ) . '" target="_blank">Cointopay Account</a>.', 'Cointopay' ),
                    'default' => '' 
                ) 
            );
        }
        public function admin_options()
        {
?>
				<h3><?php
            esc_html_e( 'Cointopay Checkout', 'Cointopay' );
?></h3>

				<div id="wc_get_started">
				<span class="main"><?php
            esc_html_e( 'Provides a secure way to accept crypto currencies.', 'Cointopay' );
?></span>
				<p><a href="https://app.cointopay.com/index.jsp?#Register" target="_blank" class="button button-primary"><?php
            esc_html_e( 'Join free', 'Cointopay' );
?></a> <a href="https://cointopay.com" target="_blank" class="button"><?php
            esc_html_e( 'Learn more about WooCommerce and Cointopay', 'Cointopay' );
?></a></p>
				</div>

				<table class="form-table">
			  <?php
            $this->generate_settings_html();
?>
				</table>
				<?php
        }
        public function payment_fields()
        {
            if ( !empty( $this->description ) ) {
                echo esc_html( $this->description );
            }
        }
        public function process_payment( $orderid )
        {
            global $woocommerce;
            $order     = wc_get_order( $orderid );
            $itemnames = array();
            if ( count( $order->get_items() ) > 0 ):
                foreach ( $order->get_items() as $item ):
                    if ( true === $item['qty'] ) {
                        $itemnames[] = $item['name'] . ' x ' . $item['qty'];
                    }
                endforeach;
            endif;
            $order->update_status( 'pending', 'awaiting payment-complete notificiation' );
            $params    = array(
                 "authentication:$this->apikey",
                'cache-control: no-cache' 
            );
            $itemnames = 'Order ' . $order->get_order_number() . ' - ' . implode( ', ', $itemnames );
            $params    = array(
                 'body' => 'SecurityCode=' . $this->secret . '&MerchantID=' . $this->merchantid . '&Amount=' . number_format( $order->get_total(), 8, '.', '' ) . '&AltCoinID=' . $this->altcoinid . '&output=json&inputCurrency=' . get_woocommerce_currency() . '&CustomerReferenceNr=' . $orderid . '&returnurl=' . rawurlencode( esc_url( $this->get_return_url( $order ) ) ) . '&transactionconfirmurl=' . site_url( '/?wc-api=Cointopay' ) . '&transactionfailurl=' . rawurlencode( esc_url( $order->get_cancel_order_url() ) ) 
            );
            $url       = 'https://app.cointopay.com/MerchantAPI?Checkout=true';
            $response  = wp_safe_remote_post( $url, $params );
            if ( ( false === is_wp_error( $response ) ) && ( 200 === $response['response']['code'] ) && ( 'OK' === $response['response']['message'] ) ) {
                $results = json_decode( $response['body'] );
                return array(
                     'result' => 'success',
                    'redirect' => $results->shortURL 
                );
            }
        }
        public function check_cointopay_response()
        {
            global $woocommerce;
            $woocommerce->cart->empty_cart();
            $orderid          = ( !empty( intval( $_REQUEST['CustomerReferenceNr'] ) ) ) ? intval( $_REQUEST['CustomerReferenceNr'] ) : 0;
            $ordstatus        = ( !empty( sanitize_text_field( $_REQUEST['status'] ) ) ) ? sanitize_text_field( $_REQUEST['status'] ) : '';
            $ordtransactionid = ( !empty( sanitize_text_field( $_REQUEST['TransactionID'] ) ) ) ? sanitize_text_field( $_REQUEST['TransactionID'] ) : '';
            $ordconfirmcode   = ( !empty( sanitize_text_field( $_REQUEST['ConfirmCode'] ) ) ) ? sanitize_text_field( $_REQUEST['ConfirmCode'] ) : '';
            $order            = new WC_Order( $orderid );
            $data             = array(
                 'mid' => $this->merchantid,
                'TransactionID' => $ordtransactionid,
                'ConfirmCode' => $ordconfirmcode 
            );
            $response         = $this->validate_order( $data );
            if ( $response->Status !== $ordstatus ) {
                get_header();
                echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img src="' . esc_url( plugins_url( 'assets/images/fail.png', __FILE__ ) ) . '"><p style="font-size:20px;color:#5C5C5C;">01 - We have detected different order status. Your order has been halted.</p><a href="' . esc_url( site_url() ) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br></div></div></div>';
                get_footer();
                exit;
            } elseif ( intval( $response->CustomerReferenceNr ) === $orderid ) {
                if ( 'paid' === $ordstatus ) {
                    if ( 'completed' === $order->status ) {
                        $order->update_status( 'completed', sprintf( __( 'IPN: Payment completed notification from Cointopay', 'woocommerce' ) ) );
                    } else {
                        $order->payment_complete();
                        $order->update_status( 'processing', sprintf( __( 'IPN: Payment completed notification from Cointopay', 'woocommerce' ) ) );
                    }
                    get_header();
                    echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#0fad00">Success!</h2><img src="' . esc_url( plugins_url( 'assets/images/check.png', __FILE__ ) ) . '"><p style="font-size:20px;color:#5C5C5C;">The payment has been received and confirmed successfully.</p><a href="' . esc_url( site_url() ) . '" style="background-color: #0fad00;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br><br><br></div></div></div>';
                    get_footer();
                    exit;
                } elseif ( 'failed' === $ordstatus ) {
                    $order->update_status( 'pending', sprintf( __( 'IPN: Payment failed notification from Cointopay because not enough or time limit reached', 'woocommerce' ) ) );
                    get_header();
                    echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img src="' . esc_url( plugins_url( 'assets/images/fail.png', __FILE__ ) ) . '"><p style="font-size:20px;color:#5C5C5C;">The payment has been failed.</p><a href="' . esc_url( site_url() ) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br><br><br></div></div></div>';
                    get_footer();
                    exit;
                } else {
                    $order->update_status( 'failed', sprintf( __( 'IPN: Payment failed notification from Cointopay', 'woocommerce' ) ) );
                    get_header();
                    echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img src="' . esc_url( plugins_url( 'assets/images/fail.png', __FILE__ ) ) . '"><p style="font-size:20px;color:#5C5C5C;">The payment has been failed.</p><a href="' . esc_url( site_url() ) . '" style="background-color:#ff0000;border:none;color: white;padding:15px 32px;text-align: center;text-decoration:none;display:inline-block;font-size:16px;">Back</a><br><br><br><br></div></div></div>';
                    get_footer();
                    exit;
                }
            } elseif ( 'not found' === $response ) {
                $order->update_status( 'failed', sprintf( __( 'We have detected different order status. Your order has not been found.', 'woocommerce' ) ) );
                get_header();
                echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img src="' . esc_url( plugins_url( 'assets/images/fail.png', __FILE__ ) ) . '"><p style="font-size:20px;color:#5C5C5C;">02 - We have detected different order status. Your order has not been found..</p><a href="' . esc_url( site_url() ) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;">Back</a><br><br><br><br></div></div></div>';
                get_footer();
            } else {
                $order->update_status( 'failed', sprintf( __( 'We have detected different order status. Your order has been halted.', 'woocommerce' ) ) );
                get_header();
                echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img src="' . esc_url( plugins_url( 'assets/images/fail.png', __FILE__ ) ) . '"><p style="font-size:20px;color:#5C5C5C;">030 - We have detected different order status. Your order has been halted.</p><a href="' . esc_url( site_url() ) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;">Back</a><br><br><br><br></div></div></div>';
                get_footer();
            }
        }
        public function apikey_missingmessage()
        {
            $message = '<div class="error">';
            $message .= '<p><strong>Gateway Disabled</strong> You should enter your API key in Cointopay configuration. <a href="' . get_admin_url() . 'admin.php?page=wc-settings&amp;tab=checkout&amp;section=cointopay">Click here to configure</a></p>';
            $message .= '</div>';
            echo esc_html( $message );
        }
        public function secret_missingmessage()
        {
            $message = '<div class="error">';
            $message .= '<p><strong>Gateway Disabled</strong> You should check your SecurityCode in Cointopay configuration. <a href="' . get_admin_url() . 'admin.php?page=wc-settings&amp;tab=checkout&amp;section=cointopay">Click here to configure!</a></p>';
            $message .= '</div>';
            echo esc_html( $message );
        }
        public function validate_order( $data )
        {
            $params   = array(
                 'body' => 'MerchantID=' . $data['mid'] . '&Call=QA&APIKey=_&output=json&TransactionID=' . $data['TransactionID'] . '&ConfirmCode=' . $data['ConfirmCode'],
                'authentication' => 1,
                'cache-control' => 'no-cache' 
            );
            $url      = 'https://app.cointopay.com/v2REAPI?';
            $response = wp_safe_remote_post( $url, $params );
            $results  = json_decode( $response['body'] );
            return $results;
            if ( true === $results->CustomerReferenceNr ) {
                return $results;
            } elseif ( '"not found"' === $response ) {
                get_header();
                echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img src="' . esc_url( plugins_url( 'assets/images/fail.png', __FILE__ ) ) . '"><p style="font-size:20px;color:#5C5C5C;">Your order not found.</p><a href="' . esc_url( site_url() ) . '" style="background-color:#ff0000;border:none;color:white;padding: 15px 32px;text-align: center;text-decoration:none;display:inline-block;font-size:16px;">Back</a><br><br></div></div></div>';
                get_footer();
                exit;
            }
        }
    }
}
