<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * Class Logisnap_Notices
 */
class Logisnap_Notices
{
    public function __construct()
    {
        add_action('admin_notices', [$this, 'api_key_errors']);
    }

    function api_key_errors()
    {
        if (get_current_screen()->id != 'woocommerce_page_logisnap-shipping-for-woocommerce' && get_current_screen()->id != 'shipments_page_logisnap-shipping-for-woocommerce') {
          
            if(LogiSnapShipping()->logisnap_is_configured() === false){
                 $authErrorMessage = LogiSnapShipping()->options->get('auth-error');

                 ?>
                <div class="error notice">
                    <p>
                        <?php 
                            if(isset($authErrorMessage))
                            {
                                _e($authErrorMessage,'logisnap-shipping-for-woocommerce'); 
                            }
                        ?>                            
                    </p>
                    <p>
                        <a href="<?php _e(esc_attr(LogiSnapShipping()->settings_url()), 'logisnap-shipping-for-woocommerce'); ?>">
                            <?php _e('Configure LogiSnap', 'logisnap-shipping-for-woocommerce') ?>
                        </a>
                    </p>
                </div>
                <?php
            }
            else if(LogiSnapShipping()->is_user_auth_notice() === false){
                $authErrorMessage = LogiSnapShipping()->options->get('auth-error');
                //var_dump($authErrorMessage);
                ?>
                <div class="error notice">
                    <p><?php _e($authErrorMessage,
                            'logisnap-shipping-for-woocommerce'); ?></p>
                    <p>
                        <a target="_blank" href="<?php _e(esc_attr(LogiSnapShipping()->get_auth_url()), 'logisnap-shipping-for-woocommerce'); ?>">
                            <?php _e('Logisnap needs permission to import orders correctly', 'logisnap-shipping-for-woocommerce') ?>
                        </a>
                    </p>
                </div>
                <?php

            }


          

        }
    }

    public function no_pickup_points_notice()
    {
        $pages = [
            'edit-shop_order',
        ];

        if ( ! in_array(get_current_screen()->id, $pages)) {
            return;
        }

        $show = false;

        $carriers = LogiSnapShipping()->carriers->all();
        if (is_array($carriers)) {
            foreach ($carriers as $carrier => $settings) {
                if (LogiSnapShipping()->options->getBool($carrier) && ($settings['products']['service_point'])) {
                    if (count(LogiSnapShipping()->locations->all($carrier)) == 0) {
                        $show = true;
                        break;
                    } else {
                        // Check only one
                        break;
                    }
                }
            }
        }

        if ( ! $show) {
            return;
        }
        ?>
        <div class="error notice" style="display: flex;">
            <div style="-webkit-box-flex: 1;-ms-flex: 1;flex: 1;-webkit-box-align: center;-ms-flex-align: center;align-items: center;display: -webkit-box;display: -ms-flexbox;display: flex;">
                <strong><?php _e('LogiSnap Shipping For WooCommerce', 'logisnap-shipping-for-woocommerce'); ?>: </strong> <?php _e('No locations found. Please try to run manual update',
                    'logisnap-shipping-for-woocommerce'); ?>
            </div>
            <p style="-webkit-box-flex: 0;-ms-flex: 0;flex: 0;">
                <a class="button button-primary"
                   href="<?php _e(admin_url('admin-post.php?action=logisnap_update_data'), 'logisnap-shipping-for-woocommerce'); ?>">
                    <?php _e('Manual update', 'logisnap-shipping-for-woocommerce') ?>
                </a>
            </p>
        </div>
        <?php
    }
}

return new Logisnap_Notices();
