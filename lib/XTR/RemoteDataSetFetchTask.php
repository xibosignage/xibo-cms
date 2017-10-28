<?php
/*
 * Lukas Zurschmiede aka LukyLuke - https://github.com/LukyLuke
 * Copyright (C) 2018 Lukas Zurschmiede
 * (RemoteDataSetFetchTask.php)
 */


namespace Xibo\XTR;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\TaskRunException;
use Xibo\Entity\DataSetRemote;
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
            $processing = $this->buildDependantList($dataSets);
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
    
    /**
     * Builds a List of \Xibo\Entity\DataSetRemote which depends on each other. The resulting list has to be processed like returned.
     * @param array &$dataSets Reference to an Array which holds all not yet processed DataSets
     * @return array Ordered list of \Xibo\Entity\DataSetRemote to process
     */
    private function buildDependantList(array &$dataSets) {
        $processing = [ array_shift($dataSets) ];
        $last = 0;
        
        // Indicator to break the while loop if no matching dependant DataSet is found
        $found = true;

        // As long as the current processing DataSet depends on an other, get that one and process it before
        while ($found && $this->isDependantIsSet($processing[$last])) {
            foreach ($dataSets as $k => $dataSet) {
                $found = false;
                
                // If we found the dependant DataSet, add it to the Processing list and remove it from the original so we not process it multiple times
                if ($dataSet->dataSetId == $processing[$last]->runsAfter) {
                    $processing[] = $dataSet;
                    $last++;
                    $found = true;
                    unset($dataSets[$k]);
                    break;
                }
            }
        }

        // Process in reverse order (LastIn-FirstOut)
        return array_reverse($processing);
    }
    
    /**
     * Checks if there is a Dependant DataSet which has to be processed before the passed one
     * @param \Xibo\Entity\DataSetRemote $dataSet The DataSet to check if there is a dependant set
     * @return boolean
     */
    private function isDependantIsSet(DataSetRemote $dataSet) {
        return ($dataSet->runsAfter != $dataSet->dataSetId) && ($dataSet->runsAfter > -1);
    }
}