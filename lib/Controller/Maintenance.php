<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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


use Xibo\Entity\Task;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\TaskFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Maintenance
 * @package Xibo\Controller
 */
class Maintenance extends Base
{
    /** @var TaskFactory */
    private $taskFactory;

    /** @var  StorageServiceInterface */
    private $store;

    /** @var  MediaFactory */
    private $mediaFactory;

    /** @var  LayoutFactory */
    private $layoutFactory;

    /** @var  WidgetFactory */
    private $widgetFactory;

    /** @var  DisplayGroupFactory */
    private $displayGroupFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var  ScheduleFactory */
    private $scheduleFactory;

    /** @var  PlayerVersionFactory */
    private $playerVersionFactory;

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
     * @param TaskFactory $taskFactory
     * @param MediaFactory $mediaFactory
     * @param LayoutFactory $layoutFactory
     * @param WidgetFactory $widgetFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @param DisplayFactory $displayFactory
     * @param ScheduleFactory $scheduleFactory
     * @param PlayerVersionFactory $playerVersionFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $taskFactory, $mediaFactory, $layoutFactory, $widgetFactory, $displayGroupFactory, $displayFactory, $scheduleFactory, $playerVersionFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);
        $this->taskFactory = $taskFactory;
        $this->store = $store;
        $this->mediaFactory = $mediaFactory;
        $this->layoutFactory = $layoutFactory;
        $this->widgetFactory = $widgetFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->displayFactory = $displayFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->playerVersionFactory = $playerVersionFactory;
    }

    /**
     * Run Maintenance through the WEB portal
     */
    public function run()
    {
        // Output HTML Headers
        print '<html>';
        print '  <head>';
        print '    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';
        print '    <title>Maintenance</title>';
        print '  </head>';
        print '<body>';

        // Should the Scheduled Task script be running at all?
        if ($this->getConfig()->getSetting("MAINTENANCE_ENABLED") == "Off") {
            print "<h1>" . __("Maintenance Disabled") . "</h1>";
            print __("Maintenance tasks are disabled at the moment. Please enable them in the &quot;Settings&quot; dialog.");

        } else {
            $quick = ($this->getSanitizer()->getCheckbox('quick') == 1);

            // Set defaults that don't match on purpose!
            $key = 1;
            $aKey = 2;
            $pKey = 3;

            if ($this->getConfig()->getSetting("MAINTENANCE_ENABLED")=="Protected") {
                // Check that the magic parameter is set
                $key = $this->getConfig()->getSetting("MAINTENANCE_KEY");

                // Get key from arguments
                $pKey = $this->getSanitizer()->getString('key');
            }

            if (($aKey == $key) || ($pKey == $key) || ($this->getConfig()->getSetting("MAINTENANCE_ENABLED")=="On")) {

                // Are we full maintenance?
                if (!$quick) {
                    $this->runTask('MaintenanceDailyTask');
                }

                // Always run quick tasks
                $this->runTask('MaintenanceRegularTask');
                $this->runTask('EmailNotificationsTask');
            }
            else {
                print __("Maintenance key invalid.");
            }
        }

        // Output HTML Footers
        print "\n  </body>\n";
        print "</html>";

        $this->getLog()->debug('Maintenance Complete');

        // No output
        $this->setNoOutput(true);
    }

    /**
     * Run task
     * @param $class
     */
    private function runTask($class)
    {
        /** @var \Xibo\Controller\Task $taskController */
        $taskController = $this->getApp()->container->get('\Xibo\Controller\Task');
        $taskController->setApp($this->getApp());

        $task = $this->taskFactory->getByClass('\Xibo\XTR\\' . $class);

        // Check we aren't already running
        if ($task->status == Task::$STATUS_RUNNING) {
            echo __('Task already running');

        } else {
            // Hand off to the task controller
            $taskController->run($task->taskId);

            // Echo the task output
            $task = $this->taskFactory->getById($task->taskId);
            echo \Parsedown::instance()->text($task->lastRunMessage);
        }
    }

    /**
     * Tidy Library Form
     */
    public function tidyLibraryForm()
    {
        $this->getState()->template = 'maintenance-form-tidy';
        $this->getState()->setData([
            'help' => $this->getHelp()->link('Settings', 'TidyLibrary')
        ]);
    }

    /**
     * Tidies up the library
     */
    public function tidyLibrary()
    {
        $tidyOldRevisions = $this->getSanitizer()->getCheckbox('tidyOldRevisions');
        $cleanUnusedFiles = $this->getSanitizer()->getCheckbox('cleanUnusedFiles');
        $tidyGenericFiles = $this->getSanitizer()->getCheckbox('tidyGenericFiles');

        if ($this->getConfig()->getSetting('SETTING_LIBRARY_TIDY_ENABLED') != 1)
            throw new AccessDeniedException(__('Sorry this function is disabled.'));

        // Also run a script to tidy up orphaned media in the library
        $library = $this->getConfig()->getSetting('LIBRARY_LOCATION');
        $this->getLog()->debug('Library Location: ' . $library);

        // Remove temporary files
        $this->getApp()->container->get('\Xibo\Controller\Library')->removeTempFiles();

        $media = array();
        $unusedMedia = array();
        $unusedRevisions = array();

        // DataSets with library images
        $dataSetSql = '
            SELECT dataset.dataSetId, datasetcolumn.heading
              FROM dataset
                INNER JOIN datasetcolumn
                ON datasetcolumn.DataSetID = dataset.DataSetID
             WHERE DataTypeID = 5 AND DataSetColumnTypeID <> 2;
        ';

        $dataSets = $this->store->select($dataSetSql, []);

        // Run a query to get an array containing all of the media in the library
        // this must contain ALL media, so that we can delete files in the storage that aren;t in the table
        $sql = '
            SELECT media.mediaid, media.storedAs, media.type, media.isedited,
                SUM(CASE WHEN IFNULL(lkwidgetmedia.widgetId, 0) = 0 THEN 0 ELSE 1 END) AS UsedInLayoutCount,
                SUM(CASE WHEN IFNULL(lkmediadisplaygroup.mediaId, 0) = 0 THEN 0 ELSE 1 END) AS UsedInDisplayCount,
                SUM(CASE WHEN IFNULL(layout.layoutId, 0) = 0 THEN 0 ELSE 1 END) AS UsedInBackgroundImageCount
        ';

        if (count($dataSets) > 0) {
            $sql .= ' , SUM(CASE WHEN IFNULL(dataSetImages.mediaId, 0) = 0 THEN 0 ELSE 1 END) AS UsedInDataSetCount ';
        } else {
            $sql .= ' , 0 AS UsedInDataSetCount ';
        }

        $sql .= '
              FROM `media`
                LEFT OUTER JOIN `lkwidgetmedia`
                ON lkwidgetmedia.mediaid = media.mediaid
                LEFT OUTER JOIN `lkmediadisplaygroup`
                ON lkmediadisplaygroup.mediaid = media.mediaid
                LEFT OUTER JOIN `layout`
                ON `layout`.backgroundImageId = `media`.mediaId
         ';

        if (count($dataSets) > 0) {

            $sql .= ' LEFT OUTER JOIN (';

            $first = true;
            foreach ($dataSets as $dataSet) {

                if (!$first)
                    $sql .= ' UNION ALL ';

                $first = false;

                $dataSetId = $this->getSanitizer()->getInt('dataSetId', $dataSet);
                $heading = $this->getSanitizer()->getString('heading', $dataSet);

                $sql .= ' SELECT `' . $heading . '` AS mediaId FROM `dataset_' . $dataSetId . '`';
            }

            $sql .= ') dataSetImages 
                ON dataSetImages.mediaId = `media`.mediaId
            ';
        }

        $sql .= '
            GROUP BY media.mediaid, media.storedAs, media.type, media.isedited
        ';

        foreach ($this->store->select($sql, []) as $row) {
            $media[$row['storedAs']] = $row;

            $type = $this->getSanitizer()->getString('type', $row);

            // Ignore any module files or fonts
            if ($type == 'module' || $type == 'font' || $type == 'playersoftware' || ($type == 'genericfile' && $tidyGenericFiles != 1))
                continue;

            // Collect media revisions that aren't used
            if ($tidyOldRevisions && $row['UsedInLayoutCount'] <= 0 && $row['UsedInDisplayCount'] <= 0 && $row['UsedInBackgroundImageCount'] <= 0 && $row['UsedInDataSetCount'] <= 0 && $row['isedited'] > 0) {
                $unusedRevisions[$row['storedAs']] = $row;
            }
            // Collect any files that aren't used
            else if ($cleanUnusedFiles && $row['UsedInLayoutCount'] <= 0 && $row['UsedInDisplayCount'] <= 0 && $row['UsedInBackgroundImageCount'] <= 0 && $row['UsedInDataSetCount'] <= 0) {
                $unusedMedia[$row['storedAs']] = $row;
            }
        }

        $i = 0;

        // Library location
        $libraryLocation = $this->getConfig()->getSetting("LIBRARY_LOCATION");

        // Get a list of all media files
        foreach(scandir($library) as $file) {

            if ($file == '.' || $file == '..')
                continue;

            if (is_dir($library . $file))
                continue;

            // Ignore thumbnails
            if (strstr($file, 'tn_'))
                continue;

            // Ignore XLF files
            if (strstr($file, '.xlf'))
                continue;

            $i++;

            // Is this file in the system anywhere?
            if (!array_key_exists($file, $media)) {
                // Totally missing
                $this->getLog()->debug('Deleting file: ' . $file);

                // If not, delete it
                unlink($libraryLocation . $file);
            }
            else if (array_key_exists($file, $unusedRevisions)) {
                // It exists but isn't being used any more
                $this->getLog()->debug('Deleting unused revision media: ' . $media[$file]['mediaid']);

                $this->mediaFactory->getById($media[$file]['mediaid'])
                    ->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory, $this->displayFactory, $this->scheduleFactory, $this->playerVersionFactory)
                    ->delete();
            }
            else if (array_key_exists($file, $unusedMedia)) {
                // It exists but isn't being used any more
                $this->getLog()->debug('Deleting unused media: ' . $media[$file]['mediaid']);

                $this->mediaFactory->getById($media[$file]['mediaid'])
                    ->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory, $this->displayFactory, $this->scheduleFactory, $this->playerVersionFactory)
                    ->delete();
            }
            else {
                $i--;
            }
        }

        // Return
        $this->getState()->hydrate([
            'message' => __('Library Tidy Complete'),
            'data' => [
                'tidied' => $i
            ]
        ]);
    }
}