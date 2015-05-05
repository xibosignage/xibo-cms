<?php

namespace Forecast;

class Forecast
{
    const API_ENDPOINT = 'https://api.forecast.io/forecast/';
    private $api_key;

    public function __construct($api_key)
    {
        $this->api_key = $api_key;
    }

    private function request($latitude, $longitude, $time = null, $options = array())
    {
        $request_url = self::API_ENDPOINT
            . '[APIKEY]'
            . '/'
            . $latitude
            . ','
            . $longitude
            . ((is_null($time)) ? '' : ','. $time);

        if (!empty($options)) {
            $request_url .= '?'. http_build_query($options);
        }

        \Debug::Audit('Calling API with: ' . $request_url);

        $request_url = str_replace('[APIKEY]', $this->api_key, $request_url);

        $httpOptions = array(
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Xibo Digital Signage',
            CURLOPT_HEADER => false,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $request_url
        );

        // Proxy support
        if (\Config::GetSetting('PROXY_HOST') != '' && !\Config::isProxyException($request_url)) {
            $httpOptions[CURLOPT_PROXY] = \Config::GetSetting('PROXY_HOST');
            $httpOptions[CURLOPT_PROXYPORT] = \Config::GetSetting('PROXY_PORT');

            if (\Config::GetSetting('PROXY_AUTH') != '')
            $httpOptions[CURLOPT_PROXYUSERPWD] = \Config::GetSetting('PROXY_AUTH');
        }

        $curl = curl_init();
        curl_setopt_array($curl, $httpOptions);
        $result = curl_exec($curl);

        // Get the response headers
        $outHeaders = curl_getinfo($curl);

        if ($outHeaders['http_code'] == 0) {
            // Unable to connect
            \Debug::Error('Unable to reach Forecast API. No Host Found (HTTP Code 0). Curl Error = ' . curl_error($curl));
            return false;
        }
        else if ($outHeaders['http_code'] != 200) {
            \Debug::Error('ForecastIO API returned ' . $outHeaders['http_code'] . ' status. Unable to proceed. Headers = ' . var_export($outHeaders, true));

            // See if we can parse the error.
            $body = json_decode($result);

            \Debug::Error('ForecastIO Error: ' . ((isset($body->errors[0])) ? $body->errors[0]->message : 'Unknown Error'));

            return false;
        }

        // Parse out header and body
        $body = json_decode($result);

        return $body;
    }

    public function get($latitude, $longitude, $time = null, $options = array())
    {
        return $this->request($latitude, $longitude, $time, $options);
    }
}