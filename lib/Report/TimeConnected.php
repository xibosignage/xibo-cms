<?php

namespace Xibo\Report;

use Carbon\Carbon;
use Psr\Container\ContainerInterface;
use Xibo\Entity\ReportForm;
use Xibo\Entity\ReportResult;
use Xibo\Entity\ReportSchedule;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Translate;
use Xibo\Service\ReportServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class TimeConnected
 * @package Xibo\Report
 */
class TimeConnected implements ReportInterface
{
    use ReportDefaultTrait;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var ReportServiceInterface
     */
    private $reportService;

    /** @inheritdoc */
    public function setFactories(ContainerInterface $container)
    {
        $this->displayFactory = $container->get('displayFactory');
        $this->displayGroupFactory = $container->get('displayGroupFactory');
        $this->reportService = $container->get('reportService');

        return $this;
    }

    /** @inheritdoc */
    public function getReportEmailTemplate()
    {
        return 'timeconnected-email-template.twig';
    }

    /** @inheritdoc */
    public function getSavedReportTemplate()
    {
        return 'timeconnected-report-preview';
    }

    /** @inheritdoc */
    public function getReportForm()
    {
        $groups = [];
        $displays = [];

        foreach ($this->displayGroupFactory->query(['displayGroup'], ['isDisplaySpecific' => -1]) as $displayGroup) {
            /* @var \Xibo\Entity\DisplayGroup $displayGroup */

            if ($displayGroup->isDisplaySpecific == 1) {
                $displays[] = $displayGroup;
            } else {
                $groups[] = $displayGroup;
            }
        }

        return new ReportForm(
            'timeconnected-report-form',
            'timeconnected',
            'Display',
            [
                'displays' => $displays,
                'displayGroups' => $groups,
                'fromDateOneDay' => Carbon::now()->subSeconds(86400)->format(DateFormatHelper::getSystemFormat()),
                'toDate' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            ],
            __('Select a type and an item (i.e., layout/media/tag)')
        );
    }

    /** @inheritdoc */
    public function getReportScheduleFormData(SanitizerInterface $sanitizedParams)
    {
        $data = [];

        $data['formTitle'] = 'Add Report Schedule';

        $data['hiddenFields'] =  json_encode([
        ]);

        $data['reportName'] = 'timeconnected';

        $groups = [];
        $displays = [];
        foreach ($this->displayGroupFactory->query(['displayGroup'], ['isDisplaySpecific' => -1]) as $displayGroup) {
            /* @var \Xibo\Entity\DisplayGroup $displayGroup */

            if ($displayGroup->isDisplaySpecific == 1) {
                $displays[] = $displayGroup;
            } else {
                $groups[] = $displayGroup;
            }
        }
        $data['displays'] = $displays;
        $data['displayGroups'] = $groups;

        return [
            'template' => 'timeconnected-schedule-form-add',
            'data' => $data
        ];
    }

