<?php
/**
 * Plugin Name: WooCommerce Cointopay.com
 * Description: Extends WooCommerce with crypto payments gateway.
 * Version: 1.4
 * Author: Cointopay, Therightsw
 *
 * @package  WooCommerce
 * @author   Cointopay <info@cointopay.com>
 * @link     cointopay.com
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-content/plugins/woocommerce/woocommerce.php';
if ( is_plugin_active( 'woocommerce/woocommerce.php' ) === true ) {
    // Add the Gateway to WooCommerce.
    add_filter( 'woocommerce_payment_gateways', 'wc_ctp_gateway' );
    add_action( 'plugins_loaded', 'woocommerce_cointopay_init', 0 );
    
    
    /**
     * Add the gateways to WooCommerce
     *
     * @param array $methods $args {
     *                       Optional. An array of arguments.
     *
     * @type   type $key Description. Default 'value'. Accepts 'value', 'value'.
     *    (aligned with Description, if wraps to a new line)
     * @type   type $key Description.
     * }
     * @return array $methods
     * @since  1.0.0
     */
    function wc_ctp_gateway( $methods )
    {
        $methods[ ] = 'cointopay';
        return $methods;
        
    } //end wc_ctp_gateway()
    
    
    /**
     * Add the Gateway to WooCommerce init
     *
     * @return bool
     */
    function woocommerce_cointopay_init( )
    {
        if ( class_exists( 'WC_Payment_Gateway' ) === false ) {
            return;
        } //class_exists( 'WC_Payment_Gateway' ) === false
        
    } //end woocommerce_cointopay_init()
    
    
    /**
     * Define Cointopay Class
     *
     * @package  WooCommerce
     * @author   Cointopay <info@cointopay.com>
     * @link     cointopay.com
     */
    class Cointopay extends WC_Payment_Gateway
    {
        
        
        
        /**
         * Define Cointopay Class constructor
         **/
        public function __construct( )
        {
            $this->id   = 'cointopay';
            $this->icon = plugins_url( 'images/crypto.png', __FILE__ );
            
            $this->init_form_fields();
            $this->init_settings();
            
            $this->title       = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->altcoinid   = $this->get_option( 'altcoinid' );
            $this->merchantid  = $this->get_option( 'merchantid' );
            
            $this->apikey           = '1';
            $this->secret           = $this->get_option( 'secret' );
            $this->msg[ 'message' ] = '';
            $this->msg[ 'class' ]   = '';
            
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
            
            add_action( 'woocommerce_thankyou', array(
                 &$this,
                'client_returning_from_gateway' 
            ) );
            // Valid for use.
            if ( empty( $this->settings[ 'enabled' ] ) === false && empty( $this->apikey ) === false && empty( $this->secret ) === false ) {
                $this->enabled = 'yes';
            } //empty( $this->settings[ 'enabled' ] ) === false && empty( $this->apikey ) === false && empty( $this->secret ) === false
            else {
                $this->enabled = 'no';
            }
            
            // Checking if apikey is not empty.
            if ( empty( $this->apikey ) === true ) {
                add_action( 'admin_notices', array(
                     &$this,
                    'apikey_missingmessage' 
                ) );
            } //empty( $this->apikey ) === true
            
            // Checking if app_secret is not empty.
            if ( empty( $this->secret ) === true ) {
                add_action( 'admin_notices', array(
                     &$this,
                    'secret_missingmessage' 
                ) );
            } //empty( $this->secret ) === true
            
            
        } //end __construct()
        
        
        /**
         * Define initFormfields function
         *
         * @return mixed
         */
        public function init_form_fields( )
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
            
        } //end init_form_fields()
        
        
        /**
         * Define adminOptions function
         *
         * @return mixed
         */
        public function admin_options( )
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
            
        } //end admin_options()
        
        
        /**
         *  There are no payment fields for Cointopay, but we want to show the description if set.
         *
         * @return string
         **/
        public function payment_fields( )
        {
            if ( true === $this->description ) {
                echo esc_html( $this->description );
            } //true === $this->description
            
        } //end payment_fields()
        
        public function client_returning_from_gateway( $order_id )
        {
            $status = get_post_meta( $order_id, 'cointopay_Status', true );
            if ( isset( $status ) ) {
                wp_redirect( $this->get_return_url( $order ) );
            } //isset( $status )
            $order = wc_get_order( $order_id );
        }
        
        public function update_meta( $order_id, $data )
        {
            foreach ( $data as $key => $val ) {
                update_post_meta( $order_id, "cointopay_$key", "$val" );
            } //$data as $key => $val
        }
        
        
        /**
         * Process the payment and return the result
         *
         * @param int $orderid comment
         *
         * @return $array
         **/
        public function process_payment( $orderid )
        {
            // check if client already has a transaction initiated for this order
            // we do not want multiple cointopay transactions for a single order
            $existing_cointopay_redirect_url = get_post_meta( $orderid, 'cointopay_shortURL', true );
            if ( isset( $existing_cointopay_redirect_url ) && !empty( $existing_cointopay_redirect_url ) ) {
                return array(
                     'result' => 'success',
                    'redirect' => $existing_cointopay_redirect_url 
                );
            } //isset( $existing_cointopay_redirect_url ) && !empty( $existing_cointopay_redirect_url )
            
            global $woocommerce;
            $order = wc_get_order( $orderid );
            
            $itemnames = array( );
            
            if ( count( $order->get_items() ) > 0 ):
                foreach ( $order->get_items() as $item ):
                    if ( true === $item[ 'qty' ] ) {
                        $itemnames[ ] = $item[ 'name' ] . ' x ' . $item[ 'qty' ];
                    } //true === $item[ 'qty' ]
                endforeach;
            endif;
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
            if ( ( false === is_wp_error( $response ) ) && ( 200 === $response[ 'response' ][ 'code' ] ) && ( 'OK' === $response[ 'response' ][ 'message' ] ) ) {
                $results = json_decode( $response[ 'body' ] );
                $this->update_meta( $orderid, $results );
                $order->update_status( 'pending', sprintf( 'customer redirected to the <a href="%s" target="_blank">payment portal</a>', $results->shortUrl ) );
                return array(
                     'result' => 'success',
                    'redirect' => $results->shortURL 
                );
            } //( false === is_wp_error( $response ) ) && ( 200 === $response[ 'response' ][ 'code' ] ) && ( 'OK' === $response[ 'response' ][ 'message' ] )
            
        } //end process_payment()
        
        
        /**
         * Check for valid Cointopay server callback
         *
         * @return string
         **/
        public function check_cointopay_response( )
        {
            global $woocommerce;
            $woocommerce->cart->empty_cart();
            $orderid          = ( !empty( intval( $_REQUEST[ 'CustomerReferenceNr' ] ) ) ) ? intval( $_REQUEST[ 'CustomerReferenceNr' ] ) : 0;
            $ordstatus        = ( !empty( sanitize_text_field( $_REQUEST[ 'status' ] ) ) ) ? sanitize_text_field( $_REQUEST[ 'status' ] ) : '';
            $ordtransactionid = ( !empty( sanitize_text_field( $_REQUEST[ 'TransactionID' ] ) ) ) ? sanitize_text_field( $_REQUEST[ 'TransactionID' ] ) : '';
            $ordconfirmcode   = ( !empty( sanitize_text_field( $_REQUEST[ 'ConfirmCode' ] ) ) ) ? sanitize_text_field( $_REQUEST[ 'ConfirmCode' ] ) : '';
            $notenough        = ( !empty( sanitize_text_field( $_REQUEST[ 'notenough' ] ) ) ) ? sanitize_text_field( $_REQUEST[ 'notenough' ] ) : '';
            $notenough        = intval( $notenough );
            $order            = new WC_Order( $orderid );
            $data             = array(
                 'mid' => $this->merchantid,
                'TransactionID' => $ordtransactionid,
                'ConfirmCode' => $ordconfirmcode,
                'order_id' => $orderid 
            );
            $transactionData  = $this->validate_order( $data );
            if ( 200 !== $transactionData[ 'status_code' ] ) {
                get_header();
                echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><p>code 1</p><img style="margin:auto;"  src="' . esc_url( plugins_url( 'images/fail.png', __FILE__ ) ) . '"><p style="font-size:20px;color:#5C5C5C;">' . $transactionData[ 'message' ] . '</p><a href="' . esc_url( site_url() ) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br></div></div></div>';
                get_footer();
                exit;
            } //200 !== $transactionData[ 'status_code' ]
            else {
                if ( $transactionData[ 'data' ][ 'Security' ] != $ordconfirmcode ) {
                    get_header();
                    echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><p>code 2</p><img style="margin:auto;"  src="' . esc_url( plugins_url( 'images/fail.png', __FILE__ ) ) . '"><p style="font-size:20px;color:#5C5C5C;">Data mismatch! ConfirmCode doesn\'t match</p><a href="' . esc_url( site_url() ) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br></div></div></div>';
                    get_footer();
                    exit;
                } //$transactionData[ 'data' ][ 'Security' ] != $ordconfirmcode
                elseif ( $transactionData[ 'data' ][ 'CustomerReferenceNr' ] != $orderid ) {
                    get_header();
                    echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><p>code 3</p><img style="margin:auto;"  src="' . esc_url( plugins_url( 'images/fail.png', __FILE__ ) ) . '"><p style="font-size:20px;color:#5C5C5C;">Data mismatch! CustomerReferenceNr doesn\'t match</p><a href="' . esc_url( site_url() ) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br></div></div></div>';
                    get_footer();
                    exit;
                } //$transactionData[ 'data' ][ 'CustomerReferenceNr' ] != $orderid
                    elseif ( $transactionData[ 'data' ][ 'TransactionID' ] != $ordtransactionid ) {
                    get_header();
                    echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img style="margin:auto;"  src="' . esc_url( plugins_url( 'images/fail.png', __FILE__ ) ) . '"><p>code 4</p><p style="font-size:20px;color:#5C5C5C;">Data mismatch! TransactionID doesn\'t match</p><a href="' . esc_url( site_url() ) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br></div></div></div>';
                    get_footer();
                    exit;
                } //$transactionData[ 'data' ][ 'TransactionID' ] != $ordtransactionid
                    elseif ( $transactionData[ 'data' ][ 'Status' ] != $ordstatus ) {
                    get_header();
                    echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img style="margin:auto;"  src="' . esc_url( plugins_url( 'images/fail.png', __FILE__ ) ) . '"><p>code 5</p><p style="font-size:20px;color:#5C5C5C;">Data mismatch! status doesn\'t match. Your order status is ' . $transactionData[ 'data' ][ 'Status' ] . '</p><a href="' . esc_url( site_url() ) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br></div></div></div>';
                    get_footer();
                    exit;
                } //$transactionData[ 'data' ][ 'Status' ] != $ordstatus
                
            }
            if ( ( 'paid' === $ordstatus ) && ( 0 === $notenough ) ) {
                // Do your magic here, and return 200 OK to Cointopay.
                // if ('processing' === $order->status) {
                
                if ( 'processing' !== $order->status ) {
                    $order->payment_complete();
                    $order->update_status( 'processing', sprintf( __( 'IPN: Payment completed notification from Cointopay', 'woocommerce' ) ) );
                    
                } //'processing' !== $order->status
                $this->client_returning_from_gateway( $order_id );
                
            } //( 'paid' === $ordstatus ) && ( 0 === $notenough )
            elseif ( 'failed' === $ordstatus && 1 === $notenough ) {
                $order->update_status( 'on-hold', sprintf( __( 'IPN: Payment failed notification from Cointopay because notenough', 'woocommerce' ) ) );
                get_header();
                echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img style="margin:auto;"  src="' . esc_url( plugins_url( 'images/fail.png', __FILE__ ) ) . '"><p>code 7</p><p style="font-size:20px;color:#5C5C5C;">The payment has been failed.</p><a href="' . esc_url( site_url() ) . '" style="background-color: #ff0000;border: none;color: white; padding: 15px 32px; text-align: center;text-decoration: none;display: inline-block; font-size: 16px;" >Back</a><br><br><br><br></div></div></div>';
                get_footer();
                exit;
            } //'failed' === $ordstatus && 1 === $notenough
            else {
                $order->update_status( 'failed', sprintf( __( 'IPN: Payment failed notification from Cointopay', 'woocommerce' ) ) );
                get_header();
                echo '<div class="container" style="text-align: center;"><div><div><br><br><h2 style="color:#ff0000">Failure!</h2><img style="margin:auto;"  src="' . esc_url( plugins_url( 'images/fail.png', __FILE__ ) ) . '"><p style="font-size:20px;color:#5C5C5C;">The payment has been failed.</p><p>code 8</p><a href="' . esc_url( site_url() ) . '" style="background-color:#ff0000;border:none;color: white;padding:15px 32px;text-align: center;text-decoration:none;display:inline-block;font-size:16px;">Back</a><br><br><br><br></div></div></div>';
                get_footer();
                exit;
            } //end if
            
            
        } //end check_cointopay_response()
        
        
        /**
         * Adds error message when not configured the api key.
         *
         * @return string Error Mensage.
         */
        public function apikey_missingmessage( )
        {
            $message = '<div class="error">';
            $message .= '<p><strong>Gateway Disabled</strong> You should enter your API key in Cointopay configuration. <a href="' . get_admin_url() . 'admin.php?page=wc-settings&amp;tab=checkout&amp;section=cointopay">Click here to configure</a></p>';
            $message .= '</div>';
            
            echo esc_html( $message );
            
        } //end apikey_missingmessage()
        
        
        /**
         * Adds error message when not configured the secret.
         *
         * @return String Error Mensage.
         */
        public function secret_missingmessage( )
        {
            $message = '<div class="error">';
            $message .= '<p><strong>Gateway Disabled</strong> You should check your SecurityCode in Cointopay configuration. <a href="' . get_admin_url() . 'admin.php?page=wc-settings&amp;tab=checkout&amp;section=cointopay">Click here to configure!</a></p>';
            $message .= '</div>';
            
            echo esc_html( $message );
            
        } //end secret_missingmessage()
        
        
        /**
         * Check for valid Cointopay server callback
         *
         * @param array $data $args {
         *                    Optional. An array of arguments.
         *
         * @type   type $key Description. Default 'value'. Accepts 'value', 'value'.
         *    (aligned with Description, if wraps to a new line)
         * @type   type $key Description.
         * }
         * @return string
         **/
        public function validate_order( $data )
        {
            $params = array(
                 'body' => 'MerchantID=' . $data[ 'mid' ] . '&Call=Transactiondetail&APIKey=a&output=json&ConfirmCode=' . $data[ 'ConfirmCode' ],
                'authentication' => 1,
                'cache-control' => 'no-cache' 
            );
            
            $url = 'https://app.cointopay.com/v2REAPI?';
            
            $response = wp_safe_remote_post( $url, $params );
            $results  = json_decode( $response[ 'body' ], true );
            $this->update_meta( $data[ 'order_id' ], $results[ 'data' ] );
            return $results;
            
        } //end validate_order()
        
        
    } //end class
    
} //end if
