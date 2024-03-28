<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

namespace Xibo\Connector;

use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use Stash\Invalidation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\WidgetDataRequestEvent;
use Xibo\Event\WidgetEditOptionRequestEvent;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;
use Xibo\Widget\Provider\DataProviderInterface;

/**
 * A connector to get data from the AlphaVantage API for use by the Currencies and Stocks Widgets
 */
class AlphaVantageConnector implements ConnectorInterface
{
    use ConnectorTrait;

    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): ConnectorInterface
    {
        $dispatcher->addListener(WidgetEditOptionRequestEvent::$NAME, [$this, 'onWidgetEditOption']);
        $dispatcher->addListener(WidgetDataRequestEvent::$NAME, [$this, 'onDataRequest']);
        return $this;
    }

    public function getSourceName(): string
    {
        return 'alphavantage';
    }

    public function getTitle(): string
    {
        return 'Alpha Vantage';
    }

    public function getDescription(): string
    {
        return 'Get Currencies and Stocks data';
    }

    public function getThumbnail(): string
    {
        return '';
    }

    public function getSettingsFormTwig(): string
    {
        return 'alphavantage-form-settings';
    }

    /**
     * @param SanitizerInterface $params
     * @param array $settings
     * @return array
     */
    public function processSettingsForm(SanitizerInterface $params, array $settings): array
    {
        if (!$this->isProviderSetting('apiKey')) {
            $settings['apiKey'] = $params->getString('apiKey');
            $settings['isPaidPlan'] = $params->getCheckbox('isPaidPlan');
            $settings['cachePeriod'] = $params->getInt('cachePeriod');
        }
        return $settings;
    }

    /**
     * If the requested dataSource is either Currencies or stocks, get the data, process it and add to dataProvider
     *
     * @param WidgetDataRequestEvent $event
     * @return void
     */
    public function onDataRequest(WidgetDataRequestEvent $event)
    {
        $dataProvider = $event->getDataProvider();
        if ($dataProvider->getDataSource() === 'currencies' || $dataProvider->getDataSource() === 'stocks') {
            if (empty($this->getSetting('apiKey'))) {
                $this->getLogger()->debug('onDataRequest: Alpha Vantage not configured.');
                return;
            }

            $event->stopPropagation();

            try {
                if ($dataProvider->getDataSource() === 'stocks') {
                    $this->getStockResults($dataProvider);
                } else if ($dataProvider->getDataSource() === 'currencies') {
                    $this->getCurrenciesResults($dataProvider);
                }

                // If we've got data, then set our cache period.
                $event->getDataProvider()->setCacheTtl($this->getSetting('cachePeriod', 3600));
            } catch (\Exception $exception) {
                $this->getLogger()->error('onDataRequest: Failed to get results. e = ' . $exception->getMessage());
                if ($exception instanceof InvalidArgumentException) {
                    $dataProvider->addError($exception->getMessage());
                } else {
                    $dataProvider->addError(__('Unable to contact the AlphaVantage API'));
                }
            }
        }
    }

    /**
     * If the Widget type is stocks, process it and update options
     *
     * @param WidgetEditOptionRequestEvent $event
     * @return void
     * @throws NotFoundException
     */
    public function onWidgetEditOption(WidgetEditOptionRequestEvent $event): void
    {
        $this->getLogger()->debug('onWidgetEditOption');

        // Pull the widget we're working with.
        $widget = $event->getWidget();
        if ($widget === null) {
            throw new NotFoundException();
        }

        // We handle the stocks widget and the property with id="items"
        if ($widget->type === 'stocks' && $event->getPropertyId() === 'items') {
            if (empty($this->getSetting('apiKey'))) {
                $this->getLogger()->debug('onWidgetEditOption: AlphaVantage API not configured.');
                return;
            }

            try {
                $results = [];
                $bestMatches = $this->getSearchResults($event->getPropertyValue() ?? '');
                $this->getLogger()->debug('onWidgetEditOption::getSearchResults => ' . var_export([
                    'bestMatches' => $bestMatches,
                ], true));

                if ($bestMatches === false) {
                    $results[] = [
                        'name'  => strtoupper($event->getPropertyValue()),
                        'type'  => strtoupper(trim($event->getPropertyValue())),
                        'id'    => $event->getPropertyId(),
                    ];
                } else if (count($bestMatches) > 0) {
                    foreach($bestMatches as $match) {
                        $results[] = [
                            'name'  => implode(' ', [$match['1. symbol'], $match['2. name']]),
                            'type'  => $match['1. symbol'],
                            'id'    => $event->getPropertyId(),
                        ];
                    }
                }

                $event->setOptions($results);
            } catch (\Exception $exception) {
                $this->getLogger()->error('onWidgetEditOption: Failed to get symbol search results. e = ' . $exception->getMessage());
            }
        }
    }

    /**
     * Get Stocks data through symbol search
     *
     * @param string $keywords
     * @return array|bool
     * @throws GeneralException
     */
    private function getSearchResults(string $keywords): array|bool
    {
        try {
            $this->getLogger()->debug('AlphaVantage Connector : getSearchResults is served from the API.');

            $request = $this->getClient()->request('GET', 'https://avg.signcdn.com/query', [
                'query' => [
                    'function' => 'SYMBOL_SEARCH',
                    'keywords' => $keywords,
                ]
            ]);

            $data = json_decode($request->getBody(), true);

            if (array_key_exists('bestMatches', $data)) {
                return $data['bestMatches'];
            }

            if (array_key_exists('Note', $data)) {
                return false;
            }

            return [];

        } catch (GuzzleException $guzzleException) {
            throw new GeneralException(
                'Guzzle exception getting Stocks data . E = '
                . $guzzleException->getMessage(),
                $guzzleException->getCode(),
                $guzzleException
            );
        }
    }

    /**
     * Get Stocks data, parse it to an array and add each item to the dataProvider
     *
     * @throws ConfigurationException
     * @throws InvalidArgumentException|GeneralException
     */
    private function getStockResults(DataProviderInterface $dataProvider): void
    {
        // Construct the YQL
        // process items
        $items = $dataProvider->getProperty('items');

        if ($items == '') {
            $this->getLogger()->error('Missing Items for Stocks Module with WidgetId ' . $dataProvider->getWidgetId());
            throw new InvalidArgumentException(__('Add some stock symbols'), 'items');
        }

        // Parse items out into an array
        $items = array_map('trim', explode(',', $items));

        foreach ($items as $symbol) {
            try {
                // Does this symbol have any additional data
                $parsedSymbol = explode('|', $symbol);

                $symbol = $parsedSymbol[0];
                $name = ($parsedSymbol[1] ?? $symbol);
                $currency = ($parsedSymbol[2] ?? '');

                $result = $this->getStockQuote($symbol, $this->getSetting('isPaidPlan'));

                $this->getLogger()->debug(
                    'AlphaVantage Connector : getStockResults data: ' .
                    var_export($result, true)
                );

                $item = [];

                foreach ($result['Time Series (Daily)'] as $series) {
                    $item = [
                        'Name' => $name,
                        'Symbol' => $symbol,
                        'time' => $result['Meta Data']['3. Last Refreshed'],
                        'LastTradePriceOnly' => round($series['4. close'], 4),
                        'RawLastTradePriceOnly' => $series['4. close'],
                        'YesterdayTradePriceOnly' => round($series['1. open'], 4),
                        'RawYesterdayTradePriceOnly' => $series['1. open'],
                        'TimeZone' => $result['Meta Data']['5. Time Zone'],
                        'Currency' => $currency
                    ];

                    $item['Change'] = round($item['RawLastTradePriceOnly'] - $item['RawYesterdayTradePriceOnly'], 4);
                    $item['SymbolTrimmed'] = explode('.', $item['Symbol'])[0];
                    $item = $this->decorateWithReplacements($item);
                    break;
                }

                // Parse the result and add it to our data array
                $dataProvider->addItem($item);
                $dataProvider->setIsHandled();
            } catch (InvalidArgumentException $invalidArgumentException) {
                $this->getLogger()->error('Invalid symbol ' . $symbol . ', e: ' . $invalidArgumentException->getMessage());
                throw new InvalidArgumentException(__('Invalid symbol ' . $symbol), 'items');
            }
        }
    }

    /**
     * Call Alpha Vantage API to get Stocks data, different endpoint depending on the paidPlan
     * cache results for cachePeriod defined in the Connector
     *
     * @param string $symbol
     * @param ?int $isPaidPlan
     * @return array
     * @throws GeneralException
     */
    protected function getStockQuote(string $symbol, ?int $isPaidPlan): array
    {
        try {
            $cache = $this->getPool()->getItem('/widget/stock/api_'.md5($symbol));
            $cache->setInvalidationMethod(Invalidation::SLEEP, 5000, 15);

            $data = $cache->get();

            if ($cache->isMiss()) {
                $this->getLogger()->debug('AlphaVantage Connector : getStockQuote is served from the API.');

                $request = $this->getClient()->request('GET', 'https://www.alphavantage.co/query', [
                    'query' => [
                        'function' => $isPaidPlan === 1 ? 'TIME_SERIES_DAILY_ADJUSTED' : 'TIME_SERIES_DAILY',
                        'symbol' => $symbol,
                        'apikey' => $this->getSetting('apiKey')
                    ]
                ]);

                $data = json_decode($request->getBody(), true);

                if (!array_key_exists('Time Series (Daily)', $data)) {
                    $this->getLogger()->debug('getStockQuote Data: ' . var_export($data, true));
                    throw new InvalidArgumentException(__('Stocks data invalid'), 'Time Series (Daily)');
                }

                // Cache this and expire in the cache period
                $cache->set($data);
                $cache->expiresAt(Carbon::now()->addSeconds($this->getSetting('cachePeriod', 14400)));

                $this->getPool()->save($cache);
            } else {
                $this->getLogger()->debug('AlphaVantage Connector : getStockQuote is served from the cache.');
            }

            return $data;
        } catch (GuzzleException $guzzleException) {
            throw new GeneralException(
                'Guzzle exception getting Stocks data . E = ' .
                $guzzleException->getMessage(),
                $guzzleException->getCode(),
                $guzzleException
            );
        }
    }

    /**
     * Replacements shared between Stocks and Currencies
     *
     * @param array $item
     * @return array
     */
    private function decorateWithReplacements(array $item): array
    {
        if (($item['Change'] == null || $item['LastTradePriceOnly'] == null)) {
            $item['ChangePercentage'] = '0';
        } else {
            // Calculate the percentage dividing the change by the ( previous value minus the change )
            $percentage = $item['Change'] / ( $item['LastTradePriceOnly'] - $item['Change'] );

            // Convert the value to percentage and round it
            $item['ChangePercentage'] = round($percentage*100, 2);
        }

        if (($item['Change'] != null && $item['LastTradePriceOnly'] != null)) {
            if ($item['Change'] > 0) {
                $item['ChangeIcon'] = 'up-arrow';
                $item['ChangeStyle'] = 'value-up';
            } else if ($item['Change'] < 0) {
                $item['ChangeIcon'] = 'down-arrow';
                $item['ChangeStyle'] = 'value-down';
            }
        } else {
            $item['ChangeStyle'] = 'value-equal';
            $item['ChangeIcon'] = 'right-arrow';
        }

        return $item;
    }

    /**
     * Get Currencies data from Alpha Vantage, parse it and add to dataProvider
     *
     * @param DataProviderInterface $dataProvider
     * @return void
     * @throws InvalidArgumentException
     */
    private function getCurrenciesResults(DataProviderInterface $dataProvider): void
    {
        // What items/base currencies are we interested in?
        $items = $dataProvider->getProperty('items');
        $base = $dataProvider->getProperty('base');

        if (empty($items) || empty($base)) {
            $this->getLogger()->error(
                'Missing Items for Currencies Module with WidgetId ' .
                $dataProvider->getWidgetId()
            );
            throw new InvalidArgumentException(
                __('Missing Items for Currencies Module. Please provide items in order to proceed.'),
                'items'
            );
        }

        // Does this require a reversed conversion?
        $reverseConversion = ($dataProvider->getProperty('reverseConversion', 0) == 1);

        // Is this paid plan?
        $isPaidPlan = ($this->getSetting('isPaidPlan', 0) == 1);

        // Parse items out into an array
        $items = array_map('trim', explode(',', $items));

        // Each item we want is a call to the results API
        try {
            foreach ($items as $currency) {
                // Remove the multiplier if there's one (this is handled when we substitute the results into
                // the template)
                $currency = explode('|', $currency)[0];

                // Do we need to reverse the from/to currency for this comparison?
                $result = $reverseConversion
                    ? $this->getCurrencyExchangeRate($currency, $base, $isPaidPlan)
                    : $this->getCurrencyExchangeRate($base, $currency, $isPaidPlan);

                $this->getLogger()->debug(
                    'AlphaVantage Connector : getCurrenciesResults are: ' .
                    var_export($result, true)
                );

                if ($isPaidPlan) {
                    $item = [
                        'time' => $result['Realtime Currency Exchange Rate']['6. Last Refreshed'],
                        'ToName' => $result['Realtime Currency Exchange Rate']['3. To_Currency Code'],
                        'FromName' => $result['Realtime Currency Exchange Rate']['1. From_Currency Code'],
                        'Bid' => round($result['Realtime Currency Exchange Rate']['5. Exchange Rate'], 4),
                        'Ask' => round($result['Realtime Currency Exchange Rate']['5. Exchange Rate'], 4),
                        'LastTradePriceOnly' => round($result['Realtime Currency Exchange Rate']['5. Exchange Rate'], 4),
                        'RawLastTradePriceOnly' => $result['Realtime Currency Exchange Rate']['5. Exchange Rate'],
                        'TimeZone' => $result['Realtime Currency Exchange Rate']['7. Time Zone'],
                    ];
                } else {
                    $item = [
                        'time' => $result['Meta Data']['5. Last Refreshed'],
                        'ToName' => $result['Meta Data']['3. To Symbol'],
                        'FromName' => $result['Meta Data']['2. From Symbol'],
                        'Bid' => round(array_values($result['Time Series FX (Daily)'])[0]['1. open'], 4),
                        'Ask' => round(array_values($result['Time Series FX (Daily)'])[0]['1. open'], 4),
                        'LastTradePriceOnly' => round(array_values($result['Time Series FX (Daily)'])[0]['1. open'], 4),
                        'RawLastTradePriceOnly' => array_values($result['Time Series FX (Daily)'])[0]['1. open'],
                        'TimeZone' => $result['Meta Data']['6. Time Zone'],
                    ];
                }

                // Set the name/currency to be the full name including the base currency
                $item['Name'] = $item['FromName'] . '/' . $item['ToName'];
                $currencyName = ($reverseConversion) ? $item['FromName'] : $item['ToName'];
                $item['NameShort'] = $currencyName;

                // work out the change when compared to the previous day

                // We need to get the prior day for this pair only (reversed)
                $priorDay = $reverseConversion
                    ? $this->getCurrencyPriorDay($currency, $base, $isPaidPlan)
                    : $this->getCurrencyPriorDay($base, $currency, $isPaidPlan);

                /*$this->getLog()->debug('Percentage change requested, prior day is '
                    . var_export($priorDay['Time Series FX (Daily)'], true));*/

                $priorDay = count($priorDay['Time Series FX (Daily)']) < 2
                    ? ['1. open' => 1]
                    : array_values($priorDay['Time Series FX (Daily)'])[1];

                $item['YesterdayTradePriceOnly'] = $priorDay['1. open'];
                $item['Change'] = $item['RawLastTradePriceOnly'] - $item['YesterdayTradePriceOnly'];


                $item = $this->decorateWithReplacements($item);

                $this->getLogger()->debug(
                    'AlphaVantage Connector : Parsed getCurrenciesResults are: ' .
                    var_export($item, true)
                );

                $dataProvider->addItem($item);
                $dataProvider->setIsHandled();
            }
        } catch (GeneralException $requestException) {
            $this->getLogger()->error('Problem getting currency information. E = ' . $requestException->getMessage());
            $this->getLogger()->debug($requestException->getTraceAsString());
            return;
        }
    }

    /**
     * Call Alpha Vantage API to get Currencies data, different endpoint depending on the paidPlan
     * cache results for cachePeriod defined on the Connector
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @param bool $isPaidPlan
     * @return mixed
     * @throws GeneralException
     * @throws InvalidArgumentException
     */
    private function getCurrencyExchangeRate(string $fromCurrency, string $toCurrency, bool $isPaidPlan)
    {
        try {
            $cache = $this->getPool()->getItem('/widget/currency/' . md5($fromCurrency . $toCurrency . $isPaidPlan));
            $cache->setInvalidationMethod(Invalidation::SLEEP, 5000, 15);

            $data = $cache->get();

            if ($cache->isMiss()) {
                $this->getLogger()->debug('AlphaVantage Connector : getCurrencyExchangeRate is served from the API.');
                // Use a different function depending on whether we have a paid plan or not.
                if ($isPaidPlan) {
                    $query = [
                        'function' => 'CURRENCY_EXCHANGE_RATE',
                        'from_currency' => $fromCurrency,
                        'to_currency' => $toCurrency,
                    ];
                } else {
                    $query = [
                        'function' => 'FX_DAILY',
                        'from_symbol' => $fromCurrency,
                        'to_symbol' => $toCurrency,
                    ];
                }
                $query['apikey'] = $this->getSetting('apiKey');

                $request = $this->getClient()->request('GET', 'https://www.alphavantage.co/query', [
                    'query' => $query
                ]);

                $data = json_decode($request->getBody(), true);

                if ($isPaidPlan) {
                    if (!array_key_exists('Realtime Currency Exchange Rate', $data)) {
                        $this->getLogger()->debug('Data: ' . var_export($data, true));
                        throw new InvalidArgumentException(
                            __('Currency data invalid'),
                            'Realtime Currency Exchange Rate'
                        );
                    }
                } else {
                    if (!array_key_exists('Meta Data', $data)) {
                        $this->getLogger()->debug('Data: ' . var_export($data, true));
                        throw new InvalidArgumentException(__('Currency data invalid'), 'Meta Data');
                    }

                    if (!array_key_exists('Time Series FX (Daily)', $data)) {
                        $this->getLogger()->debug('Data: ' . var_export($data, true));
                        throw new InvalidArgumentException(__('Currency data invalid'), 'Time Series FX (Daily)');
                    }
                }

                // Cache this and expire in the cache period
                $cache->set($data);
                $cache->expiresAt(Carbon::now()->addSeconds($this->getSetting('cachePeriod', 14400)));

                $this->getPool()->save($cache);
            } else {
                $this->getLogger()->debug('AlphaVantage Connector : getCurrencyExchangeRate is served from the cache.');
            }

            return $data;
        } catch (GuzzleException $guzzleException) {
            throw new GeneralException(
                'Guzzle exception getting currency exchange rate. E = ' .
                $guzzleException->getMessage(),
                $guzzleException->getCode(),
                $guzzleException
            );
        }
    }

    /**
     * Call Alpha Vantage API to get currencies data, cache results for a day
     *
     * @param $fromCurrency
     * @param $toCurrency
     * @param $isPaidPlan
     * @return mixed
     * @throws GeneralException
     * @throws InvalidArgumentException
     */
    private function getCurrencyPriorDay($fromCurrency, $toCurrency, $isPaidPlan)
    {
        if ($isPaidPlan) {
            $key = md5($fromCurrency . $toCurrency . Carbon::yesterday()->format('Y-m-d') . '1');
        } else {
            $key = md5($fromCurrency . $toCurrency . '0');
        }

        try {
            $cache = $this->getPool()->getItem('/widget/Currencies/' . $key);
            $cache->setInvalidationMethod(Invalidation::SLEEP, 5000, 15);

            $data = $cache->get();

            if ($cache->isMiss()) {
                $this->getLogger()->debug('AlphaVantage Connector : getPriorDay is served from the API.');

                // Use a web request
                $request = $this->getClient()->request('GET', 'https://www.alphavantage.co/query', [
                    'query' => [
                        'function' => 'FX_DAILY',
                        'from_symbol' => $fromCurrency,
                        'to_symbol' => $toCurrency,
                        'apikey' => $this->getSetting('apiKey')
                    ]
                ]);

                $data = json_decode($request->getBody(), true);

                if (!array_key_exists('Meta Data', $data)) {
                    $this->getLogger()->debug('Data: ' . var_export($data, true));
                    throw new InvalidArgumentException(__('Currency data invalid'), 'Meta Data');
                }

                if (!array_key_exists('Time Series FX (Daily)', $data)) {
                    $this->getLogger()->debug('Data: ' . var_export($data, true));
                    throw new InvalidArgumentException(__('Currency data invalid'), 'Time Series FX (Daily)');
                }

                // Cache this and expire tomorrow (results are valid for the entire day regardless of settings)
                $cache->set($data);
                $cache->expiresAt(Carbon::tomorrow());

                $this->getPool()->save($cache);
            } else {
                $this->getLogger()->debug('AlphaVantage Connector : getPriorDay is served from the cache.');
            }

            return $data;
        } catch (GuzzleException $guzzleException) {
            throw new GeneralException(
                'Guzzle exception getting currency exchange rate. E = ' .
                $guzzleException->getMessage(),
                $guzzleException->getCode(),
                $guzzleException
            );
        }
    }
}
