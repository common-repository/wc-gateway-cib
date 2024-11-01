<?php
/**
 * @package Payment Gateway via CIB for WooCommerce
 * @author  szathmari.hu
 *
 */
defined( 'ABSPATH' ) || exit;
$logo      = array();
$logo      = $this->get_files( $logo, $this->plugin_dir . '/assets/*.png' );
$des       = array();
$des[0]    = __( 'Alapértelmezett', 'wc-gateway-cib' );
$des       = $this->get_files( $des, $this->plugin_dir . '/*.des' );
$informing = array();
if ( is_admin() && isset( $_REQUEST[ 'tab' ] )  && isset( $_REQUEST[ 'section' ] ) && $_REQUEST[ 'section' ] == 'cib' ) {
$args      = array(
    'posts_per_page' => -1,
    'post_type' => 'page',
);
$query        = new WP_Query( $args );
$informing[0] = __( 'Válassz egy oldalt', 'wc-gateway-cib' );
while ( $query->have_posts() ) {
    $query->the_post();
    $informing[get_the_ID()] = get_the_title( $query->post->ID );
}
wp_reset_postdata();
}
$statuses = wc_get_order_statuses();
foreach ( $statuses as $slug => $name ) {
    $order_status[substr( $slug, 3 )] = __( $name, 'wc-gateway-cib' );
}
$options    = array();
$data_store = WC_Data_Store::load( 'shipping-zone' );
$raw_zones  = $data_store->get_zones();
foreach ( $raw_zones as $raw_zone ) {
    $zones[] = new WC_Shipping_Zone( $raw_zone );
}
$zones[] = new WC_Shipping_Zone( 0 );
foreach ( WC()->shipping()->load_shipping_methods() as $method ) {
    $options[$method->get_method_title()]              = array();
    $options[$method->get_method_title()][$method->id] = sprintf( __( 'Any &quot;%1$s&quot; method', 'woocommerce' ), $method->get_method_title() );
    foreach ( $zones as $zone ) {
        $shipping_method_instances = $zone->get_shipping_methods();
        foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {
            if ( $shipping_method_instance->id !== $method->id ) {
                continue;
            }
            $option_id                                        = $shipping_method_instance->get_rate_id();
            $option_instance_title                            = sprintf( __( '%1$s (#%2$s)', 'woocommerce' ), $shipping_method_instance->get_title(), $shipping_method_instance_id );
            $option_title                                     = sprintf( __( '%1$s &ndash; %2$s', 'woocommerce' ), $zone->get_id() ? $zone->get_zone_name() : __( 'Other locations', 'woocommerce' ), $option_instance_title );
            $options[$method->get_method_title()][$option_id] = $option_title;
        }
    }
}
return array(
    'enabled' => array(
        'title' => __( 'Enable/Disable', 'woocommerce' ),
        'type' => 'checkbox',
        'label' => __( 'Enable CIB payment gateway', 'wc-gateway-cib' ),
        'default' => 'no',
    ),
    'merchant_id' => array(
        'title' => __( 'Kereskedőazonosító', 'wc-gateway-cib' ),
        'type' => 'text',
        'desc_tip' => true,
        'description' => __( 'Banktól kapott azonosítószám', 'wc-gateway-cib' ),
        'default' => '',
    ),
    'des' => array(
        'title' => __( 'Titkosító kulcs', 'wc-gateway-cib' ),
        'type' => 'select',
        'desc_tip' => true,
        'description' => __( 'CIB-től kapott titkosító teszt vagy éles kulcs (rendszerint három betű0001.des fájl). Amennyiben nem választol, alapértelmezés szerinti a (Kereskedőazonosító).des fájl lesz használva.', 'wc-gateway-cib' ),
        'default' => 0,
        'options' => $des,
    ),
    'title' => array(
        'title' => __( 'Fizetési mód elnevezése', 'wc-gateway-cib' ),
        'type' => 'text',
        'description' => __( 'Fizetés folyamán ez jelenik meg a fizetési mód melett', 'wc-gateway-cib' ),
        'default' => __( 'CIB Bankkártyás fizetés', 'wc-gateway-cib' ),
        'desc_tip' => true,
    ),
    'button_text' => array(
        'title' => __( 'Gomb felirata', 'wc-gateway-cib' ),
        'type' => 'text',
        'desc_tip' => true,
        'description' => __( 'Feizetés megkezdése gombra kerülő felirat', 'wc-gateway-cib' ),
        'default' => __( "Folytatás kártyás fizetéssel", 'wc-gateway-cib' ),
    ),
    'description' => array(
        'title' => __( 'Leírás', 'woocommerce' ),
        'type' => 'textarea',
        'desc_tip' => true,
        'css' => 'width: 90%',
        'description' => __( 'Fizetés folyamán ez jelenik meg a fizetési mód alatt', 'wc-gateway-cib' ),
        'default' => __( 'Bankkártyás fizetés szolgáltató: CIB Bank Zrt. Az XY Kft. székhelyének országa és országkódja: Magyarország (HU)', 'wc-gateway-cib' ),
        'placeholder' => __( 'Bankkártyás fizetés szolgáltató: CIB Bank Zrt. A (Kereskedő vagy XY Kft.) székhelyének országa és országkódja: Magyarország (HU)', 'wc-gateway-cib' ),
    ),
    'logo' => array(
        'title' => __( 'Logó', 'wc-gateway-cib' ),
        'type' => 'select',
        'desc_tip' => true,
        'description' => __( 'Fizetési módnál megjelenő logó', 'wc-gateway-cib' ),
        'default' => 'cib_25.png',
        'options' => $logo,
    ),
    'informing' => array(
        'title' => __( 'Tájékoztató oldal', 'wc-gateway-cib' ),
        'type' => 'select',
        'desc_tip' => true,
        'description' => __( 'Tájékoztató oldal a CIB bankkártyás fizetésről. Amennyiben nem választol, alapértelmezés szerinti /cib lesz használva.', 'wc-gateway-cib' ),
        'default' => 0,
        'options' => $informing,
    ),
    'enable_for_methods' => array(
        'title' => __( 'Csak a következő szállítási módoknál érhető el', 'wc-gateway-cib' ),
        'type' => 'multiselect',
        'class' => 'wc-enhanced-select',
        'css' => 'min-width: 150px;',
        'default' => '',
        'description' => __( 'Ha csak néhány szállítási módnál engedélyezed a CIB bankártyás fizetést, akkor itt kiválaszthatod ezeket.', 'wc-gateway-cib' ),
        'options' => $options,
        'desc_tip' => true,
        'custom_attributes' => array(
            'data-placeholder' => __( 'Válassz szállítási módot', 'wc-gateway-cib' ),
        ),
    ),
    'order_status_succesfull' => array(
        'title' => __( 'Rendelés állapota sikeres fizetés után', 'wc-gateway-cib' ),
        'type' => 'select',
        'desc_tip' => true,
        'description' => __( 'Válaszd ki a rendlés állapotát', 'wc-gateway-cib' ),
        'default' => 'completed',
        'options' => $order_status,
    ),
    'advanced' => array(
        'title' => __( 'Advanced options', 'woocommerce' ),
        'type' => 'title',
        'description' => '',
    ),
    'testmode' => array(
        'title' => __( 'Teszt mód', 'woocommerce' ),
        'type' => 'checkbox',
        'label' => __( 'Tesztelés engedélyezése', 'wc-gateway-cib' ),
        'default' => 'no',
    ),
    'stat' => array(
        'title' => __( 'Telepítési statisztika', 'wc-gateway-cib' ),
        'type' => 'checkbox',
        'label' => __( 'Telepítéskor engedélyezi az URL, mail cím küldését statisztikához', 'wc-gateway-cib' ),
        'default' => 'yes',
    ),
    'debug' => array(
        'title' => __( 'Debug log', 'woocommerce' ),
        'type' => 'checkbox',
        'label' => __( 'Enable logging', 'woocommerce' ),
        'default' => 'no',
        'description' => sprintf( __( 'CIB kártyás fizetések naplózása a %s fájlba', 'woocommerce' ), '<code>' . WC_Log_Handler_File::get_log_file_path( 'wc-gateway-cib' ) . '</code>' ),
    ),
);
?>
