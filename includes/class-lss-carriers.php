<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * Class Logisnap_Carriers
 */
class Logisnap_Carriers
{
    protected $carriers = [];

    /**
     * Logisnap_Carriers constructor.
     */
    public function __construct()
    {
        $this->load();
        $this->setup_ajax_requests();
    }

    /**
     * @param string $code
     *
     * @return mixed
     */
    public function get($code)
    {
        if (array_key_exists($code, $this->carriers)) {
            return $this->carriers[$code];
        }

        return [];
    }

    /**
     * @param string $code
     *
     * @return string
     */
    public function name($code)
    {
        if ( ! array_key_exists($code, $this->carriers)) {
            return $code;
        }

        return __($this->carriers[$code]['name'], 'logisnap-shipping-for-woocommerce');
    }

    private function load()
    {
        $this->carriers = LogiSnapShipping()->options->get('carriers', true);
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->carriers;
    }

    /**
     * @return array
     */
    public function carrier()
    {
        return $this->carriers[LogiSnapShipping()->identifier];
    }

    public function all_enabled()
    {
        $carriers = [];

        foreach ($this->all() as $item) {
            if (LogiSnapShipping()->options->getBool($item['carrier_code'])) {
                $carriers[$item['carrier_code']] = $item['carrier_code'];
            }
        }

        return $carriers;
    }

    public function carrier_options_all_json() {
        $product_types_object = '{';
            $x = 0;
            if(isset($this->carrier()['carriers'])) {
                foreach($this->carrier()['carriers'] as $carrier) {
                    $product_types_object .= '"'.$carrier['carrierName']. '": [';
                    if(isset($carrier['shipping'])) {
                    
                        foreach($carrier['shipping'] as $product)
                        {    
                            $product_types_object .= '"'.$product['Name'].'|'.$product['UID'].'"' . ',';
                            $x++;
                        }

                    }

                    $product_types_object = rtrim($product_types_object, ',');

                    $product_types_object .= '],';
                }

                $product_types_object = rtrim($product_types_object, ',');
            }

            $product_types_object .= '}';

            return $product_types_object;
    }

    function carrier_type_options(){
        $types = [];
        $x = 0;
        $product_types_object = "";
        if(isset($this->carrier()['carriers']))
        {
            foreach($this->carrier()['carriers'] as $carrier)
            {
                $product_types_object .= '"'.$carrier['carrierName']. '": [';
                if(isset($carrier['shipping']))
                {
                    foreach($carrier['shipping'] as $product)
                    {
                        $types[$x] = $product['Name'];
                        $x++;
                    }
                }
            }
        } 

        return $types;
    }

    // When user presses on manual update
    public function update()
    {
        $response = LogiSnapShipping()->api_client->request_carriers();

        $settings[LogiSnapShipping()->identifier] = [
            'name'          => __(LogiSnapShipping()->plugin_title, 'logisnap-shipping-for-woocommerce'),
            'carrier_code'  =>  LogiSnapShipping()->identifier
        ];

        if ($response->Success())
        {
            $data     = $this->convert_from_carrier_to_Logisnap_CustomCarrier($response->GetMessage());
            $carriers = [];
           
            foreach($data as $row) {
                $carriers[$row['carrierName']] = $row;
            }

            $settings[LogiSnapShipping()->identifier]['carriers'] = $carriers;

            LogiSnapShipping()->options->set('carriers', $settings, true);

            LogiSnapShipping()->options->set(LogiSnapShipping()->identifier, true);
        }
    }

        public function convert_from_carrier_to_Logisnap_CustomCarrier($carriers){

        $custom_carriers = [];

        foreach ($carriers as $carrier) {            
            
            if($this->custom_carrier_exists($custom_carriers, $carrier['ClientName']))
            {
                foreach ($custom_carriers as $custom_carrier)
                {
                    if($custom_carrier->carrierName == $carrier['ClientName']){
                        $carrier['Name'] = $this->format_name($carrier['Name']);
                        array_push($custom_carrier->shipping, $carrier);
                    }
                }
            }
            else
            {
                $c_carrier = new Logisnap_CustomCarrier();

                $c_carrier->carrierName = $carrier['ClientName'];
                $carrier['Name'] = $this->format_name($carrier['Name']);
                array_push($c_carrier->shipping, $carrier);

                array_push($custom_carriers, $c_carrier);
            }
        }  

        return json_decode(json_encode($custom_carriers), true);
    }

    function custom_carrier_exists($custom_array, $carrier_name){
        foreach ($custom_array as $custom_carrier) {
            if($custom_carrier->carrierName == $carrier_name){
                return true;
            }
        }
        return false;
    }

    function format_name($carrier_name){
        // name formating change first letter big and big after space remove _ with ' '
        // from BUSINESS_HALFPALLET
        // to Business Halfpallet

        $found_space = false;
        $new_name = '';
        $characters = str_split($carrier_name);

        foreach ($characters as $char_index => $character) {

            if($character !== ' ')
                $character = strtolower($character);
            
            if($found_space === true){
                $character = strtoupper($character);
                $found_space = false;
            }

            if($character === '_' || $character === '-')
                $character = ' ';

            if($character === ' ')
                $found_space = true;

            if($char_index == '0')
                $character = strtoupper($character);

            $new_name .= $character;
        }



        return $new_name;
    }
    public function is_not_logisnap_shipping_method($method)
    {
        if ($method instanceof WC_Order) {
            $shipping_methods = $method->get_shipping_methods();
            $shipping_methods = reset($shipping_methods);

            $method = $shipping_methods['method_id'];
        }

        if (substr($method, 0, strlen('logisnap_')) != 'logisnap_') {
            return true;
        }

        return false;
    }

