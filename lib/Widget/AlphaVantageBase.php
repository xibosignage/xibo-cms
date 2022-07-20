<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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


use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Stash\Invalidation;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\GeneralException;

/**
 * Class AlphaVantageBase
 *  base class for the AlphaVantage Stocks and Currencies Module
 * @package Xibo\Widget
 */
abstract class AlphaVantageBase extends ModuleWidget
{
    /**
     * @param $fromCurrency
     * @param $toCurrency
     * @return array
     * @throws ConfigurationException
     * @throws GeneralException
     */
    protected function getCurrencyExchangeRate($fromCurrency, $toCurrency)
    {
        try {
            $cache = $this->getPool()->getItem($this->makeCacheKey(md5($fromCurrency . $toCurrency)));
            $cache->setInvalidationMethod(Invalidation::SLEEP, 5000, 15);

            $data = $cache->get();

            if ($cache->isMiss()) {

                $this->getLog()->debug('getCurrencyExchangeRate is served from the API.');

                $cache->lock();

                // Use a web request
                $client = new Client();

                $request = $client->request('GET', 'https://www.alphavantage.co/query', $this->getConfig()->getGuzzleProxy([
                    'query' => [
                        'function' => 'CURRENCY_EXCHANGE_RATE',
                        'from_currency' => $fromCurrency,
                        'to_currency' => $toCurrency,
                        'apikey' => $this->getApiKey()
                    ]
                ]));

                $data = json_decode($request->getBody(), true);

                // Cache this and expire in the cache period
                $cache->set($data);
                $cache->expiresAt(Carbon::now()->addSeconds($this->getSetting('cachePeriod', 14400)));

                $this->getPool()->save($cache);
            } else {
                $this->getLog()->debug('getCurrencyExchangeRate is served from the cache.');
            }

            return $data;

        } catch (GuzzleException $guzzleException) {
            throw new GeneralException('Guzzle exception getting currency exchange rate. E = ' . $guzzleException->getMessage(), $guzzleException->getCode(), $guzzleException);
        }
    }
    /**
     * @param $symbol
     * @return array
     * @throws ConfigurationException
     * @throws GeneralException
     */
    protected function getStockQuote($symbol)
    {
        try {
            $cache = $this->getPool()->getItem($this->makeCacheKey(md5($symbol)));
            $cache->setInvalidationMethod(Invalidation::SLEEP, 5000, 15);

            $data = $cache->get();

            if ($cache->isMiss()) {

                $this->getLog()->debug('getStockQuote is served from the API.');

                $cache->lock();

                // Use a web request
                $client = new Client();

                $request = $client->request('GET', 'https://www.alphavantage.co/query', $this->getConfig()->getGuzzleProxy([
                    'query' => [
                        'function' => 'TIME_SERIES_DAILY',
                        'symbol' => $symbol,
                        'apikey' => $this->getApiKey()
                    ]
                ]));

                $data = json_decode($request->getBody(), true);

                // Cache this and expire in the cache period
                $cache->set($data);
                $cache->expiresAt(Carbon::now()->addSeconds($this->getSetting('cachePeriod', 14400)));

                $this->getPool()->save($cache);
            } else {
                $this->getLog()->debug('getStockQuote is served from the cache.');
            }

            return $data;
        } catch (GuzzleException $guzzleException) {
            throw new GeneralException('Guzzle exception getting currency exchange rate. E = ' . $guzzleException->getMessage(), $guzzleException->getCode(), $guzzleException);
        }
    }

    /**
     * Get the API Key
     * @return string
     * @throws ConfigurationException
     */
    protected function getApiKey()
    {
        $apiKey = $this->getSetting('apiKey', null);

        if ($apiKey == null)
            throw new ConfigurationException(__('Missing API Key'));

        return $apiKey;
    }

    /**
     * @param $fromCurrency
     * @param $toCurrency
     * @return mixed
     * @throws GeneralException
     */
    protected function getPriorDay($fromCurrency, $toCurrency)
    {
        $yesterday = Carbon::yesterday()->format('Y-m-d');

        try {
            $cache = $this->getPool()->getItem($this->makeCacheKey(md5($fromCurrency . $toCurrency . $yesterday)));
            $cache->setInvalidationMethod(Invalidation::SLEEP, 5000, 15);

            $data = $cache->get();

            if ($cache->isMiss()) {

                $this->getLog()->debug('getPriorDay is served from the API.');

                $cache->lock();

                // Use a web request
                $client = new Client();
                $request = $client->request('GET', 'https://www.alphavantage.co/query', $this->getConfig()->getGuzzleProxy([
                    'query' => [
                        'function' => 'FX_DAILY',
                        'from_symbol' => $fromCurrency,
                        'to_symbol' => $toCurrency,
                        'apikey' => $this->getApiKey()
                    ]
                ]));

                $data = json_decode($request->getBody(), true);

                // Cache this and expire tomorrow (results are valid for the entire day regardless of settings)
                $cache->set($data);
                $cache->expiresAt(Carbon::tomorrow());

                $this->getPool()->save($cache);
            } else {
                $this->getLog()->debug('getPriorDay is served from the cache.');
            }

            return $data;

        } catch (GuzzleException $guzzleException) {
            throw new GeneralException('Guzzle exception getting currency exchange rate. E = ' . $guzzleException->getMessage(), $guzzleException->getCode(), $guzzleException);
        }
    }
}