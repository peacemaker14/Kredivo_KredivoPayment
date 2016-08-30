<?php

class Kredivo_Api
{
    public static function get_redirection_url($params)
    {

        $params['server_key'] = Kredivo_Config::$server_key;

        $result = Kredivo_Request::post(
            Kredivo_Config::get_api_endpoint() . '/get_checkout_url',
            $params
        );

        return $result->redirect_url;
    }

    public static function response_notification($data = array())
    {
        header('Content-Type: application/json');

        $default = array(
            "status"  => "OK",
            "message" => "Notification has been received",
        );
        $data = array_merge($default, $data);

        return json_encode($data);
    }

    public static function confirm_order_status($params)
    {
        $result = Kredivo_Request::get(
            Kredivo_Config::get_api_endpoint() . '/update?' . http_build_query($params)
        );

        return $result;
    }
}
