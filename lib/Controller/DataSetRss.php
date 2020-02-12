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


use Carbon\Exceptions\InvalidDateException;
use Jenssegers\Date\Date;
use PicoFeed\Syndication\Rss20FeedBuilder;
use PicoFeed\Syndication\Rss20ItemBuilder;
use Stash\Interfaces\PoolInterface;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DataSetRssFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

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

    /** @var StorageServiceInterface */
    private $store;

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
     * @param StorageServiceInterface $store
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $dataSetRssFactory, $dataSetFactory, $dataSetColumnFactory, $pool, $store)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->dataSetRssFactory = $dataSetRssFactory;
        $this->dataSetFactory = $dataSetFactory;
        $this->dataSetColumnFactory = $dataSetColumnFactory;
        $this->pool = $pool;
        $this->store = $store;
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
     *          @SWG\Items(ref="#/definitions/DataSetRss")
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
            'dataSetId' => $dataSetId,
            'useRegexForName' => $this->getSanitizer()->getCheckbox('useRegexForName')
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

        $columns = $dataSet->getColumn();
        $dateColumns = [];

        foreach ($columns as $column) {
            if ($column->dataTypeId ===  3)
                $dateColumns[] = $column;
        }

        $this->getState()->template = 'dataset-rss-form-add';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'extra' => [
                'orderClauses' => [],
                'filterClauses' => [],
                'columns' => $columns,
                'dateColumns' => $dateColumns
            ]
        ]);
    }

    /**
     * Add
     * @param $dataSetId
     *
     * @SWG\Post(
     *  path="/dataset/{dataSetId}/rss",
     *  operationId="dataSetRssAdd",
     *  tags={"dataset"},
     *  summary="Add RSS",
     *  description="Add a RSS to a DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="title",
     *      in="formData",
     *      description="The title for the RSS",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="title",
     *      in="formData",
     *      description="The author for the RSS",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="summaryColumnId",
     *      in="formData",
     *      description="The columnId to be used as each item summary",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="contentColumnId",
     *      in="formData",
     *      description="The columnId to be used as each item content",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="publishedDateColumnId",
     *      in="formData",
     *      description="The columnId to be used as each item published date",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DataSetRss"),
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

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        if ($this->getSanitizer()->getString('title') == '') {
            throw new InvalidArgumentException(__('Please enter title'), 'title');
        }

        if ($this->getSanitizer()->getString('author') == '') {
            throw new InvalidArgumentException(__('Please enter author name'), 'author');
        }

        // Create RSS
        $feed = $this->dataSetRssFactory->createEmpty();
        $feed->dataSetId = $dataSetId;
        $feed->title = $this->getSanitizer()->getString('title');
        $feed->author = $this->getSanitizer()->getString('author');
        $feed->titleColumnId = $this->getSanitizer()->getInt('titleColumnId');
        $feed->summaryColumnId = $this->getSanitizer()->getInt('summaryColumnId');
        $feed->contentColumnId = $this->getSanitizer()->getInt('contentColumnId');
        $feed->publishedDateColumnId = $this->getSanitizer()->getInt('publishedDateColumnId');
        $this->handleFormFilterAndOrder($feed);

        // New feed needs a PSK
        $feed->setNewPsk();

        // Save
        $feed->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $feed->title),
            'id' => $feed->id,
            'data' => $feed
        ]);
    }

    /**
     * @param \Xibo\Entity\DataSetRss $feed
     */
    private function handleFormFilterAndOrder($feed)
    {
        // Order criteria
        $orderClauses = $this->getSanitizer()->getStringArray('orderClause');
        $orderClauseDirections = $this->getSanitizer()->getStringArray('orderClauseDirection');
        $orderClauseMapping = [];

        $i = -1;
        foreach ($orderClauses as $orderClause) {
            $i++;

            if ($orderClause == '')
                continue;

            // Map the stop code received to the stop ref (if there is one)
            $orderClauseMapping[] = [
                'orderClause' => $orderClause,
                'orderClauseDirection' => isset($orderClauseDirections[$i]) ? $orderClauseDirections[$i] : '',
            ];
        }

        $feed->sort = json_encode([
            'sort' => $this->getSanitizer()->getString('sort'),
            'useOrderingClause' => $this->getSanitizer()->getCheckbox('useOrderingClause'),
            'orderClauses' => $orderClauseMapping
        ]);

        // Filter criteria
        $filterClauses = $this->getSanitizer()->getStringArray('filterClause');
        $filterClauseOperator = $this->getSanitizer()->getStringArray('filterClauseOperator');
        $filterClauseCriteria = $this->getSanitizer()->getStringArray('filterClauseCriteria');
        $filterClauseValue = $this->getSanitizer()->getStringArray('filterClauseValue');
        $filterClauseMapping = [];

        $i = -1;
        foreach ($filterClauses as $filterClause) {
            $i++;

            if ($filterClause == '')
                continue;

            // Map the stop code received to the stop ref (if there is one)
            $filterClauseMapping[] = [
                'filterClause' => $filterClause,
                'filterClauseOperator' => isset($filterClauseOperator[$i]) ? $filterClauseOperator[$i] : '',
                'filterClauseCriteria' => isset($filterClauseCriteria[$i]) ? $filterClauseCriteria[$i] : '',
                'filterClauseValue' => isset($filterClauseValue[$i]) ? $filterClauseValue[$i] : '',
            ];
        }

        $feed->filter = json_encode([
            'filter' => $this->getSanitizer()->getString('filter'),
            'useFilteringClause' => $this->getSanitizer()->getCheckbox('useFilteringClause'),
            'filterClauses' => $filterClauseMapping
        ]);
    }

    /**
     * Edit Form
     * @param $dataSetId
     * @param $dataSetRssId
     *
     * @throws XiboException
     */
    public function editForm($dataSetId, $dataSetRssId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $feed = $this->dataSetRssFactory->getById($dataSetRssId);

        $columns = $dataSet->getColumn();
        $dateColumns = [];

        foreach ($columns as $column) {
            if ($column->dataTypeId ===  3)
                $dateColumns[] = $column;
        }

        $this->getState()->template = 'dataset-rss-form-edit';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'feed' => $feed,
            'extra' => array_merge($feed->getSort(), $feed->getFilter(), ['columns' => $columns, 'dateColumns' => $dateColumns])
        ]);
    }

    /**
     * Edit
     * @param $dataSetId
     * @param $rssId
     *
     * @SWG\Put(
     *  path="/dataset/{dataSetId}/rss/{rssId}",
     *  operationId="dataSetRssEdit",
     *  tags={"dataset"},
     *  summary="Edit Rss",
     *  description="Edit DataSet Rss Feed",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="rssId",
     *      in="path",
     *      description="The RSS ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="title",
     *      in="formData",
     *      description="The title for the RSS",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="title",
     *      in="formData",
     *      description="The author for the RSS",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="summaryColumnId",
     *      in="formData",
     *      description="The rssId to be used as each item summary",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="contentColumnId",
     *      in="formData",
     *      description="The columnId to be used as each item content",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="publishedDateColumnId",
     *      in="formData",
     *      description="The columnId to be used as each item published date",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="regeneratePsk",
     *      in="formData",
     *      description="Regenerate the PSK?",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws XiboException
     */
    public function edit($dataSetId, $rssId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        if ($this->getSanitizer()->getString('title') == '') {
            throw new InvalidArgumentException(__('Please enter title'), 'title');
        }

        if ($this->getSanitizer()->getString('author') == '') {
            throw new InvalidArgumentException(__('Please enter author name'), 'author');
        }

        $feed = $this->dataSetRssFactory->getById($rssId);
        $feed->title = $this->getSanitizer()->getString('title');
        $feed->author = $this->getSanitizer()->getString('author');
        $feed->titleColumnId = $this->getSanitizer()->getInt('titleColumnId');
        $feed->summaryColumnId = $this->getSanitizer()->getInt('summaryColumnId');
        $feed->contentColumnId = $this->getSanitizer()->getInt('contentColumnId');
        $feed->publishedDateColumnId = $this->getSanitizer()->getInt('publishedDateColumnId');
        $this->handleFormFilterAndOrder($feed);

        if ($this->getSanitizer()->getCheckbox('regeneratePsk')) {
            $feed->setNewPsk();
        }

        $feed->save();

        // Delete from the cache
        $this->pool->deleteItem('/dataset/rss/' . $feed->id);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $feed->title),
            'id' => $feed->id,
            'data' => $feed
        ]);
    }

    /**
     * Delete Form
     * @param $dataSetId
     * @param $rssId
     *
     * @throws XiboException
     */
    public function deleteForm($dataSetId, $rssId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkDeleteable($dataSet))
            throw new AccessDeniedException();

        $feed = $this->dataSetRssFactory->getById($rssId);

        $this->getState()->template = 'dataset-rss-form-delete';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'feed' => $feed
        ]);
    }

    /**
     * Delete
     * @param $dataSetId
     * @param $rssId
     *
     * @SWG\Delete(
     *  path="/dataset/{dataSetId}/rss/{rssId}",
     *  operationId="dataSetRSSDelete",
     *  tags={"dataset"},
     *  summary="Delete RSS",
     *  description="Delete DataSet RSS",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="rssId",
     *      in="path",
     *      description="The RSS ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws XiboException
     */
    public function delete($dataSetId, $rssId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkDeleteable($dataSet))
            throw new AccessDeniedException();

        $feed = $this->dataSetRssFactory->getById($rssId);
        $feed->delete();

        // Delete from the cache
        $this->pool->deleteItem('/dataset/rss/' . $feed->id);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $feed->title)
        ]);
    }

    /**
     * @param $psk
     */
    public function feed($psk)
    {
        $this->setNoOutput();

        $this->getLog()->debug('RSS Feed Request with PSK ' . $psk);

        // Try and get the feed using the PSK
        try {
            $feed = $this->dataSetRssFactory->getByPsk($psk);

            // Get the DataSet out
            $dataSet = $this->dataSetFactory->getById($feed->dataSetId);

            // What is the edit date of this data set
            $dataSetEditDate = ($dataSet->lastDataEdit == 0) ? $this->getDate()->parse()->subMonths(2) : $this->getDate()->parse($dataSet->lastDataEdit, 'U');

            // Do we have this feed in the cache?
            $cache = $this->pool->getItem('/dataset/rss/' . $feed->id);

            $output = $cache->get();

            if ($cache->isMiss() || $cache->getCreation() < $dataSetEditDate) {
                // We need to recache
                $this->getLog()->debug('Generating RSS feed and saving to cache. Created on ' . (($cache->getCreation() !== false) ? $cache->getCreation()->format('Y-m-d H:i:s') : 'never'));

                $output = $this->generateFeed($feed, $dataSetEditDate, $dataSet);

                $cache->set($output);
                $cache->expiresAfter(new \DateInterval('PT5M'));
                $this->pool->saveDeferred($cache);
            } else {
                $this->getLog()->debug('Serving from Cache');
            }

            $this->getApp()->response()->header('Content-Type', 'application/rss+xml');
            echo $output;

        } catch (NotFoundException $notFoundException) {
            $this->getState()->httpStatus = 404;
        }
    }

    /**
     * @param \Xibo\Entity\DataSetRss $feed
     * @param Date $dataSetEditDate
     * @param \Xibo\Entity\DataSet $dataSet
     * @return string
     * @throws XiboException
     */
    private function generateFeed($feed, $dataSetEditDate, $dataSet)
    {
        // Create the start of our feed, its description, etc.
        $builder = Rss20FeedBuilder::create()
            ->withTitle($feed->title)
            ->withAuthor($feed->author)
            ->withDate($dataSetEditDate);

        $sort = $feed->getSort();
        $filter = $feed->getFilter();

        // Get results, using the filter criteria
        // Ordering
        $ordering = '';

        if ($sort['useOrderingClause'] == 1) {
            $ordering = $sort['sort'];
        } else {
            // Build an order string
            foreach ($sort['orderClauses'] as $clause) {
                $ordering .= $clause['orderClause'] . ' ' . $clause['orderClauseDirection'] . ',';
            }

            $ordering = rtrim($ordering, ',');
        }

        // Filtering
        $filtering = '';

        if ($filter['useFilteringClause'] == 1) {
            $filtering = $filter['filter'];
        } else {
            // Build
            $i = 0;
            foreach ($filter['filterClauses'] as $clause) {
                $i++;
                $criteria = '';

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
                        // Continue out of the switch and the loop (this takes us back to our foreach)
                        continue 2;
                }

                if ($i > 1)
                    $filtering .= ' ' . $clause['filterClauseOperator'] . ' ';

                // Ability to filter by not-empty and empty
                if ($clause['filterClauseCriteria'] == 'is-empty') {
                    $filtering .= 'IFNULL(`' . $clause['filterClause'] . '`, \'\') = \'\'';
                } else if ($clause['filterClauseCriteria'] == 'is-not-empty') {
                    $filtering .= 'IFNULL(`' . $clause['filterClause'] . '`, \'\') <> \'\'';
                } else {
                    $filtering .= $clause['filterClause'] . ' ' . $criteria;
                }
            }
        }

        // Get an array representing the id->heading mappings
        $mappings = [];
        $columns = [];

        if ($feed->titleColumnId != 0)
            $columns[] = $feed->titleColumnId;

        if ($feed->summaryColumnId != 0)
            $columns[] = $feed->summaryColumnId;

        if ($feed->contentColumnId != 0)
            $columns[] = $feed->contentColumnId;

        if ($feed->publishedDateColumnId != 0)
            $columns[] = $feed->publishedDateColumnId;

        foreach ($columns as $dataSetColumnId) {
            // Get the column definition this represents
            $column = $dataSet->getColumn($dataSetColumnId);
            /* @var \Xibo\Entity\DataSetColumn $column */

            $mappings[$column->heading] = [
                'dataSetColumnId' => $dataSetColumnId,
                'heading' => $column->heading,
                'dataTypeId' => $column->dataTypeId
            ];
        }

        $filter = [
            'filter' => $filtering,
            'order' => $ordering
        ];

        // Set the timezone for SQL
        $dateNow = $this->getDate()->parse();

        $this->store->setTimeZone($this->getDate()->getLocalDate($dateNow, 'P'));

        // Get the data (complete table, filtered)
        $dataSetResults = $dataSet->getData($filter);

        foreach ($dataSetResults as $row) {
            $item = Rss20ItemBuilder::create($builder);

            $hasContent = false;
            $hasDate = false;

            // Go through the columns of each row
            foreach ($row as $key => $value) {
                // Is this one of the columns we're interested in?
                if (isset($mappings[$key])) {
                    // Yes it is - which one?
                    $hasContent = true;

                    if ($mappings[$key]['dataSetColumnId'] === $feed->titleColumnId) {
                        $item->withTitle($value);
                    } else if ($mappings[$key]['dataSetColumnId'] === $feed->summaryColumnId) {
                        $item->withSummary($value);
                    } else if ($mappings[$key]['dataSetColumnId'] === $feed->contentColumnId) {
                        $item->withContent($value);
                    } else if ($mappings[$key]['dataSetColumnId'] === $feed->publishedDateColumnId) {
                        try {
                            $date = $this->getDate()->parse($value, 'U');
                        } catch (InvalidDateException $dateException) {
                            $date = $dataSetEditDate;
                        }

                        if ($date !== null) {
                            $item->withPublishedDate($date);
                            $hasDate = true;
                        }
                    }
                }
            }

            if (!$hasDate)
                $item->withPublishedDate($dataSetEditDate);

            if ($hasContent)
                $builder->withItem($item);
        }

        // Found, do things
        return $builder->build();
    }
}