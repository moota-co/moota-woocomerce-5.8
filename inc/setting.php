<?php
/**
 * SETUP settings option for WooMoota
 * @author Onnay <onnay@moota.co>
 */

WC_Settings_Tab_Woomota::init();
class WC_Settings_Tab_Woomota {
    public static function init() {
        add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50 );
    }
    public static function add_settings_tab( $settings_tabs ) {
        $settings_tabs['setting_tab_woomoota'] = __( 'WooMoota', 'woomoota' );
        return $settings_tabs;
    }
}

add_action( 'woocommerce_settings_tabs_setting_tab_woomoota', 'woomota_settings_tab' );
function woomota_settings_tab() {
    woocommerce_admin_fields( get_woomoota_settings() );
}
function get_woomoota_settings() {
    $settings = array(
        'section_title' => array(
            'name'     => __( 'Pengaturan API Key & Nomor Unik Pesanan', 'woomoota' ),
            'type'     => 'title',
            'desc'     => 'Tutorial integrasi Moota dengan WooCommerce, silahkan cek di sini: <a href="https://moota.co/integrasi-dengan-woocommerce/" target="_blank">https://moota.co/integrasi-dengan-woocommerce/</a>.',
            'id'       => 'woomoota_settings_label'
        ),
        'mode' => array(
            'name' => __( 'Mode', 'woomoota' ),
            'type' => 'select',
            'desc' => __( '<br>Pilih Mode', 'woomoota' ),
            'default'   =>  'testing',
            'options' => array(
                'testing'       => 'Testing',
                'production'    => 'Production'
            ),
            'id'   => 'woomoota_mode'
        ),
        'api_endpoint' => array(
            'name'              => __( 'API Endpoint', 'woomoota' ),
            'type'              => 'text',
            'css'               => 'min-width:420px;',
            'default'           => add_query_arg( 'woomoota', 'push', get_bloginfo('url') . '/' ),
            'desc'              => __( '<br>Masukan URL ini kedalam pengaturan Push Notification', 'woomoota' ),
            'id'                => 'woomoota_api_endpoint',
            'custom_attributes' => array(
                'disabled'  => true
            )
        ),
        'api_key' => array(
            'name' => __( 'Api Key', 'woomoota' ),
            'type' => 'text',
            'css'      => 'min-width:420px;',
            'desc' => __( '<br>Dapatkan API Key melalui : <a href="https://app.moota.co/integrations/token" target="_new">https://app.moota.co/integrations/token</a>', 'woomoota' ),
            'id'   => 'woomoota_api_key'
        ),
        'success_status' => array(
            'name' => __( 'Status Berhasil', 'woomoota' ),
            'type' => 'select',
            'desc' => __( '<br>Status setelah berhasil menemukan order yang telah dibayar', 'woomoota' ),
            'default'   =>  'processing',
            'options' => array(
                'completed'     => 'Completed',
                'on-hold'       => 'On Hold',
                'processing'    => 'Processing'
            ),
            'id'   => 'woomoota_success_status'
        ),
        'range_order' => array(
            'name' => __( 'Batas lama pengecekkan invoice', 'woomoota' ),
            'type' => 'number',
            'desc' => __( '<br>Pengecekkan invoice berdasarkan x hari ke belakang (default: 7 hari kebelakang)', 'woomoota' ),
            'id'   => 'woomoota_range_order',
            'default' => 7,
            'custom_attributes' => array(
                'min'  => 1,
                'max'  => 31
            )
        ),
        'change_day' => array(
            'name' => __( 'Perubahan status di hari ke?', 'woomoota' ),
            'type' => 'select',
            'desc' => __( '<br>Setelah konsumen checkout dan belum bayar, pilih hari ke berapa status order berubah otomatis dari ON-HOLD ke PENDING.', 'woomoota' ),
            'default'   =>  'disable',
            'options' => array(
                'disable'      => 'Tidak Aktif',
                '1'      => 'H+1',
                '2'      => 'H+2',
                '3'      => 'H+3',
                '4'      => 'H+4',
                '5'      => 'H+5',
                '6'      => 'H+6',
                '7'      => 'H+7',
            ),
            'id'   => 'woomoota_change_day'
        ),
        'toggle_status' => array(
            'name' => __( 'Nomor Unik?', 'woomoota' ),
            'type' => 'checkbox',
            'desc' => __( '<br>Centang, untuk aktifkan fitur penambahan 3 angka unik di setiap akhir pesanan / order. Sebagai pembeda dari order satu dengan yang lainnya.', 'woomoota' ),
            'id'   => 'woomoota_toggle_status'
        ),
        'label_unique' => array(
            'name' => __( 'Label Kode Unik', 'woomoota' ),
            'type' => 'text',
            'default' => 'Kode Unik',
            'css'      => 'min-width:420px;',
            'desc' => __( '<br>Label yang akan muncul di form checkout', 'woomoota' ),
            'id'   => 'woomoota_label_unique'
        ),
        'type_append' => array(
            'name' => __( 'Tipe Tambahan', 'woomoota' ),
            'type' => 'select',
            'desc' => __( '<br>Increase = Menambah unik number ke total harga, Decrease = Mengurangi total harga dengan unik number', 'woomoota' ),
            'default'   =>  'increase',
            'options' => array(
                'increase'      => 'Tambahkan',
                'decrease'      => 'Kurangi'
            ),
            'id'   => 'woomoota_type_append'
        ),
        'unique_start' => array(
            'name' => __( 'Batas Awal Angka Unik', 'woomoota' ),
            'type' => 'number',
            'desc' => __( '<br>Masukan batas awal angka unik', 'woomoota' ),
            'id'   => 'woomoota_start_unique_number',
            'default' => 1,
            'custom_attributes' => array(
                'min'  => 0,
                'max'  => 99999
            )
        ),
        'unique_end' => array(
            'name' => __( 'Batas Akhir Angka Unik', 'woomoota' ),
            'type' => 'number',
            'desc' => __( '<br>Masukan batas akhir angka unik', 'woomoota' ),
            'id'   => 'woomoota_end_unique_number',
            'default' => 999,
            'custom_attributes' => array(
                'min'  => 0,
                'max'  => 99999
            )
        ),
        'section_end' => array(
            'type' => 'sectionend',
            'id' => 'wc_settings_tab_woomoota_section_end'
        )
    );
    return apply_filters( 'wc_setting_tab_woomoota_settings', $settings );
}

add_action( 'woocommerce_update_options_setting_tab_woomoota', 'woo_moota_update_settings' );
function woo_moota_update_settings() {
    woocommerce_update_options( get_woomoota_settings() );
}
