<?php
/*
 * Plugin Name: Payment Gateway via CIB for WooCommerce
 * Plugin URI: https://szathmari.hu/wordpress/
 * Description: Extends WooCommerce with CIB Payment Gateway. Bankkártyás fizetés CIB bank által.
 * Version: 1.2
 * Author: szathmari.hu
 * Author URI: https://szathmari.hu/
 * Text Domain: wc-gateway-cib
 *
 * Requires at least: 4.0
 * Tested up to: 6.5
 * Requires PHP: 7.4
 * PHP tested up to: 8.3
 *
 * Copyright: ©2024 szathmari.hu
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * doc: https://docs.woocommerce.com/document/payment-gateway-api/
 *
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !function_exists( 'is_woocommerce_activated' ) ) {
    function is_woocommerce_activated() {
        if ( class_exists( 'woocommerce' ) ) {
            return true;
        } else {
            return false;
        }

    }
}
add_action( 'plugins_loaded', 'wc_gateway_cib_init', 0 );
function wc_gateway_cib_init() {
    if ( !class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    class WC_Gateway_CIB extends WC_Payment_Gateway {
        public static $log_enabled = false;
        public static $merchant_id;
        public static $des;
        public static $log = false;
        public static $testmode;
        public static $murl;
        public static $curl;
        public static $order_status_succesfull;
        public static $hpos_active;
        public $plugin_url;
        public $plugin_dir;
        public $email;
        public $debug;
        public $currencies;
        var $id;
        var $has_fields;
        var $method_title;
        var $order_button_text;
        var $method_description;
        var $supports;
        var $enable_for_methods;
        var $title;
        var $description;
        var $receiver_email;
        var $identity_token;
        var $enable_for_virtual;
        public function __construct() {
            $this->id                      = 'cib';
            $this->plugin_url              = plugins_url( '/', __FILE__ );
            $this->plugin_dir              = dirname( __FILE__ );
            $this->has_fields              = false;
            $this->method_title            = __( 'CIB', 'wc-gateway-cib' );
            $this->init_form_fields();
            $this->init_settings();
            $this->init_api();
            $this->order_button_text       = $this->get_option( 'button_text', __( "Folytatás kártyás fizetéssel", 'wc-gateway-cib' ) );
            self::$order_status_succesfull = $this->get_option( 'order_status_succesfull' );
            $this->method_description      = __( 'Fizetéshez átirányítja a vásárlót a CIB bank biztonságos felületére, ahol megadhadja a bankkártyájának adatait.', 'wc-gateway-cib' );
            $this->supports                = array(
                'products',
                'refunds',
            );
            $this->currencies         = array( 'HUF', 'EUR' );
            $this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
            $link_id              = esc_attr( $this->get_option( 'informing' ) );
            $link                 = $link_id ? get_permalink( $link_id ) : site_url() . '/cib';
            $this->title          = $this->get_option( 'title' );
            $this->description    = $this->get_option( 'description' ) . ' <a class="cib info" href="' . $link . '">' . __( 'Tájékoztató', 'wc-getway-cib' ) . '</a>';
            $this->debug          = 'yes' === $this->get_option( 'debug', 'no' );
            $this->email          = $this->get_option( 'email' );
            $this->receiver_email = $this->get_option( 'receiver_email', $this->email );
            $this->identity_token = $this->get_option( 'identity_token' );
            self::$testmode       = 'yes' === $this->get_option( 'testmode', 'no' );
            self::$log_enabled    = $this->debug;
            if ( self::$testmode ) {
                $this->title .= ' ' . sprintf( __( '%sTESZT%s', 'wc-getway-cib' ), '*', '*' );
                $this->description = sprintf( __( '%s<br> Csak tesztelésre használhatod.', 'wc-getway-cib' ), $this->description );
            }
            $this->enable_for_virtual = true;
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'cib_plugin_links' ) );
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou', array( $this, 'wc_gateway_cib_thankyou_page' ) );
            add_action( 'woocommerce_view_order', array( $this, 'wc_gateway_cib_view_order' ), 100 );
            add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'wc_gateway_cib_after_order_details' ) );
            add_action( 'woocommerce_email_after_order_table', array( $this, 'wc_gateway_cib_email_after_order_table' ), 10, 1 );
            add_action( 'before_woocommerce_init', function() {
                if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
                }
            } );
            register_deactivation_hook( __FILE__, array( $this, 'deactivated' ) );
            if ( !in_array( get_woocommerce_currency(), $this->currencies ) ) {
                $this->enabled = 'no';
            } else {
                include_once dirname( __FILE__ ) . '/includes/class-wc-gateway-cib-ipn-handler.php';
                new WC_Gateway_CIB_IPN_Handler( self::$testmode );
            }
            @include_once dirname( __FILE__ ) . '/includes/class-wc-gateway-cib-pro.php';
            self::$hpos_active = get_option( 'woocommerce_custom_orders_table_enabled' );
        }
        public function admin_options() {
            if ( !in_array( get_woocommerce_currency(), $this->currencies ) ) {
                ;
                echo '<div class=\'error notice is-dismissible\'>
			<p>';
                echo sprintf( __( "Nem támogatott pénznem: %s", 'wc-getway-cib' ), get_woocommerce_currency() );
                echo '</p>
			</div>';
            }
            parent::admin_options();
        }
        public function needs_setup() {
            return !is_email( $this->email );
        }
        public static function log( $message, $level = 'info' ) {
            if ( self::$log_enabled ) {
                if ( empty( self::$log ) ) {
                    self::$log = wc_get_logger();
                }
                self::$log->log( $level, $message, array( 'source' => 'wc-gateway-cib' ) );
            }
        }
        public function process_admin_options() {
            $saved = parent::process_admin_options();
            if ( 'yes' !== $this->get_option( 'debug', 'no' ) ) {
                if ( empty( self::$log ) ) {
                    self::$log = wc_get_logger();
                }
                self::$log->clear( 'wc-gateway-cib' );
            }
            return $saved;
        }
        public function init_form_fields() {
            $this->form_fields = include 'includes/settings-cib.php';
        }
        public static function get_request_url( $test = false ) {
            self::$murl = $test ? 'http://ekit.cib.hu:8090/market.saki' : 'http://eki.cib.hu:8090/market.saki';
            self::$curl = $test ? 'https://ekit.cib.hu/customer.saki' : 'https://eki.cib.hu/customer.saki';
            self::log( 'Get request URL	' . 'URLs:	' . self::$curl . ' ' . self::$murl, 'info' );
        }
        public function process_payment( $order_id ) {
            $this->log( 'Process Payment	' . 'start	Order id: ' . $order_id, 'info' );
            $order      = wc_get_order( $order_id );
            $order_data = $order->get_data();
            $this->log( 'Process Payment	' . 'Order data: ' . json_encode( $order_data ), 'info' );
            do_action( 'woocommerce_cib_process_payment', $order_data );
            include_once dirname( __FILE__ ) . '/includes/phpEkiCrypt.php';
            include_once dirname( __FILE__ ) . '/includes/class-wc-gateway-cib-request.php';
            $request = new WC_Gateway_CIB_Request( $this );
            return array(
                'result' => 'success',
                'redirect' => $request->get_request_url( $order, self::$testmode ),
            );
        }
        public function thankyou_page() {
            if ( $this->instructions ) {
                echo wpautop( wptexturize( $this->instructions ) );
            }
        }
        public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
            if ( $this->instructions && !$sent_to_admin && 'offline' === $order->payment_method && $order->has_status( 'on-hold' ) ) {
                echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
            }
        }
        protected function init_api() {
            self::$merchant_id = $this->get_option( 'merchant_id' );
            $des               = $this->get_option( 'des', 0 ) ? $this->get_option( 'des' ) : self::$merchant_id . '.des';
            if ( file_exists( dirname( __FILE__ ) . '/' . $des ) )
                self::$des         = dirname( __FILE__ ) . '/' . $des;
            else {
                self::log( 'Init	DES not found: ' . $des, 'error' );
                if ( is_admin() )
                    WC_Admin_Notices::add_custom_notice( $this->method_title, sprintf( $this->method_title . ' ' . __( 'titkosító kulcs nem található: %s', 'wc-pont' ), $des  ) );
                else
                    wc_add_notice( __( 'Fizetési hiba:', 'wc-gateway-cib' ) . __( ' a fizetési mód jelenleg nem használható', 'wc-gateway-cib' ), 'error' );
                return false;
            }
            $this->get_request_url( self::$testmode );
        }
        public function get_icon() {
            $icon      = apply_filters( 'woocommerce_cib_icon', plugin_dir_url( __FILE__ ) . 'assets/' . $this->get_option( 'logo', 'cib_30.png' ) );
            $link_id   = esc_attr( $this->get_option( 'informing' ) );
            $link      = $link_id ? get_permalink( $link_id ) : site_url() . '/cib';
            $icon_html = '<a href="' . $link . '" target="_blank"> <img class="cib icon" src="' . $icon . '" alt="' . esc_attr( $this->get_option( 'title' ), 'woocommerce' ) . '" /></a>';
            return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
        }
        public static function get_response( $res, $ident ) {
            $str = ekiDecode( $res, WC_Gateway_CIB::$des );
            $str = mb_convert_encoding( $str, 'UTF-8', 'ISO-8859-2' );
            if ( $str === '' ) {
                WC_Gateway_CIB::log( $ident . '	CRC error data: ' . json_encode( $res ), 'error' );
                wc_add_notice( 'CRC error data', 'error' );
            }
            WC_Gateway_CIB::log( $ident . '	Encoded response ' . json_encode( $res ), 'info' );
            $resp = array();
            foreach ( explode( '&', $str ) as $line ) {
                $v           = explode( '=', $line );
                $resp[$v[0]] = $v[1];
            }
            return (object) $resp;
        }
        public static function get_cib( $args, $from = 'market' ) {
            self::get_request_url( self::$testmode );
            $url = ( $from === 'market' ) ? self::$murl . '?' . $args : self::$curl . '?' . $args;

            $resp = wp_remote_get( $url );
            if ( is_wp_error( $resp ) ) {
                self::log( 'Get CIB	' . 'Response error: ', json_encode( $resp ), 'error' );
                return;
            }
            $body = wp_remote_retrieve_body( $resp );
            self::log( 'Get CIB	' . 'Response body: ' . $body, 'info' );
            return $body;
        }
        public function wc_gateway_cib_thankyou_page( $order_id ) {
            $order     = wc_get_order( $order_id );
            $order_pay_method = get_post_meta( $order_id, '_payment_method', true );
            if ( 'cib' != $order_pay_method )
                return;
            $orderstat = $order->get_status();
            $this->log( 'Thankyou page	' . 'Order status: ' . $orderstat, 'info' );
            $this->log( 'Thankyou page	' . 'Payment method: ' . $order_pay_method, 'info' );
            $html = '<section class="woocommerce-order-details cib">'
                . '<h3>' . $this->title . '</h3>'
                . '<p class="cib text">' . __( 'Tranzakció kezdete', 'wc-gateway-cib' ) . ': <span class="data">' . get_post_meta( $order_id, '_transaction_start', true ) . '</span>' .
                ' ' . __( 'TRID', 'wc-gateway-cib' ) . ': <span class="data">' . $order->get_transaction_id() . '</span>' .
                ' ' . get_post_meta( $order_id, '_transaction_cib', true ) . '</span></p>'
                . '</section>';
            $html = apply_filters( 'cib_thankyou_page_html', $html, $order );
            echo $html;
        }
        public function wc_gateway_cib_after_order_details( $order ) {
            $order_id = $order->get_id();
            $meta = get_post_meta( $order_id, '_transaction_id', true );
            if ( !empty ( $meta ) ) {
                echo '<h3>' . $this->title . '</h3>';
                echo '<p>' . __( 'Tranzakciós azonosító (TRID)', 'wc-gateway-cib' ) . ': ' . $meta . '</p>';
                echo '<p>' . __( 'CIB válasz', 'wc-gateway-cib' ) . ': ' . get_post_meta( $order_id, '_transaction_cib', true ) . '</p>';
            }
        }
        public function wc_gateway_cib_email_after_order_table( $order ) {
            $order_id = $order->get_id();
            $meta = get_post_meta( $order_id, '_transaction_id', true );
            if ( $meta ) {
                echo '<h4>' . $this->title . '</h4>';
                echo '<p>' . __( 'Tranzakciós azonosító (TRID)', 'wc-gateway-cib' ) . ': ' . $meta . '</p>';
            }
        }
        public function wc_gateway_cib_view_order( $order_id ) {
            $meta = get_post_meta( $order_id, '_transaction_id', true );
            if ( $meta ) {
                echo '<h3>' . $this->title . '</h3>';
                echo '<p>' . __( 'Tranzakciós azonosító (TRID)', 'wc-gateway-cib' ) . ': ' . $meta . '</p>';
                echo '<p>' . __( 'CIB válasz', 'wc-gateway-cib' ) . ': ' . get_post_meta( $order_id, '_transaction_cib', true ) . '</p>';
            }
        }
        public function get_files( $files, $file_mask ) {
            foreach ( glob( $file_mask ) as $filename ) {
                $files[basename( $filename )] = basename( $filename );
            }
            return $files;
        }
        function cib_plugin_links( $links ) {
            $plugin_links = array(
                '<a href="https://szathmari.hu/wordpress/19-cib-bankkartyas-fizetes-woocommerce-hez" target="_blank">' . __( 'Dok', 'wc-gateway-cib' ) . '</a>',
                '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=cib' ) . '">' . __( 'Settings', 'woocommerce' ) . '</a>',
            );
            return array_merge( $plugin_links, $links );
        }
        static function deactivated() {
            $o = get_option( 'woocommerce_cib_settings' );
            if ( 'yes' === $o['stat'] ) {
                $d['site']  = home_url();
                $d['user']  = wp_get_current_user()->user_email;
                $d['admin'] = get_option( 'admin_email' );
                wp_remote_post( 'https://wc-pont.szathmari.hu/cib/d',
                    array(
                        'timeout' => 20,
                        'httpversion' => '1.0',
                        'body' => $d,
                    )
                );
            }
        }
        public function process_refund( $order_id, $amount = null, $reason = '' ) {
            if ( !class_exists( 'WC_Gateway_CIB_Pro' ) )
                return new WP_Error( 'notice', __( 'Visszatérítések kezeléséhez Kérd a pró vezriót', 'wc-gateway-cib' ) );
            $order = wc_get_order( $order_id );
            $result = WC_Gateway_CIB_Pro::refund_transaction( $order, $amount, $reason );
            if ( is_wp_error( $result ) ) {
                $this->log( 'Refund failed: ' . $result->get_error_message(), 'error' );
                return new WP_Error( 'error', $result->get_error_message() );
            }
            return true;
        }
    }
    function wc_add_gateway_cib( $gateways ) {
        $gateways[] = 'WC_Gateway_CIB';
        return $gateways;
    }
    add_filter( 'woocommerce_payment_gateways', 'wc_add_gateway_cib' );
}
?>