<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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


namespace Xibo\XTR;

use Carbon\Carbon;
use Xibo\Entity\DataSet;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Support\Exception\GeneralException;

/**
 * Class RemoteDataSetFetchTask
 * @package Xibo\XTR
 */
class RemoteDataSetFetchTask implements TaskInterface
{
    use TaskTrait;

    /** @var DataSetFactory */
    private $dataSetFactory;

    /** @var NotificationFactory */
    private $notificationFactory;

    /** @var UserFactory */
    private $userFactory;

    /** @var UserGroupFactory */
    private $userGroupFactory;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->dataSetFactory = $container->get('dataSetFactory');
        $this->notificationFactory = $container->get('notificationFactory');
        $this->userFactory = $container->get('userFactory');
        $this->userGroupFactory = $container->get('userGroupFactory');
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $this->runMessage = '# ' . __('Fetching Remote-DataSets') . PHP_EOL . PHP_EOL;

        $runTime = Carbon::now()->format('U');

        /** @var DataSet $dataSet */
        $dataSet = null;

        // Process all Remote DataSets (and their dependants)
        $dataSets = $this->orderDataSetsByDependency($this->dataSetFactory->query(null, ['isRemote' => 1]));

        // Log the order.
        $this->log->debug(
            'Order of processing: ' . json_encode(array_map(function ($element) {
                return $element->dataSetId . ' - ' . $element->runsAfter;
            }, $dataSets))
        );

        // Reorder this list according to which order we want to run in
        foreach ($dataSets as $dataSet) {
            $this->log->debug('Processing ' . $dataSet->dataSet . '. ID:' . $dataSet->dataSetId);
            $hardRowLimit = $this->config->getSetting('DATASET_HARD_ROW_LIMIT');
            $softRowLimit = $dataSet->rowLimit;
            $limitPolicy = $dataSet->limitPolicy;
            $currentNumberOfRows = intval($this->store->select('SELECT COUNT(*) AS total FROM `dataset_' . $dataSet->dataSetId . '`', [])[0]['total']);

            try {
                // Has this dataSet been accessed recently?
                if (!$dataSet->isActive()) {
                    // Skipping dataSet due to it not being accessed recently
                    $this->log->info('Skipping dataSet ' . $dataSet->dataSetId . ' due to it not being accessed recently');
                    continue;
                }

                // Get all columns
                $columns = $dataSet->getColumn();

                // Filter columns where dataSetColumnType is "Remote"
                $filteredColumns = array_filter($columns, function ($column) {
                    return $column->dataSetColumnTypeId == '3';
                });

                // Check if there are any remote columns defined in the dataset
                if (count($filteredColumns) === 0) {
                    $this->log->info('Skipping dataSet ' . $dataSet->dataSetId . ': No remote columns defined in the dataset.');
                    continue;
                }

                $this->log->debug('Comparing run time ' . $runTime . ' to next sync time ' . $dataSet->getNextSyncTime());

                if ($runTime >= $dataSet->getNextSyncTime()) {
                    // Getting the dependant DataSet to process the current DataSet on
                    $dependant = null;
                    if ($dataSet->runsAfter != null && $dataSet->runsAfter != $dataSet->dataSetId) {
                        $dependant = $this->dataSetFactory->getById($dataSet->runsAfter);
                    }

                    $this->log->debug('Fetch and process ' . $dataSet->dataSet);

                    $results = $this->dataSetFactory->callRemoteService($dataSet, $dependant);

                    if ($results->number > 0) {
                        // Truncate only if we also fetch new Data
                        if ($dataSet->isTruncateEnabled()
                            && $results->isEligibleToTruncate
                            && $runTime >= $dataSet->getNextClearTime()
                        ) {
                            $this->log->debug('Truncate ' . $dataSet->dataSet);
                            $dataSet->deleteData();

                            // Update the last clear time.
                            $dataSet->saveLastClear($runTime);
                        }

                        $rowsToAdd = $results->number;
                        $this->log->debug('Current number of rows in DataSet ID ' . $dataSet->dataSetId . ' is: ' . $currentNumberOfRows . ' number of records to add ' . $rowsToAdd);

                        // row limit reached
                        if ($currentNumberOfRows + $rowsToAdd >= $hardRowLimit || $softRowLimit != null && $currentNumberOfRows + $rowsToAdd >= $softRowLimit) {
                            // handle remote DataSets created before introduction of limit policy
                            if ($limitPolicy == null) {
                                $this->log->debug('No limit policy set, default to stop syncing.');
                                $limitPolicy = 'stop';
                            }

                            // which limit policy was set?
                            if ($limitPolicy === 'stop') {
                                $this->log->info('DataSet ID ' . $dataSet->dataSetId . ' reached the row limit, due to selected limit policy, it will stop syncing');
                                continue;
                            } elseif ($limitPolicy === 'fifo') {
                                // FiFo
                                $this->log->info('DataSet ID ' . $dataSet->dataSetId . ' reached the row limit, due to selected limit policy, oldest rows will be removed');

                                $this->store->update('DELETE FROM `dataset_' . $dataSet->dataSetId . '` ORDER BY id ASC LIMIT ' . $rowsToAdd, []);
                            } elseif ($limitPolicy === 'truncate') {
                                // truncate
                                $this->log->info('DataSet ID ' . $dataSet->dataSetId . ' reached the row limit, due to selected limit policy, we will truncate the DataSet data');
                                $dataSet->deleteData();

                                // Update the last clear time.
                                $dataSet->saveLastClear($runTime);
                            }
                        }

                        if ($dataSet->sourceId === 1) {
                            $this->dataSetFactory->processResults($dataSet, $results);
                        } else {
                            $this->dataSetFactory->processCsvEntries($dataSet, $results);
                        }

                        // notify here
                        $dataSet->notify();
                    } else if ($dataSet->truncateOnEmpty
                        && $results->isEligibleToTruncate
                        && $dataSet->isTruncateEnabled()
                        && $runTime >= $dataSet->getNextClearTime()
                    ) {
                        $this->log->debug('Truncate ' . $dataSet->dataSet);
                        $dataSet->deleteData();

                        // Update the last clear time.
                        $dataSet->saveLastClear($runTime);
                        $this->appendRunMessage(__('No results for %s, truncate with no new data enabled', $dataSet->dataSet));
                    } else {
                        $this->appendRunMessage(__('No results for %s', $dataSet->dataSet));
                    }

                    $dataSet->saveLastSync($runTime);
                } else {
                    $this->log->debug('Sync not required for ' . $dataSet->dataSetId);
                }
            } catch (GeneralException $e) {
                $this->appendRunMessage(__('Error syncing DataSet %s', $dataSet->dataSet));
                $this->log->error('Error syncing DataSet ' . $dataSet->dataSetId . '. E = ' . $e->getMessage());
                $this->log->debug($e->getTraceAsString());

                // Send a notification to the dataSet owner, informing them of the failure.
                $notification = $this->notificationFactory->createEmpty();
                $notification->subject = __('Remote DataSet %s failed to synchronise', $dataSet->dataSet);
                $notification->body = 'The error is: ' . $e->getMessage();
                $notification->createDt = Carbon::now()->format('U');
                $notification->releaseDt = $notification->createDt;
                $notification->isInterrupt = 0;
                $notification->userId = $this->user->userId;
                $notification->type = 'dataset';

                // Assign me
                $dataSetUser = $this->userFactory->getById($dataSet->userId);
                $notification->assignUserGroup($this->userGroupFactory->getById($dataSetUser->groupId));

                // Send
                $notification->save();

                // You might say at this point that if there are other data sets further down the list, we shouldn't
                // continue because they might depend directly on this one
                // however, it is my opinion that they should be processed anyway with the current cache of data.
                // hence continue
            }
        }

