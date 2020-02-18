<?php

namespace Xibo\Report;

use MongoDB\BSON\UTCDateTime;
use Xibo\Entity\ReportSchedule;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ReportScheduleFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Factory\UserFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\ReportServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;

/**
 * Class ProofOfPlay
 * @package Xibo\Report
 */
class ProofOfPlay implements ReportInterface
{

    use ReportTrait;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var SavedReportFactory
     */
    private $savedReportFactory;

    /**
     * @var ReportScheduleFactory
     */
    private $reportScheduleFactory;

    /**
     * @var UserFactory
     */
    private $userFactory;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var ReportServiceInterface
     */
    private $reportService;

    private $table = 'stat';

    private $tagsType = [
        'dg' => 'Display group',
        'media' => 'Media',
        'layout' => 'Layout'
    ];

    /**
     * Report Constructor.
     * @param \Xibo\Helper\ApplicationState $state
     * @param StorageServiceInterface $store
     * @param TimeSeriesStoreInterface $timeSeriesStore
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param DateServiceInterface $date
     * @param SanitizerServiceInterface $sanitizer
     */
    public function __construct($state, $store, $timeSeriesStore, $log, $config, $date, $sanitizer)
    {
        $this->setCommonDependencies($state, $store, $timeSeriesStore, $log, $config, $date, $sanitizer);
    }

    /** @inheritdoc */
    public function setFactories($container)
    {

        $this->displayFactory = $container->get('displayFactory');
        $this->mediaFactory = $container->get('mediaFactory');
        $this->layoutFactory = $container->get('layoutFactory');
        $this->savedReportFactory = $container->get('savedReportFactory');
        $this->reportScheduleFactory = $container->get('reportScheduleFactory');
        $this->userFactory = $container->get('userFactory');
        $this->displayGroupFactory = $container->get('displayGroupFactory');
        $this->reportService = $container->get('reportService');

        return $this;
    }

    /** @inheritdoc */
    public function getReportChartScript($results)
    {
        return null;
    }

    /** @inheritdoc */
    public function getReportEmailTemplate()
    {
        return 'proofofplay-email-template.twig';
    }

    /** @inheritdoc */
    public function getReportForm()
    {
        return [
            'template' => 'proofofplay-report-form',
            'data' =>  [
                'fromDate' => $this->getDate()->getLocalDate(time() - (86400 * 35)),
                'fromDateOneDay' => $this->getDate()->getLocalDate(time() - 86400),
                'toDate' => $this->getDate()->getLocalDate(),
                'availableReports' => $this->reportService->listReports()
            ]
        ];
    }

    /** @inheritdoc */
    public function getReportScheduleFormData()
    {

        $data = [];

        $title = 'Add Report Schedule';
        $data['formTitle'] = $title;

        $data['type'] = $this->getSanitizer()->getParam('type', '');
        $data['tagsType'] = $this->getSanitizer()->getParam('tagsType', '');

        $exactTags = $this->getSanitizer()->getParam('exactTags', false);
        $data['exactTags'] = ($exactTags == 'true') ? true : false;

        $tags = $this->getSanitizer()->getParam('tags', '');
        $data['tags'] = $tags;

        $data['hiddenFields'] =  '';
        $data['reportName'] = 'proofofplayReport';

        return [
            'template' => 'proofofplay-schedule-form-add',
            'data' => $data
        ];
    }

    /** @inheritdoc */
    public function setReportScheduleFormData()
    {

        // We use the following variables in temp array
        $filter = $this->getSanitizer()->getString('filter');
        $displayId = $this->getSanitizer()->getInt('displayId');
        $layoutIds = $this->getSanitizer()->getIntArray('layoutId');
        $mediaIds = $this->getSanitizer()->getIntArray('mediaId');
        $type = $this->getSanitizer()->getString('type');
        $sortBy = $this->getSanitizer()->getString('sortBy');
        $tagsType = $this->getSanitizer()->getString('tagsType');
        $tags = $this->getSanitizer()->getString('tags');
        $exactTags = $this->getSanitizer()->getCheckbox('exactTags');

        $temp = ['filter', 'displayId', 'layoutIds', 'mediaIds', 'type', 'sortBy', 'tagsType', 'tags', 'exactTags'];

        $filterCriteria = [];
        foreach ($temp as $value) {
            $filterCriteria[$value] = $$value;
        }

        $schedule = '';
        if ($filter == 'daily') {
            $schedule = ReportSchedule::$SCHEDULE_DAILY;
            $filterCriteria['reportFilter'] = 'yesterday';

        } else if ($filter == 'weekly') {
            $schedule = ReportSchedule::$SCHEDULE_WEEKLY;
            $filterCriteria['reportFilter'] = 'lastweek';

        } else if ($filter == 'monthly') {
            $schedule = ReportSchedule::$SCHEDULE_MONTHLY;
            $filterCriteria['reportFilter'] = 'lastmonth';

        } else if ($filter == 'yearly') {
            $schedule = ReportSchedule::$SCHEDULE_YEARLY;
            $filterCriteria['reportFilter'] = 'lastyear';
        }

        $filterCriteria['sendEmail'] = $this->getSanitizer()->getCheckbox('sendEmail');
        $filterCriteria['nonusers'] = $this->getSanitizer()->getString('nonusers');

        // Return
        return [
            'filterCriteria' => json_encode($filterCriteria),
            'schedule' => $schedule
        ];
    }

