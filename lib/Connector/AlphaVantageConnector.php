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

namespace Xibo\Connector;

use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\WidgetDataRequestEvent;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\GeneralException;
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

    public function processSettingsForm(SanitizerInterface $params, array $settings): array
    {
        $settings['apiKey'] = $params->getString('apiKey');
        $settings['isPaidPlan'] = $params->getCheckbox('isPaidPlan');
        $settings['cachePeriod'] = $params->getInt('cachePeriod');
        return $settings;
    }

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
            }
        }
    }

    /**
     * @throws ConfigurationException
     * @throws GeneralException
     */
    private function getStockResults(DataProviderInterface $dataProvider)
    {
        // Construct the YQL
        // process items
        $items = $dataProvider->getProperty('items');

        if ($items == '') {
            $this->getLogger()->error('Missing Items for Stocks Module with WidgetId ' . $dataProvider->getWidgetId());
            return;
        }

        // Parse items out into an array
        $items = array_map('trim', explode(',', $items));

        foreach ($items as $symbol) {
            // Does this symbol have any additional data
            $parsedSymbol = explode('|', $symbol);

            $symbol = $parsedSymbol[0];
            $name = ($parsedSymbol[1] ?? $symbol);
            $currency = ($parsedSymbol[2] ?? '');

            $result = $this->getStockQuote($symbol, $this->getSetting('isPaidPlan'));

            $this->getLogger()->debug('getStockResults data: ' . var_export($result, true));

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
                $item = $this->decorateWithStocksReplacements($item);
                break;
            }
            
            // Parse the result and add it to our data array
            $dataProvider->addItem($item);
        }
    }

    /**
     * @param string $symbol
     * @param ?int $isPaidPlan
     * @return array
     * @throws GeneralException
     */
    protected function getStockQuote(string $symbol, ?int $isPaidPlan): array
    {
        try {
            $this->getLogger()->debug('getStockQuote is served from the API.');

            $request = $this->getClient()->request('GET', 'https://www.alphavantage.co/query', [
                'query' => [
                    'function' => $isPaidPlan === 1 ? 'TIME_SERIES_DAILY' : 'TIME_SERIES_DAILY_ADJUSTED',
                    'symbol' => $symbol,
                    'apikey' => $this->getSetting('apiKey')
                ]
            ]);

            return json_decode($request->getBody(), true);
        } catch (GuzzleException $guzzleException) {
            throw new GeneralException(
                'Guzzle exception getting Stocks data . E = ' .
                $guzzleException->getMessage(),
                $guzzleException->getCode(),
                $guzzleException
            );
        }
    }

    private function decorateWithStocksReplacements(array $item)
    {
        if (($item['Change'] == null || $item['LastTradePriceOnly'] == null)) {
            $item['ChangePercentage'] = '0';
        } else {
            // Calculate the percentage dividing the change by the ( previous value minus the change )
            $percentage = $item['Change'] / ( $item['LastTradePriceOnly'] - $item['Change'] );

            // Convert the value to percentage and round it
            $item['ChangePercentage'] = round($percentage*100, 2);
        }

        $item['SymbolTrimmed'] = explode('.', $item['Symbol'])[0];

        if (($item['Change'] != null && $item['LastTradePriceOnly'] != null)) {
            if ($item['Change'] > 0) {
                $item['ChangeIcon'] = 'up-arrow';
                $item['ChangeStyle'] = 'value-up';
            } else if ($item['Change'] < 0){
                $item['ChangeIcon'] = 'down-arrow';
                $item['ChangeStyle'] = 'value-down';
            }
        } else {
            $item['ChangeStyle'] = 'value-equal';
            $item['ChangeIcon'] = 'right-arrow';
        }

        return $item;
    }

    private function getCurrenciesResults(DataProviderInterface $dataProvider)
    {
        // TODO
        return;
    }
}
