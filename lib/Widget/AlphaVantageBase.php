<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017 Spring Signage Ltd
 * (AlphaVantageBase.php)
 */


namespace Xibo\Widget;


use GuzzleHttp\Client;
use Jenssegers\Date\Date;
use Xibo\Exception\ConfigurationException;

abstract class AlphaVantageBase extends ModuleWidget
{
    /**
     * @param $fromCurrency
     * @param $toCurrency
     * @return array
     */
    protected function getCurrencyExchangeRate($fromCurrency, $toCurrency)
    {
        // Use a web request
        $client = new Client();

        $request = $client->request('GET', 'https://www.alphavantage.co/query', [
            'query' => [
                'function' => 'CURRENCY_EXCHANGE_RATE',
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'apikey' => $this->getApiKey()
            ]
        ]);

        return json_decode($request->getBody(), true);
    }
    /**
     * @param $symbol
     * @return array
     */
    protected function getStockQuote($symbol)
    {
        // Use a web request
        $client = new Client();

        $request = $client->request('GET', 'https://www.alphavantage.co/query', [
            'query' => [
                'function' => 'TIME_SERIES_DAILY',
                'symbol' => $symbol,
                'apikey' => $this->getApiKey()
            ]
        ]);

        return json_decode($request->getBody(), true);
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
     */
    protected function getPriorDay($base, $pairs)
    {
        // Use a web request
        $client = new Client();

        $yesterday = Date::yesterday()->format('Y-m-d');

        $request = $client->request('GET', 'https://api.fixer.io/' . $yesterday, [
            'query' => [
                'base' => $base,
                'symbols' => is_array($pairs) ? implode(',', $pairs) : $pairs
            ]
        ]);

        return json_decode($request->getBody(), true)['rates'];
    }
}