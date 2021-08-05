<?php
/*
 * Plugin Name: Moota Woocommerce
 * Plugin URI: http://moota.co
 * Description: Plugin ini adalah addon dari Moota.co sebagai payment gateway woocomerce wordpress dan auto konfirmasi. Integrasikan toko online Anda dengan moota.co, sistem akan auto konfirmasi setiap ada transaksi masuk ke rekening Anda.
 * Version: 0.6.3
 * Author: Moota.co
 * Author URI: https://moota.co
 * WC requires at least: 5.8
 * WC tested up to: 5.8
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 **/
include('inc/setting.php');


// add_action( 'woocommerce_calculate_totals', 'woocommerce_set_total');
// /**
//  * Set Unique to Total Order
//  */
// function woocommerce_set_total(){
//     global $woocommerce;
//     print_r($woocommerce->order->get_fees());
// }

add_action('init', 'register_my_session');
function register_my_session()
{
    if( !session_id() )
    {
        session_start();
    }
}


add_action( 'woocommerce_cart_calculate_fees','woocommerce_woomoota_surcharge' );
//add_action( 'woocommerce_calculate_totals', 'woocommerce_woomoota_surcharge');
/**
 * Add Unique to Total Order
 */
function woocommerce_woomoota_surcharge($cart = null) {
    global $woocommerce;
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    if(get_option('woomoota_toggle_status', 'no') == 'no') {
        return;
    }

    /**
     * Check if subtotal is below 0, than return false;
     */
    if ( $woocommerce->cart->subtotal <= 0) {
        return;
    }

    $unique = moota_get_uqcodes();


    $label_unique = get_option('woomoota_label_unique', 'Diskon');

    if(! is_cart()){
        
        if(!isset($_SESSION[session_id().'_cart_unique_code'])){
            if(count($woocommerce->cart->get_fees()) == 0){
                $woocommerce->cart->add_fee( $label_unique, $unique, true, '' );
                $_SESSION[session_id().'_cart_unique_code'] = $unique;
            }
        } else {
            $woocommerce->cart->add_fee( $label_unique, $_SESSION[session_id().'_cart_unique_code'], true, '' );
        }
    }
}


add_action('woocommerce_thankyou', 'clear_session');

function clear_session(){
    unset($_SESSION[session_id().'_cart_unique_code']);
}

add_action('wp_loaded', 'moota_notification_handler');

/**
 * Moota Notification Handler
 */
function moota_notification_handler() {
    if ( !class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'moota_wc_warning' );
        return;
    }

    if(get_option('woomoota_mode', 'testing') == 'testing') {
        add_action( 'admin_notices', 'moota_warning' );
    }

    if(!isset($_GET['woomoota']) || isset($_GET['woomoota']) && $_GET['woomoota'] != 'push') {
        return;
    }

    if( !moota_check_authorize() ) {
        die("Need Authorize");
        return;
    }

    $notifications = json_decode(file_get_contents("php://input"));
    if(!is_array($notifications)) {
        $notifications = json_decode( $notifications );
    }

    $results = array();

    if( count($notifications) > 0 ) {
        $range_order = get_option('woomoota_range_order', 7);
        foreach( $notifications as $notification) {
            do_action( 'moota-before-auto-confirm', $notification );
            $args = array(
                'post_type'     => 'shop_order',
                'meta_query' => array(
                    array(
                        'key'     => '_order_total',
                        'value'   => (int) $notification->amount,
                        'type'    => 'numeric',
                        'compare' => '=',
                    ),
                ),
                'post_status'   => array('wc-on-hold', 'wc-pending'),
                'date_query'    => array(
                    array(
                        'column'    =>  'post_date_gmt',
                        'after'    =>  $range_order . ' days ago'
                    )
                )
            );
            $query = new WP_Query( $args );

            if( $query->have_posts() ) {
                if ($query->found_posts > 1) {

                    /** Send notification to admin */
                    $admin_email = get_bloginfo('admin_email');
                    $message = sprintf( __( 'Hai Admin.' ) ) . "\r\n\r\n";
                    $message .= sprintf( __( 'Ada order yang sama, dengan nominal Rp %s' ), $notification->amount ). "\r\n\r\n";
                    $message .= sprintf( __( 'Mohon dicek manual.' ) ). "\r\n\r\n";
                    wp_mail( $admin_email, sprintf( __( '[%s] Ada nominal order yang sama - Moota' ), get_option('blogname') ), $message );

                } else {

                    while ( $query->have_posts() ) {
                        $query->the_post();
                        $order = new WC_Order( get_the_ID() );
                        if( $order->has_status( get_option('woomoota_success_status', 'processing') ) ) {
                            continue;
                        }
                        $order->add_order_note('Pembayaran Melalui Bank : ' . strtoupper($notification->bank_type) . ' -  Moota');
                        $order->update_status( get_option('woomoota_success_status', 'processing') );
                        array_push($results, array(
                            'order_id'  =>  $order->get_order_number(),
                            'status'    =>  $order->get_status(),
                        ));
                    }
                    wp_reset_postdata();

                }
            }
        }
    }

    print(json_encode($results)); exit();
}

/**
 * Check Moota Authorize
 * @return bool
 */
