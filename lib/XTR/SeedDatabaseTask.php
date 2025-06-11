<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

namespace Xibo\XTR;

use Carbon\Carbon;
use Exception;
use Xibo\Entity\Display;
use Xibo\Entity\Schedule;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\FolderFactory;
use Xibo\Factory\FontFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\SyncGroupFactory;
use Xibo\Factory\TaskFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\Random;
use Xibo\Service\MediaServiceInterface;
use Xibo\Support\Exception\DuplicateEntityException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class SeedDatabaseTask
 * Run only once, by default disabled
 * @package Xibo\XTR
 */
class SeedDatabaseTask implements TaskInterface
{
    use TaskTrait;

    private ModuleFactory $moduleFactory;
    private WidgetFactory $widgetFactory;
    private LayoutFactory $layoutFactory;
    private CampaignFactory $campaignFactory;
    private TaskFactory $taskFactory;
    private DisplayFactory $displayFactory;
    private DataSetFactory $dataSetFactory;
    private DataSetColumnFactory $dataSetColumnFactory;
    private SyncGroupFactory $syncGroupFactory;
    private ScheduleFactory $scheduleFactory;
    private UserFactory $userFactory;
    private UserGroupFactory $userGroupFactory;
    private FontFactory $fontFactory;
    private MediaServiceInterface $mediaService;
    /** @var array The cache for layout */
    private array $layoutCache = [];
    private FolderFactory $folderFactory;
    private CommandFactory $commandFactory;
    private DisplayGroupFactory $displayGroupFactory;
    private MediaFactory $mediaFactory;
    private array $displayGroups;
    private array $displays;
    private array $layouts;
    private array $parentCampaigns = [];
    private array $syncGroups;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->moduleFactory = $container->get('moduleFactory');
        $this->widgetFactory = $container->get('widgetFactory');
        $this->layoutFactory = $container->get('layoutFactory');
        $this->campaignFactory = $container->get('campaignFactory');
        $this->taskFactory = $container->get('taskFactory');
        $this->displayFactory = $container->get('displayFactory');
        $this->displayGroupFactory = $container->get('displayGroupFactory');
        $this->mediaService = $container->get('mediaService');
        $this->userFactory = $container->get('userFactory');
        $this->userGroupFactory = $container->get('userGroupFactory');
        $this->fontFactory = $container->get('fontFactory');
        $this->dataSetFactory = $container->get('dataSetFactory');
        $this->dataSetColumnFactory = $container->get('dataSetColumnFactory');
        $this->syncGroupFactory = $container->get('syncGroupFactory');
        $this->scheduleFactory = $container->get('scheduleFactory');
        $this->folderFactory = $container->get('folderFactory');
        $this->commandFactory = $container->get('commandFactory');
        $this->mediaFactory = $container->get('mediaFactory');

