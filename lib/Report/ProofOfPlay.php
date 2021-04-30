<?php

namespace Xibo\Report;

use Carbon\Carbon;
use MongoDB\BSON\UTCDateTime;
use Psr\Container\ContainerInterface;
use Xibo\Controller\DataTablesDotNetTrait;
use Xibo\Entity\ReportForm;
use Xibo\Entity\ReportResult;
use Xibo\Entity\ReportSchedule;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ReportScheduleFactory;
use Xibo\Factory\UserFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\SanitizerService;
use Xibo\Helper\Translate;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class ProofOfPlay
 * @package Xibo\Report
 */
class ProofOfPlay implements ReportInterface
{
    use ReportDefaultTrait, DataTablesDotNetTrait;

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
     * @var ReportScheduleFactory
     */
    private $reportScheduleFactory;

    /**
     * @var SanitizerService
     */
    private $sanitizer;

    /**
     * @var ApplicationState
     */
    private $state;

    /**
     * @var UserFactory
     */
    private $userFactory;

    private $table = 'stat';

    private $tagsType = [
        'dg' => 'Display group',
        'media' => 'Media',
        'layout' => 'Layout'
    ];

    /** @inheritdoc */
    public function setFactories(ContainerInterface $container)
    {
        $this->displayFactory = $container->get('displayFactory');
        $this->mediaFactory = $container->get('mediaFactory');
        $this->layoutFactory = $container->get('layoutFactory');
        $this->reportScheduleFactory = $container->get('reportScheduleFactory');
        $this->userFactory = $container->get('userFactory');
        $this->sanitizer = $container->get('sanitizerService');

        return $this;
    }

    /** @inheritdoc */
    public function getReportEmailTemplate()
    {
        return 'proofofplay-email-template.twig';
    }

    /** @inheritdoc */
    public function getSavedReportTemplate()
    {
        return 'proofofplay-report-preview';
    }

    /** @inheritdoc */
    public function getReportForm()
    {
        return new ReportForm(
            'proofofplay-report-form',
            'proofofplayReport',
            'Proof of Play',
            [
                'fromDateOneDay' => Carbon::now()->subSeconds(86400)->format(DateFormatHelper::getSystemFormat()),
                'toDate' => Carbon::now()->format(DateFormatHelper::getSystemFormat())
            ],
            __('Select a type and an item (i.e., layout/media/tag)')
        );
    }

    /** @inheritdoc */
    public function getReportScheduleFormData(SanitizerInterface $sanitizedParams)
    {
        $data = [];

        $title = 'Add Report Schedule';
        $data['formTitle'] = $title;

        $data['type'] = $sanitizedParams->getString('type');
        $data['tagsType'] = $sanitizedParams->getString('tagsType');

        $exactTags = $sanitizedParams->getCheckbox('exactTags');
        $data['exactTags'] = ($exactTags == 'true') ? true : false;

        $tags = $sanitizedParams->getString('tags');
        $data['tags'] = $tags;

        $data['hiddenFields'] =  '';
        $data['reportName'] = 'proofofplayReport';

        return [
            'template' => 'proofofplay-schedule-form-add',
            'data' => $data
        ];
    }

