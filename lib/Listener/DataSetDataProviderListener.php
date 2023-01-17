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

namespace Xibo\Listener;

use Carbon\Carbon;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\DataSet;
use Xibo\Event\DataSetDataRequestEvent;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Widget\Provider\DataProviderInterface;

/**
 * Listens to requests for data from DataSets.
 */
class DataSetDataProviderListener
{
    use ListenerLoggerTrait;

    /** @var \Xibo\Storage\StorageServiceInterface */
    private $store;

    /** @var \Xibo\Service\ConfigServiceInterface */
    private $config;

    /** @var \Xibo\Factory\DataSetFactory */
    private $dataSetFactory;

    /** @var \Xibo\Factory\DisplayFactory */
    private $displayFactory;

    public function __construct(
        StorageServiceInterface $store,
        ConfigServiceInterface $config,
        DataSetFactory $dataSetFactory,
        DisplayFactory $displayFactory
    ) {
        $this->store = $store;
        $this->config = $config;
        $this->dataSetFactory = $dataSetFactory;
        $this->displayFactory = $displayFactory;
    }

    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): DataSetDataProviderListener
    {
        $dispatcher->addListener(DataSetDataRequestEvent::$NAME, [$this, 'onDataRequest']);
        return $this;
    }

    public function onDataRequest(DataSetDataRequestEvent $event)
    {
        if ($event->getDataProvider()->getDataSource() === 'dataSet') {
            $dataProvider = $event->getDataProvider();

            // We must have a dataSetId configured.
            $dataSetId = $dataProvider->getProperty('dataSetId', 0);
            if (empty($dataSetId)) {
                $this->getLogger()->debug('onDataRequest: no dataSetId.');
                return;
            }

            // Get this dataset
            try {
                $dataSet = $this->dataSetFactory->getById($dataSetId);
            } catch (NotFoundException $notFoundException) {
                $this->getLogger()->error('onDataRequest: dataSetId ' . $dataSetId . ' not found.');
                return;
            }

            $this->getData($dataSet, $dataProvider);
        }
    }

    private function getData(DataSet $dataSet, DataProviderInterface $dataProvider): void
    {
        $dataSet->load();

        // Columns
        // Build a list of column mappings we will make available as metadata
        $mappings = [];
        $columnIds = $dataProvider->getProperty('columns');
        $columnIds = $columnIds !== null ? explode(',', $columnIds) : null;

        foreach ($dataSet->columns as $column) {
            if ($columnIds === null || in_array($column->dataSetColumnId, $columnIds)) {
                $mappings[] = [
                    'dataSetColumnId' => $column->dataSetColumnId,
                    'heading' => $column->heading,
                    'dataTypeId' => $column->dataTypeId
                ];
            }
        }

        // Build filter, order and limit parameters to pass to the DataSet entity
        // Ordering
        $ordering = '';
        if ($dataProvider->getProperty('useOrderingClause', 1) == 1) {
            $ordering = $dataProvider->getProperty('ordering');
        } else {
            // Build an order string
            foreach (json_decode($dataProvider->getProperty('orderClauses', '[]'), true) as $clause) {
                $ordering .= $clause['orderClause'] . ' ' . $clause['orderClauseDirection'] . ',';
            }

            $ordering = rtrim($ordering, ',');
        }

        // Build a filter to pass to the dataset
        $filter = [
            'filter' => $this->buildFilterClause($dataProvider),
            'order' => $ordering,
            'displayId' => $dataProvider->getDisplayId(),
        ];

        // limits?
        $upperLimit = $dataProvider->getProperty('upperLimit', 0);
        $lowerLimit = $dataProvider->getProperty('lowerLimit', 0);
        if ($lowerLimit !== 0 || $upperLimit !== 0) {
            // Start should be the lower limit
            // Size should be the distance between upper and lower
            $filter['start'] = $lowerLimit;
            $filter['size'] = $upperLimit - $lowerLimit;
        }

        // Expiry time for any images
        $expires = Carbon::now()
            ->addSeconds($dataProvider->getProperty('updateInterval', 3600) * 60)
            ->format('U');

        try {
            $this->setTimezone($dataProvider);

            $dataSetResults = $dataSet->getData($filter);

            foreach ($dataSetResults as $row) {
                // Add an item containing the columns we have selected
                $item = [];
                foreach ($mappings as $mapping) {
                    // This column is selected
                    $cellValue = $row[$mapping['heading']];
                    if ($mapping['dataTypeId'] === 4) {
                        // Grab the external image
                        $item[$mapping['heading']] = $dataProvider->addImage(
                            'dataset_' . md5($dataSet->dataSetId . $mapping['dataSetColumnId'] . $cellValue),
                            str_replace(' ', '%20', htmlspecialchars_decode($cellValue)),
                            $expires
                        );
                    } else if ($mapping['dataTypeId'] === 5) {
                        // Library Image
                        // The content is the ID of the image
                        $item[$mapping['heading']] = $dataProvider->addLibraryFile(intval($cellValue));
                    } else {
                        // Just a normal column
                        $item[$mapping['heading']] = $cellValue;
                    }
                }
                $dataProvider->addItem($item);
            }

            // Add the mapping we've generated to the metadata
            $dataProvider->addOrUpdateMeta('mapping', $mappings);
        } catch (\Exception $exception) {
            $this->getLogger()->error('onDataRequest: unable to get data for dataSetId ' . $dataSet->dataSetId
                . ' e: ' . $exception->getMessage());
        }
    }

    private function buildFilterClause(DataProviderInterface $dataProvider): ?string
    {
        $filter = '';

        if ($dataProvider->getProperty('useFilteringClause', 1) == 1) {
            $filter = $dataProvider->getProperty('filter');
        } else {
            // Build
            $i = 0;
            foreach (json_decode($dataProvider->getProperty('filterClauses', '[]'), true) as $clause) {
                $i++;

                switch ($clause['filterClauseCriteria']) {
                    case 'starts-with':
                        $criteria = 'LIKE \'' . $clause['filterClauseValue'] . '%\'';
                        break;

                    case 'ends-with':
                        $criteria = 'LIKE \'%' . $clause['filterClauseValue'] . '\'';
                        break;

                    case 'contains':
                        $criteria = 'LIKE \'%' . $clause['filterClauseValue'] . '%\'';
                        break;

                    case 'equals':
                        $criteria = '= \'' . $clause['filterClauseValue'] . '\'';
                        break;

                    case 'not-contains':
                        $criteria = 'NOT LIKE \'%' . $clause['filterClauseValue'] . '%\'';
                        break;

                    case 'not-starts-with':
                        $criteria = 'NOT LIKE \'' . $clause['filterClauseValue'] . '%\'';
                        break;

                    case 'not-ends-with':
                        $criteria = 'NOT LIKE \'%' . $clause['filterClauseValue'] . '\'';
                        break;

                    case 'not-equals':
                        $criteria = '<> \'' . $clause['filterClauseValue'] . '\'';
                        break;

                    case 'greater-than':
                        $criteria = '> \'' . $clause['filterClauseValue'] . '\'';
                        break;

                    case 'less-than':
                        $criteria = '< \'' . $clause['filterClauseValue'] . '\'';
                        break;

                    default:
                        continue 2;
                }

                if ($i > 1) {
                    $filter .= ' ' . $clause['filterClauseOperator'] . ' ';
                }

                $filter .= $clause['filterClause'] . ' ' . $criteria;
            }
        }

        return $filter;
    }

    /**
     * @param \Xibo\Widget\Provider\DataProviderInterface $dataProvider
     * @return void
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    private function setTimezone(DataProviderInterface $dataProvider)
    {
        // Set the timezone for SQL
        $dateNow = Carbon::now();
        if ($dataProvider->getDisplayId() != 0) {
            $display = $this->displayFactory->getById($dataProvider->getDisplayId());
            $timeZone = $display->getSetting('displayTimeZone', '');
            $timeZone = ($timeZone == '') ? $this->config->getSetting('defaultTimezone') : $timeZone;
            $dateNow->timezone($timeZone);
            $this->logger->debug(sprintf(
                'Display Timezone Resolved: %s. Time: %s.',
                $timeZone,
                $dateNow->toDateTimeString()
            ));
        }

        // Run this command on a new connection so that we do not interfere with any other queries on this connection.
        $this->store->setTimeZone($dateNow->format('P'), 'dataset');
    }
}
