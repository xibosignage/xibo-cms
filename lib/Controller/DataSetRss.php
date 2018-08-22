<?php
/**
 * Copyright (C) 2018 Xibo Signage Ltd
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


namespace Xibo\Controller;


use Stash\Interfaces\PoolInterface;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DataSetRssFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

class DataSetRss extends Base
{
    /** @var DataSetRssFactory */
    private $dataSetRssFactory;

    /** @var  DataSetFactory */
    private $dataSetFactory;

    /** @var  DataSetColumnFactory */
    private $dataSetColumnFactory;

    /** @var PoolInterface */
    private $pool;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param DataSetRssFactory $dataSetRssFactory
     * @param DataSetFactory $dataSetFactory
     * @param DataSetColumnFactory $dataSetColumnFactory
     * @param PoolInterface $pool
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $dataSetRssFactory, $dataSetFactory, $dataSetColumnFactory, $pool)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->dataSetRssFactory = $dataSetRssFactory;
        $this->dataSetFactory = $dataSetFactory;
        $this->dataSetColumnFactory = $dataSetColumnFactory;
        $this->pool = $pool;
    }

    /**
     * Display Page
     * @param $dataSetId
     * @throws AccessDeniedException
     * @throws XiboException
     */
    public function displayPage($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $this->getState()->template = 'dataset-rss-page';
        $this->getState()->setData([
            'dataSet' => $dataSet
        ]);
    }

    /**
     * Search
     * @param $dataSetId
     *
     * @SWG\Get(
     *  path="/dataset/{dataSetId}/rss",
     *  operationId="dataSetRSSSearch",
     *  tags={"dataset"},
     *  summary="Search RSSs",
     *  description="Search RSSs for DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/DataSetFeed")
     *      )
     *  )
     * )
     *
     * @throws XiboException
     */
    public function grid($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $feeds = $this->dataSetRssFactory->query($this->gridRenderSort(), $this->gridRenderFilter([
            'dataSetId' => $dataSetId
        ]));

        foreach ($feeds as $feed) {

            if ($this->isApi())
                continue;

            $feed->includeProperty('buttons');

            // Edit
            $feed->buttons[] = array(
                'id' => 'datasetrss_button_edit',
                'url' => $this->urlFor('dataSet.rss.edit.form', ['id' => $dataSetId, 'rssId' => $feed->id]),
                'text' => __('Edit')
            );

            if ($this->getUser()->checkDeleteable($dataSet)) {
                // Delete
                $feed->buttons[] = array(
                    'id' => 'datasetrss_button_delete',
                    'url' => $this->urlFor('dataSet.rss.delete.form', ['id' => $dataSetId, 'rssId' => $feed->id]),
                    'text' => __('Delete')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($feeds);
    }

    /**
     * Add form
     * @param int $dataSetId
     *
     * @throws XiboException
     */
    public function addForm($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $this->getState()->template = 'dataset-rss-form-add';
        $this->getState()->setData([
            'dataSet' => $dataSet
        ]);
    }

    /**
     * Add
     * @param $dataSetId
     *
     * @SWG\Post(
     *  path="/dataset/{dataSetId}/column",
     *  operationId="dataSetColumnAdd",
     *  tags={"dataset"},
     *  summary="Add Column",
     *  description="Add a Column to a DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="heading",
     *      in="formData",
     *      description="The heading for the Column",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="listContent",
     *      in="formData",
     *      description="A comma separated list of content for drop downs",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="columnOrder",
     *      in="formData",
     *      description="The display order for this column",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataTypeId",
     *      in="formData",
     *      description="The data type ID for this column",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataSetColumnTypeId",
     *      in="formData",
     *      description="The column type for this column",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="formula",
     *      in="formData",
     *      description="MySQL SELECT syntax formula for this Column if the column type is formula",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="remoteField",
     *      in="formData",
     *      description="JSON-String to select Data from the Remote DataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="showFilter",
     *      in="formData",
     *      description="Flag indicating whether this column should present a filter on DataEntry",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="showSort",
     *      in="formData",
     *      description="Flag indicating whether this column should allow sorting on DataEntry",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DataSetColumn"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @throws XiboException
     */
    public function add($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        // Create a Column
        $column = $this->dataSetColumnFactory->createEmpty();
        $column->heading = $this->getSanitizer()->getString('heading');
        $column->listContent = $this->getSanitizer()->getString('listContent');
        $column->columnOrder = $this->getSanitizer()->getInt('columnOrder');
        $column->dataTypeId = $this->getSanitizer()->getInt('dataTypeId');
        $column->dataSetColumnTypeId = $this->getSanitizer()->getInt('dataSetColumnTypeId');
        $column->formula = $this->getSanitizer()->getParam('formula', null);
        $column->remoteField = $this->getSanitizer()->getParam('remoteField', null);
        $column->showFilter = $this->getSanitizer()->getCheckbox('showFilter');
        $column->showSort = $this->getSanitizer()->getCheckbox('showSort');

        if ($column->dataSetColumnTypeId == 3){
            $this->pool->deleteItem('/dataset/cache/' . $dataSet->dataSetId);
            $this->getLog()->debug('New remote column detected, clear cache for remote dataSet ID ' . $dataSet->dataSetId);
        }

        // Assign the column to set the column order if necessary
        $dataSet->assignColumn($column);

        // Save the column
        $column->save();

        // Notify the change
        $dataSet->notify();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $column->heading),
            'id' => $column->dataSetColumnId,
            'data' => $column
        ]);
    }

    /**
     * Edit Form
     * @param $dataSetId
     * @param $dataSetColumnId
     */
    public function editForm($dataSetId, $dataSetColumnId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $this->getState()->template = 'dataset-column-form-edit';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'dataSetColumn' => $this->dataSetColumnFactory->getById($dataSetColumnId),
            'dataTypes' => $this->dataTypeFactory->query(),
            'dataSetColumnTypes' => $this->dataSetColumnTypeFactory->query(),
            'help' => $this->getHelp()->link('DataSet', 'EditColumn')
        ]);
    }

    /**
     * Edit
     * @param $dataSetId
     * @param $dataSetColumnId
     *
     * @SWG\Put(
     *  path="/dataset/{dataSetId}/column/{dataSetColumnId}",
     *  operationId="dataSetColumnEdit",
     *  tags={"dataset"},
     *  summary="Edit Column",
     *  description="Edit a Column to a DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataSetColumnId",
     *      in="path",
     *      description="The Column ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="heading",
     *      in="formData",
     *      description="The heading for the Column",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="listContent",
     *      in="formData",
     *      description="A comma separated list of content for drop downs",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="columnOrder",
     *      in="formData",
     *      description="The display order for this column",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataTypeId",
     *      in="formData",
     *      description="The data type ID for this column",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataSetColumnTypeId",
     *      in="formData",
     *      description="The column type for this column",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="formula",
     *      in="formData",
     *      description="MySQL SELECT syntax formula for this Column if the column type is formula",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="remoteField",
     *      in="formData",
     *      description="JSON-String to select Data from the Remote DataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="showFilter",
     *      in="formData",
     *      description="Flag indicating whether this column should present a filter on DataEntry",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="showSort",
     *      in="formData",
     *      description="Flag indicating whether this column should allow sorting on DataEntry",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DataSetColumn"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @throws XiboException
     */
    public function edit($dataSetId, $dataSetColumnId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        // Column
        $column = $this->dataSetColumnFactory->getById($dataSetColumnId);
        $column->heading = $this->getSanitizer()->getString('heading');
        $column->listContent = $this->getSanitizer()->getString('listContent');
        $column->columnOrder = $this->getSanitizer()->getInt('columnOrder');
        $column->dataTypeId = $this->getSanitizer()->getInt('dataTypeId');
        $column->dataSetColumnTypeId = $this->getSanitizer()->getInt('dataSetColumnTypeId');
        $column->formula = $this->getSanitizer()->getParam('formula', null);
        $column->remoteField = $this->getSanitizer()->getParam('remoteField', null);
        $column->showFilter = $this->getSanitizer()->getCheckbox('showFilter');
        $column->showSort = $this->getSanitizer()->getCheckbox('showSort');
        $column->save();

        if ($column->dataSetColumnTypeId == 3 && $column->hasPropertyChanged('remoteField')){
            $this->pool->deleteItem('/dataset/cache/' . $dataSet->dataSetId);
            $this->getLog()->debug('Edited remoteField detected, clear cache for remote dataSet ID ' . $dataSet->dataSetId);
        }

        $dataSet->notify();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $column->heading),
            'id' => $column->dataSetColumnId,
            'data' => $column
        ]);
    }

    /**
     * Delete Form
     * @param $dataSetId
     * @param $dataSetColumnId
     */
    public function deleteForm($dataSetId, $dataSetColumnId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkDeleteable($dataSet))
            throw new AccessDeniedException();

        $this->getState()->template = 'dataset-column-form-delete';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'dataSetColumn' => $this->dataSetColumnFactory->getById($dataSetColumnId),
            'help' => $this->getHelp()->link('DataSet', 'DeleteColumn')
        ]);
    }

    /**
     * Delete
     * @param $dataSetId
     * @param $dataSetColumnId
     *
     * @SWG\Delete(
     *  path="/dataset/{dataSetId}/column/{dataSetColumnId}",
     *  operationId="dataSetColumnDelete",
     *  tags={"dataset"},
     *  summary="Delete Column",
     *  description="Delete DataSet Column",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataSetColumnId",
     *      in="path",
     *      description="The Column ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function delete($dataSetId, $dataSetColumnId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkDeleteable($dataSet))
            throw new AccessDeniedException();

        // Get the column
        $column = $this->dataSetColumnFactory->getById($dataSetColumnId);
        $column->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $column->heading)
        ]);
    }

    public function feed($psk)
    {
        $this->setNoOutput();

        // Try and get the feed using the PSK
        try {
            $feed = $this->dataSetRssFactory->getByPsk($psk);

            // Found, do things
            echo 'Feed' . $psk;

        } catch (NotFoundException $notFoundException) {
            $this->getState()->httpStatus = 404;
        }
    }
}