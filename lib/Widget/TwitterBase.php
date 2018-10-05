<?php
/*
* Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2018 Spring Signage Ltd
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Xibo\Widget;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

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
        $cache = $this->getPool()->getItem($this->makeCacheKey('bearer_' . $key));

        $token = $cache->get();

        if ($cache->isHit()) {
            $this->getLog()->debug('Bearer Token served from cache');
            return $token;
        }

        // We can take up to 30 seconds to request a new token
        $cache->lock(30);

        $this->getLog()->debug('Bearer Token served from API');

        $client = new Client($this->getConfig()->getGuzzleProxy());

        try {
            $response = $client->request('POST', $url, [
                'form_params' => [
                    'grant_type' => 'client_credentials'
                ],
                'headers' => [
                    'Authorization' => 'Basic ' . $key
                ]
            ]);

            $result = json_decode($response->getBody()->getContents());

            if ($result->token_type !== 'bearer') {
                $this->getLog()->error('Twitter API returned OK, but without a bearer token. ' . var_export($result, true));
                return false;
            }

            // It is, so lets cache it
            // long times...
            $cache->set($result->access_token);
            $cache->expiresAfter(100000);
            $this->getPool()->saveDeferred($cache);

            return $result->access_token;

        } catch (RequestException $requestException) {
            $this->getLog()->error('Twitter API returned ' . $requestException->getMessage() . ' status. Unable to proceed.');

            return false;
        }
    }

    /**
     * Search the twitter API
     * @param $token
     * @param $term
     * @param $language
     * @param string $resultType
     * @param string $geoCode
     * @param int $count
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function searchApi($token, $term, $language = '', $resultType = 'mixed', $geoCode = '', $count = 15)
    {
        $client = new Client($this->getConfig()->getGuzzleProxy());

        $query = [
            'q' => trim($term),
            'result_type' => $resultType,
            'count' => $count,
            'include_entities' => true,
            'tweet_mode' => 'extended'
        ];

        if ($geoCode != '')
            $query['geocode'] = $geoCode;

        if ($language != '')
            $query['lang'] = $language;

        $this->getLog()->debug('Query is: ' . json_encode($query));

        try {
            $request = $client->request('GET', 'https://api.twitter.com/1.1/search/tweets.json', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token
                ],
                'query' => $query
            ]);

            return json_decode($request->getBody()->getContents());

        } catch (RequestException $requestException) {
            $this->getLog()->error('Unable to reach twitter api. ' . $requestException->getMessage());
            return false;
        }
    }
}