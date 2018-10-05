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
use Xibo\Exception\InvalidArgumentException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\ByteFormatter;
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

    /** @var  UserFactory */
    private $userFactory;

    /** @var  UserGroupFactory */
    private $userGroupFactory;

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
     * @param UserFactory $userFactory
     * @param UserGroupFactory $userGroupFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $displayFactory, $layoutFactory, $mediaFactory, $userFactory, $userGroupFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->store = $store;
        $this->displayFactory = $displayFactory;
        $this->layoutFactory = $layoutFactory;
        $this->mediaFactory = $mediaFactory;
        $this->userFactory = $userFactory;
        $this->userGroupFactory = $userGroupFactory;
    }

    /**
     * Stats page
     */
    function displayPage()
    {
        $data = [
            // List of Displays this user has permission for
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
     * Stats page
     */
    function displayProofOfPlayPage()
    {
        $data = [
            // List of Displays this user has permission for
            'defaults' => [
                'fromDate' => $this->getDate()->getLocalDate(time() - (86400 * 35)),
                'fromDateOneDay' => $this->getDate()->getLocalDate(time() - 86400),
                'toDate' => $this->getDate()->getLocalDate()
            ]
        ];

        $this->getState()->template = 'stats-proofofplay-page';
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
     *      name="type",
     *      in="formData",
     *      description="The type of stat to return. Layout|Media|Widget or All",
     *      type="string",
     *      required=false
     *   ),
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
        $type = strtolower($this->getSanitizer()->getString('type'));

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
            throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');

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

        // Type filter
        if ($type == 'layout') {
            $body .= ' AND `stat`.type = \'layout\' ';
        } else if ($type == 'media') {
            $body .= ' AND `stat`.type = \'media\' AND IFNULL(`media`.mediaId, 0) <> 0 ';
        } else if ($type == 'widget') {
            $body .= ' AND `stat`.type = \'media\' AND IFNULL(`widget`.widgetId, 0) <> 0 ';
        }

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

        $body .= 'GROUP BY stat.type, display.Display, layout.Layout, layout.layoutId, stat.mediaId, stat.widgetId, IFNULL(`media`.name, IFNULL(`widgetoption`.value, `widget`.type)) ';

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
            $entry['layoutId'] = $this->getSanitizer()->int($row['layoutId']);
            $entry['widgetId'] = $this->getSanitizer()->int($row['widgetId']);
            $entry['mediaId'] = $this->getSanitizer()->int($row['mediaId']);

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
        $onlyLoggedIn = $this->getSanitizer()->getCheckbox('onlyLoggedIn') == 1;

        // Get an array of display id this user has access to.
        $displayIds = array();

        foreach ($this->displayFactory->query() as $display) {
            $displayIds[] = $display->displayId;
        }

        if (count($displayIds) <= 0)
            throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');

        // Get some data for a bandwidth chart
        $params = array(
            'start' => $fromDt->format('U'),
            'end' => $toDt->format('U')
        );

        $SQL = '
            SELECT display.display,
                SUM(LEAST(IFNULL(`end`, :end), :end) - GREATEST(`start`, :start)) AS duration
              FROM `displayevent`
                INNER JOIN `display`
                ON display.displayId = `displayevent`.displayId
             WHERE `start` <= :end
                AND IFNULL(`end`, :end) >= :start
                AND display.displayId IN (' . implode(',', $displayIds) . ') ';

        if ($displayId != 0) {
            $SQL .= ' AND display.displayId = :displayId ';
            $params['displayId'] = $displayId;
        }

        if ($onlyLoggedIn) {
            $SQL .= ' AND `display`.loggedIn = 1 ';
        }

        $SQL .= '
            GROUP BY display.display
        ';

        $rows = $this->store->select($SQL, $params);

        $labels = [];
        $data = [];
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
            $labels[] = $this->getSanitizer()->string($row['display']);
            $data[] = round($this->getSanitizer()->double($row['duration']) / $divisor, 2);
        }

        $this->getState()->extra = [
            'labels' => $labels,
            'data' => $data,
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
            throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');

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

        $labels = [];
        $data = [];

        foreach ($results as $row) {

            // label depends whether we are filtered by display
            if ($displayId != 0) {
                $labels[] = $row['type'];
            } else {
                $labels[] = $row['display'];
            }

            $data[] = round((double)$row['size'] / (pow(1024, $base)), 2);
        }

        // Set up some suffixes
        $suffixes = array('bytes', 'k', 'M', 'G', 'T');

        $this->getState()->extra = [
            'labels' => $labels,
            'data' => $data,
            'postUnits' => (isset($suffixes[$base]) ? $suffixes[$base] : '')
        ];
    }

    /**
     * Output CSV Form
     */
    public function exportForm()
    {
        $this->getState()->template = 'statistics-form-export';
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

        // Run our query using a connection object (to save memory)
        $connection = $this->store->getConnection();
        $statement = $connection->prepare($sql);

        // Execute
        $statement->execute($params);

        // Do some post processing
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
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

    /**
     * Stats page
     */
    function displayLibraryPage()
    {
        $this->getState()->template = 'stats-library-page';
        $data = [];

        // Set up some suffixes
        $suffixes = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB');

        // Widget for the library usage pie chart
        try {
            if ($this->getUser()->libraryQuota != 0) {
                $libraryLimit = $this->getUser()->libraryQuota * 1024;
            } else {
                $libraryLimit = $this->getConfig()->GetSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;
            }

            // Library Size in Bytes
            $params = [];
            $sql = 'SELECT IFNULL(SUM(FileSize), 0) AS SumSize, type FROM `media` WHERE 1 = 1 ';
            $this->mediaFactory->viewPermissionSql('Xibo\Entity\Media', $sql, $params, '`media`.mediaId', '`media`.userId');
            $sql .= ' GROUP BY type ';

            $sth = $this->store->getConnection()->prepare($sql);
            $sth->execute($params);

            $results = $sth->fetchAll();

            // Do we base the units on the maximum size or the library limit
            $maxSize = 0;
            if ($libraryLimit > 0) {
                $maxSize = $libraryLimit;
            } else {
                // Find the maximum sized chunk of the items in the library
                foreach ($results as $library) {
                    $maxSize = ($library['SumSize'] > $maxSize) ? $library['SumSize'] : $maxSize;
                }
            }

            // Decide what our units are going to be, based on the size
            $base = ($maxSize == 0) ? 0 : floor(log($maxSize) / log(1024));

            $libraryUsage = [];
            $libraryLabels = [];
            $totalSize = 0;
            foreach ($results as $library) {
                $libraryUsage[] = round((double)$library['SumSize'] / (pow(1024, $base)), 2);
                $libraryLabels[] = ucfirst($library['type']) . ' ' . $suffixes[$base];

                $totalSize = $totalSize + $library['SumSize'];
            }

            // Do we need to add the library remaining?
            if ($libraryLimit > 0) {
                $remaining = round(($libraryLimit - $totalSize) / (pow(1024, $base)), 2);

                $libraryUsage[] = $remaining;
                $libraryLabels[] = __('Free') . ' ' . $suffixes[$base];
            }

            // What if we are empty?
            if (count($results) == 0 && $libraryLimit <= 0) {
                $libraryUsage[] = 0;
                $libraryLabels[] = __('Empty');
            }

            $data['libraryLimitSet'] = ($libraryLimit > 0);
            $data['libraryLimit'] = (round((double)$libraryLimit / (pow(1024, $base)), 2)) . ' ' . $suffixes[$base];
            $data['librarySize'] = ByteFormatter::format($totalSize, 1);
            $data['librarySuffix'] = $suffixes[$base];
            $data['libraryWidgetLabels'] = json_encode($libraryLabels);
            $data['libraryWidgetData'] = json_encode($libraryUsage);

        } catch (\Exception $exception) {
            $this->getLog()->error('Error rendering the library stats page widget');
        }

        $data['users'] = $this->userFactory->query();
        $data['groups'] = $this->userGroupFactory->query();

        $this->getState()->setData($data);
    }

    public function libraryUsageGrid()
    {
        $params = [];
        $select = '
            SELECT `user`.userId,
                `user`.userName,
                IFNULL(SUM(`media`.FileSize), 0) AS bytesUsed,
                COUNT(`media`.mediaId) AS numFiles
        ';
        $body = '     
              FROM `user`
                LEFT OUTER JOIN `media`
                ON `media`.userID = `user`.UserID
              WHERE 1 = 1
        ';

        // Restrict on the users we have permission to see
        // Normal users can only see themselves
        $permissions = '';
        if ($this->getUser()->userTypeId == 3) {
            $permissions .= ' AND user.userId = :currentUserId ';
            $filterBy['currentUserId'] = $this->getUser()->userId;
        }
        // Group admins can only see users from their groups.
        else if ($this->getUser()->userTypeId == 2) {
            $permissions .= '
                AND user.userId IN (
                    SELECT `otherUserLinks`.userId
                      FROM `lkusergroup`
                        INNER JOIN `group`
                        ON `group`.groupId = `lkusergroup`.groupId
                            AND `group`.isUserSpecific = 0
                        INNER JOIN `lkusergroup` `otherUserLinks`
                        ON `otherUserLinks`.groupId = `group`.groupId
                     WHERE `lkusergroup`.userId = :currentUserId
                )
            ';
            $params['currentUserId'] = $this->getUser()->userId;
        }

        // Filter by userId
        if ($this->getSanitizer()->getInt('userId') !== null) {
            $body .= ' AND user.userId = :userId ';
            $params['userId'] = $this->getSanitizer()->getInt('userId');
        }

        // Filter by groupId
        if ($this->getSanitizer()->getInt('groupId') !== null) {
            $body .= ' AND user.userId IN (SELECT userId FROM `lkusergroup` WHERE groupId = :groupId) ';
            $params['groupId'] = $this->getSanitizer()->getInt('groupId');
        }

        $body .= $permissions;
        $body .= '            
            GROUP BY `user`.userId,
              `user`.userName
        ';


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
        $rows = [];

        foreach ($this->store->select($sql, $params) as $row) {
            $entry = [];

            $entry['userId'] = $this->getSanitizer()->int($row['userId']);
            $entry['userName'] = $this->getSanitizer()->string($row['userName']);
            $entry['bytesUsed'] = $this->getSanitizer()->int($row['bytesUsed']);
            $entry['bytesUsedFormatted'] = ByteFormatter::format($this->getSanitizer()->int($row['bytesUsed']), 2);
            $entry['numFiles'] = $this->getSanitizer()->int($row['numFiles']);

            $rows[] = $entry;
        }

        // Paging
        if ($limit != '' && count($rows) > 0) {
            $results = $this->store->select('SELECT COUNT(*) AS total FROM `user` ' . $permissions, $params);
            $this->getState()->recordsTotal = intval($results[0]['total']);
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($rows);
    }
}
