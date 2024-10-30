<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * Class Logisnap_Actions
 */
class Logisnap_Actions
{
    public function __construct()
    {
        add_action('admin_post_logisnap_update_data', [$this, 'update_data_with_redirect']);
        add_action('admin_post_logisnap_carrier_change', [$this, 'carrier_change']);
        add_action('logisnap_update_data_cron', [$this, 'update_data']);
    }

    public static function update()
    {
        $instance = new self;
        $instance->update_data();
    }

    public function update_data($redirect = false)
    {
        LogiSnapShipping()->carriers->update();

        if ($redirect) {
            wp_redirect(LogiSnapShipping()->settings_url());
            exit;
        }
    }

    function update_data_with_redirect()
    {
        $this->update_data(true);
    }

    public function carrier_change()
    {
        $value   = false;
        $carrier = sanitize_text_field($_GET['carrier']);

        if (sanitize_text_field($_GET['change']) == 'enable') {
            $value = true;
        }

        LogiSnapShipping()->options->set($carrier, $value);

        wp_redirect(LogiSnapShipping()->settings_url());
        exit;
    }
}

return new Logisnap_Actions();