        return $this;
    }

    /** @inheritdoc
     * @throws Exception
     */
    public function run()
    {
        // This task should only be run once
        $this->runMessage = '# ' . __('Seeding Database') . PHP_EOL . PHP_EOL;

        // Create display groups
        $this->createDisplayGroups();

        // Create displays
        $this->createDisplays();

        // Assign displays to display groups
        $this->assignDisplaysToDisplayGroups();

        // Import layouts
        $this->importLayouts();

        // Create campaign
        $this->createAdCampaigns();
        $this->createListCampaigns();

        // Create stats
        $this->createStats();

        // Create Schedules
        $this->createSchedules();

        // Create Sync Groups
        $this->createSyncGroups();
        $this->createSynchronizedSchedules();

        // Create User
        $this->createUsers();

        // Create Folders
        $this->createFolders();
        $this->createCommands();

        // Create bandwidth data display 1
        $this->createBandwidthReportData();

        // Create disconnected display event for yesterday for 10 minutes for display 1
        $this->createDisconnectedDisplayEvent();

        $this->runMessage .= ' - ' . __('Done.') . PHP_EOL . PHP_EOL;

        $this->log->info('Task completed');
        $this->appendRunMessage('Task completed');
    }

    /**
     */
    private function createDisplayGroups(): void
    {
        $displayGroups = [
            'POP Display Group',
            'Display Group 1',
            'Display Group 2',

            // Display groups for displaygroups.cy.js test
            'disp5_dispgrp',
        ];

        foreach ($displayGroups as $displayGroupName) {
            try {
                // Don't create if the display group exists
                $groups = $this->displayGroupFactory->query(null, ['displayGroup' => $displayGroupName]);
                if (count($groups) > 0) {
                    foreach ($groups as $displayGroup) {
                        $this->displayGroups[$displayGroup->displayGroup] = $displayGroup->getId();
                    }
                } else {
                    $displayGroup = $this->displayGroupFactory->createEmpty();
                    $displayGroup->displayGroup = $displayGroupName;
                    $displayGroup->userId = $this->userFactory->getSystemUser()->getId();
                    $displayGroup->save();
                    $this->store->commitIfNecessary();
                    // Cache
                    $this->displayGroups[$displayGroup->displayGroup] = $displayGroup->getId();
                }
            } catch (GeneralException $e) {
                $this->log->error('Error creating display group: '. $e->getMessage());
            }
        }
    }

    /**
     * @throws Exception
     */
    private function createDisplays(): void
    {
        // Create Displays
        $displays = [
            'POP Display 1' => ['license' => Random::generateString(12, 'seed'), 'licensed' => false,
                'clientType' => 'android', 'clientCode' => 400, 'clientVersion' => 4],
            'POP Display 2' => ['license' => Random::generateString(12, 'seed'), 'licensed' => false,
                'clientType' => 'android', 'clientCode' => 400, 'clientVersion' => 4],
            'List Campaign Display 1' => ['license' => Random::generateString(12, 'seed'), 'licensed' => true,
                'clientType' => 'android', 'clientCode' => 400, 'clientVersion' => 4],
            'List Campaign Display 2' => ['license' => Random::generateString(12, 'seed'), 'licensed' => true,
                'clientType' => 'android', 'clientCode' => 400, 'clientVersion' => 4],

            // Displays for displays.cy.js test
            'dis_disp1' => ['license' => 'dis_disp1', 'licensed' => true, 'clientType' => 'android', 'clientCode' => 400, 'clientVersion' => 4],
            'dis_disp2' => ['license' => 'dis_disp2', 'licensed' => true, 'clientType' => 'android', 'clientCode' => 400, 'clientVersion' => 4],
            'dis_disp3' => ['license' => 'dis_disp3', 'licensed' => false, 'clientType' => 'android', 'clientCode' => 400, 'clientVersion' => 4],
            'dis_disp4' => ['license' => 'dis_disp4', 'licensed' => true, 'clientType' => 'android', 'clientCode' => 400, 'clientVersion' => 4],
            'dis_disp5' => ['license' => 'dis_disp5', 'licensed' => true, 'clientType' => 'android', 'clientCode' => 400, 'clientVersion' => 4],

            // Displays for displaygroups.cy.js test
            'dispgrp_disp1' => ['license' => 'dispgrp_disp1', 'licensed' => true, 'clientType' => 'android', 'clientCode' => 400, 'clientVersion' => 4],
            'dispgrp_disp2' => ['license' => 'dispgrp_disp2', 'licensed' => true, 'clientType' => 'android', 'clientCode' => 400, 'clientVersion' => 4],
            'dispgrp_disp_dynamic1' => ['license' => 'dispgrp_disp_dynamic1', 'licensed' => true, 'clientType' => 'android', 'clientCode' => 400, 'clientVersion' => 4],
            'dispgrp_disp_dynamic2' => ['license' => 'dispgrp_disp_dynamic2', 'licensed' => true, 'clientType' => 'android', 'clientCode' => 400, 'clientVersion' => 4],


            // 6 displays for xmds
            'phpunitv7' => ['license' => 'PHPUnit7', 'licensed' => true, 'clientType' => 'android', 'clientCode' => 400, 'clientVersion' => 4],
            'phpunitwaiting' => ['license' => 'PHPUnitWaiting', 'licensed' => false, 'clientType' => 'android', 'clientCode' => 400, 'clientVersion' => 4],
            'phpunitv6' => ['license' => 'PHPUnit6', 'licensed' => true, 'clientType' => 'windows', 'clientCode' => 304, 'clientVersion' => 3],
            'phpunitv5' => ['license' => 'PHPUnit5', 'licensed' => true, 'clientType' => 'windows', 'clientCode' => 304, 'clientVersion' => 3],
            'phpunitv4' => ['license' => 'PHPUnit4', 'licensed' => true, 'clientType' => 'android', 'clientCode' => 217, 'clientVersion' => 2],
            'phpunitv3' => ['license' => 'PHPUnit3', 'licensed' => true, 'clientType' => 'android', 'clientCode' => 217, 'clientVersion' => 2],
        ];

        foreach ($displays as $displayName => $displayData) {
            try {
                // Don't create if the display exists
                $disps = $this->displayFactory->query(null, ['display' => $displayName]);
                if (count($disps) > 0) {
                    foreach ($disps as $display) {
                        // Cache
                        $this->displays[$display->display] = $display->displayId;
                    }
                } else {
                    $display = $this->displayFactory->createEmpty();
                    $display->display = $displayName;
                    $display->auditingUntil = 0;
                    $display->defaultLayoutId = $this->getConfig()->getSetting('DEFAULT_LAYOUT');
                    $display->license = $displayData['license'];
                    $display->licensed = $displayData['licensed'] ? 1 : 0; // Authorised?
                    $display->clientType = $displayData['clientType'];
                    $display->clientCode = $displayData['clientCode'];
                    $display->clientVersion = $displayData['clientVersion'];

                    $display->incSchedule = 0;
                    $display->clientAddress = '';

                    if (!$display->isDisplaySlotAvailable()) {
                        $display->licensed = 0;
                    }
                    $display->lastAccessed = Carbon::now()->format('U');
                    $display->loggedIn = 1;

                    $display->save(Display::$saveOptionsMinimum);
                    $this->store->commitIfNecessary();
                    // Cache
                    $this->displays[$display->display] = $display->displayId;
                }
            } catch (GeneralException $e) {
                $this->log->error('Error creating display: ' . $e->getMessage());
            }
        }
    }

    /**
     * @throws NotFoundException
     */
    private function assignDisplaysToDisplayGroups(): void
    {
        $displayGroup = $this->displayGroupFactory->getById($this->displayGroups['POP Display Group']);
        $displayGroup->load();

        $display = $this->displayFactory->getById($this->displays['POP Display 1']);

        try {
            $displayGroup->assignDisplay($display);
            $displayGroup->save();
        } catch (GeneralException $e) {
            $this->log->error('Error assign display to display group: '. $e->getMessage());
        }

        $this->store->commitIfNecessary();
    }

    /**
     * Import Layouts
     * @throws GeneralException
     */
    private function importLayouts(): void
    {
        $this->runMessage .= '## ' . __('Import Layout To Seed Database') . PHP_EOL;

        // Make sure the library exists
        $this->mediaService->initLibrary();

        // all layouts name and file name
        $layoutNames = [
            'dataset test ' => 'export-dataset-test.zip',
            'layout_with_8_items_dataset' => 'export-layout-with-8-items-dataset.zip',
            'Image test' => 'export-image-test.zip',
            'Layout for Schedule 1' => 'export-layout-for-schedule-1.zip',
            'List Campaign Layout 1' => 'export-list-campaign-layout-1.zip',
            'List Campaign Layout 2' => 'export-list-campaign-layout-2.zip',
            'POP Layout 1' => 'export-pop-layout-1.zip',

            // Layout for displaygroups.cy.js test
            'disp4_default_layout' => 'export-disp4-default-layout.zip',

            // Layout editor tests
            'Audio-Video-PDF' => 'export-audio-video-pdf.zip'
        ];

        // Get all layouts
        $importedLayouts = [];
        foreach ($this->layoutFactory->query() as $layout) {
            // cache
            if (array_key_exists($layout->layout, $layoutNames)) {
                $importedLayouts[] = $layoutNames[$layout->layout];
            }

            // Cache
            $this->layouts[trim($layout->layout)] = $layout->layoutId;
        }

        // Import a layout
        $folder = PROJECT_ROOT . '/tests/resources/seeds/layouts/';

        foreach (array_diff(scandir($folder), array('..', '.')) as $file) {
            // Check if the layout file has already been imported
            if (!in_array($file, $importedLayouts)) {
                if (stripos($file, '.zip')) {
                    try {
                        $layout = $this->layoutFactory->createFromZip(
                            $folder . '/' . $file,
                            null,
                            $this->userFactory->getSystemUser()->getId(),
                            false,
                            false,
                            true,
                            false,
                            true,
                            $this->dataSetFactory,
                            null,
                            $this->mediaService,
                            1
                        );

                        $layout->save([
                            'audit' => false,
                            'import' => true
                        ]);

                        if (!empty($layout->getUnmatchedProperty('thumbnail'))) {
                            rename($layout->getUnmatchedProperty('thumbnail'), $layout->getThumbnailUri());
                        }

                        $this->store->commitIfNecessary();
                        // Update Cache
                        $this->layouts[trim($layout->layout)] = $layout->layoutId;
                    } catch (Exception $exception) {
                        $this->log->error('Seed Database: Unable to import layout: ' . $file . '. E = ' . $exception->getMessage());
                        $this->log->debug($exception->getTraceAsString());
                    }
                }
            }
        }
    }

    /**
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws DuplicateEntityException
     */
    private function createAdCampaigns(): void
    {
        $layoutId = $this->layouts['POP Layout 1'];

        // Get All Ad Campaigns
        $campaigns = $this->campaignFactory->query(null, ['type' => 'ad']);
        foreach ($campaigns as $campaign) {
            $this->parentCampaigns[$campaign->campaign] = $campaign->getId();
        }

        if (!array_key_exists('POP Ad Campaign 1', $this->parentCampaigns)) {
            $campaign = $this->campaignFactory->create(
                'ad',
                'POP Ad Campaign 1',
                $this->userFactory->getSystemUser()->getId(),
                1
            );

            $campaign->targetType = 'plays';
            $campaign->target = 100;
            $campaign->listPlayOrder = 'round';

            try {
                // Assign the layout
                $campaign->assignLayout($layoutId);
                $campaign->save(['validate' => false, 'saveTags' => false]);
                $this->store->commitIfNecessary();
                // Cache
                $this->parentCampaigns[$campaign->campaign] = $campaign->getId();
            } catch (GeneralException $e) {
                $this->getLogger()->error('Save: ' . $e->getMessage());
            }
        }
    }

    /**
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws DuplicateEntityException
     */
    private function createListCampaigns(): void
    {
        $campaignName = 'Campaign for Schedule 1';

        // Get All List Campaigns
        $campaigns = $this->campaignFactory->query(null, ['type' => 'list']);
        foreach ($campaigns as $campaign) {
            $this->parentCampaigns[$campaign->campaign] = $campaign->getId();
        }

        if (!array_key_exists($campaignName, $this->parentCampaigns)) {
            $campaign = $this->campaignFactory->create(
                'list',
                $campaignName,
                $this->userFactory->getSystemUser()->getId(),
                1
            );
            $campaign->listPlayOrder = 'round';

            try {
                // Assign the layout
                $campaign->save(['validate' => false, 'saveTags' => false]);
                $this->store->commitIfNecessary();
                // Cache
                $this->parentCampaigns[$campaign->campaign] = $campaign->getId();
            } catch (GeneralException $e) {
                $this->getLogger()->error('Save: ' . $e->getMessage());
            }
        }
    }

    /**
     * @throws NotFoundException
     */
    private function createStats(): void
    {
        // Delete Stats
        $this->store->update('DELETE FROM stat WHERE displayId = :displayId', [
            'displayId' => $this->displays['POP Display 1']
        ]);

        // Get layout campaign Id
        $campaignId = $this->layoutFactory->getById($this->layouts['POP Layout 1'])->campaignId;
        $columns = 'type, statDate, scheduleId, displayId, campaignId, parentCampaignId, layoutId, mediaId, widgetId, `start`, `end`, tag, duration, `count`';
        $values = ':type, :statDate, :scheduleId, :displayId, :campaignId, :parentCampaignId, :layoutId, :mediaId, :widgetId, :start, :end, :tag, :duration, :count';

        // a layout stat for today
        try {
            $params = [
                'type' => 'layout',
                'statDate' => Carbon::now()->hour(12)->format('U'),
                'scheduleId' => 0,
                'displayId' => $this->displays['POP Display 1'],
                'campaignId' => $campaignId,
                'parentCampaignId' => $this->parentCampaigns['POP Ad Campaign 1'],
                'layoutId' => $this->layouts['POP Layout 1'],
                'mediaId' => null,
                'widgetId' => 0,
                'start' => Carbon::now()->hour(12)->format('U'),
                'end' => Carbon::now()->hour(12)->addSeconds(60)->format('U'),
                'tag' => null,
                'duration' => 60,
                'count' => 1,
            ];
            $this->store->insert('INSERT INTO `stat` (' . $columns . ') VALUES (' . $values . ')', $params);

            // a layout stat for lastweek
            $params = [
                'type' => 'layout',
                'statDate' => Carbon::now()->subWeek()->hour(12)->format('U'),
                'scheduleId' => 0,
                'displayId' => $this->displays['POP Display 1'],
                'campaignId' => $campaignId,
                'parentCampaignId' => $this->parentCampaigns['POP Ad Campaign 1'],
                'layoutId' => $this->layouts['POP Layout 1'],
                'mediaId' => null,
                'widgetId' => 0,
                'start' => Carbon::now()->subWeek()->hour(12)->format('U'),
                'end' => Carbon::now()->subWeek()->hour(12)->addSeconds(60)->format('U'),
                'tag' => null,
                'duration' => 60,
                'count' => 1,
            ];
            $this->store->insert('INSERT INTO `stat` (' . $columns . ') VALUES (' . $values . ')', $params);

            // Media stats
            $columns = 'type, statDate, scheduleId, displayId, campaignId, layoutId, mediaId, widgetId, `start`, `end`, tag, duration, `count`';
            $values = ':type, :statDate, :scheduleId, :displayId, :campaignId, :layoutId, :mediaId, :widgetId, :start, :end, :tag, :duration, :count';

            // Get Layout
            $layout = $this->layoutFactory->getById($this->layouts['POP Layout 1']);
            $layout->load();

            // Take a mediaId and widgetId of the layout
            foreach ($layout->getAllWidgets() as $widget) {
                $widgetId = $widget->widgetId;
                $mediaId = $widget->mediaIds[0];
                break;
            }

            // Get Media
            $media = $this->mediaFactory->getById($mediaId);

            // a media stat for today
            $params = [
                'type' => 'media',
                'statDate' => Carbon::now()->hour(12)->format('U'),
                'scheduleId' => 0,
                'displayId' => $this->displays['POP Display 1'],
                'campaignId' => $campaignId,
                'layoutId' => $this->layouts['POP Layout 1'],
                'mediaId' => $media->mediaId,
                'widgetId' => $widgetId,
                'start' => Carbon::now()->hour(12)->format('U'),
                'end' => Carbon::now()->hour(12)->addSeconds(60)->format('U'),
                'tag' => null,
                'duration' => 60,
                'count' => 1,
            ];
            $this->store->insert('INSERT INTO `stat` (' . $columns . ') VALUES (' . $values . ')', $params);

            // another media stat for today
            $params = [
                'type' => 'media',
                'statDate' => Carbon::now()->hour(12)->addSeconds(60)->format('U'),
                'scheduleId' => 0,
                'displayId' => $this->displays['POP Display 1'],
                'campaignId' => $campaignId,
                'layoutId' => $this->layouts['POP Layout 1'],
                'mediaId' => $media->mediaId,
                'widgetId' => $widgetId,
                'start' => Carbon::now()->hour(12)->addSeconds(60)->format('U'),
                'end' => Carbon::now()->hour(12)->addSeconds(120)->format('U'),
                'tag' => null,
                'duration' => 60,
                'count' => 1,
            ];
            $this->store->insert('INSERT INTO `stat` (' . $columns . ') VALUES (' . $values . ')', $params);

            // a media stat for lastweek
            // Last week stats -
            $params = [
                'type' => 'media',
                'statDate' => Carbon::now()->subWeek()->addDays(2)->hour(12)->format('U'),
                'scheduleId' => 0,
                'displayId' => $this->displays['POP Display 1'],
                'campaignId' => $campaignId,
                'layoutId' => $this->layouts['POP Layout 1'],
                'mediaId' => $media->mediaId,
                'widgetId' => $widgetId,
                'start' => Carbon::now()->subWeek()->hour(12)->format('U'),
                'end' => Carbon::now()->subWeek()->hour(12)->addSeconds(60)->format('U'),
                'tag' => null,
                'duration' => 60,
                'count' => 1,
            ];
            $this->store->insert('INSERT INTO `stat` (' . $columns . ') VALUES (' . $values . ')', $params);

            // another media stat for lastweek
            $params = [
                'type' => 'media',
                'statDate' => Carbon::now()->subWeek()->addDays(2)->hour(12)->addSeconds(60)->format('U'),
                'scheduleId' => 0,
                'displayId' => $this->displays['POP Display 1'],
                'campaignId' => $campaignId,
                'layoutId' => $this->layouts['POP Layout 1'],
                'mediaId' => $media->mediaId,
                'widgetId' => $widgetId,
                'start' => Carbon::now()->subWeek()->hour(12)->addSeconds(60)->format('U'),
                'end' => Carbon::now()->subWeek()->hour(12)->addSeconds(120)->format('U'),
                'tag' => null,
                'duration' => 60,
                'count' => 1,
            ];
            $this->store->insert('INSERT INTO `stat` (' . $columns . ') VALUES (' . $values . ')', $params);

            // an widget stat for today
            $params = [
                'type' => 'widget',
                'statDate' => Carbon::now()->hour(12)->format('U'),
                'scheduleId' => 0,
                'displayId' => $this->displays['POP Display 1'],
                'campaignId' => $campaignId,
                'layoutId' => $this->layouts['POP Layout 1'],
                'mediaId' => $media->mediaId,
                'widgetId' => $widgetId,
                'start' => Carbon::now()->hour(12)->format('U'),
                'end' => Carbon::now()->hour(12)->addSeconds(60)->format('U'),
                'tag' => null,
                'duration' => 60,
                'count' => 1,
            ];
            $this->store->insert('INSERT INTO `stat` (' . $columns . ') VALUES (' . $values . ')', $params);

            // a event stat for today
            $params = [
                'type' => 'event',
                'statDate' => Carbon::now()->hour(12)->format('U'),
                'scheduleId' => 0,
                'displayId' => $this->displays['POP Display 1'],
                'campaignId' => 0,
                'layoutId' => 0,
                'mediaId' => null,
                'widgetId' => 0,
                'start' => Carbon::now()->hour(12)->format('U'),
                'end' => Carbon::now()->hour(12)->addSeconds(60)->format('U'),
                'tag' => 'Event123',
                'duration' => 60,
                'count' => 1,
            ];
            $this->store->insert('INSERT INTO `stat` (' . $columns . ') VALUES (' . $values . ')', $params);
        } catch (GeneralException $e) {
            $this->getLogger()->error('Error inserting stats: '. $e->getMessage());
        }
        $this->store->commitIfNecessary();
    }

    private function createSchedules(): void
    {
        // Don't create if the schedule exists
        $schedules = $this->scheduleFactory->query(null, [
            'eventTypeId' => Schedule::$LAYOUT_EVENT,
            'campaignId' => $this->layouts['dataset test']
        ]);

        if (count($schedules) <= 0) {
            try {
                $schedule = $this->scheduleFactory->createEmpty();
                $schedule->userId = $this->userFactory->getSystemUser()->getId();
                $schedule->eventTypeId = Schedule::$LAYOUT_EVENT;
                $schedule->dayPartId = 2;
                $schedule->displayOrder = 0;
                $schedule->isPriority = 0;
                // Campaign Id
                $schedule->campaignId = $this->layouts['dataset test'];
                $schedule->syncTimezone = 0;
                $schedule->syncEvent = 0;
                $schedule->isGeoAware = 0;
                $schedule->maxPlaysPerHour = 0;

                $displays = $this->displayFactory->query(null, ['display' => 'phpunitv']);
                foreach ($displays as $display) {
                    $displayGroupId = $display->displayGroupId;
                    $schedule->assignDisplayGroup($this->displayGroupFactory->getById($displayGroupId));
                }
                $schedule->save(['notify' => false]);

                $this->store->commitIfNecessary();
            } catch (GeneralException $e) {
                $this->log->error('Error creating schedule : '. $e->getMessage());
            }
        }
    }

    private function createSyncGroups(): void
    {
        // Don't create if the sync group exists
        $syncGroups = $this->syncGroupFactory->query(null, [
            'eventTypeId' => Schedule::$LAYOUT_EVENT,
            'campaignId' => $this->layouts['dataset test']
        ]);

        if (count($syncGroups) > 0) {
            foreach ($syncGroups as $syncGroup) {
                // Cache
                $this->syncGroups[$syncGroup->name] = $syncGroup->getId();
            }
        } else {
            // Create a SyncGroup - SyncGroup name `Simple Sync Group`
            try {
                $syncGroup = $this->syncGroupFactory->createEmpty();
                $syncGroup->name = 'Simple Sync Group';
                $syncGroup->ownerId = $this->userFactory->getSystemUser()->getId();
                $syncGroup->syncPublisherPort = 9590;
                $syncGroup->folderId = 1;
                $syncGroup->permissionsFolderId = 1;
                $syncGroup->save();
                $this->store->update('UPDATE `display` SET `display`.syncGroupId = :syncGroupId WHERE `display`.displayId = :displayId', [
                    'syncGroupId' => $syncGroup->syncGroupId,
                    'displayId' => $this->displays['phpunitv6']
                ]);

                $this->store->update('UPDATE `display` SET `display`.syncGroupId = :syncGroupId WHERE `display`.displayId = :displayId', [
                    'syncGroupId' => $syncGroup->syncGroupId,
                    'displayId' => $this->displays['phpunitv7']
                ]);

                $syncGroup->leadDisplayId = $this->displays['phpunitv7'];
                $syncGroup->save();
                $this->store->commitIfNecessary();
                // Cache
                $this->syncGroups[$syncGroup->name] = $syncGroup->getId();
            } catch (GeneralException $e) {
                $this->log->error('Error creating sync group: '. $e->getMessage());
            }
        }
    }

    private function createSynchronizedSchedules(): void
    {
        // Don't create if the schedule exists
        $schedules = $this->scheduleFactory->query(null, [
            'eventTypeId' => Schedule::$SYNC_EVENT,
            'syncGroupId' => $this->syncGroups['Simple Sync Group']
        ]);

        if (count($schedules) <= 0) {
            try {
                $schedule = $this->scheduleFactory->createEmpty();
                $schedule->userId = $this->userFactory->getSystemUser()->getId();
                $schedule->eventTypeId = Schedule::$SYNC_EVENT;
                $schedule->dayPartId = 2;

                $schedule->displayOrder = 0;
                $schedule->isPriority = 0;

                // Campaign Id
                $schedule->campaignId = null;
                $schedule->syncTimezone = 0;
                $schedule->syncEvent = 1;
                $schedule->isGeoAware = 0;
                $schedule->maxPlaysPerHour = 0;
                $schedule->syncGroupId = $this->syncGroups['Simple Sync Group'];

                $displayV7 = $this->displayFactory->getById($this->displays['phpunitv7']);
                $schedule->assignDisplayGroup($this->displayGroupFactory->getById($displayV7->displayGroupId));
                $displayV6 = $this->displayFactory->getById($this->displays['phpunitv6']);
                $schedule->assignDisplayGroup($this->displayGroupFactory->getById($displayV6->displayGroupId));

                $schedule->save(['notify' => false]);
                $this->store->commitIfNecessary();
                // Update Sync Links
                $this->store->insert('INSERT INTO `schedule_sync` (`eventId`, `displayId`, `layoutId`)
            VALUES(:eventId, :displayId, :layoutId) ON DUPLICATE KEY UPDATE layoutId = :layoutId', [
                    'eventId' => $schedule->eventId,
                    'displayId' => $this->displays['phpunitv7'],
                    'layoutId' => $this->layouts['Image test']
                ]);

                $this->store->insert('INSERT INTO `schedule_sync` (`eventId`, `displayId`, `layoutId`)
            VALUES(:eventId, :displayId, :layoutId) ON DUPLICATE KEY UPDATE layoutId = :layoutId', [
                    'eventId' => $schedule->eventId,
                    'displayId' => $this->displays['phpunitv6'],
                    'layoutId' => $this->layouts['Image test']
                ]);
                $this->store->commitIfNecessary();
            } catch (GeneralException $e) {
                $this->log->error('Error creating sync schedule: '. $e->getMessage());
            }
        }
    }

    private function createUsers(): void
    {
        // Don't create if exists
        $users = $this->userFactory->query(null, [
            'exactUserName' => 'folder_user'
        ]);

        if (count($users) <= 0) {
            // Create a user - user name `Simple User`
            try {
                $user = $this->userFactory->create();
                $user->setChildAclDependencies($this->userGroupFactory);
                $user->userName = 'folder_user';
                $user->email = '';
                $user->homePageId = 'icondashboard.view';
                $user->libraryQuota = 20;
                $user->setNewPassword('password');
                $user->homeFolderId = 1;
                $user->userTypeId = 3;
                $user->isSystemNotification = 0;
                $user->isDisplayNotification = 0;
                $user->isPasswordChangeRequired = 0;
                $user->firstName = 'test';
                $user->lastName = 'user';
                $user->save();
                $this->store->commitIfNecessary();
            } catch (GeneralException $e) {
                $this->log->error('Error creating user: '. $e->getMessage());
            }
        }
    }

    private function createFolders(): void
    {
        $folders = [
            'ChildFolder', 'FolderHome', 'EmptyFolder', 'ShareFolder', 'FolderWithContent', 'FolderWithImage', 'MoveToFolder', 'MoveFromFolder'
        ];

        foreach ($folders as $folderName) {
            try {
                // Don't create if the folder exists
                $folds = $this->folderFactory->query(null, ['folderName' => $folderName]);
                if (count($folds) <= 0) {
                    $folder = $this->folderFactory->createEmpty();
                    $folder->text = $folderName;
                    $folder->parentId = 1;
                    $folder->children = '';

                    $folder->save();
                    $this->store->commitIfNecessary();
                }
            } catch (GeneralException $e) {
                $this->log->error('Error creating folder: '. $e->getMessage());
            }
        }

        // Place the media in folders
        $folderWithImages = [
            'MoveToFolder' => 'test12',
            'MoveFromFolder' => 'test34',
            'FolderWithContent' => 'media_for_not_empty_folder',
            'FolderWithImage' => 'media_for_search_in_folder'
        ];

        foreach ($folderWithImages as $folderName => $mediaName) {
            try {
                $folders = $this->folderFactory->query(null, ['folderName' => $folderName]);
                if (count($folders) == 1) {
                    $test12 = $this->mediaFactory->getByName($mediaName);
                    $test12->folderId = $folders[0]->getId(); // Get the folder id of FolderHome
                    $test12->save();
                    $this->store->commitIfNecessary();
                }
            } catch (GeneralException $e) {
                $this->log->error('Error moving media ' . $mediaName . ' to the folder: ' . $folderName . ' ' . $e->getMessage());
            }
        }
    }

    private function createBandwidthReportData(): void
    {
        // Check if the record exists
        $monthU = Carbon::now()->startOfDay()->hour(12)->format('U');
        $record = $this->store->select('SELECT * FROM bandwidth WHERE type = 8 AND displayId = :displayId AND month = :month', [
            'displayId' => $this->displays['POP Display 1'],
            'month' => $monthU
        ]);

        if (count($record) <= 0) {
            $this->store->insert('INSERT INTO `bandwidth` (Month, Type, DisplayID, Size) VALUES (:month, :type, :displayId, :size)', [
                'month' => $monthU,
                'type' => 8,
                'displayId' => $this->displays['POP Display 1'],
                'size' => 200
            ]);
            $this->store->commitIfNecessary();
        }
    }

    private function createDisconnectedDisplayEvent(): void
    {
        // Delete if the record exists
        $date = Carbon::now()->subDay()->format('U');
        $this->store->update('DELETE FROM displayevent WHERE displayId = :displayId', [
            'displayId' => $this->displays['POP Display 1']
        ]);

        $this->store->insert('INSERT INTO `displayevent` (eventDate, start, end, displayID) VALUES (:eventDate, :start, :end, :displayId)', [
            'eventDate' => $date,
            'start' => $date,
            'end' => Carbon::now()->subDay()->addSeconds(600)->format('U'),
            'displayId' => $this->displays['POP Display 1']
        ]);
        $this->store->commitIfNecessary();
    }

    private function createCommands()
    {
        $commandName = 'Set Timezone';

        // Don't create if exists
        $commands = $this->commandFactory->query(null, [
            'command' => $commandName
        ]);

        if (count($commands) <= 0) {
            // Create a user - user name `Simple User`
            try {
                $command = $this->commandFactory->create();
                $command->command = $commandName;
                $command->description = 'a command to test schedule';
                $command->code = 'TIMEZONE';
                $command->userId = $this->userFactory->getSystemUser()->getId();
                $command->createAlertOn = 'never';
                $command->save();
                $this->store->commitIfNecessary();
            } catch (GeneralException $e) {
                $this->log->error('Error creating command: '. $e->getMessage());
            }
        }
    }
}
