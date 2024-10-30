<?php
/**
 * Plugin Name: LogiSnap
 * Description: Easily integrate your LogiSnap solution into Woocommerce.
 * Version: 1.2.3
 * Author: LogiSnap
 * Author URI: //LogiSnap.com
 */

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;


/**
 * Class LogiSnapShipping
 */
class LogiSnapShipping
{
    /**
     * The single instance of the class.
     *
     * @var LogiSnap
     * @since 0.1
     */
    protected static $_instance;

    public $identifier = 'logisnap';

    /** @var $*/
    public $version = '1.2.3';

    /** @var LSS_Options */
    public $options;

    /** @var LSS_API_Client */
    public $api_client;

    /** @var LSS_Carriers */
    public $carriers;

    /** @var $*/
    public $contact_email = 'info@logisnap.com';

     /** @var $*/
     public $web_page = 'logisnap.com';

    /** @var $*/
    public $plugin_title = 'Logisnap';

    /** @var array */
    public $shipping_methods = [];

    /** @var LSS_Woocommerce */
    public $woocommerce;

    /**
     * @since 0.1
     *
     * @return LogiSnapShipping
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        
        return self::$_instance;
    }

    /**
     * LogiSnapShipping constructor.
     */
    function __construct()
    {
        $this->load_plugin_textdomain();

        add_action('plugins_loaded', function () {
            if ($this->version_checks()) {
                $this->plugins_loaded();
            }
        });

        add_action( 'init', [$this, 'wc_session_enabler']);
    }

    private function version_checks()
    {
        if ( ! version_compare(PHP_VERSION, '5.6.0', '>=')) {
            add_action('admin_notices', [$this, 'php_version_check_notice']);

            return false;
        }

        if ( ! defined('WC_VERSION') || ! version_compare(WC_VERSION, '3.0.0', '>=')) {
            add_action('admin_notices', [$this, 'wc_version_check_notice']);

            return false;
        }

        return true;
    }

    public function php_version_check_notice()
    {
        ?>
        <div class="error notice">
            <p>
                <strong><?php _e(LogiSnapShipping()->plugin_title, 'logisnap-shipping-for-woocommerce') ?></strong>
            </p>
            <p>
                <?php _e('This plugin requires at least PHP version 5.6.0. Please contact your server administrator to upgrade your PHP version.',
                    'logisnap-shipping-for-woocommerce'); ?>
            </p>
        </div>
        <?php
    }

    public function wc_version_check_notice()
    {
        ?>
        <div class="error notice">
            <p>
                <strong><?php _e(LogiSnapShipping()->plugin_title, 'logisnap-shipping-for-woocommerce') ?></strong>
            </p>
            <p>
                <?php _e('This plugin requires at least WooCommerce version 3.0.0.',
                    'logisnap-shipping-for-woocommerce'); ?>
                <?php
                if (defined('WC_VERSION')) {
                    _e('Please upgrade your WooCommerce.', 'logisnap-shipping-for-woocommerce');
                    ?>

                    <br>
                    <?php _e(sprintf(__('Your WooCommerce version is %s.',
                        'logisnap-shipping-for-woocommerce'), sprintf("<strong>%s</strong>", WC_VERSION)),'logisnap-shipping-for-woocommerce'); ?>
                    <?php
                }
                ?>
            </p>
        </div>
        <?php
    }

    public function plugins_loaded()
    {
        $this->includes();
        add_action('admin_init', ['PAnD', 'init']);

        $this->init_hooks();

        $plugin_version           = $this->version;
        $installed_plugin_version = $this->options->get('version', true);
    }

    private function init_hooks()
    {
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [__CLASS__, 'plugin_action_links']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);

        // run after checkout but before order is completed
        // automatically saves the order after the even is run       
        add_action('woocommerce_checkout_create_order', [$this, 'before_checkout_create_order'], 20, 2);