    /**
     * @param  WC_Order  $order
     *
     * @return mixed
     */
    public function method_name($order)
    {
        $shipping_methods = $order->get_shipping_methods();
        $shipping_methods = reset($shipping_methods);

        if (isset($shipping_methods['name'])) {
            return $shipping_methods['name'];
        }

        return '';
    }

    public function get_shipping_method()
    {                       
        // Get all your existing shipping zones IDS
        $zone_ids = array_keys( array('') + WC_Shipping_Zones::get_zones() );

        $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );

        $current_values = explode(':', $chosen_methods[0]);

        $method_type = $current_values[0];
        $method_id = $current_values[1];

        // Loop through shipping Zones IDs
        foreach ( $zone_ids as $zone_id ) 
        {
            // Get the shipping Zone object
            $shipping_zone = new WC_Shipping_Zone($zone_id);

            // Get all shipping method values for the shipping zone
            $shipping_methods = $shipping_zone->get_shipping_methods( true, 'values' );

            // Loop through each shipping methods set for the current shipping zone
            foreach ( $shipping_methods as $instance_id => $shipping_method ) 
            {
                if($shipping_method->instance_id == $method_id) {
                    return $shipping_method;

                }
            }
        }

        return null;
    }

    /**
     * Look up carrier pickup points
     *
     * @return json
     */
    public function lookup_carrier_pickup_points($zipcode)
    {
        $parameters = [];

        if(WC()->customer->billing['country']) {
            $parameters['countryiso'] = WC()->customer->billing['country'];
        }

        if(isset($_POST['zipcode'])) {
            $parameters['zipcode'] = sanitize_text_field($_POST['zipcode']);
        }

        $shipping_method = $this->get_shipping_method();

        $parameters['agent'] = strtolower($shipping_method->instance_settings['carrier']);

        if(isset($_POST['street'])) {
            $parameters['street'] = sanitize_text_field($_POST['street']);
        }else{
            $parameters['street'] = "";
        }

        if(isset($_POST['city'])) {
            $parameters['city'] = sanitize_text_field($_POST['city']);
        }else{
            $parameters['city'] = "";
        }

        $response = ['error' => true];
        if($shipping_method) {

            $droppoint_options = new Logisnap_ParcelShopOptions();
            $droppoint_options->Amount = 20;
            $droppoint_options->Agent = $parameters['agent'];
            $droppoint_options->CountryISO = $parameters['countryiso'];
            $droppoint_options->AgreementCountry = $parameters['countryiso'];
            $droppoint_options->Zipcode = $parameters['zipcode'];            
            $droppoint_options->Street = $parameters['street'];
            $droppoint_options->City = $parameters['city'];

            $response = LogiSnapShipping()->api_client->get_droppoints($droppoint_options)->GetMessage();
        }
        // send response to js
        _e($response,'logisnap-shipping-for-woocommerce');

        die();
    }

    /**
     * Look up carrier type
     *
     * @return json
     */
    public function lookup_carrier_type()
    {
        $shipping_method = $this->get_shipping_method();

        $type = '';

        if($shipping_method) {
            $type = $this->get_respose_type_from_shipping_method($shipping_method->instance_settings);
        }
         
        _e($type, 'logisnap-shipping-for-woocommerce');

        die();
    }

    public function setup_ajax_requests() {
        add_action("wp_ajax_nopriv_logisnap_carrier_type", [$this, 'lookup_carrier_type']);
        add_action("wp_ajax_logisnap_carrier_type", [$this, 'lookup_carrier_type']);
        
        add_action("wp_ajax_nopriv_logisnap_carrier_pickup_points", [$this, 'lookup_carrier_pickup_points']);
        add_action("wp_ajax_logisnap_carrier_pickup_points", [$this, 'lookup_carrier_pickup_points']);
    }

    function get_respose_type_from_shipping_method($type_options){

        $carriers = LogiSnapShipping()->carriers->all();
       
        $old_carrier_uid = LogiSnapShipping()->get_old_carrier_uid($type_options); 

        if (is_array($carriers) && count($carriers) >= 1)
        {
            $api_carrier = $carriers[LogiSnapShipping()->identifier];
        
            if($api_carrier['carriers'])
            {
                foreach ($api_carrier['carriers'] as $carrier_name => $carrier)
                {  
                    foreach ($carrier['shipping'] as $shipment)
                    {
                        if( $shipment['UID'] == $old_carrier_uid && $shipment['TypeID'] == 20)
                        {            
                            return 'service_point';
                        } 
                   }                  
                }
            }
        }

        return "private";
    }
}

class Logisnap_CustomCarrier{
    public $carrierName = "";
    public $shipping = [];
}

class Logisnap_ParcelShopOptions
{
    public $Zipcode = "";
    public $CountryISO = "DK";
    public $AgreementCountry = "DK";
    public $City = "";
    public $Street = "";
    public $StreetNumber = "";
    public $Amount = 0;
    public $Agent = "";
}

return new Logisnap_Carriers();
