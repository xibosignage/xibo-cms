<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Xibo\Controller\Display;
use Xibo\Event\DisplayGroupLoadEvent;
use Xibo\Event\MaintenanceRegularEvent;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Profiler;
use Xibo\Helper\Status;
use Xibo\Helper\WakeOnLan;
use Xibo\Service\MediaServiceInterface;
use Xibo\Support\Exception\GeneralException;

/**
 * Class MaintenanceRegularTask
 * @package Xibo\XTR
 */
class MaintenanceRegularTask implements TaskInterface
{
    use TaskTrait;

    /** @var Display */
    private $displayController;

    /** @var MediaServiceInterface */
    private $mediaService;

    /** @var DisplayFactory */
    private $displayFactory;

    /** @var DisplayGroupFactory */
    private $displayGroupFactory;

    /** @var NotificationFactory */
    private $notificationFactory;

    /** @var UserGroupFactory */
    private $userGroupFactory;

    /** @var LayoutFactory */
    private $layoutFactory;

    /** @var PlaylistFactory */
    private $playlistFactory;

    /** @var ModuleFactory */
    private $moduleFactory;

    /** @var  \Xibo\Helper\SanitizerService */
    private $sanitizerService;
    /**
     * @var ScheduleFactory
     */
    private $scheduleFactory;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->displayController = $container->get('\Xibo\Controller\Display');
        $this->mediaService = $container->get('mediaService');

        $this->displayFactory = $container->get('displayFactory');
        $this->displayGroupFactory = $container->get('displayGroupFactory');
        $this->notificationFactory = $container->get('notificationFactory');
        $this->userGroupFactory = $container->get('userGroupFactory');
        $this->layoutFactory = $container->get('layoutFactory');
        $this->playlistFactory = $container->get('playlistFactory');
        $this->moduleFactory = $container->get('moduleFactory');
        $this->sanitizerService = $container->get('sanitizerService');
        $this->scheduleFactory = $container->get('scheduleFactory');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $this->runMessage = '# ' . __('Regular Maintenance') . PHP_EOL . PHP_EOL;

        $this->displayDownEmailAlerts();

        $this->licenceSlotValidation();

        $this->wakeOnLan();

        $this->updatePlaylistDurations();

        $this->buildLayouts();

        $this->tidyLibrary();

        $this->checkLibraryUsage();

        $this->checkOverRequestedFiles();

        $this->publishLayouts();

        $this->assessDynamicDisplayGroups();

        $this->tidyAdCampaignSchedules();

        $this->assertXmrKey();

