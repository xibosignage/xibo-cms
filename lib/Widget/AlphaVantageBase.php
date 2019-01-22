<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017 Spring Signage Ltd
 * (AlphaVantageBase.php)
 */


namespace Xibo\Widget;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Jenssegers\Date\Date;
use Stash\Invalidation;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\XiboException;

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
     * @throws XiboException
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
                $cache->expiresAt(Date::now()->addSeconds($this->getSetting('cachePeriod', 14400)));

                $this->getPool()->save($cache);
            } else {
                $this->getLog()->debug('getCurrencyExchangeRate is served from the cache.');
            }

            return $data;

        } catch (GuzzleException $guzzleException) {
            throw new XiboException('Guzzle exception getting currency exchange rate. E = ' . $guzzleException->getMessage(), $guzzleException->getCode(), $guzzleException);
        }
    }
    /**
     * @param $symbol
     * @return array
     * @throws ConfigurationException
     * @throws XiboException
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
                $cache->expiresAt(Date::now()->addSeconds($this->getSetting('cachePeriod', 14400)));

                $this->getPool()->save($cache);
            } else {
                $this->getLog()->debug('getStockQuote is served from the cache.');
            }

            return $data;
        } catch (GuzzleException $guzzleException) {
            throw new XiboException('Guzzle exception getting currency exchange rate. E = ' . $guzzleException->getMessage(), $guzzleException->getCode(), $guzzleException);
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
     * @param $base
     * @param $pairs
     * @return mixed
     * @throws XiboException
     */
    protected function getPriorDay($base, $pairs)
    {
        $yesterday = Date::yesterday()->format('Y-m-d');

        try {
            $cache = $this->getPool()->getItem($this->makeCacheKey(md5($base . $yesterday)));
            $cache->setInvalidationMethod(Invalidation::SLEEP, 5000, 15);

            $data = $cache->get();

            if ($cache->isMiss()) {

                $this->getLog()->debug('getPriorDay is served from the API.');

                $cache->lock();

                // Use a web request
                $client = new Client();


                $request = $client->request('GET', 'https://api.exchangeratesapi.io/' . $yesterday, $this->getConfig()->getGuzzleProxy([
                    'query' => [
                        'base' => $base
                    ]
                ]));

                $data = json_decode($request->getBody(), true)['rates'];

                // Cache this and expire tomorrow (results are valid for the entire day regardless of settings)
                $cache->set($data);
                $cache->expiresAt(Date::tomorrow());

                $this->getPool()->save($cache);
            } else {
                $this->getLog()->debug('getPriorDay is served from the cache.');
            }

            $return = [];

            foreach ($pairs as $pair) {
                $return[$pair] = isset($data[$pair]) ? $data[$pair] : null;
            }

            return $return;

        } catch (GuzzleException $guzzleException) {
            throw new XiboException('Guzzle exception getting currency exchange rate. E = ' . $guzzleException->getMessage(), $guzzleException->getCode(), $guzzleException);
        }
    }
}