<?php

class Kredivo_Config
{
    public static $server_key;
    public static $api_version   = 'v1';
    public static $is_production = false;

    const SANDBOX_ENDPOINT    = 'http://sandbox.kredivo.com/kredivo';
    const PRODUCTION_ENDPOINT = 'https://api.kredivo.com/kredivo';

    public static function get_api_endpoint()
    {
        $sandbox    = Kredivo_Config::SANDBOX_ENDPOINT . '/' . Kredivo_Config::$api_version;
        $production = Kredivo_Config::PRODUCTION_ENDPOINT . '/' . Kredivo_Config::$api_version;
        return Kredivo_Config::$is_production ? $production : $sandbox;
    }
}
