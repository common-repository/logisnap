<?php

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

/**
 * Class LSS_Api_Client_Response
 */
class Logisnap_Api_Response
{
    protected $success;
    protected $message;

    public function __construct($httpCode, $output){

        if($httpCode == "200")
            $this->success = true;
        else
            $this->success = false;

        $this->message = $output;
    }

    public function Success(){
        return $this->success;
    }

    public function GetMessage(){
        return $this->message;
    }

    public function SetMessage($newMessage){
        $this->message = $newMessage;
    }
}