    /** @inheritdoc */
    public function setReportScheduleFormData(SanitizerInterface $sanitizedParams)
    {
        // We use the following variables in temp array
        $filter = $sanitizedParams->getString('filter');
        $displayId = $sanitizedParams->getInt('displayId');
        $layoutIds = $sanitizedParams->getIntArray('layoutId');
        $mediaIds = $sanitizedParams->getIntArray('mediaId');
        $type = $sanitizedParams->getString('type');
        $sortBy = $sanitizedParams->getString('sortBy');
        $tagsType = $sanitizedParams->getString('tagsType');
        $tags = $sanitizedParams->getString('tags');
        $exactTags = $sanitizedParams->getCheckbox('exactTags');

        $temp = ['filter', 'displayId', 'layoutIds', 'mediaIds', 'type', 'sortBy', 'tagsType', 'tags', 'exactTags'];

        $filterCriteria = [];
        foreach ($temp as $value) {
            $filterCriteria[$value] = $$value;
        }

        $schedule = '';
        if ($filter == 'daily') {
            $schedule = ReportSchedule::$SCHEDULE_DAILY;
            $filterCriteria['reportFilter'] = 'yesterday';
        } elseif ($filter == 'weekly') {
            $schedule = ReportSchedule::$SCHEDULE_WEEKLY;
            $filterCriteria['reportFilter'] = 'lastweek';
        } elseif ($filter == 'monthly') {
            $schedule = ReportSchedule::$SCHEDULE_MONTHLY;
            $filterCriteria['reportFilter'] = 'lastmonth';
        } elseif ($filter == 'yearly') {
            $schedule = ReportSchedule::$SCHEDULE_YEARLY;
            $filterCriteria['reportFilter'] = 'lastyear';
        }

        $filterCriteria['sendEmail'] = $sanitizedParams->getCheckbox('sendEmail');
        $filterCriteria['nonusers'] = $sanitizedParams->getString('nonusers');

        // Return
        return [
            'filterCriteria' => json_encode($filterCriteria),
            'schedule' => $schedule
        ];
    }

