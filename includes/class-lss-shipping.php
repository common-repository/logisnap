<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Logisnap_Shipping' ) ) {
    function logisnap_shipping_method_init() {
        class WC_Logisnap_Shipping extends WC_Shipping_Method 
        {
            /**
             * Constructor for LogiSnap shipping class
             *
             * @access public
             * @return void
             */
            public function __construct($instance_id = 0) {
                $this->instance_id = absint($instance_id);

                // Shipping method ID. Needs to be unique
                $this->id                 = 'ls_shipping';

                // Get custom fetched carrier
                $this->carrier = LogiSnapShipping()->carriers->carrier();

                // Used in admin
                $this->method_title       = __( 'LogiSnap Shipping methods', 'logisnap-shipping-for-woocommerce');  // Title shown in admin
                $this->method_description = __( 'Custom methods', 'logisnap-shipping-for-woocommerce'); // Description shown in admin

                $this->supports           = [
                    'shipping-zones',
                    'instance-settings',
                    'instance-settings-modal'
                ];

                $this->init();
                
                // By default always enabled. It is also handled elsewhere
                $this->enabled = $this->get_option( 'enabled' ) || 'yes';

                // Actual title
                $this->title = $this->get_title();
            }

            function get_title() {
                $title = __( 'LogiSnap Shipping', 'logisnap-shipping-for-woocommerce');

                if(isset($this->carrier['carriers'][$this->get_option('carrier')])) {
                    $title = $this->carrier['carriers'][$this->get_option('carrier')]['carrierName'];
                }

                if($this->get_option( 'title' )) {
                    $title = $this->get_option( 'title' );
                }

                return $title;
            }


            /**
             * Init your settings
             *
             * @access public
             * @return void
             */
            function init() {
                // Load the settings API
                $this->init_form_fields(); // Overwrite
                $this->init_settings(); // Then init

                // Save settings in admin if you have any defined
                add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
                add_action( 'admin_footer', array( 'WC_Logisnap_Shipping', 'enqueue_admin_js' ), 9 );
            }          


            /**
             * Setup settings fields
             *
             * @access public
             * @return void
             */
            function init_form_fields() {
                // Setup fields for shipping zones modal
                $this->instance_form_fields = [];

                if($this->carrier['carriers']) {

                    $options = array_map(function ($carrier_settings) {
                        return [$carrier_settings['carrierName']] = $carrier_settings['carrierName'];
                    }, $this->carrier['carriers']);


                    $this->instance_form_fields['title'] = [
                        'title'             => __( 'Title', 'woocommerce' ),
                        'type'              => 'text',
                        'placeholder'       => '',
                        'default'           => '',
                    ];
                    
                    $type_options = LogiSnapShipping()->carriers->carrier_type_options();

                    $product_types_object = LogiSnapShipping()->carriers->carrier_options_all_json();
                    
                    $this->instance_form_fields['carrier'] = [
                        'title'         => __( 'Select Carrier', 'logisnap-shipping-for-woocommerce' ),
                        'type'          => 'select',
                        'class'         => '',
                        'options'       => $options
                    ];

                    $this->instance_form_fields['type'] = [
                        'title'         => __( 'Shipment Type', 'logisnap-shipping-for-woocommerce' ),
                        'type'          => 'select',
                        'class'         => 'lss_product_type_field',
                        'options'       => $type_options
                    ];


                    $this->instance_form_fields['cost'] = [
                        'title'             => __( 'Shipping Price', 'woocommerce' ),
                        'type'              => 'text',
                        'placeholder'       => '',
                        'default'           => '0',
                        'sanitize_callback' => array( $this, 'sanitize_cost' )
                    ];

                    $this->instance_form_fields['type_options'] = [
                        'title'             => '',
                        'type'              => 'hidden',
                        'placeholder'       => '',
                        'default'           => $product_types_object,
                        'value'             => $product_types_object,
                        'sanitize_callback' => array( $this, 'sanitize_cost' )
                    ];

                    $this->instance_form_fields['reduce_shipping_price'] = [
                        'title'             => __( 'Reduce Shipping Price By %', 'woocommerce' ),
                        'type'              => 'number',
                        'custom_attributes' => array(
                            'step' => '1',
                            'min'  => '0',
                            'max'  => '100'
                        ),
                        'placeholder'       => '',
                        'default'           => '0',
                        'sanitize_callback' => array( $this, 'sanitize_cost' )
                    ];


                    $this->instance_form_fields['reduce_shipping_price_condition'] = [
                        'title'             => __( 'Condition', 'woocommerce' ),
                        'type'              => 'select',
                        'placeholder'       => '',
                        'default'           => '0',
                        'sanitize_callback' => array( $this, 'sanitize_cost' ),
                        'options'           => $this->get_conditions()
                    ];

                    $this->instance_form_fields['reduce_shipping_condition_amount'] = [
                        'title'             => __( 'Amount', 'woocommerce' ),
                        'type'              => 'number',
                        'custom_attributes' => array(
                            'min'  => '0'
                        ),
                        'placeholder'       => '',
                        'default'           => '0',
                        'sanitize_callback' => array( $this, 'sanitize_cost' )
                    ];

                    // when changing position 
                    // change index in admin enque js
                       // 'When cart is below a price',
                       // 'When cart is above a price',
                    $coupon_optionsArray = [
                       'Cut shipping price by procent',
                       'Cut shipping price by amount',
                       'Set Price to zero',
                       'Dont Allow Coupons'
                    ];

                    $this->instance_form_fields['coupon_options'] = [
                        'title'         => __( 'Coupon Code', 'logisnap-shipping-for-woocommerce' ),
                        'type'          => 'select',
                        'class'         => 'lss_coupon_options_field',
                        'options'       => $coupon_optionsArray,
                        'sanitize_callback' => array( $this, 'sanitize_cost' )
                    ];

                    $this->instance_form_fields['coupon_amount'] = [
                        'title'             => __( 'Amount', 'woocommerce' ),
                        'type'              => 'number',
                        'custom_attributes' => array(
                            'min'  => '0'
                        ),
                        'class'             => 'lss_coupon_amount_field',
                        'placeholder'       => '',
                        'default'           => '0',
                        'sanitize_callback' => array( $this, 'sanitize_cost' )
                    ];
                }
            }

            function get_conditions(){
                // when changing position 
                // change index in admin enque js
                $condiction = [
                   'When cart is above amount',
                   'When cart is below amount'
                ];
                return $condiction;
            }

            /**
             * Evaluate a cost from a sum/string.
             *
             * @param  string $sum Sum of shipping.
             * @param  array  $args Args, must contain `cost` and `qty` keys. Having `array()` as default is for back compat reasons.
             * @return string
             */
            protected function evaluate_cost( $sum, $args = array() ) {
                // Add warning for subclasses.
                if ( ! is_array( $args ) || ! array_key_exists( 'qty', $args ) || ! array_key_exists( 'cost', $args ) ) {
                    wc_doing_it_wrong( __FUNCTION__, '$args must contain `cost` and `qty` keys.', '4.0.1' );
                }

                include_once WC()->plugin_path() . '/includes/libraries/class-wc-eval-math.php';

                // Allow 3rd parties to process shipping cost arguments.
                $args           = apply_filters( 'woocommerce_evaluate_shipping_cost_args', $args, $sum, $this );
                $locale         = localeconv();
                $decimals       = array( wc_get_price_decimal_separator(), $locale['decimal_point'], $locale['mon_decimal_point'], ',' );
                $this->fee_cost = $args['cost'];

                // Expand shortcodes.
                add_shortcode( 'fee', array( $this, 'fee' ) );

                $sum = do_shortcode(
                    str_replace(
                        array(
                            '[qty]',
                            '[cost]',
                        ),
                        array(
                            $args['qty'],
                            $args['cost'],
                        ),
                        $sum
                    )
                );

                remove_shortcode( 'fee', array( $this, 'fee' ) );

                // Remove whitespace from string.
                $sum = preg_replace( '/\s+/', '', $sum );

                // Remove locale from string.
                $sum = str_replace( $decimals, '.', $sum );

                // Trim invalid start/end characters.
                $sum = rtrim( ltrim( $sum, "\t\n\r\0\x0B+*/" ), "\t\n\r\0\x0B+-*/" );

                // Do the math.
                return $sum ? WC_Eval_Math::evaluate( $sum ) : 0;
            }

            
            /**
             * Get items in package.
             *
             * @param  array $package Package of items from cart.
             * @return int
             */
            public function get_package_item_qty( $package ) {
                $total_quantity = 0;
                foreach ( $package['contents'] as $item_id => $values ) {
                    if ( $values['quantity'] > 0 && $values['data']->needs_shipping() ) {
                        $total_quantity += $values['quantity'];
                    }
                }
                return $total_quantity;
            }

            /**
             * Work out fee (shortcode).
             *
             * @param  array $atts Attributes.
             * @return string
             */
            public function fee( $atts ) {
                $atts = shortcode_atts(
                    array(
                        'percent' => '',
                        'min_fee' => '',
                        'max_fee' => '',
                    ),
                    $atts,
                    'fee'
                );

                $calculated_fee = 0;

                if ( $atts['percent'] ) {
                    $calculated_fee = $this->fee_cost * ( floatval( $atts['percent'] ) / 100 );
                }

                if ( $atts['min_fee'] && $calculated_fee < $atts['min_fee'] ) {
                    $calculated_fee = $atts['min_fee'];
                }

                if ( $atts['max_fee'] && $calculated_fee > $atts['max_fee'] ) {
                    $calculated_fee = $atts['max_fee'];
                }

                return $calculated_fee;
            }

            /**
             * Calculate the shipping costs.
             *
             * @param array $package Package of items from cart.
             */
            public function calculate_shipping( $package = array() ) {


                $rate = array(
                    'id'      => $this->get_rate_id(),
                    'label'   => $this->title,
                    'cost'    => 0,
                    'package' => $package,
                );

                // Calculate the costs.
                $has_costs = false; // True when a cost is set. False if all costs are blank strings.
                $cost      = $this->get_option( 'cost' );

                if ( '' !== $cost ) {
                    $has_costs    = true;
                    $rate['cost'] = $this->evaluate_cost(
                        $cost,
                        array(
                            'qty'  => $this->get_package_item_qty( $package ),
                            'cost' => $package['contents_cost'],
                        )
                    );
                }
                
                

                // checks if there is a free shipping coupon active
                $free_shipping_coupon = false;
                global $woocommerce;
                $all_applied_coupons = $woocommerce->cart->get_applied_coupons();

                if ( $all_applied_coupons ) {
                    foreach ( $all_applied_coupons as $coupon_code ) {
                        $this_coupon = new WC_Coupon( $coupon_code );
                        if ( $this_coupon->get_free_shipping() ) {
                            $free_shipping_coupon  = true;
                            break;
                        }
                    }
                }

                $reduce_choice = $this->get_option('reduce_shipping_price_condition');
                $cart_price = $package['contents_cost'];


                if($reduce_choice == 0){
                    $reduce_amount = $this->get_option( 'reduce_shipping_price' );
                    $reduce_condition_amount = $this->get_option( 'reduce_shipping_condition_amount' );

                    if($reduce_condition_amount < $cart_price)
                        $rate['cost'] -= ($rate['cost'] / 100) * $reduce_amount;
                }
                else if($reduce_choice == 1){
                    $reduce_amount = $this->get_option( 'reduce_shipping_price' );
                    $reduce_condition_amount = $this->get_option( 'reduce_shipping_condition_amount' );

                    if($reduce_condition_amount > $cart_price)
                        $rate['cost'] -= ($rate['cost'] / 100) * $reduce_amount;
                }


                if($free_shipping_coupon)
                {
                    $coupon_choice = $this->get_option( 'coupon_options' );

                    if($coupon_choice == 0)
                    {
                        $rate['cost'] -= ($rate['cost'] / 100) * $this->get_option( 'coupon_amount' );
                    }
                    else if($coupon_choice == 1){

                        $rate['cost'] -= $this->get_option( 'coupon_amount' );
                    }
                    else if($coupon_choice == 2){

                        $rate['cost'] = 0;
                    }
                }

                
                if ( $has_costs ) {
                    $this->add_rate( $rate );
                }

                do_action( 'woocommerce_' . $this->id . '_shipping_add_rate', $this, $rate );
            }

      
            /**
             * Enqueue JS to handle free shipping options.
             *
             * Static so that's enqueued only once.
             */
            public static function enqueue_admin_js() {
                wc_enqueue_js(
                    "jQuery( function( $ ) {

                        function ShippingProductTypeOptionParser( ) {
                            var options_field = $( '#woocommerce_ls_shipping_type_options' );

                            var type_field = $('#woocommerce_ls_shipping_type');

                            var carrier_field = $( '#woocommerce_ls_shipping_carrier' );

                            var lss_options = JSON.parse(options_field.val());

                            var foundSelected = false;
                            var lastElement = null;
                           $('option', type_field).each(function(key,el){
                                var _el = $(el);

                                _el.hide();
                                // name|id
                                var productType = el.innerHTML;
                                var carVal = carrier_field.val();
                                var showOptions = lss_options[carrier_field.val()];

                                for(var i = 0; i < showOptions.length; i++){
                                    if(showOptions[i].split('|')[0] == productType){
                                        _el.show();
                                        
                                        if(_el[0].selected === true){
                                            type_field.val(_el.val()).change();
                                            foundSelected = true;
                                        }
                                        lastElement = _el;
                                    }
                                }
                             
                            });
                            if(foundSelected === false)
                                type_field.val(lastElement.val()).change();
                        }

                        $( document.body ).on( 'change', '#woocommerce_ls_shipping_carrier', function(el) {
                            ShippingProductTypeOptionParser( this );
                        });

                        // Change while load.
                        $( '#woocommerce_ls_shipping_carrier' ).trigger( 'change' );



                        $( document.body ).on( 'wc_backbone_modal_loaded', function( evt, target ) {
                            if ( 'wc-modal-shipping-method-settings' === target ) {
                                ShippingProductTypeOptionParser();

                            }
                        });
                    });"
                );

                wc_enqueue_js(
                    "jQuery( function( $ ) {

                        function CouponCodeTypeOptionParser(el) {
                            var form = $( el ).closest( 'form' );
                            var minAmountField = $( '#woocommerce_ls_shipping_coupon_amount', form ).closest( 'tr' );
                            var couponCodeOptions = $( '#woocommerce_ls_shipping_coupon_options' );
                            var valueOption = $('#woocommerce_ls_shipping_coupon_options').val();
                            
                            // if free shipping is selected hide the amount field
                            // or if dont apply free coupon is selected
                            if(valueOption == 2 || valueOption == 3){
                                minAmountField.hide();
                                
                            }else{
                                minAmountField.show();
                            }
                        }

                        $( document.body ).on( 'change', '#woocommerce_ls_shipping_coupon_options', function(el) {
                            CouponCodeTypeOptionParser( this );
                        });

                        // Change while load.
                        $( '#woocommerce_ls_shipping_coupon_options' ).trigger( 'change' );



                        $( document.body ).on( 'wc_backbone_modal_loaded', function( evt, target ) {
                            if ( 'wc-modal-shipping-method-settings' === target ) {
                                CouponCodeTypeOptionParser($( '#wc-backbone-modal-dialog #woocommerce_ls_shipping_coupon_options', evt.currentTarget ));
                            }
                        });
                    });"
                );
            }
        }
    }
}
