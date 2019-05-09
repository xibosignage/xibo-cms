<?php

namespace Xibo\Report;

use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Factory\DisplayFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;

/**
 * Class SummaryReport
 * @package Xibo\Report
 */
class SummaryReport implements ReportInterface
{
    use ReportTrait;

    /**
     * Report Constructor.
     * @param \Xibo\Helper\ApplicationState $state
     * @param StorageServiceInterface $store
     * @param TimeSeriesStoreInterface $timeSeriesStore
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param DateServiceInterface $date
     * @param SanitizerServiceInterface $sanitizer
     * @param DisplayFactory displayFactory
     * @param MediaFactory mediaFactory
     * @param LayoutFactory layoutFactory
     * @param SavedReportFactory savedReportFactory
     */
    public function __construct($state, $store, $timeSeriesStore, $log, $config, $date, $sanitizer, $displayFactory, $mediaFactory, $layoutFactory, $savedReportFactory)
    {
        $this->setCommonDependencies($state, $store, $timeSeriesStore, $log, $config, $date, $sanitizer, $displayFactory, $mediaFactory, $layoutFactory, $savedReportFactory);
    }

    /** @inheritdoc */
    public function getReportForm()
    {
        return 'summary-report-form';
    }

    /** @inheritdoc */
    public function generateSavedReportName($filterCriteria)
    {

        if ($filterCriteria['type'] == 'layout') {
            $layout = $this->layoutFactory->getById($filterCriteria['layoutId']);
            $saveAs = ucfirst($filterCriteria['filter']). ' report for Layout '. $layout->layout;

        } else {
            $media = $this->mediaFactory->getById($filterCriteria['mediaId']);
            $saveAs = ucfirst($filterCriteria['filter']). ' report for Media '. $media->name;
        }

        return $saveAs;
    }

    /** @inheritdoc */
    public function getSavedReportResults($savedreportId)
    {

        $savedReport = $this->savedReportFactory->getById($savedreportId);

        // Open a zipfile and read the json
        $zipFile = $this->getConfig()->getSetting('LIBRARY_LOCATION') . $savedReport->storedAs;

        // Do some pre-checks on the arguments we have been provided
        if (!file_exists($zipFile))
            throw new \InvalidArgumentException(__('File does not exist'));

        // Open the Zip file
        $zip = new \ZipArchive();
        if (!$zip->open($zipFile))
            throw new \InvalidArgumentException(__('Unable to open ZIP'));

        // Get the reportscheduledetails
        $results = json_decode($zip->getFromName('reportschedule.json'), true);

        // Return data to build chart
        return [
            'template' => 'summary-report-preview',
            'saveAs' => $savedReport->saveAs,
            'labels' => json_encode($results['labels']),
            'countData' => json_encode($results['countData']),
            'durationData' => json_encode($results['durationData']),
            'backgroundColor' => json_encode($results['backgroundColor']),
            'borderColor' => json_encode($results['borderColor']),
        ];
    }

