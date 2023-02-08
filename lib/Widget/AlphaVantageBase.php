<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
use Xibo\Support\Exception\InvalidArgumentException;

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
        $isPaidPlan = $this->getSetting('isPaidPlan', 0) == 1;
        try {
            $cache = $this->getPool()->getItem($this->makeCacheKey(md5($fromCurrency . $toCurrency . $isPaidPlan)));
            $cache->setInvalidationMethod(Invalidation::SLEEP, 5000, 15);

            $data = $cache->get();

            if ($cache->isMiss()) {
                $this->getLog()->debug('getCurrencyExchangeRate is served from the API.');

                $cache->lock();

                // Use a web request
                $client = new Client();

                // Use a different function depending on whether we have a paid plan or not.
                if ($isPaidPlan) {
                    $query = [
                        'function' => 'CURRENCY_EXCHANGE_RATE',
                        'from_currency' => $fromCurrency,
                        'to_currency' => $toCurrency,
                        'apikey' => $this->getApiKey()
                    ];
                } else {
                    $query = [
                        'function' => 'FX_DAILY',
                        'from_symbol' => $fromCurrency,
                        'to_symbol' => $toCurrency,
                    ];
                }
                $query['apikey'] = $this->getApiKey();

                $request = $client->request('GET', 'https://www.alphavantage.co/query', $this->getConfig()->getGuzzleProxy([
                    'query' => $query,
                ]));

                $data = json_decode($request->getBody(), true);

                if ($isPaidPlan) {
                    if (!array_key_exists('Realtime Currency Exchange Rate', $data)) {
                        $this->getLog()->debug('Data: ' . var_export($data, true));
                        throw new InvalidArgumentException(__('Currency data invalid'), 'Realtime Currency Exchange Rate');
                    }
                } else {
                    if (!array_key_exists('Meta Data', $data)) {
                        $this->getLog()->debug('Data: ' . var_export($data, true));
                        throw new InvalidArgumentException(__('Currency data invalid'), 'Meta Data');
                    }

                    if (!array_key_exists('Time Series FX (Daily)', $data)) {
                        $this->getLog()->debug('Data: ' . var_export($data, true));
                        throw new InvalidArgumentException(__('Currency data invalid'), 'Time Series FX (Daily)');
                    }
                }

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
        $isPaidPlan = $this->getSetting('isPaidPlan', 0) == 1;
        try {
            $cache = $this->getPool()->getItem($this->makeCacheKey(md5($symbol . $isPaidPlan)));
            $cache->setInvalidationMethod(Invalidation::SLEEP, 5000, 15);

            $data = $cache->get();

            if ($cache->isMiss()) {
                $this->getLog()->debug('getStockQuote is served from the API.');

                $cache->lock();

                // Use a web request
                $client = new Client();

                $request = $client->request('GET', 'https://www.alphavantage.co/query', $this->getConfig()->getGuzzleProxy([
                    'query' => [
                        'function' => $isPaidPlan ? 'TIME_SERIES_DAILY' : 'TIME_SERIES_DAILY_ADJUSTED',
                        'symbol' => $symbol,
                        'apikey' => $this->getApiKey()
                    ]
                ]));

                $data = json_decode($request->getBody(), true);

                if (!array_key_exists('Time Series (Daily)', $data)) {
                    $this->getLog()->debug('getStockQuote Data: ' . var_export($data, true));
                    throw new InvalidArgumentException(__('Stocks data invalid'), 'Time Series (Daily)');
                }

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

        if ($apiKey == null) {
            throw new ConfigurationException(__('Missing API Key'));
        }

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
        $isPaidPlan = $this->getSetting('isPaidPlan', 0) == 1;
        if ($isPaidPlan) {
            $key = md5($fromCurrency . $toCurrency . Carbon::yesterday()->format('Y-m-d') . '1');
        } else {
            $key = md5($fromCurrency . $toCurrency . '0');
        }

        try {
            $cache = $this->getPool()->getItem($this->makeCacheKey($key));
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

                if (!array_key_exists('Meta Data', $data)) {
                    $this->getLog()->debug('Data: ' . var_export($data, true));
                    throw new InvalidArgumentException(__('Currency data invalid'), 'Meta Data');
                }

                if (!array_key_exists('Time Series FX (Daily)', $data)) {
                    $this->getLog()->debug('Data: ' . var_export($data, true));
                    throw new InvalidArgumentException(__('Currency data invalid'), 'Time Series FX (Daily)');
                }

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
