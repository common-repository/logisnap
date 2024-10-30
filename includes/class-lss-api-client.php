<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * Class Logisnap_API_Client
 */
class Logisnap_API_Client
{
    public function get_droppoints($droppoints_options){
        $url = 'https://callback.logisnap.com/v15/woocommerce/droppoints';

        $headers = array(
            'Authorization' => $this->get_header_starter() . LogiSnapShipping()->get_user_token()
        );

        $args = array(
            'body' => json_decode(json_encode($droppoints_options), true),
            'headers' => $headers,
        );

        $response =  json_decode(json_encode(wp_remote_post($url, $args)), true);

        if(!is_null($response)){
            $response_body = $response['body'];
        }
        $code = $response['response']['code'];

        return new Logisnap_Api_Response($code, $response_body);
    }

    public function get_first_token($userName, $userPassword){
       // API URL
        $url = 'https://logi-scallback.azurewebsites.net/v1/user/getaccesstoken';

        $body = array(
            'Email' => $userName,
            'Password' => $userPassword
        );

        $headers = array();

        $args = array(
            'body' => $body,
            'headers' => $headers,
        );

        $first_token = LogiSnapShipping()->options->get('first_token');

        if(!isset($first_token)){

            $response =  json_decode(json_encode(wp_remote_post($url, $args)), true);
    
            $token = json_decode($response['body'], true);
            $code = $response['response']['code'];
            
            if(isset($token)){

                LogiSnapShipping()->options->set('api_first_token', json_encode($token));
    
                return new Logisnap_Api_Response(json_encode($code), json_encode($token));
            }

            return new Logisnap_Api_Response('500', "");
        }
        else{
            return new Logisnap_Api_Response('200', $first_token);
        }
    }

    public function check_for_logisnap_connection(){
        $url = 'https://callback.logisnap.com/v15/woocommerce/credentialsactive';

        $body = array();
        
        $headers = array(
            'Authorization' => $this->get_header_starter() . LogiSnapShipping()->get_user_token(),
            'WC_User_Version' => LogiSnapShipping()->get_token_version(null)
        );

        $args = array(
            'body' => $body,
            'headers' => $headers,
        );

        $response =  json_decode(json_encode(wp_remote_get($url, $args)), true);
        
        if(!is_null($response))
        {
            $response_body = $response['body'];
        }
        else{
            $response_body = null;
        }

        $code = $response['response']['code'];

        return new Logisnap_Api_Response($code, $response_body);
    }

    public function ls_login($username, $password){
        $url = 'https://callback.logisnap.com/v15/woocommerce/Login';

        $headers = array(
            'Accept' => 'application/json'
        );

        $body = array(
            'Email' => $username,
            'Password' => $password
        );

        $args = array(
            'body' => $body,
            'headers' => $headers,
        );

        $response =  json_decode(json_encode(wp_remote_post($url, $args)), true);

        $response_body = null;
        if(!is_null($response))
        {
            $response_body = $response['body'];
        }
        else{
            $response_body = null;
        }

        $code = $response['response']['code'];

        return new Logisnap_Api_Response($code, $response_body);
    }

    public function request_user_token($userName, $userPassword){
         
        $full_token = LogiSnapShipping()->options->get('full_token');

        if(isset($full_token))
            return new Logisnap_Api_Response('200', $full_token);

        $first_token_response = $this->get_first_token($userName, $userPassword);

        $first_token = $first_token_response->GetMessage();

        $second_token = LogiSnapShipping()->options->get('api_second_token');

        if($first_token_response->Success() == false)
            return new Logisnap_Api_Response('500', 'Error getting first token');

        if($second_token == "" || !isset($second_token))
            return new Logisnap_Api_Response('500', 'Error getting second token');

        return new Logisnap_Api_Response('200', $first_token . '.' . $second_token);
    }

    public function request_carriers()
    {
        $url = 'https://callback.logisnap.com/v15/woocommerce/shipmenttypes';

        $headers = array(
            'Authorization' => $this->get_header_starter() . LogiSnapShipping()->get_user_token()
        );

        $body = array();


        $args = array(
            'body' => $body,
            'headers' => $headers,
        );

        $response =  json_decode(json_encode(wp_remote_get($url, $args)), true);
      
        $response_body = null;
        if(!is_null($response))
        {
            $response_body = json_decode($response['body'], true);
        }
        else{
            $response_body = null;
        }
        $code = $response['response']['code'];

        return new Logisnap_Api_Response($code, $response_body);
    }

    public function request_second_token($first_token)
    {
        $url = 'https://apiv1.logisnap.com/user/getaccounts';

        $headers = array(
            'Accept' => 'application/json',
            'Authorization'=> 'basic ' . $first_token 
        );

        $args = array(
            'headers' => $headers,
        );

        $response =  json_decode(json_encode(wp_remote_get($url, $args)), true);  
        $code = $response['response']['code'];  
         
        $custom_respose = new Logisnap_Api_Response(json_encode($code), $response['body']);

        return $custom_respose;
    }   


    public function send_full_order($order_data, $token){
        $url = 'https://callback.logisnap.com/v15/woocommerce/NewOrder';

        $headers = array(
            'Accept' =>  'application/json',
            'Authorization' => $this->get_header_starter() . LogiSnapShipping()->get_user_token()
        );        

        $args = array(
            'body' => json_encode($order_data),
            'headers' => $headers,
        );

        $response =  json_decode(json_encode(wp_remote_post($url, $args)), true);
        $response_body = json_decode($response['body'], true);
        $code = $response['response']['code'];  

        $custom_respose = new Logisnap_Api_Response(json_encode($code), $response_body);
        
        return $custom_respose;
    }

    private function get_header_starter(){
        
        if(LogiSnapShipping()->is_version_1(null))
            return "basic ";
        else
        {
            return "bearer ";
        }
    }
}

return new Logisnap_API_Client();