    /** @inheritdoc */
    public function generateSavedReportName($filterCriteria)
    {
        $saveAs = __(ucfirst($filterCriteria['filter']). ' report for Type') .': ';

        switch ($filterCriteria['type']) {

            case 'layout':
                $saveAs .= 'Layout. ';
                break;

            case 'media':
                $saveAs .= 'Media. ';
                break;

            case 'widget':
                $saveAs .= 'Widget. ';
                break;

            case 'event':
                $saveAs .= 'Event. ';
                break;

            default:
                $saveAs .= 'All. ';
                break;
        }


        if (count($filterCriteria['layoutIds']) > 0) {

            $layouts = '';
            foreach ($filterCriteria['layoutIds'] as $id) {
                try {
                    $layout = $this->layoutFactory->getById($id);

                } catch (NotFoundException $error) {

                    // Get the campaign ID
                    $campaignId = $this->layoutFactory->getCampaignIdFromLayoutHistory($id);
                    $layoutId = $this->layoutFactory->getLatestLayoutIdFromLayoutHistory($campaignId);
                    $layout = $this->layoutFactory->getById($layoutId);

                }

                $layouts .= $layout->layout . ', ';
            }

            $saveAs .= 'Layouts: '. $layouts;
        }

        if (count($filterCriteria['mediaIds']) > 0) {
            $medias = '';
            foreach ($filterCriteria['mediaIds'] as $id) {

                try {
                    $media = $this->mediaFactory->getById($id);
                    $name = $media->name;

                } catch (NotFoundException $error) {
                    $name = 'Media ' . __('Not Found');
                }

                $medias .= $name . ', ';

            }

            $saveAs .= 'Media: ' . $medias;

        }


        if (!empty($filterCriteria['displayId'])) {

            // Get display
            try{
                $displayName = $this->displayFactory->getById($filterCriteria['displayId'])->display;
                $saveAs .= '(Display: '. $displayName . ')';

            } catch (NotFoundException $error){
                $saveAs .= ' (DisplayId: ' . __('Not Found') . ' )';

            }
        }

        return $saveAs;
    }

    /** @inheritdoc */
    public function getSavedReportResults($json, $savedReport)
    {
        // Get filter criteria
        $rs = $this->reportScheduleFactory->getById($savedReport->reportScheduleId)->filterCriteria;
        $filterCriteria = json_decode($rs, true);

        $tagsType = $filterCriteria['tagsType'];
        $tags = $filterCriteria['tags'];
        $exactTags = ($filterCriteria['exactTags'] == 1) ? ' (exact match)': '';

        // Show filter criteria
        $filterInfo = '';
        if ($tags != null) {
            $filterInfo = __('Tags from') . ': '. $this->tagsType[$tagsType]. ', Tags: '. $tags. $exactTags;
        }

        // Return data to build chart
        return [
            'template' => 'proofofplay-report-preview',
            'chartData' => [
                'savedReport' => $savedReport,
                'filterInfo' => $filterInfo,
                'generatedOn' => $this->dateService->parse($savedReport->generatedOn, 'U')->format('Y-m-d H:i:s'),
                'periodStart' => isset($json['periodStart']) ? $json['periodStart'] : '',
                'periodEnd' => isset($json['periodEnd']) ? $json['periodEnd'] : '',
                'result' => json_encode($json['result']),
            ]
        ];
    }


