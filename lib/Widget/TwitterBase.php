<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (TwitterBase.php)
 */


namespace Xibo\Widget;

/**
 * Class TwitterBase
 * @package Xibo\Widget
 */
abstract class TwitterBase extends ModuleWidget
{
    /**
     * Get a auth token
     * @return bool|mixed
     */
    protected function getToken()
    {
        // Prepare the URL
        $url = 'https://api.twitter.com/oauth2/token';

        // Prepare the consumer key and secret
        $key = base64_encode(urlencode($this->getSetting('apiKey')) . ':' . urlencode($this->getSetting('apiSecret')));

        // Check to see if we have the bearer token already cached
        $cache = $this->getPool()->getItem('bearer_' . $key);

        $token = $cache->get();

        if ($cache->isHit()) {
            $this->getLog()->debug('Bearer Token served from cache');
            return $token;
        }

        $this->getLog()->debug('Bearer Token served from API');

        // Shame - we will need to get it.
        // and store it.
        $httpOptions = array(
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => array(
                'POST /oauth2/token HTTP/1.1',
                'Authorization: Basic ' . $key,
                'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
                'Content-Length: 29'
            ),
            CURLOPT_USERAGENT => 'Xibo Twitter Module',
            CURLOPT_HEADER => false,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(array('grant_type' => 'client_credentials')),
            CURLOPT_URL => $url,
        );

        // Proxy support
        if ($this->getConfig()->GetSetting('PROXY_HOST') != '' && !$this->getConfig()->isProxyException($url)) {
            $httpOptions[CURLOPT_PROXY] = $this->getConfig()->GetSetting('PROXY_HOST');
            $httpOptions[CURLOPT_PROXYPORT] = $this->getConfig()->GetSetting('PROXY_PORT');

            if ($this->getConfig()->GetSetting('PROXY_AUTH') != '')
                $httpOptions[CURLOPT_PROXYUSERPWD] = $this->getConfig()->GetSetting('PROXY_AUTH');
        }

        $curl = curl_init();

        // Set options
        curl_setopt_array($curl, $httpOptions);

        // Call exec
        if (!$result = curl_exec($curl)) {
            // Log the error
            $this->getLog()->error('Error contacting Twitter API: ' . curl_error($curl));
            return false;
        }

        // We want to check for a 200
        $outHeaders = curl_getinfo($curl);

        if ($outHeaders['http_code'] != 200) {
            $this->getLog()->error('Twitter API returned ' . $result . ' status. Unable to proceed. Headers = ' . var_export($outHeaders, true));

            // See if we can parse the error.
            $body = json_decode($result);

            $this->getLog()->error('Twitter Error: ' . ((isset($body->errors[0])) ? $body->errors[0]->message : 'Unknown Error'));

            return false;
        }

        // See if we can parse the body as JSON.
        $body = json_decode($result);

        // We have a 200 - therefore we want to think about caching the bearer token
        // First, lets check its a bearer token
        if ($body->token_type != 'bearer') {
            $this->getLog()->error('Twitter API returned OK, but without a bearer token. ' . var_export($body, true));
            return false;
        }

        // It is, so lets cache it
        // long times...
        $cache->set($body->access_token);
        $cache->expiresAfter(100000);
        $this->getPool()->saveDeferred($cache);

        return $body->access_token;
    }

    /**
     * Search the twitter API
     * @param $token
     * @param $term
     * @param string $resultType
     * @param string $geoCode
     * @param int $count
     * @return bool|mixed
     */
    protected function searchApi($token, $term, $resultType = 'mixed', $geoCode = '', $count = 15)
    {

        // Construct the URL to call
        $url = 'https://api.twitter.com/1.1/search/tweets.json';
        $queryString = '?q=' . urlencode(trim($term)) .
            '&result_type=' . $resultType .
            '&count=' . $count .
            '&include_entities=true' .
            '&tweet_mode=extended';

        if ($geoCode != '')
            $queryString .= '&geocode=' . $geoCode;

        $httpOptions = array(
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => array(
                'GET /1.1/search/tweets.json' . $queryString . 'HTTP/1.1',
                'Host: api.twitter.com',
                'Authorization: Bearer ' . $token
            ),
            CURLOPT_USERAGENT => 'Xibo Twitter Module',
            CURLOPT_HEADER => false,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $url . $queryString,
        );

        // Proxy support
        if ($this->getConfig()->GetSetting('PROXY_HOST') != '' && !$this->getConfig()->isProxyException($url)) {
            $httpOptions[CURLOPT_PROXY] = $this->getConfig()->GetSetting('PROXY_HOST');
            $httpOptions[CURLOPT_PROXYPORT] = $this->getConfig()->GetSetting('PROXY_PORT');

            if ($this->getConfig()->GetSetting('PROXY_AUTH') != '')
                $httpOptions[CURLOPT_PROXYUSERPWD] = $this->getConfig()->GetSetting('PROXY_AUTH');
        }

        $this->getLog()->debug('Calling API with: ' . $url . $queryString);

        $curl = curl_init();
        curl_setopt_array($curl, $httpOptions);
        $result = curl_exec($curl);

        // Get the response headers
        $outHeaders = curl_getinfo($curl);

        if ($outHeaders['http_code'] == 0) {
            // Unable to connect
            $this->getLog()->error('Unable to reach twitter api.');
            return false;
        } else if ($outHeaders['http_code'] != 200) {
            $this->getLog()->error('Twitter API returned ' . $outHeaders['http_code'] . ' status. Unable to proceed. Headers = ' . var_export($outHeaders, true));

            // See if we can parse the error.
            $body = json_decode($result);

            $this->getLog()->error('Twitter Error: ' . ((isset($body->errors[0])) ? $body->errors[0]->message : 'Unknown Error'));

            return false;
        }

        // Parse out header and body
        $body = json_decode($result);

        return $body;
    }
}