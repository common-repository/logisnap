<?php

defined( 'ABSPATH' ) || exit;

/**
 * Class Logisnap_Woocommerce
 */
class Logisnap_Woocommerce
{
    /**
     * Logisnap_Woocommerce constructor.
     */
    public function __construct()
    {
        $this->shipping_init();
        $this->init_parcel_pickup_checkout();

        $this->add_extra_shipping_fields();

        add_action("wp_ajax_nopriv_logisnap_google_key", [$this, 'add_jscript']);
        add_action("wp_ajax_logisnap_google_key", [$this, 'add_jscript']);
    }

	public function add_jscript() {

        $api_key =LogiSnapShipping()->options->get( 'google_maps_api_key' );

        if($api_key != "")
        {
            _e(sprintf( 'https://maps.googleapis.com/maps/api/js?key=%s', LogiSnapShipping()->options->get( 'google_maps_api_key' ) ),'logisnap-shipping-for-woocommerce');
        }
        else
        {
            _e('','logisnap-shipping-for-woocommerce');
        }

        // die();
	}

    // Can be extended to handle multiple by adding a simple foreach
    static public function shipping_init()
    {
        add_action( 'woocommerce_shipping_init', 'logisnap_shipping_method_init' );
 
        function add_shipping_method( $methods ) {
            $methods['ls_shipping'] = 'WC_Logisnap_Shipping';
            return $methods;
        }
     
        add_filter( 'woocommerce_shipping_methods', 'add_shipping_method' );
    }

    static public function add_extra_shipping_fields() {
        /**
         * Add the field to the checkout
         */
        add_action( 'woocommerce_after_order_notes', 'lss_custom_checkout_field' );

        function lss_custom_checkout_field( $checkout ) {

            woocommerce_form_field('parcel_pickup_id', array(
                'type'          => 'hidden',
                'class'         => ['lss_parcel_pickup_field_id'],
            ));

            woocommerce_form_field('parcel_pickup_place_description', array(
                'type'          => 'hidden',
                'class'         => ['lss_parcel_pickup_field_place_description'],
            ));
        }

        add_action( 'woocommerce_checkout_update_order_meta', 'lss_checkout_field_pickup_id_update_order_meta', 10, 1 );
        function lss_checkout_field_pickup_id_update_order_meta( $order_id ) {
            if ( ! empty( $_POST['parcel_pickup_id'] ) ){
                update_post_meta( $order_id, 'parcel_pickup_id', sanitize_text_field( $_POST['parcel_pickup_id'] ) );

                // get the customer ID
                $customer_id = get_post_meta( $order_id, '_customer_user', true );

                // Update customer user data
                update_user_meta( $customer_id, 'parcel_pickup_id', true );
            }
        }

        add_action( 'woocommerce_admin_order_data_after_shipping_address', 'lss_checkout_field_pickup_id_display_admin_order_meta', 10, 1 );
        function lss_checkout_field_pickup_id_display_admin_order_meta( $order ){
            $parcel_pickup_id = get_post_meta( $order->get_id(), 'parcel_pickup_id', true );
            if( ! empty( $parcel_pickup_id ))
                _e('<p><strong>'.__('Pickup place id', 'woocommerce').':</strong> ' . $parcel_pickup_id . '</p>','logisnap-shipping-for-woocommerce');
        }

        add_action( 'woocommerce_checkout_update_order_meta', 'lss_checkout_field_pickup_description_update_order_meta', 10, 1 );
        function lss_checkout_field_pickup_description_update_order_meta( $order_id ) {
            if ( ! empty( $_POST['parcel_pickup_place_description'] ) ){
                update_post_meta( $order_id, 'parcel_pickup_place_description', sanitize_text_field( $_POST['parcel_pickup_place_description'] ) );

                // get the customer ID
                $customer_id = get_post_meta( $order_id, '_customer_user', true );

                // Update customer user data
                update_user_meta( $customer_id, 'parcel_pickup_place_description', true );
            }
        }

        add_action( 'woocommerce_admin_order_data_after_shipping_address', 'lss_checkout_field_pickup_description_display_admin_order_meta', 10, 1 );
        function lss_checkout_field_pickup_description_display_admin_order_meta( $order ){
            $parcel_pickup_place_description = get_post_meta( $order->get_id(), 'parcel_pickup_place_description', true );
            if( ! empty( $parcel_pickup_place_description ))
                _e('<p><strong>'.__('Pickup place description', 'woocommerce').':</strong> ' . $parcel_pickup_place_description . '</p>','logisnap-shipping-for-woocommerce');
        }
    }

    static public function init_parcel_pickup_checkout() {
        // Add button
        function pickup_location_button()
        {
        ?>  
            <div id="parcel_pickup_chosen_wrap" style="display:none;">
                <h4><?php _e( LogiSnapShipping()->translate_text('Chosen pickup point'), 'logisnap-shipping-for-woocommerce' );?></h4>
                <div class="parcel_pickup_chosen_description"></div>
            </div>

            <div class="logisnap-pickup-point-trigger-holder">
                <input type="button" class="button lss-pickup-point-trigger-button" value="<?php _e( LogiSnapShipping()->translate_text('Chosen pickup point'), 'logisnap-shipping-for-woocommerce' );?>" />
            </div>
        <?php
        }

        add_action( 'woocommerce_review_order_before_payment', 'pickup_location_button');

        // Add Map holder
        function pickup_location_selector_display()
        {
        ?>
            <div class="lss-wc-pickup-point-shipping-wrap">
                <div class="lss-wc-pickup-point-shipping-modal">
                    <h3><?php _e( LogiSnapShipping()->translate_text('Chosen pickup point'), 'logisnap-shipping-for-woocommerce' );?></h3>

                    <p class="form-row">
                        <span class="woocommerce-input-wrapper">
                            <label><?php _e( 'Postcode / ZIP', 'logisnap-shipping-for-woocommerce' );?></label>
                            <input class="input-text lss-parcel-pickup-postalcode-input" placeholder="<?php _e( 'Postal Code', 'woocommerce' );?>" />
                        </span>
                    </p>

                    <div class="lss-map-preview">
                        <div class="lss-map-loader"><h2><?php _e( 'Loading pickup points', 'logisnap-shipping-for-woocommerce' );?></h2></div>
                        <div id="lss_parcel_pickup_gmap"></div>
                    </div>

                    <div class="lss-pickup-point-list">
                    </div>

                    <a class="lss-wc-pickup-point-shipping-close" href="">X<a>
                </div>
            </div>

            <?php
        }

        add_action( 'woocommerce_review_order_after_submit', 'pickup_location_selector_display' );
    }
}

return new Logisnap_Woocommerce();
