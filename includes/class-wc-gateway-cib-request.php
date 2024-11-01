<?php
/**
 * @package Payment Gateway via CIB for WooCommerce
 * @author  szathmari.hu
 *
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class WC_Gateway_CIB_Request {
    protected $gateway;
    protected $return_url;
    public function __construct( $gateway ) {
        $this->gateway    = $gateway;
        $this->return_url = WC()->api_request_url( 'WC_Gateway_CIB' );
    }
    public function get_request_url( $order ) {
        $args = array(
            'PID' => WC_Gateway_CIB::$merchant_id,
            'CRYPTO' => 1,
            'MSGT' => 10,
            'TRID' => $this->tr_id(),
            'UID' => 'CIB12345678',
            'LANG' => 'HU',
            'TS' => date( 'YmdHis' ),
            'AUTH' => 0,
            'AMO' => ( $order->get_currency() == 'EUR' ) ? number_format( $order->get_total(), 2, '.', '' ): $order->get_total(),
            'CUR' => $order->get_currency(),
            'URL' => $this->return_url,
        );
        $encode = http_build_query( $args );
        $this->gateway->log( 'Process Payment	' . 'Args ' . json_encode( $args ), 'info' );
        $this->gateway->log( 'Process Payment	' . 'Encode ' . $encode, 'info' );
        $encoded_url = ekiEncode( $encode, WC_Gateway_CIB::$des );
        $res         = $this->gateway->get_cib( $encoded_url, 'market' );
        $this->gateway->log( 'Process Payment	' . 'DES fájl: ' . WC_Gateway_CIB::$des, 'info' );
        $this->gateway->log( 'Process Payment	' . 'Return URL: ' . $this->return_url, 'info' );
        $this->gateway->log( 'Process Payment	' . 'Encoded URL: ' . $encoded_url, 'info' );
        $this->gateway->log( 'Process Payment	' . 'Response: ' . $res, 'info' );
        $res = WC_Gateway_CIB::get_response( $res, 'Process Payment Response' );
        $this->gateway->log( 'Process Payment	' . 'Decoded response: ' . json_encode( $res ), 'info' );
        if ( $res->RC == '00' ) {
            $this->gateway->log( 'Process Payment	' . ' RC ' . '00', 'info' );
            $encode      = "MSGT=20&TRID=" . $args['TRID'] . "&PID=" . WC_Gateway_CIB::$merchant_id;
            $encoded_url = ekiEncode( $encode, WC_Gateway_CIB::$des );
            $order->set_transaction_id( $args['TRID'] );
            $order->update_meta_data( '_transaction_start', date( 'Y-m-d H:i:s' ) );
            $order->update_meta_data( '_transaction_id', $args['TRID'] );
            $order->save();
            $this->gateway->log( 'Process Payment	' . 'Order number: ' . $order->get_order_number() . ' Trid: '. $args['TRID'], 'info' );
            $cib_url = WC_Gateway_CIB::$curl . '?' . $encoded_url;
            $this->gateway->log( 'Process Payment	' . 'Encoded2: ' . $encode, 'info' );
            $this->gateway->log( 'Process Payment	' . 'Encoded2 URL: ' . $encoded_url, 'info' );
            $this->gateway->log( 'Process Payment	' . 'Redirect URL: ' . $cib_url, 'info' );
            return $cib_url;
        } else {
            $this->gateway->log( 'Process Payment	' . 'Transaction ID: ' . $args['TRID'], 'error' );
            wc_add_notice( __( 'Fizetési hiba:', 'wc-gateway-cib' ) . ' Transaction ID: ' . $args['TRID'], 'error' );
            wp_redirect( $order->get_cancel_order_url_raw() );
        }
    }
    function tr_id() {
        $rnd = '';
        for ( $i = 1; $i <= 16; $i++ ) {
            if ( $i == 1 ) {
                $num = mt_rand( 1, 9 );
            } else {
                $num = mt_rand( 0, 9 );
            }

            $rnd .= $num;
        }
        return $rnd;
    }
}
?>
