<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2011-2013 Daniel Garner
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
namespace Xibo\Controller;

use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Helper\DataSetUploadHandler;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class DataSetRemote
 * @package Xibo\Controller
 */
class DataSetRemote extends Base
{
    /** @var  DataSetFactory */
    private $dataSetFactory;

    /** @var  DataSetColumnFactory */
    private $dataSetColumnFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param DataSetFactory $dataSetFactory
     * @param DataSetColumnFactory $dataSetColumnFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $dataSetFactory, $dataSetColumnFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->dataSetFactory = $dataSetFactory;
        $this->dataSetColumnFactory = $dataSetColumnFactory;
    }

    /**
     * @return SanitizerServiceInterface
     */
    public function getSanitizer()
    {
        return parent::getSanitizer();
    }

    /**
     * @return DataSetFactory
     */
    public function getDataSetFactory()
    {
        return $this->dataSetFactory;
    }

    /**
     * DataSets for the AddRemote and EditRemote-Form to show them in a DropDown
     * @return array[DataSet]
     */
    public function dataSets()
    {
        return $this->dataSetFactory->query();
    }

    /**
     * Add Remote DataSet Form
     */
    public function addForm()
    {
        $this->getState()->template = 'dataset-form-add-remote';
        $this->getState()->setData([
            'help' => $this->getHelp()->link('DataSet', 'Add'),
            'dataSets' => $this->dataSetFactory->query()
        ]);
    }

    /**
     * Add Remote dataSet
     *
     * @SWG\Post(
     *  path="/dataset",
     *  operationId="dataSetAddRemote",
     *  tags={"dataset"},
     *  summary="Add Remote DataSet",
     *  description="Add a Remote DataSet",
     *  @SWG\Parameter(
     *      name="dataSet",
     *      in="formData",
     *      description="The DataSet Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="A description of this DataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="code",
     *      in="formData",
     *      description="A code for this DataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DataSet"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     */
    public function add()
    {
        $dataSet = $this->dataSetFactory->createEmptyRemote();
        $dataSet->dataSet = $this->getSanitizer()->getString('dataSet');
        $dataSet->description = $this->getSanitizer()->getString('description');
        $dataSet->code = $this->getSanitizer()->getString('code');
        $dataSet->userId = $this->getUser()->userId;
        $dataSet->method = $this->getSanitizer()->getString('method');
        $dataSet->uri = $this->getSanitizer()->getString('uri');
        $dataSet->postData = $this->getSanitizer()->getString('postData');
        $dataSet->authentication = $this->getSanitizer()->getString('authentication');
        $dataSet->username = $this->getSanitizer()->getString('username');
        $dataSet->password = $this->getSanitizer()->getString('password');
        $dataSet->refreshRate = $this->getSanitizer()->getInt('refreshRate');
        $dataSet->clearRate = $this->getSanitizer()->getInt('clearRate');
        $dataSet->runsAfter = $this->getSanitizer()->getInt('runsAfter');
        $dataSet->dataRoot = $this->getSanitizer()->getString('dataRoot');
        $dataSet->summarize = $this->getSanitizer()->getString('summarize');
        $dataSet->summarizeField = $this->getSanitizer()->getString('summarizeField');

        // Also add one column
        $dataSetColumn = $this->dataSetColumnFactory->createEmpty();
        $dataSetColumn->columnOrder = 1;
        $dataSetColumn->heading = 'Col1';
        $dataSetColumn->dataSetColumnTypeId = 1;
        $dataSetColumn->dataTypeId = 1;
        $dataSet->assignColumn($dataSetColumn);

        // Save
        $dataSet->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $dataSet->dataSet),
            'id' => $dataSet->dataSetId,
            'data' => $dataSet
        ]);
    }
    
    /**
     * Edit DataSet Form
     * @param int $dataSetId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function editForm($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        // Set the form
        $this->getState()->template = 'dataset-form-edit-remote';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'help' => $this->getHelp()->link('DataSet', 'Edit'),
            'dataSets' => $this->dataSetFactory->query()
        ]);
    }

    /**
     * Edit DataSet
     * @param int $dataSetId
     *
     * @SWG\Put(
     *  path="/dataset/{dataSetId}",
     *  operationId="dataSetEdit",
     *  tags={"dataset"},
     *  summary="Edit DataSet",
     *  description="Edit a DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataSet",
     *      in="formData",
     *      description="The DataSet Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="A description of this DataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="code",
     *      in="formData",
     *      description="A code for this DataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DataSet")
     *  )
     * )
     */
    public function edit($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $dataSet->dataSet = $this->getSanitizer()->getString('dataSet');
        $dataSet->description = $this->getSanitizer()->getString('description');
        $dataSet->code = $this->getSanitizer()->getString('code');
        $dataSet->userId = $this->getUser()->userId;
        $dataSet->method = $this->getSanitizer()->getString('method');
        $dataSet->uri = $this->getSanitizer()->getString('uri');
        $dataSet->postData = $this->getSanitizer()->getString('postData');
        $dataSet->authentication = $this->getSanitizer()->getString('authentication');
        $dataSet->username = $this->getSanitizer()->getString('username');
        $dataSet->password = $this->getSanitizer()->getString('password');
        $dataSet->refreshRate = $this->getSanitizer()->getInt('refreshRate');
        $dataSet->clearRate = $this->getSanitizer()->getInt('clearRate');
        $dataSet->runsAfter = $this->getSanitizer()->getInt('runsAfter');
        $dataSet->dataRoot = $this->getSanitizer()->getString('dataRoot');
        $dataSet->summarize = $this->getSanitizer()->getString('summarize');
        $dataSet->summarizeField = $this->getSanitizer()->getString('summarizeField');
        
        $dataSet->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $dataSet->dataSet),
            'id' => $dataSet->dataSetId,
            'data' => $dataSet
        ]);
    }

    /**
     * DataSet Delete
     * * @param int $dataSetId
     */
    public function deleteForm($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkDeleteable($dataSet))
            throw new AccessDeniedException();

        if ($dataSet->isLookup)
            throw new \InvalidArgumentException(__('Lookup Tables cannot be deleted'));

        // Set the form
        $this->getState()->template = 'dataset-form-delete-remote';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'help' => $this->getHelp()->link('DataSet', 'Delete')
        ]);
    }

    /**
     * DataSet Delete
     * @param int $dataSetId
     *
     * @SWG\Delete(
     *  path="/dataset/{dataSetId}",
     *  operationId="dataSetDelete",
     *  tags={"dataset"},
     *  summary="Delete DataSet",
     *  description="Delete a DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function delete($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkDeleteable($dataSet))
            throw new AccessDeniedException();
        $this->getLog()->debug('Delete data flag = ' . $this->getSanitizer()->getCheckbox('deleteData') . '. Params = ' . var_export($this->getApp()->request()->params(), true));
        // Is there existing data?
        if ($this->getSanitizer()->getCheckbox('deleteData') == 0 && $dataSet->hasData())
            throw new \InvalidArgumentException(__('There is data assigned to this data set, cannot delete.'));

        // Otherwise delete
        $dataSet->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $dataSet->dataSet)
        ]);
    }

    /**
     * Copy DataSet Form
     * @param int $dataSetId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function copyForm($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        // Set the form
        $this->getState()->template = 'dataset-form-copy';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'help' => $this->getHelp()->link('DataSet', 'Edit')
        ]);
    }

    /**
     * Copy DataSet
     * @param int $dataSetId
     *
     * @SWG\Post(
     *  path="/dataset/copy/{dataSetId}",
     *  operationId="dataSetCopy",
     *  tags={"dataset"},
     *  summary="Copy DataSet",
     *  description="Copy a DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataSet",
     *      in="formData",
     *      description="The DataSet Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="A description of this DataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="code",
     *      in="formData",
     *      description="A code for this DataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DataSet")
     *  )
     * )
     */
    public function copy($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        // Load for the Copy
        $dataSet->load();
        $oldName = $dataSet->dataSet;

        // Clone and reset parameters
        $dataSet = clone $dataSet;
        $dataSet->dataSet = $this->getSanitizer()->getString('dataSet');
        $dataSet->description = $this->getSanitizer()->getString('description');
        $dataSet->code = $this->getSanitizer()->getString('code');
        $dataSet->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Copied %s as %s'), $oldName, $dataSet->dataSet),
            'id' => $dataSet->dataSetId,
            'data' => $dataSet
        ]);
    }
    
    /**
     * Sends out a TestRequst and returns the Data as JSON to the Client so it can be shown in the Dialog
     */
    public function testRequest() {
        $dataSet = $this->dataSetFactory->createEmptyRemote();
        $dataSet->dataSet = $this->getSanitizer()->getString('dataSet');
        $dataSet->method = $this->getSanitizer()->getString('method');
        $dataSet->uri = $this->getSanitizer()->getString('uri');
        $dataSet->postData = $this->getSanitizer()->getString('postData');
        $dataSet->authentication = $this->getSanitizer()->getString('authentication');
        $dataSet->username = $this->getSanitizer()->getString('username');
        $dataSet->password = $this->getSanitizer()->getString('password');
        $dataSet->dataRoot = $this->getSanitizer()->getString('dataRoot');
        
        $data = $this->dataSetFactory->callRemoteService($dataSet);
        
        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Run Test-Request for %s on %s'), $dataSet->dataSet, $dataSet->getCurlParams()[CURLOPT_URL]),
            'id' => $dataSet->dataSetId,
            'data' => $data
        ]);
    }
    
    /**
     * Tries to process received Data against the configured DataSet with all Columns
     * 
     * @param \Xibo\Entity\DataSetRemote $dataSet The RemoteDataset to process
     * @param \stdClass $results A simple Object with one Property 'entries' which contains all results
     */
    public function processResults(\Xibo\Entity\DataSetRemote $dataSet, \stdClass $results) {
        if (property_exists('entries', $results) && is_array($results->entries)) {
            foreach ($result as $results->entries) {
                $this->process($dataSet, $result);
            }
        }
    }

    /**
     * Tries to process received Data against the configured DataSet with all Columns
     * 
     * @param \Xibo\Entity\DataSetRemote $dataSet The RemoteDataset to process
     * @param array The JSON received from the remote endpoint
     */
    private function process(\Xibo\Entity\DataSetRemote $dataSet, array $result) {
        // Remote Data has to have the configured DataRoot which has to be an Array
        if (empty($dataSet->dataRoot) || array_key_exists($dataSet->dataRoot, $result)) {
            $data = null;
            if (empty($dataSet->dataRoot)) {
                $data = $result;
            } else {
                $data = $result[$dataSet->dataRoot];
            }
            
            if (is_array($data)) {
                $columns = $this->dataSetColumnFactory->query(null, ['dataSetId' => $dataSet->dataSetId]);
                $entries = [];
                
                // First process each entry form the remote and try to map the values to the configured columns
                foreach($data as $k => $entry) {
                    if (is_array($entry) || is_object($entry)) {
                        $entries[] = $this->processEntry($dataSet, (array) $entry, $columns);
                    } else {
                        $message = sprintf(__('DataSet \'%s\' failed: DataRoot \'%s\' contains data which are not arrays and not objects.'), $dataSet->dataSet, $dataSet->dataRoot);
                        break;
                    }
                }

                // If there is a Consilidation-Function, use the Data against it
                $entries = $this->consolidateEntries($dataSet, $entries, $columns);
                
                // Finally add each entry as a new Row in the DataSet
                foreach ($entries as $entry) {
                    $dataSet->addRow($entry);
                }
                
            } else {
                $message = sprintf(__('DataSet \'%s\' missconfigured: DataRoot \'%s\' is not an Array.'), $dataSet->dataSet, $dataSet->dataRoot);
            }
        }
        
        // Return
        $this->getState()->hydrate([
            'message' => $message,
            'id' => $dataSet->dataSetId
        ]);
    }
    
    /**
     * Process a single Data-Entry form the remote system and map it to the configured Columns
     * 
     * @param \Xibo\Entity\DataSetRemote $dataSet The DataSet which is processed currently
     * @param array $entry The Data from the remte system
     * @param array $columns The configured Columns form the current DataSet
     * @return array The processed $entry as a List of Fields from $columns
     */
    private function processEntry(\Xibo\Entity\DataSetRemote $dataSet, array $entry, array $columns) {
        $result = [];

        foreach ($columns as $k => $column) {
            if (($column->remoteField != null) && ($column->remoteField != '')) {
                $dataTypeId = $column->dataTypeId;
                
                // The Field may be a Date, timestamp or a real field
                if ($column->remoteField == '{{DATE}}') {
                    $value = [0, date('Y-m-d')];
                    
                } else if ($column->remoteField == '{{TIMESTAMP}}') {
                    $value = [0, time()];
                    
                } else {
                    $chunks = explode('.', $column->remoteField);
                    $value = $this->getFieldValueFromEntry($chunks, $entry);
                }
                
                // Only add it to the result if we where able to process the field
                if (($value != null) && ($value[1] != null)) {
                    switch ($dataTypeId) {
                        case 2:
                            $result[$column->heading] = $this->getSanitizer()->double($value[1]);
                            break;
                        case 3:
                            $result[$column->heading] = $this->getDate()->getLocalDate(strtotime($value[1]));
                            break;
                        case 5:
                            $result[$column->heading] = $this->getSanitizer()->int($value[1]);
                            break;
                        default:
                            $result[$column->heading] = $this->getSanitizer()->string($value[1]);
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Returns the Value of the remote DataEntry based on the remoteField definition splitted into chunks
     *
     * This function is recursive, so be sure you remove the first value from chunks and pass it in again
     *
     * @param array List of Chunks which interprets the FieldNames in the actual DataEntry
     * @param array $entry Current DataEntry
     * @return Array of the last FieldName and the corresponding value
     */
    private function getFieldValueFromEntry(array $chunks, array $entry) {
        $value = null;
        $key = array_shift($chunks);

        if (array_key_exists($key, $entry)) {
            $value = $entry[$key];
        }
        
        if (($value != null) && (count($chunks) > 0)) {
            return $this->getFieldValueFromEntry($chunks, (array) $value);
        }
        
        return [ $key, $value ];
    }
    
    /**
     * Consolidates all Entries by the defined Function in the DataSet
     * 
     * This Method *sums* or *counts* all same entries and returns them.
     * If no consolidation function is configured, nothing is done here.
     * 
     * @param \Xibo\Entity\DataSetRemote $dataSet the current DataSet
     * @param array $entries All processed entries which may be consolidated
     * @param array $column The columns form this DataSet
     * @return \Slim\Helper\Set which contains all Entries to be added to the DataSet
     */
    private function consolidateEntries(\Xibo\Entity\DataSetRemote $dataSet, array $entries, array $columns) {
        if ((count($entries) > 0) && $dataSet->doConsolidate()) {
            $consolidated = new \Slim\Helper\Set();
            $field = $dataSet->getConsolidationField();
            
            // Get the Field-Heading based on the consolidation field
            foreach ($columns as $k => $column) {
                if ($column->remoteField == $dataSet->summarizeField) {
                    $field = $column->heading;
                    break;
                }
            }
            
            // Check each entry and consolidate the value form the defined field
            foreach ($entries as $entry) {
                if (array_key_exists($field, $entry)) {
                    $key = $field . '-' . $entry[$field];
                    $existing = $consolidated->get($key);
                    
                    // Create a new one if there is no currently consolidated field for this value
                    if ($existing == null) {
                        $existing = $entry;
                        $existing[$field] = 0;
                    }
                    
                    // Consolidate: Summarize, Count, Unknown
                    if ($dataSet->summarize == 'sum') {
                        $existing[$field] = $existing[$field] + $entry[$field];
                        
                    } else if ($dataSet->summarize == 'count') {
                        $existing[$field] = $existing[$field] + 1;
                        
                    } else {
                        // Unknown consolidation type :?
                        $existing[$field] = 0;
                    }
                    
                    $consolidated->set($key, $existing);
                }
            }
            
            return $consolidated;
        }
        return new \Slim\Helper\Set($entries);
    }
}
