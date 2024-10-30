<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * Class Logisnap_Admin
 */
class Logisnap_Admin
{
    const TAB_SETTINGS = 'settings';
    const TAB_CHECKOUT = 'checkout';

    const TABS = [
        self::TAB_SETTINGS,
        self::TAB_CHECKOUT,
    ];

    protected $tab = self::TAB_SETTINGS;

    /**
     * @var int
     */
    private $selected_sender_location = 0;

    /**
     * Admin constructor.
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu'], 99);
        add_action('admin_init', [$this, 'settings_init']);
    }

    function add_admin_menu() {
            add_menu_page(
                __('LogiSnap', 'logisnap-shipping-for-woocommerce'),
                __('LogiSnap', 'logisnap-shipping-for-woocommerce'),
                'manage_options',
                'logisnap-shipping-for-woocommerce',
                [ $this, 'options_page' ],
                'dashicons-admin-site',
                55.5
            );
    }

    function validate($options)
    {
        return $options;
    }

    function validate_sender_details($options)
    {
        if ( ! $options ) {
            return $options;
        }
        $data = [
            'sender' => $options,
        ];

        $response = LogiSnapShipping()->api_client->request('shipments?saving_sender_details=1', 'POST', $data);

        $final_errors = [];

        // There will always be errors
        $validation_errors = $response->GetMessage()['errors'];
        foreach ($validation_errors as $key => $errors) {
            if (substr($key, 0, 7) == 'sender.') {
                $data_key = substr($key, 7);

                foreach ($errors as $error) {
                    $final_errors[$data_key][] = [
                        'rule' => $error['rule'],
                        'text' => $error['text'],
                    ];
                }
            }
        }


        LogiSnapShipping()->options->set_sender_location(
            sanitize_text_field($_POST['logisnap_sender_details']['code']),
            sanitize_text_field($_POST['logisnap_sender_details'])
        );

        if ( LogiSnapShipping()->options->get_default_sender_location() == null ) {
            LogiSnapShipping()->options->set_default_sender_location( sanitize_text_field($_POST['logisnap_sender_details']['code'] ));
        }

        exit;
    }

    function settings_init()
    {       
        if (array_key_exists('tab', $_GET)) {
            $this->tab = sanitize_text_field($_GET['tab']);
        }

        if (array_key_exists('logisnap_tab', $_POST)) {
            $this->tab = sanitize_text_field($_POST['logisnap_tab']);
        }



        if ( ! in_array($this->tab, self::TABS)) {
            $this->tab = self::TAB_SETTINGS;
        }

        add_settings_section(
            'logisnap-shipping-for-woocommerce_section',
            null,
            [$this, 'logisnap_settings_section_callback'],
            'logisnap-shipping-for-woocommerce'
        );

        if ($this->tab == self::TAB_SETTINGS) {
            register_setting('logisnap-shipping-for-woocommerce', 'logisnap_settings', [$this, 'validate']);

            add_settings_field(
                'logisnap_api_token',
                __('LogiSnap Login', 'logisnap-shipping-for-woocommerce'),
                [$this, 'api_token_field_render'],
                'logisnap-shipping-for-woocommerce',
                'logisnap-shipping-for-woocommerce_section'
            );

            add_settings_field(
                'logisnap_api_connection',
                __('LogiSnap connection', 'logisnap-shipping-for-woocommerce'),
                [$this, 'api_connection_field_render'],
                'logisnap-shipping-for-woocommerce',
                'logisnap-shipping-for-woocommerce_section'
            );

            add_settings_field(
                'logisnap_google_maps',
                __('Google maps API key', 'logisnap-shipping-for-woocommerce'),
                [$this, 'google_maps_render'],
                'logisnap-shipping-for-woocommerce',
                'logisnap-shipping-for-woocommerce_section'
            );

            add_settings_field(
                'logisnap_carriers',
                __('Carriers', 'logisnap-shipping-for-woocommerce'),
                [$this, 'carrier_field_render'],
                'logisnap-shipping-for-woocommerce',
                'logisnap-shipping-for-woocommerce_section'
            );

            add_settings_field(
                'logisnap_update',
                __('Carrier update', 'logisnap-shipping-for-woocommerce'),
                [$this, 'update_field_render'],
                'logisnap-shipping-for-woocommerce',
                'logisnap-shipping-for-woocommerce_section'
            );
        } elseif ($this->tab == self::TAB_CHECKOUT) {
            register_setting('logisnap-shipping-for-woocommerce', 'logisnap_checkout');

            add_settings_field(
                'logisnap_checkout_hide_terminal_fields',
                __('Hide not required fields when shipping to pickup locations', 'logisnap-shipping-for-woocommerce'),
                [$this, 'checkout_hide_terminal_fields'],
                'logisnap-shipping-for-woocommerce',
                'logisnap-shipping-for-woocommerce_section'
            );
        }
    }

    public function checkout_hide_terminal_fields()
    {
        _e(sprintf("<input type='hidden' name='logisnap_tab' value='%s'/>", LSS_Admin::TAB_CHECKOUT),'logisnap-shipping-for-woocommerce');
        $enabled = LogiSnapShipping()->options->get_other_setting('checkout', 'enabled');

        ?>
        <select name='logisnap_checkout[enabled]'>
            <option value="0"><?php _e('No', 'logisnap-shipping-for-woocommerce') ?></option>
            <option value="1" <?php
            if ($enabled) {
                _e('selected','logisnap-shipping-for-woocommerce');
            }
            ?>><?php _e('Yes', 'logisnap-shipping-for-woocommerce') ?></option>
        </select>


        <div style="padding: 15px 0;">
            <?php _e("It will hide the address, city, postal code fields when delivery to pickup points is selected", 'logisnap-shipping-for-woocommerce') ?>
        </div>
        <?php
    }

    private function field($data)
    {
        $data['value']    = '';
        $data['errors']   = [];
        $data['required'] = false;

        $sender_details = LogiSnapShipping()->options->get_sender_location($this->selected_sender_location);

        if (array_key_exists($data['key'], $sender_details)) {
            $data['value'] = $sender_details[$data['key']];
        }


        if ( ! $data['value'] && array_key_exists( 'default_value', $data ) ) {
            $data['value'] = $data['default_value'];
        }

        if (array_key_exists('data', $_GET) && array_key_exists($data['key'], $_GET['data'])) {
            $data['value'] = sanitize_text_field($_GET['data'][$data['key']]);
        }

        if (array_key_exists('errors', $_GET) && array_key_exists($data['key'], $_GET['errors'])) {
            $data['errors'] = sanitize_text_field($_GET['errors'][$data['key']]);
        }

        if ($data['type'] == 'text') {
            $required = '';

            if ($data['required']) {
                $required = 'required';
            }

            _e(sprintf("<input type='text' name='logisnap_sender_details[%s]' value='%s' %s/>", $data['key'],
                $data['value'], $required),'logisnap-shipping-for-woocommerce');
        } elseif ($data['type'] == 'select') {
           _e(sprintf("<select name='logisnap_sender_details[%s]'>", $data['key']), 'logisnap-shipping-for-woocommerce');

            foreach ($data['values'] as $code => $name) {
                $selected = '';

                if ($data['value'] == $code) {
                    $selected = 'selected';
                }

                _e(sprintf("<option value='%s' %s>%s</option>", $code, $selected, $name), 'logisnap-shipping-for-woocommerce');
            }
        }

        if (count($data['errors'])) {
            foreach ($data['errors'] as $error_data) {
                $error = $error_data['rule'];

                if ($error == 'VALID_POSTAL_CODE_RULE') {
                    $text = __('Not valid or not found',
                        'logisnap-shipping-for-woocommerce');
                } elseif ($error == 'VALID_PHONE_NUMBER_RULE') {
                    $text = __('Not valid', 'logisnap-shipping-for-woocommerce');
                } elseif ($error == 'REQUIRED' || $error == 'MAYBE_REQUIRED') {
                    $text = __('This field is required', 'logisnap-shipping-for-woocommerce');
                } elseif ($error == 'EMAIL') {
                    $text = __('Not valid', 'logisnap-shipping-for-woocommerce');
                } else {
                    $text = sprintf("%s:<br/> %s",
                        __('Unknown error occurred', 'logisnap-shipping-for-woocommerce'),
                        $error_data['text']);
                }

                if ($text) {
                    _e(sprintf("<div style='color: red;'>%s</div>", $text), 'logisnap-shipping-for-woocommerce');
                }
            }
        }
    }

    public function google_maps_render()
    {
        $key      = LogiSnapShipping()->options->get('google_maps_api_key');

        _e(sprintf("<input type='text' name='logisnap_settings[google_maps_api_key]' value='%s'/ style='width:100%%;display:block;'>", $key),'logisnap-shipping-for-woocommerce');

        if ( ! $key) {
            _e(sprintf("<a href='%s' target='_blank'>%s</a><br/>",
                "https://developers.google.com/maps/documentation/javascript/get-api-key#standard-auth",
                __('Get an API key', 'logisnap-shipping-for-woocommerce')),'logisnap-shipping-for-woocommerce');
        }
    }

    public function api_connection_field_render()
    {
        $token = LogiSnapShipping()->options->get('full_token');

        if(isset($token) === true)
        {
            $is_auth = LogiSnapShipping()->is_user_auth();        
            $authErrorMessage = LogiSnapShipping()->options->get('auth-error');
            $credentialURL = esc_attr(LogiSnapShipping()->get_auth_url());

            if($is_auth === true)
            {
                _e('<h4 style="color: green">All credentials are correct and you are connected to logisnap</h4>','logisnap-shipping-for-woocommerce');
            }
            else
            {
                _e('<h4 style="color: red">'.$authErrorMessage.'</h4>','logisnap-shipping-for-woocommerce');
                _e('<a target="_blank" href="'.$credentialURL.'">
                    Logisnap needs permission to import and update orders correctly. Click here and give logisnap permision
                    </a>'
                    ,'logisnap-shipping-for-woocommerce');
            }
        }
        else
        {
            _e('<h4>Fill all information before connection can be established</h4>','logisnap-shipping-for-woocommerce');
        }
    }

    public function carrier_field_render()
    {
        $carriers = LogiSnapShipping()->carriers->all();

        if(count($carriers) == 0)
        {
              _e(sprintf("<strong style='%s'>%s</strong>",
                'color: darkorange',
                __('Press update carriers to import your freight agreement', 'logisnap-shipping-for-woocommerce')),'logisnap-shipping-for-woocommerce');
              return;
        }

        if (is_array($carriers)) {
            $config = $carriers[LogiSnapShipping()->identifier];
        ?>
            <div style="margin-bottom: 15px;display: flex;width: 95%;border-bottom: 1px solid #DDDDDD;padding-bottom: 15px;">
                <?php if($config['carriers']): ?>
                    <?php foreach ($config['carriers'] as $carrier_code => $settings): ?> 
                        <div style="flex: 1;justify-content:  center;align-items: center;display:  flex">
                            <div style="text-align: center">
                                <img src="<?php _e(LogiSnapShipping()->get_carrier_image($settings['carrierName']),'logisnap-shipping-for-woocommerce'); ?>"
                                        style="width: 150px;">
                                <br>
                                <?php _e('<h3 style="margin:0;">' . $settings['carrierName'] . '</h3>','logisnap-shipping-for-woocommerce');
                               
                                foreach ($settings['shipping'] as $carrier_index => $carrier)
                                {                                     
                                    _e('<strong>' .  _e($carrier['Name'], 'logisnap-shipping-for-woocommerce') . '</strong><br>','logisnap-shipping-for-woocommerce');
                                }

                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php 
        } else {
            _e(sprintf("<strong style='%s'>%s</strong>",
                'color: red',
                __('Please update the data to receive the carriers list', 'logisnap-shipping-for-woocommerce')),'logisnap-shipping-for-woocommerce');
        }
    }

    function get_webshops($username, $password){
        $resp = LogiSnapShipping()->api_client->ls_login($username, $password);
        $webshops = null;
        if ($resp->Success() === true) {
            $acc = json_decode($resp->GetMessage(), true);

            if(isset($acc))
                $webshops = $acc["UserAccounts"];
        }

        return $webshops;
    }

    function api_token_field_render(){
        // TODO: Add auth is incorrent to the field

        $username = LogiSnapShipping()->options->get('api_token_name');
        $password = LogiSnapShipping()->options->get('api_token_password');

        $got_credentials = (isset($username) && isset($password));
        // // do checks here isset($username) && isset($password)
        $saved_token = LogiSnapShipping()->options->get('full_token');
        // // check if full token exists and if it does send to v2 render
        $saved_response = LogiSnapShipping()->options->get('saved_response');        



        if($got_credentials === true){            

            $login_resp =  LogiSnapShipping()->api_client->ls_login($username, $password);

            if($login_resp->Success()){
                LogiSnapShipping()->options->set('saved_response', $login_resp->GetMessage(), true); 
                $saved_response = $login_resp->GetMessage();            
            }
        }


        if(isset($saved_response) === true){

            _e('    
             <input id="api_token_first_saved" type="hidden" name="logisnap_settings[saved_response]" placeholder="LogiSnap username" value=\''.$saved_response.'\' style="width:100%;display:block;"">', 'logisnap-shipping-for-woocommerce');


            $decoded_response = json_decode($saved_response, true);

            if($decoded_response["Version"] === "v1"){
                $this->v1_admin_render();
                 LogiSnapShipping()->carriers->update();
            }
            else
            {
                $this->v2_admin_render($saved_response);
                LogiSnapShipping()->carriers->update();
            }
        }
        else if(isset($saved_token)){
            $this->v1_admin_render();
        }
        else
        {
            ?>

            <form action="options.php" method="post">               
             

                <input type="text" name="logisnap_settings[api_token_name]" placeholder="LogiSnap username" style="width:100%;display:block;">
                </br>
                <input type="password" name="logisnap_settings[api_token_password]" placeholder="LogiSnap password"   style="width:100%;display:block;">      
           
            </form>

            <?php
        }
    }

    private function v1_admin_render(){
        $userName = LogiSnapShipping()->options->get('api_token_name');
        $password = LogiSnapShipping()->options->get('api_token_password');
        $second_token = LogiSnapShipping()->options->get('api_second_token');
        $first_token = LogiSnapShipping()->options->get('first_token');
        $full_token = LogiSnapShipping()->options->get('full_token');
        $webshops = $this->get_webshops($userName, $password);

        if(isset($webshops))
        {
            if(count($webshops) == 1){
                $second_token = $webshops[0]["AccountToken"];
                LogiSnapShipping()->options->set('api_second_token', $second_token);
            }
        }

        $first_token_response = LogiSnapShipping()->api_client->get_first_token($userName, $password);

        if($first_token_response->Success())
        {
            $first_token = $first_token_response->GetMessage();
            LogiSnapShipping()->options->set('first_token', $first_token);
        }

        if(isset($first_token) && isset($second_token))
        {        
                $full_token = $first_token.'.'.$second_token;
                $full_token = LogiSnapShipping()->replace_character($full_token, "\"");
                LogiSnapShipping()->options->set('full_token', $full_token);
            
        }

        if(isset($first_token) === true)
        {
                $respose_second_token = LogiSnapShipping()->api_client->request_second_token($first_token);

                if($respose_second_token->Success() === true)
                {
                    $webshops = json_decode($respose_second_token->GetMessage(), true);
          
                    // if user only have 1 webshop select it
                    if(count($webshops) === 1)
                    {
                        $second_token = json_decode(json_encode($webshops))[0]->AccountToken;

                        LogiSnapShipping()->options->set('api_second_token', $second_token);
                    }
                    else
                    {
                        // check if user had selected webshop but with diffrent acount
                        // clean that token if that happend, so we can display correct webshops
                        $foundShop = false;
                        foreach ($webshops as $webshop)
                        {  
                            if($second_token == $webshop['AccountToken']){
                                $foundShop = true;    
                            }
                        }

                        if($foundShop == false)
                            $second_token = "";
                    }
                }
           
        }
        
      ?>

    <form action="options.php" method="post">

        <?php 

        if(!empty($first_token)){

            _e('     <input id="api_token_first_saved" type="hidden" name="logisnap_settings[first_token]" placeholder="LogiSnap username" value=\''.$first_token.'\' style="width:100%;display:block;"">', 'logisnap-shipping-for-woocommerce');
        }

        if(!isset($full_token) && !isset($first_token))
        {
            _e('
                <input type="text" name="logisnap_settings[api_token_name]" placeholder="LogiSnap username" style="width:100%;display:block;">
            </br>
            <input type="password" name="logisnap_settings[api_token_password]" placeholder="LogiSnap password"   style="width:100%;display:block;">',
            'logisnap-shipping-for-woocommerce');
        }
        else{
            _e('
       
                <input id="api_token_saved" type="hidden" name="logisnap_settings[full_token]" placeholder="LogiSnap username" value=\''.$full_token.'\' style="width:100%;display:block;"">

            <button onclick="document.getElementById(\'api_token_saved\').value = \'\';document.getElementById(\'api_token_first_saved\').value = \'\' ">
                    Update Credentials
            </button>
            ',
            'logisnap-shipping-for-woocommerce');
        }
        ?>

        <?php
        // TODO: Add style class and create css for it, to make it look pretty 
        if($second_token == ""){

            if(isset($webshops)){
                _e('<h1 style="margin=0px">Choose current webshop</h1>','logisnap-shipping-for-woocommerce');
                               
                foreach ($webshops as $webshop)
                {                  
                    _e('<input type="radio" name="logisnap_settings[api_second_token]"
                    value="'. $webshop['AccountToken'].'">','logisnap-shipping-for-woocommerce');
                    
                    _e('<label for="'. $webshop['AccountToken'].'">'.$webshop['ClientName'].''
                        ,'logisnap-shipping-for-woocommerce');
    
                    _e('<br/>','logisnap-shipping-for-woocommerce');
    
                }
            }
        }
        else
        {
            if(isset($webshops)){

                foreach ($webshops as $webshop)
                {  
                    // if there is no button checked the second token resets
                    // therefore we create button but set the display to none
                    // so the second tokken doesnt get cleaned
                    if($second_token == $webshop['AccountToken']){
    
                        _e('<input style="display: none;" type="radio" name="logisnap_settings[api_second_token]"
                        value="'. $webshop['AccountToken'].'" checked>','logisnap-shipping-for-woocommerce');
                        _e('<label style="display: none;" for="'. $webshop['AccountToken'].'">'.$webshop['ClientName'].''
                        ,'logisnap-shipping-for-woocommerce');  

                    }                       
                }
            }
        }

        ?>


    </form>

      <?php
    }

    private function v2_admin_render($login_resp_msg){
        $userName = LogiSnapShipping()->options->get('api_token_name');
        $password = LogiSnapShipping()->options->get('api_token_password');
        
        $full_token = LogiSnapShipping()->options->get('full_token');

        if($login_resp_msg !== null){
            LogiSnapShipping()->options->set('api_login_resp', json_encode($login_resp_msg));
        }
        else{
            $login_resp_msg = LogiSnapShipping()->options->get('api_login_resp');
        }


        // Save in options as token response
        $webshops = array();


        $acc = json_decode($login_resp_msg, true);
        if(isset($acc))
            $webshops = $acc["UserAccounts"];

        // if user only have 1 webshop select it
        if(count($webshops) === 1)
        {
            $full_token = $webshops[0]["AccountToken"];
            LogiSnapShipping()->options->set('full_token', $webshops[0]["AccountToken"]);
        } 
      ?>

    <form action="options.php" method="post">       

        <?php

        // TODO: Add style class and create css for it, to make it look pretty 
        if($full_token == ""){

            if(isset($webshops)){
                _e('<h1 style="margin=0px">Choose current webshop</h1>','logisnap-shipping-for-woocommerce');
                               
                foreach ($webshops as $webshop)
                {                  
                    _e('<input type="radio" name="logisnap_settings[full_token]"
                    value="'. $webshop['AccountToken'].'">','logisnap-shipping-for-woocommerce');
                    
                    _e('<label for="'. $webshop['AccountToken'].'">'.$webshop['ClientName'].''
                        ,'logisnap-shipping-for-woocommerce');
    
                    _e('<br/>','logisnap-shipping-for-woocommerce');
    
                }
                LogiSnapShipping()->options->set('carriers', null, true);
            }
        }
        else
        {
            _e('       
                <input id="api_token_saved" type="hidden" name="logisnap_settings[full_token]" placeholder="LogiSnap username" value=\''.$full_token.'\' style="width:100%;display:block;"">

             <button onclick="document.getElementById(\'api_token_saved\').value = \'\';document.getElementById(\'api_token_first_saved\').value = \'\' ">
                    Update Credentials
            </button>
                ',
                'logisnap-shipping-for-woocommerce');    

                $full_token = LogiSnapShipping()->replace_character($full_token, "\"");
                LogiSnapShipping()->options->set('full_token', $full_token);  
        }

        ?>


    </form>

      <?php
    }


    public function update_field_render(){ ?>
        <a href="<?php  _e(admin_url('admin-post.php?action=logisnap_update_data'), 'logisnap-shipping-for-woocommerce'); ?>"
           class="button button-primary">
            <?php _e('Update carriers', 'logisnap-shipping-for-woocommerce') ?>
        </a>

     <?php
    }

    function logisnap_settings_section_callback()
    {
    }

    function options_page()
    {
        if (isset($_REQUEST['settings-updated'])) {
            _e('<div class="updated"><p>' . __('Settings saved.',
                    'logisnap-shipping-for-woocommerce') . '</p></div>', 'logisnap-shipping-for-woocommerce');
        }


        ?>
        <form action='options.php' method='post'>
            <div style="margin: 0 0px 30px -20px;padding: 20px;background-color: #303030;">
                <img src="<?php _e(LogiSnapShipping()->public_plugin_url('images/logo.png'), 'logisnap-shipping-for-woocommerce'); ?>" style="width: 75px;">

                <div style="float:right; color:white;">
                    <a href="mailto:<?php _e(LogiSnapShipping()->contact_email, 'logisnap-shipping-for-woocommerce'); ?>" style="color:inherit;text-decoration:none;display:inline-block;margin:10px 20px 0 0;"><?php _e('Need help? Contact us here.', 'logisnap-shipping-for-woocommerce');?></a><br/>
                </div>
            </div>
            <?php

            settings_fields('logisnap-shipping-for-woocommerce');
            do_settings_sections('logisnap-shipping-for-woocommerce');


            submit_button();
            ?>

        </form>
        <?php


    }
}

return new Logisnap_Admin();