    /** @inheritdoc */
    public function setReportScheduleFormData(SanitizerInterface $sanitizedParams)
    {
        $filter = $sanitizedParams->getString('filter');
        $groupByFilter = $sanitizedParams->getString('groupByFilter');
        $displayGroupIds = $sanitizedParams->getIntArray('displayGroupIds');
        $hiddenFields = json_decode($sanitizedParams->getString('hiddenFields'), true);

        $filterCriteria['displayGroupIds'] = $displayGroupIds;
        $filterCriteria['filter'] = $filter;

        $schedule = '';
        if ($filter == 'daily') {
            $schedule = ReportSchedule::$SCHEDULE_DAILY;
            $filterCriteria['reportFilter'] = 'yesterday';
            $filterCriteria['groupByFilter'] = $groupByFilter;
        } elseif ($filter == 'weekly') {
            $schedule = ReportSchedule::$SCHEDULE_WEEKLY;
            $filterCriteria['reportFilter'] = 'lastweek';
            $filterCriteria['groupByFilter'] = $groupByFilter;
        } elseif ($filter == 'monthly') {
            $schedule = ReportSchedule::$SCHEDULE_MONTHLY;
            $filterCriteria['reportFilter'] = 'lastmonth';
            $filterCriteria['groupByFilter'] = $groupByFilter;
        } elseif ($filter == 'yearly') {
            $schedule = ReportSchedule::$SCHEDULE_YEARLY;
            $filterCriteria['reportFilter'] = 'lastyear';
            $filterCriteria['groupByFilter'] = $groupByFilter;
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
        return sprintf(__('%s report for Display', ucfirst($sanitizedParams->getString('filter'))));
    }

    /** @inheritdoc */
    public function restructureSavedReportOldJson($result)
    {
        return $result;
    }

    /** @inheritdoc */
    public function getSavedReportResults($json, $savedReport)
    {
        // Report result object
        return new ReportResult(
            [
                'periodStart' => $json['metadata']['periodStart'],
                'periodEnd' => $json['metadata']['periodEnd'],
                'generatedOn' => Carbon::createFromTimestamp($savedReport->generatedOn)
                    ->format(DateFormatHelper::getSystemFormat()),
                'title' => $savedReport->saveAs,
            ],
            $json['table'],
            0,
            $json['chart'],
            $json['hasChartData']
        );
    }

    /** @inheritdoc */
    public function getResults(SanitizerInterface $sanitizedParams)
    {
        $campaignId = $sanitizedParams->getInt('campaignId');
        $type = strtolower($sanitizedParams->getString('type'));
        $layoutId = $sanitizedParams->getInt('layoutId');
        $mediaId = $sanitizedParams->getInt('mediaId');
        $eventTag = $sanitizedParams->getString('eventTag');
        $displayGroupIds = $sanitizedParams->getIntArray('displayGroupIds', ['default' => [] ]);

        $accessibleDisplayIds = [];
        $displayIds = [];

        // Get an array of display id this user has access to.
        foreach ($this->displayFactory->query() as $display) {
            $accessibleDisplayIds[] = $display->displayId;
        }

        if (count($displayGroupIds) > 0) {
            foreach ($displayGroupIds as $displayGroupId) {
                // Get all displays by Display Group
                $displays = $this->displayFactory->getByDisplayGroupId($displayGroupId);
                foreach ($displays as $display) {
                    if (in_array($display->displayId, $accessibleDisplayIds)) { // User has access to the display
                        $displayIds[] = $display->displayId;
                    }
                }
            }
        } else {
            $displayIds = $accessibleDisplayIds;
        }

        if (count($displayIds) <= 0) {
            throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');
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

                $toDt = $sanitizedParams->getDate('statsToDt', ['default' =>  Carbon::now()]);
                $toDt->addDay()->startOfDay();

                // What if the fromdt and todt are exactly the same?
                // in this case assume an entire day from midnight on the fromdt to midnight on the todt (i.e. add a day to the todt)
                if ($fromDt == $toDt) {
                    $toDt->addDay();
                }

                // No need to execute the query if fromdt/todt range is not correct
                if ($fromDt > $toDt) {
                    return [];
                }

                break;
        }

        // Use the group by filter provided
        // NB: this differs from the Summary Report where we set the group by according to the range selected
        $groupByFilter = $sanitizedParams->getString('groupByFilter');

        //
        // Get Results!
        // with keys "result", "periods", "periodStart", "periodEnd"
        // -------------
        $result = $this->getTimeDisconnectedMySql($fromDt, $toDt, $groupByFilter, $displayIds, $campaignId, $type, $layoutId, $mediaId, $eventTag);

        //
        // Output Results
        // --------------
        $displayIdsArrayChunk = array_chunk($displayIds, 4);

        // Fill  Period Data  with Displays
        $timeConnected = [];
        foreach ($result['periods'] as $resPeriods) {
            foreach ($displayIdsArrayChunk as $key => $display) {
                foreach ($display as $displayId) {
                    $temp = $resPeriods['customLabel'];
                    if (empty($timeConnected[$temp][$displayId]['percent'])) {
                        $timeConnected[$key][$temp][$displayId]['percent'] = 100;
                    }
                    if (empty($timeConnected[$temp][$displayId]['label'])) {
                        $timeConnected[$key][$temp][$displayId]['label'] = $resPeriods['customLabel'];
                    }

                    foreach ($result['result'] as $res) {
                        if ($res['displayId'] == $displayId && $res['customLabel'] == $resPeriods['customLabel']) {
                            $timeConnected[$key][$temp][$displayId]['percent'] =  100 - round($res['percent'], 2);
                            $timeConnected[$key][$temp][$displayId]['label'] = $resPeriods['customLabel'];
                        } else {
                            continue;
                        }
                    }
                }
            }
        }

        $displays = [];
        foreach ($displayIdsArrayChunk as $key => $display) {
            foreach ($display as $displayId) {
                $displays[$key][$displayId] = $this->displayFactory->getById($displayId)->display;
            }
        }

        // Return data to build chart
        return new ReportResult(
            [
                'periodStart' => Carbon::createFromTimestamp($fromDt->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()),
                'periodEnd' => Carbon::createFromTimestamp($toDt->toDateTime()->format('U'))->format(DateFormatHelper::getSystemFormat()),
            ],
            [
                'timeConnected' => $timeConnected,
                'displays' => $displays
            ],
            0,
            [],
            true //TODO why true
        );
    }

    /**
     * MySQL distribution report
     * @param Carbon $fromDt The filter range from date
     * @param Carbon $toDt The filter range to date
     * @param string $groupByFilter Grouping, byhour, bydayofweek and bydayofmonth
     * @param $displayIds
     * @param $displayGroupIds
     * @param $type
     * @param $layoutId
     * @param $mediaId
     * @param $eventTag
     * @return array
     */
    private function getTimeDisconnectedMySql($fromDt, $toDt, $groupByFilter, $displayIds, $displayGroupIds, $type, $layoutId, $mediaId, $eventTag)
    {

        if ($groupByFilter == 'bydayofmonth') {
            $customLabel = 'Y-m-d (D)';
        } else {
            $customLabel = 'Y-m-d g:i A';
        }

        // Create periods covering the from/to dates
        // -----------------------------------------
        try {
            $periods = $this->getTemporaryPeriodsTable($fromDt, $toDt, $groupByFilter, 'temp_periods', $customLabel);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return [];
        }
        try {
            $periods2 = $this->getTemporaryPeriodsTable($fromDt, $toDt, $groupByFilter, 'temp_periods2', $customLabel);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return [];
        }

        // Join in stats
        // -------------
        $query = '
            SELECT periods.id,
               periods.label,
               periods.customLabel,
               display,
               displayId,
               SUM(duration) AS downtime,
               periods.end - periods.start AS periodDuration,
               SUM(duration) / (periods.end - periods.start) * 100 AS percent
          FROM ' . $periods2 . ' AS periods
            INNER JOIN (
                SELECT id,
                    label,
                    customLabel,
                    display,
                    displayId,
                    GREATEST(periods.start, down.start) AS actualStart,
                    LEAST(periods.end, down.end) AS actualEnd,
                    LEAST(periods.end, down.end) - GREATEST(periods.start, down.start) AS duration,
                    periods.end - periods.start AS periodDuration,
                    (LEAST(periods.end, down.end) - GREATEST(periods.start, down.start)) / (periods.end - periods.start) * 100 AS percent
                  FROM ' . $periods . ' AS periods
                    LEFT OUTER JOIN (
                        SELECT start,
                            IFNULL(end, UNIX_TIMESTAMP()) AS end,
                            displayevent.displayId,
                            display.display
                          FROM displayevent
                            INNER JOIN display
                            ON display.displayId = displayevent.displayId
                          WHERE display.displayID IN (' . implode(',', $displayIds) . ')
                    ) down
                    ON down.start < periods.`end`
                        AND down.end > periods.`start`
            ) joined
            ON joined.customLabel = periods.customLabel
        GROUP BY periods.id,
             periods.start,
             periods.end,
             joined.display
        ORDER BY id, display
            ';

        return [
            'result' => $this->getStore()->select($query, []),
            'periods' => $this->getStore()->select('SELECT * from '.$periods, []),
            'periodStart' => $fromDt->format('Y-m-d H:i:s'),
            'periodEnd' => $toDt->format('Y-m-d H:i:s')
        ];
    }
}