    /** @inheritdoc */
    public function getResults($filterCriteria)
    {

        $displayId = $this->getSanitizer()->getInt('displayId', $filterCriteria);
        $layoutIds = $this->getSanitizer()->getIntArray('layoutId', $filterCriteria);
        $mediaIds = $this->getSanitizer()->getIntArray('mediaId', $filterCriteria);
        $type = strtolower($this->getSanitizer()->getString('type'));
        $tags = $this->getSanitizer()->getString('tags', $filterCriteria);
        $tagsType = $this->getSanitizer()->getString('tagsType', $filterCriteria);
        $exactTags = $this->getSanitizer()->getCheckbox('exactTags', $filterCriteria);

        // Do not filter by display if super admin and no display is selected
        // Super admin will be able to see stat records of deleted display, we will not filter by display later
        $displayIds = [];

        // Get user
        $userId = $this->getUserId();
        if ($userId == null) {
            $user = $this->userFactory->getUser();
        } else {
            $user = $this->userFactory->getById($userId);
        }
        $displayFactory = clone $this->displayFactory;
        $displayFactory->setAclDependencies($user, $this->userFactory);

        if ($user->userTypeId != 1) {
            // Get an array of display id this user has access to.
            foreach ($displayFactory->query() as $display) {
                $displayIds[] = $display->displayId;
            }

            if (count($displayIds) <= 0)
                throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');

            // Set displayIds as [-1] if the user selected a display for which they don't have permission
            if ($displayId != 0) {
                if (!in_array($displayId, $displayIds)) {
                    $displayIds = [-1];
                } else {
                    $displayIds = [$displayId];
                }
            }
        } else {
            if ($displayId != 0) {
                $displayIds = [$displayId];
            }
        }

        // Sorting?

        if($filterCriteria == null) {

            $filterBy = $this->gridRenderFilter();
            $sortOrder = $this->gridRenderSort();

            $columns = [];
            if (is_array($sortOrder))
                $columns = $sortOrder;

            // Paging
            $start = 0;
            $length = 0;
            if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {

                $start = intval($this->getSanitizer()->getInt('start', $filterBy), 0);
                $length = $this->getSanitizer()->getInt('length', 10, $filterBy);
            }
        } else {
            $start = 0;
            $length = -1;
            $sortBy = $this->getSanitizer()->getString('sortBy', $filterCriteria);

            $columns = ($sortBy == '') ? ['widgetId'] : [$sortBy];
        }

        //
        // From and To Date Selection
        // --------------------------
        // Our report has a range filter which determins whether or not the user has to enter their own from / to dates
        // check the range filter first and set from/to dates accordingly.
        $reportFilter = $this->getSanitizer()->getString('reportFilter', $filterCriteria);

        // Use the current date as a helper
        $now = $this->getDate()->parse();

        switch ($reportFilter) {

            case 'today':
                $fromDt = $now->copy()->startOfDay();
                $toDt = $fromDt->copy()->addDay();
                break;

            case 'yesterday':
                $fromDt = $now->copy()->startOfDay()->subDay();
                $toDt = $now->copy()->startOfDay();
                break;

            case 'thisweek':
                $fromDt = $now->copy()->startOfWeek();
                $toDt = $fromDt->copy()->addWeek();
                break;

            case 'thismonth':
                $fromDt = $now->copy()->startOfMonth();
                $toDt = $fromDt->copy()->addMonth();
                break;

            case 'thisyear':
                $fromDt = $now->copy()->startOfYear();
                $toDt = $fromDt->copy()->addYear();
                break;

            case 'lastweek':
                $fromDt = $now->copy()->startOfWeek()->subWeek();
                $toDt = $fromDt->copy()->addWeek();
                break;

            case 'lastmonth':
                $fromDt = $now->copy()->startOfMonth()->subMonth();
                $toDt = $fromDt->copy()->addMonth();
                break;

            case 'lastyear':
                $fromDt = $now->copy()->startOfYear()->subYear();
                $toDt = $fromDt->copy()->addYear();
                break;

            case '':
            default:
                // Expect dates to be provided.
                $fromDt = $this->getSanitizer()->getDate('statsFromDt', $this->getDate()->parse()->addDay(-1));
                $fromDt->startOfDay();

                $toDt = $this->getSanitizer()->getDate('statsToDt', $this->getDate()->parse());
                $toDt->startOfDay();

                $fromDtTime = $this->getSanitizer()->getString('statsFromDtTime');
                $toDtTime = $this->getSanitizer()->getString('statsToDtTime');

                if ($fromDtTime !== null && $toDtTime !== null) {

                    $startTimeArray = explode(':', $fromDtTime);
                    $fromDt->setTime(intval($startTimeArray[0]), intval($startTimeArray[1]));

                    $toTimeArray = explode(':', $toDtTime);
                    $toDt->setTime(intval($toTimeArray[0]), intval($toTimeArray[1]));
                }

                // What if the fromdt and todt are exactly the same?
                // in this case assume an entire day from midnight on the fromdt to midnight on the todt (i.e. add a day to the todt)
                if ($fromDt == $toDt) {
                    $toDt->addDay();
                }

                break;
        }

        //
        // Get Results!
        // -------------
        $timeSeriesStore = $this->getTimeSeriesStore()->getEngine();
        if ($timeSeriesStore == 'mongodb') {
            $result = $this->getProofOfPlayReportMongoDb($fromDt, $toDt, $displayIds, $layoutIds, $mediaIds, $type, $columns, $tags, $tagsType, $exactTags, $start, $length);
        } else {
            $result = $this->getProofOfPlayReportMySql($fromDt, $toDt, $displayIds, $layoutIds, $mediaIds, $type, $columns, $tags, $tagsType, $exactTags, $start, $length);
        }

        // Sanitize results
        $rows = [];
        foreach ($result['result'] as $row) {

            $entry = [];

            $widgetId = $this->getSanitizer()->int($row['widgetId']);
            $widgetName = $this->getSanitizer()->string($row['media']);
            // If the media name is empty, and the widgetid is not, then we can assume it has been deleted.
            $widgetName = ($widgetName == '' &&  $widgetId != 0) ? __('Deleted from Layout') : $widgetName;
            $displayName = $this->getSanitizer()->string($row['display']);
            $layoutName = $this->getSanitizer()->string($row['layout']);

            $entry['type'] = $this->getSanitizer()->string($row['type']);
            $entry['displayId'] = $this->getSanitizer()->int(($row['displayId']));
            $entry['display'] = ($displayName != '') ? $displayName : __('Not Found');
            $entry['layoutId'] = $this->getSanitizer()->int($row['layoutId']);
            $entry['layout'] = ($layoutName != '') ? $layoutName :  __('Not Found');
            $entry['widgetId'] = $this->getSanitizer()->int($row['widgetId']);
            $entry['media'] = $widgetName;
            $entry['tag'] = $this->getSanitizer()->string($row['tag']);
            $entry['numberPlays'] = $this->getSanitizer()->int($row['numberPlays']);
            $entry['duration'] = $this->getSanitizer()->int($row['duration']);
            $entry['minStart'] = $this->getDate()->parse($row['minStart'], 'U')->format('Y-m-d H:i:s');
            $entry['maxEnd'] = $this->getDate()->parse($row['maxEnd'], 'U')->format('Y-m-d H:i:s');
            $entry['mediaId'] = $this->getSanitizer()->int($row['mediaId']);

            $rows[] = $entry;
        }

        // Paging
        if ($result['count'] > 0) {
            $this->getState()->recordsTotal = intval($result['totalStats']);
        }

        //
        // Output Results
        // --------------
        $this->getState()->template = 'grid';
        $this->getState()->setData($rows);

        return [
            'periodStart' => $this->getDate()->getLocalDate($fromDt),
            'periodEnd' => $this->getDate()->getLocalDate($toDt),
            'result' => $rows,
        ];

    }