        // Dispatch an event so that consumers can hook into regular maintenance.
        $event = new MaintenanceRegularEvent();
        $this->getDispatcher()->dispatch($event, MaintenanceRegularEvent::$NAME);
        foreach ($event->getMessages() as $message) {
            $this->appendRunMessage($message);
        }
    }

    /**
     * Display Down email alerts
     *  - just runs validate displays
     */
    private function displayDownEmailAlerts()
    {
        $this->runMessage .= '## ' . __('Email Alerts') . PHP_EOL;

        $this->displayController->validateDisplays($this->displayFactory->query());

        $this->appendRunMessage(__('Done'));
    }

    /**
     * Licence Slot Validation
     */
    private function licenceSlotValidation()
    {
        $maxDisplays = $this->config->getSetting('MAX_LICENSED_DISPLAYS');

        if ($maxDisplays > 0) {
            $this->runMessage .= '## ' . __('Licence Slot Validation') . PHP_EOL;

            // Get a list of all displays
            try {
                $dbh = $this->store->getConnection();
                $sth = $dbh->prepare('SELECT displayId, display FROM `display` WHERE licensed = 1 ORDER BY lastAccessed');
                $sth->execute();

                $displays = $sth->fetchAll(\PDO::FETCH_ASSOC);

                if (count($displays) > $maxDisplays) {
                    // :(
                    // We need to un-licence some displays
                    $difference = count($displays) - $maxDisplays;

                    $this->log->alert(sprintf('Max %d authorised displays exceeded, we need to un-authorise %d of %d displays', $maxDisplays, $difference, count($displays)));

                    $update = $dbh->prepare('UPDATE `display` SET licensed = 0 WHERE displayId = :displayId');

                    foreach ($displays as $display) {
                        $sanitizedDisplay = $this->getSanitizer($display);

                        // If we are down to 0 difference, then stop
                        if ($difference == 0) {
                            break;
                        }

                        $this->appendRunMessage(sprintf(__('Disabling %s'), $sanitizedDisplay->getString('display')));
                        $update->execute(['displayId' => $display['displayId']]);

                        $this->log->audit('Display', $display['displayId'], 'Regular Maintenance unauthorised display due to max number of slots exceeded.', ['display' => $display['display']]);

                        $difference--;
                    }
                }

                $this->runMessage .= ' - Done' . PHP_EOL . PHP_EOL;
            }
            catch (\Exception $e) {
                $this->log->error($e);
            }
        }
    }

    /**
     * Wake on LAN
     */
    private function wakeOnLan()
    {
        $this->runMessage = '# ' . __('Wake On LAN') . PHP_EOL;

        try {
            // Get a list of all displays which have WOL enabled
            foreach($this->displayFactory->query(null, ['wakeOnLan' => 1]) as $display) {
                /** @var \Xibo\Entity\Display $display */
                // Time to WOL (with respect to today)
                $timeToWake = strtotime(date('Y-m-d') . ' ' . $display->wakeOnLanTime);
                $timeNow = Carbon::now()->format('U');

                // Should the display be awake?
                if ($timeNow >= $timeToWake) {
                    // Client should be awake, so has this displays WOL time been passed
                    if ($display->lastWakeOnLanCommandSent < $timeToWake) {
                        // Call the Wake On Lan method of the display object
                        if ($display->macAddress == '' || $display->broadCastAddress == '') {
                            $this->log->error('This display has no mac address recorded against it yet. Make sure the display is running.');
                            $this->runMessage .= ' - ' . $display->display . ' Did not send MAC address yet' . PHP_EOL;
                            continue;
                        }

                        $this->log->notice('About to send WOL packet to ' . $display->broadCastAddress . ' with Mac Address ' . $display->macAddress);

                        try {
                            WakeOnLan::TransmitWakeOnLan($display->macAddress, $display->secureOn, $display->broadCastAddress, $display->cidr, '9', $this->log);
                            $this->runMessage .= ' - ' . $display->display . ' Sent WOL Message. Previous WOL send time: ' . Carbon::createFromTimestamp($display->lastWakeOnLanCommandSent)->format(DateFormatHelper::getSystemFormat()) . PHP_EOL;

                            $display->lastWakeOnLanCommandSent = Carbon::now()->format('U');
                            $display->save(['validate' => false, 'audit' => true]);
                        }
                        catch (\Exception $e) {
                            $this->runMessage .= ' - ' . $display->display . ' Error=' . $e->getMessage() . PHP_EOL;
                        }
                    }
                    else {
                        $this->runMessage .= ' - ' . $display->display . ' Display already awake. Previous WOL send time: ' . Carbon::createFromTimestamp($display->lastWakeOnLanCommandSent)->format(DateFormatHelper::getSystemFormat()) . PHP_EOL;
                    }
                }
                else {
                    $this->runMessage .= ' - ' . $display->display . ' Sleeping' . PHP_EOL;
                }
            }

            $this->runMessage .= ' - Done' . PHP_EOL . PHP_EOL;
        }
        catch (\PDOException $e) {
            $this->log->error($e->getMessage());
            $this->runMessage .= ' - Error' . PHP_EOL . PHP_EOL;
        }
    }

    /**
     * Build layouts
     */
    private function buildLayouts()
    {
        $this->runMessage .= '## ' . __('Build Layouts') . PHP_EOL;

        // Build Layouts
        // We do not want to build any draft Layouts - they are built in the Layout Designer or on Publish
        foreach ($this->layoutFactory->query(null, ['status' => 3, 'showDrafts' => 0, 'disableUserCheck' => 1]) as $layout) {
            /* @var \Xibo\Entity\Layout $layout */
            try {
                $layout = $this->layoutFactory->concurrentRequestLock($layout);
                try {
                    $layout->xlfToDisk(['notify' => true]);

                    // Commit after each build
                    // https://github.com/xibosignage/xibo/issues/1593
                    $this->store->commitIfNecessary();
                } finally {
                    $this->layoutFactory->concurrentRequestRelease($layout);
                }
            } catch (\Exception $e) {
                $this->log->error(sprintf('Maintenance cannot build Layout %d, %s.', $layout->layoutId, $e->getMessage()));
            }
        }

        $this->runMessage .= ' - Done' . PHP_EOL . PHP_EOL;
    }

    /**
     * Tidy library
     */
    private function tidyLibrary()
    {
        $this->runMessage .= '## ' . __('Tidy Library') . PHP_EOL;

        // Keep tidy
        $this->mediaService->removeExpiredFiles();
        $this->mediaService->removeTempFiles();

        $this->runMessage .= ' - Done' . PHP_EOL . PHP_EOL;
    }

    /**
     * Check library usage
     * @throws \Xibo\Support\Exception\NotFoundException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    private function checkLibraryUsage()
    {
        $libraryLimit = $this->config->getSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;

        if ($libraryLimit <= 0) {
            return;
        }

        $results = $this->store->select('SELECT IFNULL(SUM(FileSize), 0) AS SumSize FROM media', []);

        $sanitizedResults = $this->getSanitizer($results);

        $size = $sanitizedResults->getInt('SumSize');

        if ($size >= $libraryLimit) {
            // Create a notification if we don't already have one today for this display.
            $subject = __('Library allowance exceeded');
            $date = Carbon::now();
            $notifications = $this->notificationFactory->getBySubjectAndDate(
                $subject,
                $date->startOfDay()->format('U'),
                $date->addDay()->startOfDay()->format('U')
            );

            if (count($notifications) <= 0) {
                $body = __(
                    sprintf(
                        'Library allowance of %s exceeded. Used %s',
                        ByteFormatter::format($libraryLimit),
                        ByteFormatter::format($size)
                    )
                );

                $notification = $this->notificationFactory->createSystemNotification(
                    $subject,
                    $body,
                    Carbon::now(),
                    'library'
                );

                $notification->save();

                $this->log->critical($subject);
            }
        }
    }

    /**
     * Checks to see if there are any overrequested files.
     * @throws \Xibo\Support\Exception\NotFoundException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    private function checkOverRequestedFiles()
    {
        $items = $this->store->select('
          SELECT display.displayId, 
              display.display,
              COUNT(*) AS countFiles 
            FROM `requiredfile`
              INNER JOIN `display`
              ON display.displayId = requiredfile.displayId
           WHERE `bytesRequested` > 0
              AND `requiredfile`.bytesRequested >= `requiredfile`.`size` * :factor
              AND `requiredfile`.type NOT IN (\'W\', \'D\')
              AND display.lastAccessed > :lastAccessed
              AND `requiredfile`.complete = 0
            GROUP BY display.displayId, display.display
        ', [
            'factor' => 3,
            'lastAccessed' => Carbon::now()->subDay()->format('U'),
        ]);

        foreach ($items as $item) {
            $sanitizedItem = $this->getSanitizer($item);
            // Create a notification if we don't already have one today for this display.
            $subject = sprintf(
                __('%s is downloading %d files too many times'),
                $sanitizedItem->getString('display'),
                $sanitizedItem->getInt('countFiles')
            );
            $date = Carbon::now();
            $notifications = $this->notificationFactory->getBySubjectAndDate(
                $subject,
                $date->startOfDay()->format('U'),
                $date->addDay()->startOfDay()->format('U')
            );

            if (count($notifications) <= 0) {
                $body = sprintf(
                    __('Please check the bandwidth graphs and display status for %s to investigate the issue.'),
                    $sanitizedItem->getString('display')
                );

                $notification = $this->notificationFactory->createSystemNotification(
                    $subject,
                    $body,
                    Carbon::now(),
                    'display'
                );

                $display = $this->displayFactory->getById($item['displayId']);

                // Add in any displayNotificationGroups, with permissions
                foreach ($this->userGroupFactory->getDisplayNotificationGroups($display->displayGroupId) as $group) {
                    $notification->assignUserGroup($group);
                }

                $notification->save();

                $this->log->critical($subject);
            }
        }
    }

    /**
     * Update Playlist Durations
     */
    private function updatePlaylistDurations()
    {
        $this->runMessage .= '## ' . __('Playlist Duration Updates') . PHP_EOL;

        // Build Layouts
        foreach ($this->playlistFactory->query(null, ['requiresDurationUpdate' => 1]) as $playlist) {
            try {
                $playlist->setModuleFactory($this->moduleFactory);
                $playlist->updateDuration();
            } catch (GeneralException $xiboException) {
                $this->log->error(
                    'Maintenance cannot update Playlist ' . $playlist->playlistId .
                    ', ' . $xiboException->getMessage()
                );
            }
        }

        $this->runMessage .= ' - Done' . PHP_EOL . PHP_EOL;
    }

    /**
     * Publish layouts with set publishedDate
     * @throws GeneralException
     */
    private function publishLayouts()
    {
        $this->runMessage .= '## ' . __('Publishing layouts with set publish dates') . PHP_EOL;

        $layouts = $this->layoutFactory->query(
            null,
            ['havePublishDate' => 1, 'disableUserCheck' => 1, 'excludeTemplates' => -1]
        );

        // check if we have any layouts with set publish date
        if (count($layouts) > 0) {
            foreach ($layouts as $layout) {
                // check if the layout should be published now according to the date
                if (Carbon::createFromFormat(DateFormatHelper::getSystemFormat(), $layout->publishedDate)
                    ->isBefore(Carbon::now()->format(DateFormatHelper::getSystemFormat()))
                ) {
                    try {
                        // publish the layout
                        $layout = $this->layoutFactory->concurrentRequestLock($layout, true);
                        try {
                            $draft = $this->layoutFactory->getByParentId($layout->layoutId);
                            if ($draft->status === Status::$STATUS_INVALID
                                && isset($draft->statusMessage)
                                && (
                                    count($draft->getStatusMessage()) > 1 ||
                                    count($draft->getStatusMessage()) === 1 &&
                                    !$draft->checkForEmptyRegion()
                                )
                            ) {
                                throw new GeneralException(json_encode($draft->statusMessage));
                            }
                            $draft->publishDraft();
                            $draft->load();
                            $draft->xlfToDisk([
                                'notify' => true,
                                'exceptionOnError' => true,
                                'exceptionOnEmptyRegion' => false
                            ]);
                        } finally {
                            $this->layoutFactory->concurrentRequestRelease($layout, true);
                        }
                        $this->log->info(
                            'Published layout ID ' . $layout->layoutId . ' new layout id is ' . $draft->layoutId
                        );
                    } catch (GeneralException $e) {
                        $this->log->error(
                            'Error publishing layout ID ' . $layout->layoutId .
                            ' with name ' . $layout->layout . ' Failed with message: ' . $e->getMessage()
                        );

                        // create a notification
                        $subject = __(sprintf('Error publishing layout ID %d', $layout->layoutId));
                        $date = Carbon::now();

                        $notifications = $this->notificationFactory->getBySubjectAndDate(
                            $subject,
                            $date->startOfDay()->format('U'),
                            $date->addDay()->startOfDay()->format('U')
                        );

                        if (count($notifications) <= 0) {
                            $body = __(
                                sprintf(
                                    'Publishing layout ID %d with name %s failed. With message %s',
                                    $layout->layoutId,
                                    $layout->layout,
                                    $e->getMessage()
                                )
                            );

                            $notification = $this->notificationFactory->createSystemNotification(
                                $subject,
                                $body,
                                Carbon::now(),
                                'layout'
                            );
                            $notification->save();

                            $this->log->critical($subject);
                        }
                    }
                } else {
                    $this->log->debug(
                        'Layouts with published date were found, they are set to publish later than current time'
                    );
                }
            }
        } else {
            $this->log->debug('No layouts to publish.');
        }

        $this->runMessage .= ' - Done' . PHP_EOL . PHP_EOL;
    }

    /**
     * Assess any eligible dynamic display groups if necessary
     * @return void
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    private function assessDynamicDisplayGroups(): void
    {
        $this->runMessage .= '## ' . __('Assess Dynamic Display Groups') . PHP_EOL;

        // Do we have a cache key set to say that dynamic display group assessment has been completed?
        $cache = $this->pool->getItem('DYNAMIC_DISPLAY_GROUP_ASSESSED');
        if ($cache->isMiss()) {
            Profiler::start('RegularMaintenance::assessDynamicDisplayGroups', $this->log);

            // Set the cache key with a long expiry and save.
            $cache->set(true);
            $cache->expiresAt(Carbon::now()->addYear());
            $this->pool->save($cache);

            // Process each dynamic display group
            $count = 0;

            foreach ($this->displayGroupFactory->getByIsDynamic(1) as $group) {
                $count++;
                try {
                    // Loads displays.
                    $this->getDispatcher()->dispatch(
                        new DisplayGroupLoadEvent($group),
                        DisplayGroupLoadEvent::$NAME
                    );
                    $group->save([
                        'validate' => false,
                        'saveGroup' => false,
                        'saveTags' => false,
                        'manageLinks' => false,
                        'manageDisplayLinks' => false,
                        'manageDynamicDisplayLinks' => true,
                        'allowNotify' => true
                    ]);
                } catch (GeneralException $exception) {
                    $this->log->error('assessDynamicDisplayGroups: Unable to manage group: '
                        . $group->displayGroup);
                }
            }
            Profiler::end('RegularMaintenance::assessDynamicDisplayGroups', $this->log);
            $this->runMessage .= ' - Done ' . $count . PHP_EOL . PHP_EOL;
        } else {
            $this->runMessage .= ' - Done (not required)' . PHP_EOL . PHP_EOL;
        }
    }

    private function tidyAdCampaignSchedules()
    {
        $this->runMessage .= '## ' . __('Tidy Ad Campaign Schedules') . PHP_EOL;
        Profiler::start('RegularMaintenance::tidyAdCampaignSchedules', $this->log);
        $count = 0;

        foreach ($this->scheduleFactory->query(null, [
            'adCampaignsOnly' => 1,
            'toDt' => Carbon::now()->subDays(90)->unix()
        ]) as $event) {
            if (!empty($event->parentCampaignId)) {
                $count++;
                $this->log->debug('tidyAdCampaignSchedules : Found old Ad Campaign interrupt event ID '
                    . $event->eventId . ' deleting');
                $event->delete(['notify' => false]);
            }
        }

        $this->log->debug('tidyAdCampaignSchedules : Deleted ' . $count . ' events');
        Profiler::end('RegularMaintenance::tidyAdCampaignSchedules', $this->log);
        $this->runMessage .= ' - Done ' . $count . PHP_EOL . PHP_EOL;
    }

    /**
     * Once per hour assert the current XMR to push its expiry time with XMR
     *  this also reseeds the key if XMR restarts
     * @return void
     */
    private function assertXmrKey(): void
    {
        $this->log->debug('assertXmrKey: asserting key');
        try {
            $key = $this->getConfig()->getSetting('XMR_CMS_KEY');
            if (!empty($key)) {
                $client = new Client($this->config->getGuzzleProxy([
                    'base_uri' => $this->getConfig()->getSetting('XMR_ADDRESS'),
                ]));

                $client->post('/', [
                    'json' => [
                        'id' => constant('SECRET_KEY'),
                        'type' => 'keys',
                        'key' => $key,
                    ],
                ]);
                $this->log->debug('assertXmrKey: asserted key');
            } else {
                $this->log->error('assertXmrKey: key empty');
            }
        } catch (GuzzleException | \Exception $e) {
            $this->log->error('cycleXmrKey: failed. E = ' . $e->getMessage());
        }
    }
}
