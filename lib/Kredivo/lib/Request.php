<?php

class Kredivo_Request
{

    public static function get($url)
    {
        return self::request($url, array(), false);
    }

    public static function post($url, $request)
    {
        return self::request($url, $request, true);
    }

    public static function request($url, $request = array(), $post = true)
    {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 13,
            CURLOPT_USERAGENT      => "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.124 Safari/537.36",
            // CURLOPT_HEADER         => true,
            // CURLINFO_HEADER_OUT    => true,
            CURLOPT_CUSTOMREQUEST  => $post ? 'POST' : 'GET',
            CURLOPT_POST           => $post,
            CURLOPT_POSTFIELDS     => json_encode($request),
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json; charset=UTF-8',
                // 'Content-Length: ' . strlen(json_encode($request)),
            ),
        ));

        $response = curl_exec($ch);

        if (curl_error($ch)) {
            throw new Exception('CURL Error: ' . curl_error($ch), curl_errno($ch));
        }

        if (empty($response)) {
            throw new Exception('Kredivo Error: Empty response.');
        }

        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($info['http_code'] == 200) {
            $response = json_decode($response);
            if (strtolower($response->status) != 'ok') {
                throw new Exception($response->message, $response->code);
            } else {
                return $response;
            }
        }

    }
}