    /**
     * MySQL proof of play report
     * @param \Jenssegers\Date\Date $fromDt The filter range from date
     * @param \Jenssegers\Date\Date $toDt The filter range to date
     * @param $displayIds array
     * @param $layoutIds array
     * @param $mediaIds array
     * @param $type string
     * @param $columns array
     * @param $tags string
     * @param $tagsType string
     * @param $exactTags mixed
     * @param $start int
     * @param $length int
     * @return array[array result, date periodStart, date periodEnd, int count, int totalStats]
     */
    private function getProofOfPlayReportMySql($fromDt, $toDt, $displayIds, $layoutIds, $mediaIds, $type, $columns, $tags, $tagsType, $exactTags, $start, $length)
    {

        $fromDt = $fromDt->format('U');
        $toDt = $toDt->format('U');

        // Media on Layouts Ran
        $select = '
          SELECT stat.type,
              display.Display,
              IFNULL(layout.Layout, 
              (SELECT `layout` FROM `layout` WHERE layoutId = (SELECT  MAX(layoutId) FROM  layouthistory  WHERE
                            campaignId = stat.campaignId))) AS Layout,
              IFNULL(`media`.name, IFNULL(`widgetoption`.value, `widget`.type)) AS Media,
              SUM(stat.count) AS NumberPlays,
              SUM(stat.duration) AS Duration,
              MIN(start) AS MinStart,
              MAX(end) AS MaxEnd,
              stat.tag,
              stat.layoutId,
              stat.mediaId,
              stat.widgetId,
              stat.displayId
        ';

        $body = '
            FROM stat
              LEFT OUTER JOIN display
              ON stat.DisplayID = display.DisplayID
              LEFT OUTER JOIN layouthistory 
              ON layouthistory.LayoutID = stat.LayoutID              
              LEFT OUTER JOIN layout
              ON layout.LayoutID = layouthistory.layoutId
              LEFT OUTER JOIN `widget`
              ON `widget`.widgetId = stat.widgetId
              LEFT OUTER JOIN `widgetoption`
              ON `widgetoption`.widgetId = `widget`.widgetId
                AND `widgetoption`.type = \'attrib\'
                AND `widgetoption`.option = \'name\'
              LEFT OUTER JOIN `media`
              ON `media`.mediaId = `stat`.mediaId
              ';

        if ($tags != '' ) {
            if ($tagsType === 'dg') {
                $body .= 'INNER JOIN `lkdisplaydg`
                        ON lkdisplaydg.DisplayID = display.displayid
                     INNER JOIN `displaygroup`
                        ON displaygroup.displaygroupId = lkdisplaydg.displaygroupId
                         AND `displaygroup`.isDisplaySpecific = 1 ';
            }
        }

        $body .= ' WHERE stat.type <> \'displaydown\'
                AND stat.end > :fromDt
                AND stat.start <= :toDt
        ';

        // Filter by display
        if (count($displayIds) > 0 ) {
            $body .= ' AND stat.displayID IN (' . implode(',', $displayIds) . ') ';
        }

        $params = [
            'fromDt' => $fromDt,
            'toDt' => $toDt
        ];

        if ($tags != '') {
            if (trim($tags) === '--no-tag') {
                if ($tagsType === 'dg') {
                    $body .= ' AND `displaygroup`.displaygroupId NOT IN (
                    SELECT `lktagdisplaygroup`.displaygroupId
                     FROM tag
                        INNER JOIN `lktagdisplaygroup`
                        ON `lktagdisplaygroup`.tagId = tag.tagId
                        )
                        ';
                }

                // old layout and latest layout have same tags
                // old layoutId replaced with latest layoutId in the lktaglayout table and
                // join with layout history to get campaignId then we can show old layouts that have no tag
                if ($tagsType === 'layout') {
                    $body .= ' AND `stat`.campaignId NOT IN (
                        SELECT 
                            `layouthistory`.campaignId
                        FROM
                        (
                            SELECT `lktaglayout`.layoutId
                            FROM tag
                            INNER JOIN `lktaglayout`
                            ON `lktaglayout`.tagId = tag.tagId ) B
                        LEFT OUTER JOIN
                        `layouthistory` ON `layouthistory`.layoutId = B.layoutId 
                        )
                        ';
                }
                if ($tagsType === 'media') {
                    $body .= ' AND `media`.mediaId NOT IN (
                    SELECT `lktagmedia`.mediaId
                     FROM tag
                        INNER JOIN `lktagmedia`
                        ON `lktagmedia`.tagId = tag.tagId
                        )
                        ';
                }
            } else {
                $operator = $exactTags == 1 ? '=' : 'LIKE';
                if ($tagsType === 'dg') {
                    $body .= " AND `displaygroup`.displaygroupId IN (
                        SELECT `lktagdisplaygroup`.displaygroupId
                          FROM tag
                            INNER JOIN `lktagdisplaygroup`
                            ON `lktagdisplaygroup`.tagId = tag.tagId
                        ";
                }
                // old layout and latest layout have same tags
                // old layoutId replaced with latest layoutId in the lktaglayout table and
                // join with layout history to get campaignId then we can show old layouts that have given tag
                if ($tagsType === 'layout') {
                    $body .= " AND `stat`.campaignId IN (
                        SELECT 
                            `layouthistory`.campaignId
                        FROM
                        (
                            SELECT `lktaglayout`.layoutId
                            FROM tag
                            INNER JOIN `lktaglayout`
                            ON `lktaglayout`.tagId = tag.tagId
                        ";
                }
                if ($tagsType === 'media') {
                    $body .= " AND `media`.mediaId IN (
                        SELECT `lktagmedia`.mediaId
                          FROM tag
                            INNER JOIN `lktagmedia`
                            ON `lktagmedia`.tagId = tag.tagId
                    ";
                }
                $i = 0;
                foreach (explode(',', $tags) as $tag) {
                    $i++;

                    $tagV = explode('|', $tag);

                    // search tag without value
                    if (!isset($tagV[1])) {
                        if ($i == 1) {
                            $body .= ' WHERE `tag` ' . $operator . ' :tags' . $i;
                        } else {
                            $body .= ' OR `tag` ' . $operator . ' :tags' . $i;
                        }

                        if ($operator === '=') {
                            $params['tags' . $i] = $tag;
                        } else {
                            $params['tags' . $i] = '%' . $tag . '%';
                        }
                        // search tag only by value
                    } elseif ($tagV[0] == '') {
                        if ($i == 1) {
                            $body .= ' WHERE `value` ' . $operator . ' :value' . $i;
                        } else {
                            $body .= ' OR `value` ' . $operator . ' :value' . $i;
                        }

                        if ($operator === '=') {
                            $params['value' . $i] = $tagV[1];
                        } else {
                            $params['value' . $i] = '%' . $tagV[1] . '%';
                        }
                        // search tag by both tag and value
                    } else {
                        if ($i == 1) {
                            $body .= ' WHERE `tag` ' . $operator . ' :tags' . $i .
                                ' AND value ' . $operator . ' :value' . $i;
                        } else {
                            $body .= ' OR `tag` ' . $operator . ' :tags' . $i .
                                ' AND value ' . $operator . ' :value' . $i;
                        }

                        if ($operator === '=') {
                            $params['tags' . $i] = $tagV[0];
                            $params['value' . $i] = $tagV[1];
                        } else {
                            $params['tags' . $i] = '%' . $tagV[0] . '%';
                            $params['value' . $i] = '%' . $tagV[1] . '%';
                        }
                    }
                }
                if ($tagsType === 'layout') {
                    $body .= " ) B
                        LEFT OUTER JOIN
                        `layouthistory` ON `layouthistory`.layoutId = B.layoutId ) ";
                }
                else {
                    $body .= " ) ";
                }
            }
        }

        // Type filter
        if ($type == 'layout') {
            $body .= ' AND `stat`.type = \'layout\' ';
        } else if ($type == 'media') {
            $body .= ' AND `stat`.type = \'media\' AND IFNULL(`media`.mediaId, 0) <> 0 ';
        } else if ($type == 'widget') {
            $body .= ' AND `stat`.type = \'widget\' AND IFNULL(`widget`.widgetId, 0) <> 0 ';
        } else if ($type == 'event') {
            $body .= ' AND `stat`.type = \'event\' ';
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

            $body .= '  AND `stat`.campaignId IN (SELECT campaignId from layouthistory where layoutId IN (' . trim($layoutSql, ',') . ')) ';
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

        $body .= 'GROUP BY stat.type, stat.tag, display.Display, stat.displayId, stat.campaignId, IFNULL(stat.mediaId, stat.widgetId), 
        IFNULL(`media`.name, IFNULL(`widgetoption`.value, `widget`.type)) ';

        $order = '';
        if ($columns != null)
            $order = 'ORDER BY ' . implode(',', $columns);

        $limit= '';
        if (($length != null) && ($length != -1))
            $limit = ' LIMIT ' . $start . ', ' . $length;

        /*Execute sql statement*/
        $sql = $select . $body . $order . $limit;

        $rows = [];
        foreach ($this->store->select($sql, $params) as $row) {
            $entry = [];

            $entry['type'] = $row['type'];
            $entry['displayId'] = $row['displayId'];
            $entry['display'] = $row['Display'];
            $entry['layout'] = $row['Layout'];
            $entry['media'] = $row['Media'];
            $entry['numberPlays'] = $row['NumberPlays'];
            $entry['duration'] = $row['Duration'];
            $entry['minStart'] = $row['MinStart'];
            $entry['maxEnd'] = $row['MaxEnd'];
            $entry['layoutId'] = $row['layoutId'];
            $entry['widgetId'] = $row['widgetId'];
            $entry['mediaId'] = $row['mediaId'];
            $entry['tag'] = $row['tag'];

            $rows[] = $entry;
        }

        // Paging
        $results = [];
        if ($limit != '' && count($rows) > 0) {
            $results = $this->store->select('
              SELECT COUNT(*) AS total FROM (SELECT stat.type, display.Display, layout.Layout, IFNULL(`media`.name, IFNULL(`widgetoption`.value, `widget`.type)) ' . $body . ') total
            ', $params);
        }

        return [
            'result' => $rows,
            'periodStart' => $this->getDate()->parse($fromDt, 'U')->format('Y-m-d H:i:s'),
            'periodEnd' => $this->getDate()->parse($toDt, 'U')->format('Y-m-d H:i:s'),
            'count' => count($rows),
            'totalStats' => isset($results[0]['total']) ? $results[0]['total'] : 0,
        ];

    }

    /**
     * MongoDB proof of play report
     * @param \Jenssegers\Date\Date $fromDt The filter range from date
     * @param \Jenssegers\Date\Date $toDt The filter range to date
     * @param $displayIds array
     * @param $layoutIds array
     * @param $mediaIds array
     * @param $type string
     * @param $columns array
     * @param $tags string
     * @param $tagsType string
     * @param $exactTags mixed
     * @param $start int
     * @param $length int
     * @return array[array result, date periodStart, date periodEnd, int count, int totalStats]
     */
    private function getProofOfPlayReportMongoDb($fromDt, $toDt, $displayIds, $layoutIds, $mediaIds, $type, $columns, $tags, $tagsType, $exactTags, $start, $length)
    {

        $fromDt = new UTCDateTime($fromDt->format('U')*1000);
        $toDt = new UTCDateTime($toDt->format('U')*1000);

        // Filters the documents to pass only the documents that
        // match the specified condition(s) to the next pipeline stage.
        $match =  [
            '$match' => [
                'end' => [ '$gt' => $fromDt],
                'start' => [ '$lte' => $toDt]
            ]
        ];

        // Display Filter
        if (count($displayIds) > 0) {
            $match['$match']['displayId'] = [ '$in' => $displayIds ];
        }

        // Type Filter
        if ($type != null) {
            $match['$match']['type'] = $type;
        }

        $tagsArray = [];

        // Tag Filter
        if ($tags != null) {

            $i = 0;
            foreach (explode(',', $tags) as $tag ) {

                $tagV = explode('|', $tag);

                if (!isset($tagV[1])) {
                    $tagsArray[$i]['tag'] = $tag;
                } elseif ($tagV[0] == '') {
                    $tagsArray[$i]['val'] = $tagV[1];
                } else {
                    $tagsArray[$i]['tag'] = $tagV[0];
                    $tagsArray[$i]['val'] = $tagV[1];
                }
                $i++;
            }

            if ($exactTags != 1) {
                $tagsArray = array_map(function ($tagValue) {
                    return array_map(function ($tag) { return new \MongoDB\BSON\Regex('.*'.$tag. '.*', 'i'); }, $tagValue);
                }, $tagsArray);
            }

            // When exact match is not desired
            if (count($tagsArray) > 0) {
                $match['$match']['tagFilter.' . $tagsType] = [
                    '$elemMatch' => [
                        '$or' => $tagsArray
                    ]
                ];
            }
        }

        // Layout Filter
        if (count($layoutIds) != 0) {
            $this->getLog()->debug(json_encode($layoutIds, JSON_PRETTY_PRINT));
            // Get campaignIds for selected layoutIds
            $campaignIds = [];
            foreach ($layoutIds as $layoutId) {
                try {
                    $campaignIds[] = $this->layoutFactory->getCampaignIdFromLayoutHistory($layoutId);
                } catch (NotFoundException $notFoundException) {
                    // Ignore the missing one
                    $this->getLog()->debug('Filter for Layout without Layout History Record, layoutId is ' . $layoutId);
                }
            }
            $match['$match']['campaignId'] = [ '$in' => $campaignIds ];
        }

        // Media Filter
        if (count($mediaIds) != 0) {
            $this->getLog()->debug(json_encode($mediaIds, JSON_PRETTY_PRINT));
            $match['$match']['mediaId'] = [ '$in' => $mediaIds ];
        }

        // For sorting
        // The selected column has a key
        $temp = [
            '_id.type' => 'type',
            '_id.display' => 'display',
            'layout' => 'layout',
            'media' => 'media',
            'eventName' => 'eventName',
            'layoutId' => 'layoutId',
            'widgetId' => 'widgetId',
            '_id.displayId' => 'displayId',
            'numberPlays' => 'numberPlays',
            'minStart' => 'minStart',
            'maxEnd' => 'maxEnd',
            'duration' => 'duration',
        ];

        // Remove ` and DESC from the array strings
        $cols = [];
        foreach ($columns as $column) {
            $str = str_replace("`","",str_replace(" DESC","",$column));
            if (\strpos($column, 'DESC') !== false) {
                $cols[$str] = -1;
            } else {
                $cols[$str] = 1;

            }
        }

        // The selected column key gets stored in an array with value 1 or -1 (for DESC)
        $array = [];
        foreach ($cols as $k => $v) {
            if (array_search($k, $temp))
                $array[array_search($k, $temp)] = $v;
        }

        $order = ['_id.type'=> 1]; // default sorting by type
        if ($array != null) {
            $order = $array;
        }

        $project = [
            '$project' => [
                'campaignId' =>  1,
                'mediaId' =>  1,
                'mediaName'=> 1,
                'media'=> [ '$ifNull' => [ '$mediaName', '$widgetName' ] ],
                'eventName' => 1,
                'widgetId' =>  1,
                'widgetName' =>  1,
                'layoutId' =>  1,
                'layoutName' =>  1,
                'displayId' =>  1,
                'displayName' =>  1,
                'start' => 1,
                'end' => 1,
                'type' => 1,
                'duration' => 1,
                'count' => 1,
                'total' => ['$sum' => 1],
            ]
        ];

        $group = [
            '$group' => [
                '_id' => [
                    'type' => '$type',
                    'campaignId'=> [ '$ifNull' => [ '$campaignId', '$layoutId' ] ],
                    'mediaorwidget'=> [ '$ifNull' => [ '$mediaId', '$widgetId' ] ],
                    'displayId'=> [ '$ifNull' => [ '$displayId', null ] ],
                    'display'=> '$displayName',
                    'eventName'=> '$eventName',
                    // we don't need to group by media name and widget name

                ],

                'media'=> [ '$first' => '$media'],
                'eventName'=> [ '$first' => '$eventName'],
                'mediaId' => ['$first' => '$mediaId'],
                'widgetId' => ['$first' => '$widgetId' ],

                'layout' => ['$first' => '$layoutName'],

                // use the last layoutId to say that is the latest layoutId
                'layoutId' => ['$last' => '$layoutId'],

                'minStart' => ['$min' => '$start'],
                'maxEnd' => ['$max' => '$end'],
                'numberPlays' => ['$sum' => '$count'],
                'duration' => ['$sum' => '$duration'],
                'total' => ['$max' => '$total'],
            ],
        ];

        // Task run
        if ($length == -1) {
            $query = [
                $match,
                $project,
                $group, [
                    '$facet' => [
                        'totalData' => [
                            ['$sort' => $order],
                        ]
                    ]
                ],

            ];

        } else { // Frontend
            $query = [
                $match,
                $project,
                $group,
                [
                    '$facet' => [
                        'totalData' => [

                            ['$skip' => $start],
                            ['$limit' => $length],
                            ['$sort' => $order],
                        ],
                        'totalCount' => [
                            [
                                '$group' => [
                                    '_id' => [],
                                    'totals' => ['$sum' => '$total'],

                                ],
                            ]
                        ]
                    ]
                ],
            ];
        }

        $result = $this->getTimeSeriesStore()->executeQuery(['collection' => $this->table, 'query' => $query]);

        $totalStats = 0;
        $rows = [];
        if (count($result) > 0) {
            
            if ($length == -1) { // Task run
                $totalCount = [];
            } else { // Get total for pagination in UI (grid)
                $totalCount = $result[0]['totalCount'];
            }

            if (count($totalCount) > 0) {
                $totalStats = $totalCount[0]['totals'];
            }

            // Grid results
            foreach ($result[0]['totalData'] as $row) {
                $entry = [];

                $entry['type'] = $row['_id']['type'];
                $entry['displayId'] = $row['_id']['displayId'];
                $entry['display'] = isset($row['_id']['display']) ? $row['_id']['display']: 'No display';
                $entry['layout'] = isset($row['layout']) ? $row['layout']: 'No layout';
                $entry['media'] = isset($row['media']) ? $row['media'] : 'No media' ;
                $entry['numberPlays'] = $row['numberPlays'];
                $entry['duration'] = $row['duration'];
                $entry['minStart'] = $row['minStart']->toDateTime()->format('U');
                $entry['maxEnd'] = $row['maxEnd']->toDateTime()->format('U');
                $entry['layoutId'] = $row['layoutId'];
                $entry['widgetId'] = $row['widgetId'];
                $entry['mediaId'] = $row['mediaId'];
                $entry['tag'] = $row['eventName'];

                $rows[] = $entry;
            }
        }

        return [
            'result' => $rows,
            'periodStart' => $this->getDate()->parse($fromDt->toDateTime()->format('U'), 'U')->format('Y-m-d H:i:s'),
            'periodEnd' => $this->getDate()->parse($toDt->toDateTime()->format('U'), 'U')->format('Y-m-d H:i:s'),
            'count' => count($rows),
            'totalStats' => $totalStats,
        ];
    }
}