    /** @inheritdoc */
    public function generateSavedReportName(SanitizerInterface $sanitizedParams)
    {
        $saveAs = sprintf(__('%s report for ', ucfirst($sanitizedParams->getString('filter'))));

        switch ($sanitizedParams->getString('type')) {
            case 'layout':
                $saveAs .= 'Type: Layout. ';
                break;

            case 'media':
                $saveAs .= 'Type: Media. ';
                break;

            case 'widget':
                $saveAs .= 'Type: Widget. ';
                break;

            case 'event':
                $saveAs .= 'Type: Event. ';
                break;

            default:
                $saveAs .= 'Type: All. ';
                break;
        }

        $layoutIds = $sanitizedParams->getIntArray('layoutIds');
        if (isset($layoutIds)) {
            if (count($layoutIds) > 0) {
                $layouts = '';
                foreach ($layoutIds as $id) {
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
        }

        $mediaIds = $sanitizedParams->getIntArray('mediaIds');
        if (isset($mediaIds)) {
            if (count($mediaIds) > 0) {
                $medias = '';
                foreach ($mediaIds as $id) {
                    try {
                        $media = $this->mediaFactory->getById($id);
                        $name = $media->name;
                    } catch (NotFoundException $error) {
                        $name = 'Media not found';
                    }

                    $medias .= $name . ', ';
                }

                $saveAs .= 'Media: ' . $medias;
            }
        }

        $displayId = $sanitizedParams->getInt('displayId');
        if (!empty($displayId)) {
            // Get display
            try {
                $displayName = $this->displayFactory->getById($displayId)->display;
                $saveAs .= '(Display: '. $displayName . ')';
            } catch (NotFoundException $error) {
                $saveAs .= '(DisplayId: Not Found )';
            }
        }

        return $saveAs;
    }

    /** @inheritdoc */
    public function restructureSavedReportOldJson($result) // TODO
    {
        return [
            'periodStart' => $result['periodStart'],
            'periodEnd' => $result['periodEnd'],
            'table' => $result['result'],
        ];
    }

    /** @inheritdoc */
    public function getSavedReportResults($json, $savedReport)
    {
        // Get filter criteria
        $rs = $this->reportScheduleFactory->getById($savedReport->reportScheduleId, 1)->filterCriteria;
        $filterCriteria = json_decode($rs, true);

        $tagsType = $filterCriteria['tagsType'];
        $tags = $filterCriteria['tags'];
        $exactTags = ($filterCriteria['exactTags'] == 1) ? ' (exact match)': '';

        // Show filter criteria
        $metadata = [];
        if ($tags != null) {
            $metadata['filterInfo'] = 'Tags from: '. $this->tagsType[$tagsType]. ', Tags: '. $tags. $exactTags;
        }

        // Get Meta data
        $metadata['periodStart'] = $json['metadata']['periodStart'];
        $metadata['periodEnd'] = $json['metadata']['periodEnd'];
        $metadata['generatedOn'] = Carbon::createFromTimestamp($savedReport->generatedOn)
            ->format(DateFormatHelper::getSystemFormat());
        $metadata['title'] = $savedReport->saveAs;

        // Report result object
        return new ReportResult($metadata, $json['table'], $json['recordTotal']);
    }

    /** @inheritdoc */
    public function getResults(SanitizerInterface $sanitizedParams)
    {
        $displayId = $sanitizedParams->getInt('displayId');
        $layoutIds = $sanitizedParams->getIntArray('layoutIds', ['default' => [] ]);
        $mediaIds = $sanitizedParams->getIntArray('mediaIds', ['default' => [] ]);
        $type = strtolower($sanitizedParams->getString('type'));
        $tags = $sanitizedParams->getString('tags');
        $tagsType = $sanitizedParams->getString('tagsType');
        $exactTags = $sanitizedParams->getCheckbox('exactTags');

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

            if (count($displayIds) <= 0) {
                throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');
            }

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

        // web
        if ($sanitizedParams->getString('sortBy') == null) {
            // Sorting?
            $sortOrder = $this->gridRenderSort($sanitizedParams);
            $columns = [];

            if (is_array($sortOrder)) {
                $columns = $sortOrder;
            }

            // Paging
            $start = intval($sanitizedParams->getInt('start'), 0);
            $length = $sanitizedParams->getInt('length', ['default' => 10]);
        } else {
            // xtr
            $start = 0;
            $length = -1;
            $sortBy = $sanitizedParams->getString('sortBy');

            $columns = ($sortBy == '') ? ['widgetId'] : [$sortBy];
        }

        //
        // From and To Date Selection
        // --------------------------
        // Our report has a range filter which determins whether or not the user has to enter their own from / to dates
        // check the range filter first and set from/to dates accordingly.
        $reportFilter = $sanitizedParams->getString('reportFilter');

        // Use the current date as a helper
        $now = Carbon::now();

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
                $fromDt = $now->copy()->locale(Translate::GetLocale())->startOfWeek();
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
                $fromDt = $now->copy()->locale(Translate::GetLocale())->startOfWeek()->subWeek();
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
                $fromDt = $sanitizedParams->getDate('statsFromDt', ['default' => Carbon::now()->subDay()]);
                $fromDt->startOfDay();

                $toDt = $sanitizedParams->getDate('statsToDt', ['default' => Carbon::now()]);
                $toDt->startOfDay();

                $fromDtTime = $sanitizedParams->getString('statsFromDtTime');
                $toDtTime = $sanitizedParams->getString('statsToDtTime');

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
            $sanitizedRow = $this->sanitizer->getSanitizer($row);

            $widgetId = $sanitizedRow->getInt('widgetId');
            $widgetName = $sanitizedRow->getString('media');
            // If the media name is empty, and the widgetid is not, then we can assume it has been deleted.
            $widgetName = ($widgetName == '' &&  $widgetId != 0) ? __('Deleted from Layout') : $widgetName;
            $displayName = $sanitizedRow->getString('display');
            $layoutName = $sanitizedRow->getString('layout');

            $entry['type'] = $sanitizedRow->getString('type');
            $entry['displayId'] = $sanitizedRow->getInt('displayId');
            $entry['display'] = ($displayName != '') ? $displayName : __('Not Found');
            $entry['layoutId'] = $sanitizedRow->getInt('layoutId');
            $entry['layout'] = ($layoutName != '') ? $layoutName :  __('Not Found');
            $entry['widgetId'] = $sanitizedRow->getInt('widgetId');
            $entry['media'] = $widgetName;
            $entry['tag'] = $sanitizedRow->getString('tag');
            $entry['numberPlays'] = $sanitizedRow->getInt('numberPlays');
            $entry['duration'] = $sanitizedRow->getInt('duration');
            $entry['minStart'] = Carbon::createFromTimestamp($row['minStart'])->format(DateFormatHelper::getSystemFormat());
            $entry['maxEnd'] =  Carbon::createFromTimestamp($row['maxEnd'])->format(DateFormatHelper::getSystemFormat());
            $entry['mediaId'] = $sanitizedRow->getInt('mediaId');


            $rows[] = $entry;
        }

        // Set Meta data
        $metadata = [
            'periodStart' => $result['periodStart'],
            'periodEnd' => $result['periodEnd'],
        ];

        // Paging
        $recordsTotal = 0;
        if ($result['count'] > 0) {
            $recordsTotal = intval($result['totalStats']);
        }

        // ----
        // Table Only
        // Return data to build chart/table
        // This will get saved to a json file when schedule runs
        return new ReportResult(
            // metadata
            $metadata,
            // table rows
            $rows,
            // total records in table
            $recordsTotal
        );
    }

    /**
     * MySQL proof of play report
     * @param Carbon $fromDt The filter range from date
     * @param Carbon $toDt The filter range to date
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
                  (SELECT MAX(`layout`) AS layout 
                     FROM `layout` 
                        INNER JOIN `layouthistory`
                        ON `layout`.layoutId = `layouthistory`.layoutId
                    WHERE `layouthistory`.campaignId = `stat`.campaignId)
              ) AS Layout,
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

        if ($tags != '') {
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
        if (count($displayIds) > 0) {
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
                } else {
                    $body .= " ) ";
                }
            }
        }

        // Type filter
        if ($type == 'layout') {
            $body .= ' AND `stat`.type = \'layout\' ';
        } elseif ($type == 'media') {
            $body .= ' AND `stat`.type = \'media\' AND IFNULL(`media`.mediaId, 0) <> 0 ';
        } elseif ($type == 'widget') {
            $body .= ' AND `stat`.type = \'widget\' AND IFNULL(`widget`.widgetId, 0) <> 0 ';
        } elseif ($type == 'event') {
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

        $body .= '
            GROUP BY stat.type, 
                stat.tag, 
                display.Display, 
                stat.displayId, 
                stat.campaignId,
                layout.layout, 
                IFNULL(stat.mediaId, stat.widgetId), 
                IFNULL(`media`.name, IFNULL(`widgetoption`.value, `widget`.type)),
                stat.tag,
                stat.layoutId,
                stat.mediaId,
                stat.widgetId,
                stat.displayId 
        ';

        $order = '';
        if ($columns != null) {
            $order = 'ORDER BY ' . implode(',', $columns);
        }

        $limit= '';
        if (($length != null) && ($length != -1)) {
            $limit = ' LIMIT ' . $start . ', ' . $length;
        }

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
            'periodStart' => Carbon::createFromTimestamp($fromDt)->format(DateFormatHelper::getSystemFormat()),
            'periodEnd' => Carbon::createFromTimestamp($toDt)->format(DateFormatHelper::getSystemFormat()),
            'count' => count($rows),
            'totalStats' => isset($results[0]['total']) ? $results[0]['total'] : 0,
        ];
    }

    /**
     * MongoDB proof of play report
     * @param Carbon $fromDt The filter range from date
     * @param Carbon $toDt The filter range to date
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
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\GeneralException
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
            foreach (explode(',', $tags) as $tag) {
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
                    return array_map(function ($tag) {
                        return new \MongoDB\BSON\Regex('.*'.$tag. '.*', 'i');
                    }, $tagValue);
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
            $str = str_replace("`", "", str_replace(" DESC", "", $column));
            if (\strpos($column, 'DESC') !== false) {
                $cols[$str] = -1;
            } else {
                $cols[$str] = 1;
            }
        }

        // The selected column key gets stored in an array with value 1 or -1 (for DESC)
        $array = [];
        foreach ($cols as $k => $v) {
            if (array_search($k, $temp)) {
                $array[array_search($k, $temp)] = $v;
            }
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
            'periodStart' => Carbon::createFromTimestamp($fromDt->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()),
            'periodEnd' => Carbon::createFromTimestamp($toDt->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()),
            'count' => count($rows),
            'totalStats' => $totalStats,
        ];
    }
}