        $this->appendRunMessage(__('Done'));
    }

    /**
     * Order the list of DataSets to be processed so that it is dependent aware.
     *
     * @param DataSet[] $dataSets Reference to an Array which holds all not yet processed DataSets
     * @return DataSet[] Ordered list of DataSets to process
     *
     *
     * What is going on here: RemoteDataSets can depend on others, so we have to be sure to fetch
     * the data from the dependant first.
     * For Example (id, dependant): (1,4), (2,3), (3,4), (4,1), (5,2), (6,6)
     * Should be processed like: 4, 1, 3, 2, 5, 6
     *
     */
    private function orderDataSetsByDependency(array $dataSets)
    {
        // DataSets are in no particular order
        // sort them according to their dependencies
        usort($dataSets, function ($a, $b) {
            /** @var DataSet $a */
            /** @var DataSet $b */
            // if a doesn't have a dependent, then a must be lower in the list (move b up)
            if ($a->runsAfter === null) {
                return -1;
            }
            // if b doesn't have a dependent, then a must be higher in the list (move b down)
            if ($b->runsAfter === null) {
                return 1;
            }
            // either a or b have a dependent
            // if they are the same, keep them where they are
            if ($a->runsAfter === $b->runsAfter) {
                return 0;
            }
            // the dependents are different.
            // if a depends on b, then move b up
            if ($a->runsAfter === $b->dataSetId) {
                return -1;
            }
            // if b depends on a, then move b down
            if ($b->runsAfter === $a->dataSetId) {
                return 1;
            }
            // Unsorted
            return 0;
        });

        // Process in reverse order (LastIn-FirstOut)
        return array_reverse($dataSets);
    }
}
