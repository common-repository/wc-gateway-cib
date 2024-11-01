<?php
/**
 * @package Payment Gateway via CIB for WooCommerce
 * @author  szathmari.hu
 *
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class WC_Gateway_CIB_IPN_Handler {
    var $testmode;
    public function __construct( $testmode = false ) {
        $this->testmode = $testmode;
        WC_Gateway_CIB::log( 'Construct	' . 'IPN', 'info' );
        add_action( 'woocommerce_api_wc_gateway_cib_return_from_payment', array( $this, 'redirect_to_order_received' ) );
        add_action( 'woocommerce_api_wc_gateway_cib', array( $this, 'check_response' ) );
    }
    public function check_response() {
        include_once dirname( __FILE__ ) . '/phpEkiCrypt.php';
        WC_Gateway_CIB::log( 'Check response	' . 'GET:	' . json_encode( $_GET ), 'info' );
        WC_Gateway_CIB::log( 'IPN Response	' . 'GET: ' . json_encode( $_GET ), 'info' );
        $decode = 'PID=' . WC_Gateway_CIB::$merchant_id . '&CRYPTO=1&DATA=' . $_GET['DATA'];
        $res    = WC_Gateway_CIB::get_response( $decode, 'IPN Response' );
        WC_Gateway_CIB::log( 'IPN Response	' . 'Variables: ' . json_encode( $res ), 'info' );
        global $wpdb;
        $sql = ( 'yes' == WC_Gateway_CIB::$hpos_active ) ?
            "SELECT * FROM {$wpdb->prefix}wc_orders WHERE `transaction_id` = {$res->TRID} LIMIT 1" :
            "SELECT * FROM {$wpdb->postmeta} WHERE meta_key = '_transaction_id' AND meta_value = {$res->TRID} LIMIT 1";
        WC_Gateway_CIB::log( 'IPN Response  ' . 'query: ' . json_encode( $sql ), 'info' );
        $metas = $wpdb->get_results( $sql, ARRAY_A );
        if ( !$metas ){
            WC_Gateway_CIB::log( 'Check response    ' . 'transaction_id:   null', 'error' );
            wc_add_notice( sprintf( __( 'Tranzakciós hiba. Azonosító nem található: %s', 'wc-gateway-cib' ), $res->TRID ), 'error' );
            wp_redirect(wc_get_checkout_url());
            return;
        }
        $post_id = ( 'yes' == WC_Gateway_CIB::$hpos_active )? $metas[0]['id'] : $metas[0]['post_id'];
        WC_Gateway_CIB::log( 'Check timeot	' . 'info:	' . json_encode( $meta ), 'info' );
        $order = wc_get_order( $post_id );
        if ( $res->PID != WC_Gateway_CIB::$merchant_id ) {
            WC_Gateway_CIB::log( 'Check response	' . 'Merchant ID:	' . $res->PID, 'error' );
            new WP_Error( 'error', __( 'CIB visszatérési hiba.', 'wc-gateway-cib' ) );
            $order->add_order_note( sprintf( __( 'CIB visszatérési hiba. Tranzakicó azonosítója: %s', 'wc-gateway-cib' ), $res->TRID ) );
            wp_redirect( $order->get_cancel_order_url_raw() );
        }
        $tr_start = $order->get_meta( '_transaction_start' );
        WC_Gateway_CIB::log( 'Check response	' . 'Transaction started:	' . $tr_start, 'info' );
        $diff = date_diff(
            date_create( $tr_start ),
            date_create( 'now' )
        );
        $diff = $diff->format( "%I" );
        WC_Gateway_CIB::log( 'Check response	' . 'Transaction diff:	' . $diff . ' minutes', 'info' );
        if ( $diff >= 10 ) {
            wc_add_notice( sprintf( __( 'Bankkártyás fizetés időtúllépés miatt megszakadt. Tranzakicó azonosítója: %s', 'wc-gateway-cib' ), $res->TRID ), 'error' );
            $order->add_order_note( sprintf( __( 'Bankkártyás fizetés időtúllépés miatt megszakadt. Tranzakicó azonosítója: %s', 'wc-gateway-cib' ), $res->TRID ) );
            WC_Gateway_CIB::log( 'Check response	' . 'Időtúllépés, TR ID:	' . $res->TRID, 'error' );
            WC_Gateway_CIB::log( 'Check response	' . 'Cancel url:	' . $order->get_cancel_order_url_raw(), 'error' );
            wp_redirect( $order->get_cancel_order_url_raw() );
        }
        $args = array(
            'PID' => WC_Gateway_CIB::$merchant_id,
            'CRYPTO' => 1,
            'MSGT' => 32,
            'TRID' => $res->TRID,
            'AMO' => $order->get_total(),
        );
        $encode = http_build_query( $args );
        WC_Gateway_CIB::log( 'Tranzakció zárása	' . 'Encode:	' . json_encode( $encode ), 'info' );
        $encoded_url = ekiEncode( $encode, WC_Gateway_CIB::$des );
        $res         = WC_Gateway_CIB::get_cib( $encoded_url, 'market' );
        WC_Gateway_CIB::log( 'Tranzakció zárása	' . 'Response:	' . $res, 'info' );
        if ( $res === false || !strstr( $res, 'DATA=' ) ) {
            $args['MSGT'] = 33;
            $encode       = http_build_query( $args );
            WC_Gateway_CIB::log( 'Tranzakció zárása	MSGT33' . 'Encode:	' . $encode, 'info' );
            $encoded_url = ekiEncode( $encode, WC_Gateway_CIB::$des );
            WC_Gateway_CIB::log( 'Tranzakció zárása	MSGT33' . 'Encoded url:	' . $encoded_url, 'info' );
            $res = WC_Gateway_CIB::get_cib( $encoded_url, 'market' );
            WC_Gateway_CIB::log( 'Tranzakció zárása	MSGT33' . 'Get CIB res:	' . $res, 'info' );
            $res = WC_Gateway_CIB::get_response( $res, 'Tranzakció zárása MSGT33' );
            wc_add_notice( sprintf( __( 'Tranzakciós hiba. Tranzakicó azonosítója: %s válaszkód (RC): %s válaszüzenet (RT): %s', 'wc-gateway-cib' ), $res->TRID, $res->RC, str_replace( '+', ' ', $res->RT ) ), 'error' );
            $order->add_order_note( sprintf( __( 'Tranzakciós hiba. Tranzakicó azonosítója: %s válaszkód (RC): %s válaszüzenet (RT): %s', 'wc-gateway-cib' ), $res->TRID, $res->RC, $res->RT ) );
            WC_Gateway_CIB::log( 'Tranzakció zárársa	' . 'Tranzakciós hiba TR ID:	' . $res->TRID . 'Válaszüzenet: ' . $res->RT, 'error' );
            wp_redirect( $order->get_cancel_order_url_raw() );
        } else {
            $res = WC_Gateway_CIB::get_response( $res, 'Tranzakció zárása' );
            WC_Gateway_CIB::log( 'Tranzakció zárása	' . 'Get CIB res:	' . json_encode( $res ), 'info' );
            if ( '31' == $res->MSGT ) {
                if ( '00' == $res->RC ) {
                    $res->RT         = str_replace( '+', ' ', $res->RT );
                    $transaction_cib = 'RT: ' . $res->RT . ' RC: ' . $res->RC . ' AMO: ' . $res->AMO . ' ANUM: ' . $res->ANUM;
                    $order->update_meta_data( '_transaction_cib', $transaction_cib );
                    $order->save();
                    $text =
                    ' Válaszüzenet (RT):' . $res->RT .
                    ' Válaszkód (RC):' . $res->RC .
                    ' Engedélyszám (ANUM):' . $res->ANUM .
                    ' Fizetett összeg (AMO):' . $res->AMO .
                    ' Tranzakció azonosítója (TRID): ' . $res->TRID .
                    ' Tanzakció zárásának időpontja: ' . date( "Y-m-d H:i:s" ) .
                    ' Rendelés száma:' . $order->get_order_number();
                    WC_Gateway_CIB::log( 'Tranzakció zárársa	' . 'Sikeres tranzakció:	' . $text, 'info' );
                    wc_add_notice( sprintf( __( 'Sikeres tranzakció. %s' ), $text ), 'info' );
                    $order->add_order_note( $text );
                    $order->payment_complete( $res->TRID );
                    WC()->cart->empty_cart();
                    if ( WC_Gateway_CIB::$order_status_succesfull ) {
                        $order_status = apply_filters( 'woocommerce_cib_order_status', WC_Gateway_CIB::$order_status_succesfull, $order );
                        $order->update_status( $order_status );
                    }
                    wp_redirect( $order->get_checkout_order_received_url() );
                } else {
                    $text =
                    'Tranzakciós azonosító (TRID): ' . $res->TRID .
                    ' Tanzakció zárásának időpontja: ' . date( "Y-m-d H:i:s" ) .
                    ' Válaszüzenet (RT):' . str_replace( '+', ' ', $res->RT ) .
                    ' Válaszkód (RC):' . $res->RC;
                    $order->add_order_note( $text );
                    wc_add_notice( sprintf( __( 'Tranzakciós hiba. Tranzakicó azonosítója: %s válaszkód (RC): %s válaszüzenet (RT): %s', 'wc-gateway-cib' ), $res->TRID, $res->RC, str_replace( '+', ' ', $res->RT ) ), 'error' );
                    WC_Gateway_CIB::log( 'Tranzakció zárársa	' . 'Sikertelen tranzakció:	' . $text, 'info' );
                    wp_redirect( $order->get_cancel_order_url_raw() );
                }
            }
        }
        exit;
    }
}
?>
