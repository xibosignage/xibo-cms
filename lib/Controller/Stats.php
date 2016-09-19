<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2016 Daniel Garner
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
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Stats
 * @package Xibo\Controller
 */
class Stats extends Base
{
    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /** @var  LayoutFactory */
    private $layoutFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param StorageServiceInterface $store
     * @param DisplayFactory $displayFactory
     * @param LayoutFactory $layoutFactory
     * @param MediaFactory $mediaFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $displayFactory, $layoutFactory, $mediaFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->store = $store;
        $this->displayFactory = $displayFactory;
        $this->layoutFactory = $layoutFactory;
        $this->mediaFactory = $mediaFactory;
    }

    /**
     * Stats page
     */
    function displayPage()
    {
        $data = [
            // List of Displays this user has permission for
            'displays' => $this->displayFactory->query(),
            // List of Media this user has permission for
            'media' => $this->mediaFactory->query(),
            // List of Layouts this user has permission for
            'layouts' => $this->layoutFactory->query(),
            'defaults' => [
                'fromDate' => $this->getDate()->getLocalDate(time() - (86400 * 35)),
                'fromDateOneDay' => $this->getDate()->getLocalDate(time() - 86400),
                'toDate' => $this->getDate()->getLocalDate()
            ]
        ];

        $this->getState()->template = 'statistics-page';
        $this->getState()->setData($data);
    }

    /**
     * @SWG\Definition(
     *  definition="StatisticsData",
     *  @SWG\Property(
     *      property="type",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="display",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="layout",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="media",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="numberPlays",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="duration",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="minStart",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="maxEnd",
     *      type="string"
     *  )
     * )
     *
     *
     * Shows the stats grid
     *
     * @SWG\Get(
     *  path="/stats",
     *  operationId="statsSearch",
     *  tags={"statistics"},
     *  @SWG\Parameter(
     *      name="fromDt",
     *      in="formData",
     *      description="The start date for the filter. Default = 24 hours ago",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="toDt",
     *      in="formData",
     *      description="The end date for the filter. Default = now.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="formData",
     *      description="An optional display Id to filter",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="layoutId",
     *      description="An optional array of layout Id to filter",
     *      in="formData",
     *      required=false,
     *      type="array",
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *   @SWG\Parameter(
     *      name="mediaId",
     *      description="An optional array of media Id to filter",
     *      in="formData",
     *      required=false,
     *      type="array",
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(
     *              ref="#/definitions/StatisticsData"
     *          )
     *      )
     *  )
     * )
     */
    public function grid()
    {
        $fromDt = $this->getSanitizer()->getDate('fromDt', $this->getSanitizer()->getDate('statsFromDt', $this->getDate()->parse()->addDay(-1)));
        $toDt = $this->getSanitizer()->getDate('toDt', $this->getSanitizer()->getDate('statsToDt', $this->getDate()->parse()));
        $displayId = $this->getSanitizer()->getInt('displayId');
        $layoutIds = $this->getSanitizer()->getIntArray('layoutId');
        $mediaIds = $this->getSanitizer()->getIntArray('mediaId');

        // What if the fromdt and todt are exactly the same?
        // in this case assume an entire day from midnight on the fromdt to midnight on the todt (i.e. add a day to the todt)
        if ($fromDt == $toDt) {
            $toDt->addDay(1);
        }

        $this->getLog()->debug('Converted Times received are: FromDt=' . $fromDt . '. ToDt=' . $toDt);

        // Get an array of display id this user has access to.
        $display_ids = array();

        foreach ($this->displayFactory->query() as $display) {
            $display_ids[] = $display->displayId;
        }

        if (count($display_ids) <= 0)
            trigger_error(__('No displays with View permissions'), E_USER_ERROR);

        // Media on Layouts Ran
        $select = '
          SELECT stat.type,
              display.Display,
              layout.Layout,
              IFNULL(`media`.name, IFNULL(`widgetoption`.value, `widget`.type)) AS Media,
              COUNT(StatID) AS NumberPlays,
              SUM(TIME_TO_SEC(TIMEDIFF(end, start))) AS Duration,
              MIN(start) AS MinStart,
              MAX(end) AS MaxEnd,
              layout.layoutId,
              stat.mediaId,
              stat.widgetId
        ';

        $body = '
            FROM stat
              INNER JOIN display
              ON stat.DisplayID = display.DisplayID
              INNER JOIN layout
              ON layout.LayoutID = stat.LayoutID
              LEFT OUTER JOIN `widget`
              ON `widget`.widgetId = stat.widgetId
              LEFT OUTER JOIN `widgetoption`
              ON `widgetoption`.widgetId = `widget`.widgetId
                AND `widgetoption`.type = \'attrib\'
                AND `widgetoption`.option = \'name\'
              LEFT OUTER JOIN `media`
              ON `media`.mediaId = `stat`.mediaId
           WHERE stat.type <> \'displaydown\'
                AND stat.end > :fromDt
                AND stat.start <= :toDt
                AND stat.displayID IN (' . implode(',', $display_ids) . ')
        ';

        $params = [
            'fromDt' => $this->getDate()->getLocalDate($fromDt),
            'toDt' => $this->getDate()->getLocalDate($toDt)
        ];

        // Layout Filter
        if (count($layoutIds) != 0) {

            $layoutSql = '';
            $i = 0;
            foreach ($layoutIds as $layoutId) {
                $i++;
                $layoutSql .= ':layoutId_' . $i . ',';
                $params['layoutId_' . $i] = $layoutId;
            }

            $body .= '  AND `stat`.layoutId IN (' . trim($layoutSql, ',') . ')';
        }

        // Media Filter
        if (count($mediaIds) != 0) {

            $mediaSql = '';
            $i = 0;
            foreach ($mediaIds as $mediaId) {
                $i++;
                $mediaSql .= ':mediaId_' . $i . ',';
                $params['mediaId_' . $i] = $mediaId;
            }

            $body .= ' AND `media`.mediaId IN (' . trim($mediaSql, ',') . ')';
        }

        if ($displayId != 0) {
            $body .= '  AND stat.displayID = :displayId ';
            $params['displayId'] = $displayId;
        }

        $body .= 'GROUP BY stat.type, display.Display, layout.Layout, layout.layoutId, stat.mediaId, IFNULL(`media`.name, IFNULL(`widgetoption`.value, `widget`.type)) ';

        // Sorting?
        $filterBy = $this->gridRenderFilter();
        $sortOrder = $this->gridRenderSort();

        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $order . $limit;
        $rows = array();

        foreach ($this->store->select($sql, $params) as $row) {
            $entry = [];

            $widgetId = $this->getSanitizer()->int($row['widgetId']);
            $widgetName = $this->getSanitizer()->string($row['Media']);
            // If the media name is empty, and the widgetid is not, then we can assume it has been deleted.
            $widgetName = ($widgetName == '' &&  $widgetId != 0) ? __('Deleted from Layout') : $widgetName;

            $entry['type'] = $this->getSanitizer()->string($row['type']);
            $entry['display'] = $this->getSanitizer()->string($row['Display']);
            $entry['layout'] = $this->getSanitizer()->string($row['Layout']);
            $entry['media'] = $widgetName;
            $entry['numberPlays'] = $this->getSanitizer()->int($row['NumberPlays']);
            $entry['duration'] = $this->getSanitizer()->int($row['Duration']);
            $entry['minStart'] = $this->getDate()->getLocalDate($this->getDate()->parse($row['MinStart']));
            $entry['maxEnd'] = $this->getDate()->getLocalDate($this->getDate()->parse($row['MaxEnd']));
            $entry['layoutId'] = $row['layoutId'];
            $entry['widgetId'] = $row['widgetId'];

            $rows[] = $entry;
        }

        // Paging
        if ($limit != '' && count($rows) > 0) {
            $results = $this->store->select('
              SELECT COUNT(*) AS total FROM (SELECT stat.type, display.Display, layout.Layout, IFNULL(`media`.name, IFNULL(`widgetoption`.value, `widget`.type)) ' . $body . ') total
            ', $params);
            $this->getState()->recordsTotal = intval($results[0]['total']);
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($rows);
    }

    public function availabilityData()
    {
        $fromDt = $this->getSanitizer()->getDate('fromDt', $this->getSanitizer()->getDate('availabilityFromDt'));
        $toDt = $this->getSanitizer()->getDate('toDt', $this->getSanitizer()->getDate('availabilityToDt'));
        $displayId = $this->getSanitizer()->getInt('displayId');

        // Get an array of display id this user has access to.
        $displayIds = array();

        foreach ($this->displayFactory->query() as $display) {
            $displayIds[] = $display->displayId;
        }

        if (count($displayIds) <= 0)
            trigger_error(__('No displays with View permissions'), E_USER_ERROR);

        // Get some data for a bandwidth chart
        $params = array(
            'start' => $fromDt->format('U'),
            'end' => $toDt->format('U')
        );

        $SQL = '
            SELECT display.display,
                SUM(LEAST(end, :end) - GREATEST(start, :start)) AS duration
              FROM `displayevent`
                INNER JOIN `display`
                ON display.displayId = `displayevent`.displayId
             WHERE start <= :end
                AND end >= :start
                AND display.displayId IN (' . implode(',', $displayIds) . ') ';

        if ($displayId != 0) {
            $SQL .= ' AND display.displayId = :displayId ';
            $params['displayId'] = $displayId;
        }

        $SQL .= '
            GROUP BY display.display
        ';

        $rows = $this->store->select($SQL, $params);

        $output = array();
        $maxDuration = 0;

        foreach ($rows as $row) {
            $maxDuration = $maxDuration + $this->getSanitizer()->double($row['duration']);
        }

        if ($maxDuration > 86400) {
            $postUnits = __('Days');
            $divisor = 86400;
        }
        else if ($maxDuration > 3600) {
            $postUnits = __('Hours');
            $divisor = 3600;
        }
        else {
            $postUnits = __('Minutes');
            $divisor = 60;
        }

        foreach ($rows as $row) {
            $output[] = array(
                'label' => $this->getSanitizer()->string($row['display']),
                'value' => $this->getSanitizer()->double($row['duration']) / $divisor
            );
        }

        $this->getState()->extra = [
            'data' => $output,
            'postUnits' => $postUnits
        ];
    }

    /**
     * Bandwidth Data
     */
    public function bandwidthData()
    {
        $fromDt = $this->getSanitizer()->getDate('fromDt', $this->getSanitizer()->getDate('bandwidthFromDt'));
        $toDt = $this->getSanitizer()->getDate('toDt', $this->getSanitizer()->getDate('bandwidthToDt'));

        // Get an array of display id this user has access to.
        $displayIds = array();

        foreach ($this->displayFactory->query() as $display) {
            $displayIds[] = $display->displayId;
        }

        if (count($displayIds) <= 0)
            trigger_error(__('No displays with View permissions'), E_USER_ERROR);

        // Get some data for a bandwidth chart
        $dbh = $this->store->getConnection();

        $displayId = $this->getSanitizer()->getInt('displayId');
        $params = array(
            'month' => $this->getDate()->getLocalDate($fromDt->setDateTime($fromDt->year, $fromDt->month, 1, 0, 0), 'U'),
            'month2' => $this->getDate()->getLocalDate($toDt->addMonth(1)->setDateTime($toDt->year, $toDt->month, 1, 0, 0), 'U')
        );

        $SQL = 'SELECT display.display, IFNULL(SUM(Size), 0) AS size ';

        if ($displayId != 0)
            $SQL .= ', bandwidthtype.name AS type ';

        $SQL .= ' FROM `bandwidth`
                INNER JOIN `display`
                ON display.displayid = bandwidth.displayid';

        if ($displayId != 0)
            $SQL .= '
                    INNER JOIN bandwidthtype
                    ON bandwidthtype.bandwidthtypeid = bandwidth.type
                ';

        $SQL .= '  WHERE month > :month
                AND month < :month2
                AND display.displayId IN (' . implode(',', $displayIds) . ') ';

        if ($displayId != 0) {
            $SQL .= ' AND display.displayid = :displayid ';
            $params['displayid'] = $displayId;
        }

        $SQL .= 'GROUP BY display.display ';

        if ($displayId != 0)
            $SQL .= ' , bandwidthtype.name ';

        $SQL .= 'ORDER BY display.display';



        $sth = $dbh->prepare($SQL);

        $sth->execute($params);

        // Get the results
        $results = $sth->fetchAll();

        $maxSize = 0;
        foreach ($results as $library) {
            $maxSize = ($library['size'] > $maxSize) ? $library['size'] : $maxSize;
        }

        // Decide what our units are going to be, based on the size
        $base = floor(log($maxSize) / log(1024));

        $output = array();

        foreach ($results as $row) {

            // label depends whether we are filtered by display
            if ($displayId != 0) {
                $label = $row['type'];
            } else {
                $label = $row['display'];
            }

            $output[] = array(
                'label' => $label,
                'value' => round((double)$row['size'] / (pow(1024, $base)), 2)
            );
        }

        // Set up some suffixes
        $suffixes = array('bytes', 'k', 'M', 'G', 'T');

        $this->getState()->extra = [
            'data' => $output,
            'postUnits' => (isset($suffixes[$base]) ? $suffixes[$base] : '')
        ];
    }

    /**
     * Output CSV Form
     */
    public function exportForm()
    {
        $this->getState()->template = 'statistics-form-export';
        $this->getState()->setData([
            'displays' => $this->displayFactory->query()
        ]);
    }

    /**
     * Outputs a CSV of stats
     */
    public function export()
    {
        // We are expecting some parameters
        $fromDt = $this->getSanitizer()->getDate('fromDt');
        $toDt = $this->getSanitizer()->getDate('toDt');
        $displayId = $this->getSanitizer()->getInt('displayId');

        // Get an array of display id this user has access to.
        $displayIds = array();

        foreach ($this->displayFactory->query() as $display) {
            $displayIds[] = $display->displayId;
        }

        if (count($displayIds) <= 0)
            throw new AccessDeniedException();

        $sql = '
        SELECT stat.*, display.Display, layout.Layout, media.Name AS MediaName
          FROM stat
            INNER JOIN display
            ON stat.DisplayID = display.DisplayID
            LEFT OUTER JOIN layout
            ON layout.LayoutID = stat.LayoutID
            LEFT OUTER JOIN media
            ON media.mediaID = stat.mediaID
         WHERE 1 = 1
          AND stat.end > :fromDt
          AND stat.start <= :toDt
          AND stat.displayID IN (' . implode(',', $displayIds) . ')
        ';

        $params = [
            'fromDt' => $this->getDate()->getLocalDate($fromDt),
            'toDt' => $this->getDate()->getLocalDate($toDt)
        ];

        if ($displayId != 0) {
            $sql .= '  AND stat.displayID = :displayId ';
            $params['displayId'] = $displayId;
        }

        $sql .= " ORDER BY stat.start ";

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Type', 'FromDT', 'ToDT', 'Layout', 'Display', 'Media', 'Tag']);

        // Do some post processing
        foreach ($this->store->select($sql, $params) as $row) {
            // Read the columns
            $type = $this->getSanitizer()->string($row['Type']);
            $fromDt = $this->getSanitizer()->string($row['start']);
            $toDt = $this->getSanitizer()->string($row['end']);
            $layout = $this->getSanitizer()->string($row['Layout']);
            $display = $this->getSanitizer()->string($row['Display']);
            $media = $this->getSanitizer()->string($row['MediaName']);
            $tag = $this->getSanitizer()->string($row['Tag']);

            fputcsv($out, [$type, $fromDt, $toDt, $layout, $display, $media, $tag]);
        }

        fclose($out);

        // We want to output a load of stuff to the browser as a text file.
        $app = $this->getApp();
        $app->response()->header('Content-Type', 'text/csv');
        $app->response()->header('Content-Disposition', 'attachment; filename="stats.csv"');
        $app->response()->header('Content-Transfer-Encoding', 'binary"');
        $app->response()->header('Accept-Ranges', 'bytes');
        $this->setNoOutput(true);
    }
}