    /** @inheritdoc */
    public function getResults($filterCriteria)
    {

        $fromDt = $this->getSanitizer()->getDate('fromDt', $this->getSanitizer()->getDate('statsFromDt', $this->getDate()->parse()->addDay(-1)));
        $toDt = $this->getSanitizer()->getDate('toDt', $this->getSanitizer()->getDate('statsToDt', $this->getDate()->parse()));

        $type = strtolower($this->getSanitizer()->getString('type', $filterCriteria));
        $layoutId = $this->getSanitizer()->getInt('layoutId', $filterCriteria);
        $mediaId = $this->getSanitizer()->getInt('mediaId', $filterCriteria);
        $reportFilter = $this->getSanitizer()->getString('reportFilter', $filterCriteria);
        $groupByFilter = $this->getSanitizer()->getString('groupByFilter', $filterCriteria);

        // What if the fromdt and todt are exactly the same?
        // in this case assume an entire day from midnight on the fromdt to midnight on the todt (i.e. add a day to the todt)
        if ($fromDt == $toDt) {
            $toDt->addDay(1);
        }

        $diffInDays = $toDt->diffInDays($fromDt);

        // Format param dates
        $fromDt = $this->getDate()->getLocalDate($fromDt);
        $toDt = $this->getDate()->getLocalDate($toDt);

        // Get an array of display id this user has access to.
        $displayIds = [];

        foreach ($this->displayFactory->query() as $display) {
            $displayIds[] = $display->displayId;
        }

        if (count($displayIds) <= 0)
            throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');

        $labels = [];
        $countData = [];
        $durationData = [];
        $backgroundColor = [];
        $borderColor = [];

        // Call the time series interface get daily summary report
        $result =  $this->getTimeSeriesStore()->getDailySummaryReport($displayIds, $diffInDays, $type, $layoutId, $mediaId, $reportFilter, $groupByFilter, $fromDt, $toDt);

        foreach ($result as $row) {
            // Label
            $tsLabel = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s');

            if ($reportFilter == '') {
                $tsLabel = $tsLabel->format('Y-m-d'); // as dates. by day (default)

                if ($groupByFilter == 'byweek') {
                    $weekEnd = $this->getDate()->parse($row['weekEnd'], 'Y-m-d H:i:s')->format('Y-m-d');
                    $weekNo = $row['weekNo'];

                    if ($weekEnd >= $toDt){
                        $weekEnd = $this->getDate()->parse($toDt, 'Y-m-d H:i:s')->format('Y-m-d');
                    }
                    $tsLabel .= ' - ' . $weekEnd. ' (w'.$weekNo.')';
                } elseif ($groupByFilter == 'bymonth') {
                    $tsLabel = __($row['shortMonth']) . ' '. $row['yearDate'];

                }

            } elseif (($reportFilter == 'today') || ($reportFilter == 'yesterday')) {
                $tsLabel = $tsLabel->format('g:i A'); // hourly format (default)

            } elseif(($reportFilter == 'lastweek') || ($reportFilter == 'thisweek') ) {
                $tsLabel = $tsLabel->format('D'); // Mon, Tues, etc.  by day (default)

            } elseif (($reportFilter == 'thismonth') || ($reportFilter == 'lastmonth')) {
                $tsLabel = $tsLabel->format('Y-m-d'); // as dates. by day (default)

                if ($groupByFilter == 'byweek') {
                    $weekEnd = $this->getDate()->parse($row['weekEnd'], 'Y-m-d H:i:s')->format('Y-m-d');
                    $weekNo = $row['weekNo'];

                    $startInMonth = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s')->format('M');
                    $weekEndInMonth = $this->getDate()->parse($row['weekEnd'], 'Y-m-d H:i:s')->format('M');

                    if ($weekEndInMonth != $startInMonth){
                        $weekEnd = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s')->endOfMonth()->format('Y-m-d');
                    }

                    $tsLabel = [ $tsLabel . ' - ' . $weekEnd, ' (w'.$weekNo.')'];
                }

            }  elseif (($reportFilter == 'thisyear') || ($reportFilter == 'lastyear')) {
                $tsLabel = __($row['shortMonth']); // Jan, Feb, etc.  by month (default)

                if ($groupByFilter == 'byday') {
                    $tsLabel = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s')->format('Y-m-d');

                } elseif ($groupByFilter == 'byweek') {
                    $weekStart = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s')->format('M d');
                    $weekEnd = $this->getDate()->parse($row['weekEnd'], 'Y-m-d H:i:s')->format('M d');
                    $weekNo = $row['weekNo'];

                    $weekStartInYear = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s')->format('Y');
                    $weekEndInYear = $this->getDate()->parse($row['weekEnd'], 'Y-m-d H:i:s')->format('Y');

                    if ($weekEndInYear != $weekStartInYear){
                        $weekEnd = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s')->endOfYear()->format('M-d');
                    }
                    $tsLabel = $weekStart . ' - ' . $weekEnd. ' (w'.$weekNo.')';
                }

            }

            // Chart labels in xaxis
            $labels[] = $tsLabel;

            $backgroundColor[] = 'rgb(95, 186, 218, 0.6)';
            $borderColor[] = 'rgb(240,93,41, 0.8)';

            $count = $this->getSanitizer()->int($row['NumberPlays']);
            $countData[] = ($count == '') ? 0 : $count;

            $duration = $this->getSanitizer()->int($row['Duration']);
            $durationData[] = ($duration == '') ? 0 : $duration;
        }

        // Return data to build chart
        return [
            'labels' => $labels,
            'countData' => $countData,
            'durationData' => $durationData,
            'backgroundColor' => $backgroundColor,
            'borderColor' => $borderColor,
        ];

    }

}