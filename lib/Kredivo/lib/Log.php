<?php
/**
 * @Author: gaghan
 * @Date:   2016-06-25 13:04:50
 * @Last Modified by:   gaghan
 * @Last Modified time: 2016-06-25 15:13:19
 */

class Kredivo_Log
{

    public static function error($message, $logfile = '')
    {
        // Determine log file
        if (empty($logfile) && defined('KREDIVO_LOG_DIR') == true) {
            $logfile = KREDIVO_LOG_DIR . 'error.log';
        }

        return self::write($message, $logfile);
    }

    public static function debug($message, $logfile = '')
    {
        // Determine log file
        if (empty($logfile) && defined('KREDIVO_LOG_DIR') == true) {
            $logfile = KREDIVO_LOG_DIR . 'debug.log';
        }

        return self::write($message, $logfile);
    }

    public static function warning($message, $logfile = '')
    {
        // Determine log file
        if (empty($logfile) && defined('KREDIVO_LOG_DIR') == true) {
            $logfile = KREDIVO_LOG_DIR . 'warning.log';
        }

        return self::write($message, $logfile);
    }

    public static function info($message, $logfile = '')
    {
        // Determine log file
        if (empty($logfile) && defined('KREDIVO_LOG_DIR') == true) {
            $logfile = KREDIVO_LOG_DIR . 'info.log';
        }

        return self::write($message, $logfile);
    }

    private static function write($message, $logfile)
    {

        if (empty($logfile)) {
            error_log('No log file defined!', 0);
            return array(status => false, message => 'No log file defined!');
        }

        // Check if $message is array
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }

        // Get time of request
        if (($time = $_SERVER['REQUEST_TIME']) == '') {
            $time = time();
        }

        // Get IP address
        if (($remote_addr = $_SERVER['REMOTE_ADDR']) == '') {
            $remote_addr = "REMOTE_ADDR_UNKNOWN";
        }

        // Get requested script
        if (($request_uri = $_SERVER['REQUEST_URI']) == '') {
            $request_uri = "REQUEST_URI_UNKNOWN";
        }

        // Format the date and time
        $date = date("Y-m-d H:i:s", $time);

        // Append to the log file
        if ($fd = @fopen($logfile, "a")) {
            $message = "[" . $date . "] [" . $remote_addr . "] [" . $request_uri . "] - " . $message . PHP_EOL;
            $result  = fputs($fd, $message);
            fclose($fd);

            if ($result > 0) {
                return array(status => true);
            } else {
                return array(status => false, message => 'Unable to write to ' . $logfile . '!');
            }

        } else {
            return array(status => false, message => 'Unable to open log ' . $logfile . '!');
        }
    }

}