        add_action('woocommerce_order_status_pending', [$this, 'checkout_process'], 10, 1);
        add_action('woocommerce_order_status_on-hold', [$this, 'checkout_process'], 10, 1);
        add_action('woocommerce_order_status_processing', [$this, 'checkout_process'], 10, 1);
        add_action('woocommerce_order_status_completed', [$this, 'checkout_process'], 10, 1);
        add_action('woocommerce_order_status_failed', [$this, 'checkout_process'], 10, 1);     
        add_action('woocommerce_order_status_refunded', [$this, 'checkout_process'], 10, 1); 
        add_action('woocommerce_order_status_cancelled', [$this, 'checkout_process'], 10, 1);    
    }   
     
    public function enqueue_scripts()
    {
        wp_enqueue_script('wp-lss-shipping-js', plugins_url('/public/js/pickup-points.js', __FILE__), ['jquery'], LogiSnapShipping()->version, true);
    }

    
    public function enqueue_styles()
    {
        wp_enqueue_style('wp-lss-shipping-css', plugins_url('/public/css/style.css', __FILE__), false, LogiSnapShipping()->version, 'all');
    }

    private function includes()
    {
        include_once 'includes/class-lss-api-client-response.php';

        $this->options = include_once 'includes/class-lss-options.php';

        $this->api_client  = include_once 'includes/class-lss-api-client.php';
        $this->carriers    = include_once 'includes/class-lss-carriers.php';

        // Admin settings
        include_once 'includes/class-lss-admin.php';
        
        // Shipping method
        include_once 'includes/class-lss-shipping.php';

        include_once 'includes/class-lss-actions.php';
        $this->woocommerce = include_once 'includes/class-lss-woocommerce.php';
        include_once 'includes/class-lss-notices.php';
        include_once 'includes/persist-admin-notices-dismissal.php';
    }

    /**
     * Show action links on the plugin screen.
     *
     * @param    mixed $links Plugin Action links
     * @return    array
     */
    public static function plugin_action_links($links)
    {
        $action_links = [
            sprintf('<a href="%s" title="%s">%s</a>',
                admin_url('admin.php?page=logisnap-shipping-for-woocommerce'),
                esc_attr(__('View LogiSnap Settings', 'logisnap-shipping-for-woocommerce')),
                __('Settings', 'logisnap-shipping-for-woocommerce')
            ),
        ];

        return array_merge($action_links, $links);
    }

    public function load_plugin_textdomain()
    {
        load_textdomain('logisnap-shipping-for-woocommerce',
            WP_LANG_DIR . '/logisnap-shipping-for-woocommerce/logisnap-shipping-for-woocommerce-' . get_locale() . '.mo');
        load_plugin_textdomain('logisnap-shipping-for-woocommerce', false,
            plugin_basename(dirname(__FILE__)) . '/translations');
    }

    public function translate_text($text){
        $translated_text = __($text, 'logisnap-shipping-for-woocommerce');

        if($translated_text != $text)
            return $translated_text;
        
        if(get_locale() == 'da_DK'){

            if($text == 'Chosen pickup point')
                return 'Vælg udleveringssted';       
        }

        return $text;
    }

    /**
     * @return string
     */
    public function plugin_path()
    {
        return untrailingslashit(plugin_dir_path(__FILE__));
    }

    /**
     * @param array $query
     * @param bool $escape
     *
     * @return string
     */
    function settings_url($query = [], $escape = true)
    {
        $url = admin_url('admin.php?page=logisnap-shipping-for-woocommerce') . '&' . http_build_query($query);

        if ($escape) {
            return esc_url($url);
        }

        return $url;
    }

    /**
     * Get the public plugin url.
     *
     * @param string|null $sub_path
     *
     * @return string
     */
    public function public_plugin_url($sub_path = null)
    {
        $path = untrailingslashit(plugins_url('/', __FILE__)) . '/public';

        if ($sub_path) {
            $path .= '/' . $sub_path;
        }

        return esc_url($path);
    }

    public function get_carrier_image($carrier_name){

        $carrier_name = esc_attr(strtolower($carrier_name));

        if($carrier_name == 'danske fragtmænd')
             $carrier_name = 'danske_fragtmaend';

         return $this->public_plugin_url('images/carriers/'. $carrier_name . '.png');
    }

    public function get_assets_url(){
        return untrailingslashit(plugins_url('/', __FILE__)). '/assets/';
    }

    public function wc_session_enabler() { 
        if ( is_user_logged_in() || is_admin() ) {
            return;
        }

        if ( isset(WC()->session) && ! WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie( true );
        }
    }

    public function before_checkout_create_order($order, $data){

        $available_methods = WC()->session->get('chosen_shipping_methods');

        // if it isnt our custom shipping return 
        if(empty(strpos(json_encode($available_methods), 'ls_shipping')) === true)
            return;

        if(count($available_methods) > 0){
            // from ls_shipping:38 to "ls_shipping_38"
            $replaced = str_replace(":","_",$available_methods[0]);
            $options_data = get_option('woocommerce_'.$replaced.'_settings'); 

            // $data['type'] is index of carrier
            $carrier_data = $this->get_carrier_data($options_data);
            $order->update_meta_data( 'shipmentTypeUID', $carrier_data[0]);
        }
    }

    public function checkout_process($order_id){
     
        $order = wc_get_order($order_id);
        $order_data = $order->get_data();

        $token = LogiSnapShipping()->get_user_token();

        $full_Order = new Logisnap_Full_Order();
        $full_Order->order_data = $order_data;
        $full_Order->items_data = array();

        $full_Order->client_version = $this->get_token_version($token);

        foreach ($order->get_items() as $item_key => $item_values){
            $product = $item_values->get_product();
            $item_values['sku'] = $product->get_sku();
            
            array_push($full_Order->items_data, $item_values->get_data());
        }

        $this->api_client->send_full_order($full_Order, $token);
    }

    // gets possibly old carrier data that is stored in shipping method
    // since the shipping isnt updated when new carrier optinos are added or removed
    public function get_old_carrier_uid($type_options){
        $index = 0;

        $options = json_decode($type_options['type_options'], true);

        // there might be a change in carriers so the index no longer points at the right one
        // so we find the values from the old/stored shipping to compare the values instead
        foreach ($options as $type_key => $type_option)
        {
            foreach ($options[$type_key] as $value) {

                if($index == $type_options['type']){

                    return explode('|', $value)[1];          
                }

                $index++;
            }
        } 

        return null;
    }



    function get_carrier_data($type_options){
        $all_carriers = LogiSnapShipping()->carriers->all();

        $old_carrier_uid = $this->get_old_carrier_uid($type_options); 
        $carrier_info = [];

        if (is_array($all_carriers) && count($all_carriers) >= 1)
        {
            $api_carrier = $all_carriers[LogiSnapShipping()->identifier];
        
            if($api_carrier['carriers'])
            {
                foreach ($api_carrier['carriers'] as $carrier_name => $carrier)
                {  
                    foreach ($carrier['shipping'] as $ship)
                    { 
                        if($ship['UID'] == $old_carrier_uid)
                        {
                            array_push($carrier_info, $ship['UID']);
                            array_push($carrier_info, $ship['TypeID']);
                            array_push($carrier_info, $ship['StatusID']);
                            return $carrier_info;
                        }  
                    }           
                }
            }
        }

        return null;
    }

    // Generate new customer token if user name and password is set in admin panel
    public function get_user_token(){
     
        $full_token = LogiSnapShipping()->options->get('full_token');
        
        return $full_token;
    }

    public function get_auth_url(){
        
        $url = '';

        $callbackUrl = urlencode('https://callback.logisnap.com/v15/woocommerce/keycallback');
        $returnPageUrl = urlencode('https://logisnap.com/');
        $encodedToken = urlencode($this->get_user_token());
        if(isset($_SERVER['HTTPS']) && sanitize_text_field($_SERVER['HTTPS']) === 'on') 
            $url = "https://";   
        else  
            $url = "http://";   
        
        $url.= sanitize_text_field($_SERVER['HTTP_HOST']); 
        $clientURL = urlencode($url);
        // return url is the current url
        $url.= '/wc-auth/v1/authorize?app_name=Logisnap&scope=read_write&user_id=123&return_url='.$returnPageUrl.'&callback_url='.$callbackUrl.'%3FclientUrl%3D'.$clientURL.'%26token%3D'.$encodedToken;

        return $url;
    }

    public function is_user_auth_notice(){
        
        $user_is_auth = LogiSnapShipping()->options->get('user_is_auth');

        if(isset($user_is_auth) === true)
        {
            return $user_is_auth;
        }
        return false;
    }

    public function is_user_auth(){
        $connection = LogiSnapShipping()->api_client->check_for_logisnap_connection();

        if($connection->success() === true){
            $resp = json_decode($connection->GetMessage());
            
            if($resp->CredentialsActive === true){
                LogiSnapShipping()->options->set('user_is_auth', true);
                return true;
            }

            LogiSnapShipping()->options->set('auth-error', $resp->Message);
        }
        else
        {
            LogiSnapShipping()->options->set('auth-error', $connection->GetMessage());
        }

        return false;
    }

    public function logisnap_is_configured(){
       
        $user_is_auth = LogiSnapShipping()->is_user_auth_notice();

        return $user_is_auth;
    }

    public function replace_character($text, $char){
        $clean = '';

        $length = strlen($text);
            for ($i=0; $i<$length; $i++) {
            if ($text[$i] !== $char) 
                $clean .= $text[$i];            
        }
        return $clean;
    }

    public function get_token_version($token){
        if(is_null($token))
            $token = LogiSnapShipping()->get_user_token();

        if($this->is_version_1($token))
            return "v1";
        else
            return "v2";
    }

    public function is_version_1($token){
        if(is_null($token))
            $token = LogiSnapShipping()->get_user_token();

        $dotCount = 0;

        $length = strlen($token);

        for ($i = 0; $i < $length; $i++) {
            if ($token[$i] === '.') 
                $dotCount += 1;            
        }

        if($dotCount == 1)
            return true;

        return false;
    }
}

/**
 * @return LogiSnapShipping
 */
function LogiSnapShipping()
{
    return LogiSnapShipping::instance();
}

LogiSnapShipping();


class Logisnap_Full_Order{
    public $order_data;
    public $items_data;
    public $client_version = "v1";
}