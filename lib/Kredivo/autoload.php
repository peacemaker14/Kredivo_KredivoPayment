<?php
/**
 * Custom SPL autoloader for the Kredivo SDK
 *
 * @package Kredivo
 */

if (version_compare(PHP_VERSION, '5.2.1', '<')) {
    throw new Exception('PHP version >= 5.2.1 required');
}
if (!function_exists('curl_init')) {
    throw new Exception('Kredivo needs the CURL PHP extension.');
}
if (!function_exists('json_decode')) {
    throw new Exception('Kredivo needs the JSON PHP extension.');
}

define('KREDIVO_LIB_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR);
define('KREDIVO_LOG_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR);

$classMap = array(
    'Kredivo_Request'      => KREDIVO_LIB_DIR . 'Request.php',
    'Kredivo_Config'       => KREDIVO_LIB_DIR . 'Config.php',
    'Kredivo_Api'          => KREDIVO_LIB_DIR . 'Api.php',
    'Kredivo_Notification' => KREDIVO_LIB_DIR . 'Notification.php',
    'Kredivo_Log'          => KREDIVO_LIB_DIR . 'Log.php',
);

foreach ($classMap as $class) {
    include $class;
}
