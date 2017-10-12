<?php
/*
 * Lukas Zurschmiede aka LukyLuke - https://github.com/LukyLuke
 * Copyright (C) 2018 Lukas Zurschmiede
 * (RemoteDataSetFetchTask.php)
 */


namespace Xibo\XTR;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\TaskRunException;
use Xibo\Factory\DataSetFactory;

/**
 * Class RemoteDataSetFetchTask
 * @package Xibo\XTR
 */
class RemoteDataSetFetchTask implements TaskInterface
{
    use TaskTrait;

    /**
     * @inheritdoc
     *
     * What is going on here: RemoteDataSets can depend on others, so we have to be dure to fetch
     * the data from the dependant first.
     * For Example (id, dependant): (1,4), (2,3), (3,4), (4,1), (5,2), (6,6)
     * Should be processed like: 4, 1, 3, 2, 5, 6
     *
     * What this Algorithm dows is:
     * 1)   Take the first and remove it from the main list
     * 1.1) Add it to a queue
     * 2)   Search for the dependant
     * 2.1) Insert it after the previus one in the queue
     * 2.2) Remove it from the main list
     * 2.3) Repeat Step 2) as long as we find a Dependant in the main list
     * 3)   Fetch the Data from the Remote Datasets in reverse order (LastIn-FirstOut)
     * 4)   Repeat this Process as long as we have entries in the main list
     */
    public function run() {
        $this->runMessage = '# ' . __('Fetching Remote-DataSets') . PHP_EOL . PHP_EOL;

        $runTime = time();
        $factory = $this->app->container->get('dataSetFactory');
        $controller = $this->app->container->get('\Xibo\Controller\DataSetRemote');
        
        $dataSets = $factory->query();
        
        // As long as we have not-procesed IDs left
        while (count($dataSets) > 0) {
            $this->log->debug('Build Dependant-List for ' . $dataSet->dataSet);
            
            // List of Dependant Datasets to be processed in this loop
            $processing = [ array_shift($dataSets) ];
            $last = 0;
            $found = true;
            while ($found && ($processing[$last]->runsAfter != $processing[$last]->dataSetId)) {
                $found = false;
                foreach ($dataSets as $k => $dataSet) {
                    if ($dataSet->dataSetId == $processing[$last]->runsAfter) {
                        $last++;
                        $found = true;
                        $processing[] = $dataSet;
                        unset($dataSets[$k]);
                        break;
                    }
                }
            }
            
            // Process in reverse order (LastIn-FirstOut)
            $processing = array_reverse($processing);
            foreach($processing as $dataSet) {
                if ($runTime >= $dataSet->getNextSyncTime()) {
                    // Truncate only if we also fetch new Data
                    if ($runTime >= $dataSet->getNextClearTime()) {
                        $this->log->debug('Truncate ' . $dataSet->dataSet);
                        $dataSet->deleteData();
                    }
                    
                    // Getting the dependant DataSet to process the current DataSet on
                    $dependant = null;
                    if ($dataSet->runsAfter != $dataSet->dataSetId) {
                        $dependant = $factory->getById($dataSet->dataSetId);
                    }
                    
                    $this->log->debug('Fetch and process ' . $dataSet->dataSet);
                    $results = $factory->callRemoteService($dataSet, $dependant);
                    $controller->processResults($dataSet, $results);
                }
            }
        }
        
        $this->runMessage .= __('Done') . PHP_EOL . PHP_EOL;
    }
}