function moota_check_authorize()
{
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        if (strpos(strtolower($_SERVER['HTTP_AUTHORIZATION']),'basic')===0) {
            list($token, $other) = explode(':', substr($_SERVER['HTTP_AUTHORIZATION'], 6));
            if(get_option('woomoota_mode', 'testing') == 'production' && get_option('woomoota_api_key') == $token) {
                return true;
            }
        }

        if(get_option('woomoota_mode', 'testing') == 'testing' && get_option('woomoota_api_key') == $token) {
            return true;
        }
    }

    if(isset($_GET['apikey'])) {
        $token = $_GET['apikey'];
        if(get_option('woomoota_mode', 'testing') == 'production' && get_option('woomoota_api_key') == $token) {
            return true;
        }

        if(get_option('woomoota_mode', 'testing') == 'testing' && $token == get_option('woomoota_mode', 'testing')) {
            return true;
        }
    }
    die("You'r Not Authenticated");
}

/**
 * Show warning if status plugin is TESTING
 * @return [type] [description]
 */
function moota_warning() {
    ?>
    <div class="update-nag notice" style="display: block;">
        <p><?php _e( '<b>WooMoota</b> Dalam Mode <b>Testing</b>', 'woomoota' ); ?></p>
    </div>
    <?php
}

/**
 * Show notification of WooCommerce not installed yet
 * @return [type] [description]
 */
function moota_wc_warning() {
    ?>
    <div class="update-nag notice" style="display: block;">
        <p><?php _e( 'Plugin <b>WooCommerce</b> belum terinstall.', 'woomoota' ); ?></p>
    </div>
    <?php
}


/**
 * Register hook activation on installed plugin
 */
function moota_register_autochange_status_order() {
    if (! wp_next_scheduled ( 'moota_autochange_status_order' )) {
        wp_schedule_event(time(), 'hourly', 'moota_autochange_status_order');
    }
}
add_action('moota_autochange_status_order', 'moota_doing_autochange_status_order');
register_activation_hook(__FILE__, 'moota_register_autochange_status_order');

/**
 * Change status order from on-hold to pending
 * @return [type] [description]
 */
function moota_doing_autochange_status_order() {
    $change_day = get_option('woomoota_change_day', 'disable');

    /** Check if change day is disable and then skip */
    if ($change_day == 'disable')
        return false;

    $args = array(
        'post_type'     => 'shop_order',
        'post_status'   => array('wc-on-hold'),
        'date_query'    => array(
            array(
                'column'    =>  'post_date_gmt',
                'before'    =>  $change_day . ' days ago'
            )
        )
    );

    /**
     * Query get all order with status on-hold
     * @var WP_Query
     */
    $query = new WP_Query( $args );
    if ($query->have_posts()) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $order = new WC_Order( get_the_ID() );
            $order->add_order_note('Perubahan status On-Hold ke Pending - Moota');
            $order->update_status( 'wc-pending' );
        }
    }

    wp_reset_postdata();

}


function moota_get_uqcodes() {
    global $wpdb;

    $uqLabel = get_option('woomoota_label_unique', 'Diskon');
    $uqMin = get_option('woomoota_start_unique_number', 1);
    $uqMax = get_option('woomoota_end_unique_number', 9999);
    $range_order = get_option('woomoota_range_order', 7);

    $sql = <<<EOF
        SELECT p.`ID`, oi.`order_item_id`, oim.`meta_value` `unique_code`
        FROM `{$wpdb->prefix}posts` p
        LEFT JOIN `{$wpdb->prefix}woocommerce_order_items` oi
        ON (
            oi.`order_id` = p.`ID`
            AND oi.`order_item_type` = 'fee'
            AND oi.`order_item_name` = '{$uqLabel}'
        )
        LEFT JOIN `{$wpdb->prefix}woocommerce_order_itemmeta` oim
        ON (
            oim.`order_item_id` = oi.`order_item_id`
            AND oim.`meta_key` = '_fee_amount'
        )
        WHERE `post_type`='shop_order'
        AND `post_status` IN (
            'wc-on-hold', 'wc-pending'
        )
        AND `post_date` >= DATE(NOW()) - INTERVAL {$range_order} DAY
        LIMIT 0, {$uqMax}
    EOF;

    $results = $wpdb->get_results($sql, OBJECT);

    $uqCodes = [];

    foreach ($results as $meta) {
        if (empty($meta->unique_code) && $meta->unique_code != '0') continue;
        $uqCodes[] = $meta->unique_code;
    }


    $unique = null;
    $loopCount = 0;

    while (empty($unique) && ++$loopCount <= count($uqCodes)) {
        $unique = mt_rand( $uqMin, $uqMax );
        if( get_option('woomoota_type_append', 'increase') == 'decrease') {
            $unique = (int) -$unique;
        }
        $unique = !empty($uqCodes) && in_array($unique, $uqCodes) ? null : $unique;
    }
    if($unique == 0 || $unique == null) {
        $unique = $uqMin;
        if( get_option('woomoota_type_append', 'increase') == 'decrease') {
            $unique = (int) -$unique;
        }
    }

    return $unique;
}


/**
 * unregister hook on deactivation installed plugin
 */
register_deactivation_hook(__FILE__, 'moota_unregister_autochange_status_order');
function moota_unregister_autochange_status_order() {
    wp_clear_scheduled_hook('moota_autochange_status_order');
}