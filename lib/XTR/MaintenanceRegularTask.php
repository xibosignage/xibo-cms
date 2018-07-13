<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (MaintenanceRegularTask.php)
 */


namespace Xibo\XTR;
use Xibo\Controller\Display;
use Xibo\Controller\Library;
use Xibo\Exception\XiboException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\WakeOnLan;

/**
 * Class MaintenanceRegularTask
 * @package Xibo\XTR
 */
class MaintenanceRegularTask implements TaskInterface
{
    use TaskTrait;

    /** @var Display */
    private $displayController;

    /** @var Library */
    private $libraryController;

    /** @var DisplayFactory */
    private $displayFactory;

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

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->displayController = $container->get('\Xibo\Controller\Display');
        $this->libraryController = $container->get('\Xibo\Controller\Library');

        $this->displayFactory = $container->get('displayFactory');
        $this->notificationFactory = $container->get('notificationFactory');
        $this->userGroupFactory = $container->get('userGroupFactory');
        $this->layoutFactory = $container->get('layoutFactory');
        $this->playlistFactory = $container->get('playlistFactory');
        $this->moduleFactory = $container->get('moduleFactory');
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
        $maxDisplays = $this->config->GetSetting('MAX_LICENSED_DISPLAYS');

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

                    $update = $dbh->prepare('UPDATE `display` SET licensed = 0 WHERE displayId = :displayId');

                    foreach ($displays as $display) {

                        // If we are down to 0 difference, then stop
                        if ($difference == 0)
                            break;

                        echo sprintf(__('Disabling %s'), $this->sanitizer->string($display['display'])) . '<br/>' . PHP_EOL;
                        $update->execute(['displayId' => $display['displayId']]);

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
                $timeNow = time();

                // Should the display be awake?
                if ($timeNow >= $timeToWake) {
                    // Client should be awake, so has this displays WOL time been passed
                    if ($display->lastWakeOnLanCommandSent < $timeToWake) {
                        // Call the Wake On Lan method of the display object
                        if ($display->macAddress == '' || $display->broadCastAddress == '')
                            throw new \InvalidArgumentException(__('This display has no mac address recorded against it yet. Make sure the display is running.'));

                        $this->log->notice('About to send WOL packet to ' . $display->broadCastAddress . ' with Mac Address ' . $display->macAddress);

                        try {
                            WakeOnLan::TransmitWakeOnLan($display->macAddress, $display->secureOn, $display->broadCastAddress, $display->cidr, '9', $this->log);
                            $this->runMessage .= ' - ' . $display->display . ' Sent WOL Message. Previous WOL send time: ' . $this->date->getLocalDate($display->lastWakeOnLanCommandSent) . PHP_EOL;

                            $display->lastWakeOnLanCommandSent = time();
                            $display->save(['validate' => false, 'audit' => true]);
                        }
                        catch (\Exception $e) {
                            $this->runMessage .= ' - ' . $display->display . ' Error=' . $e->getMessage() . PHP_EOL;
                        }
                    }
                    else {
                        $this->runMessage .= ' - ' . $display->display . ' Display already awake. Previous WOL send time: ' . $this->date->getLocalDate($display->lastWakeOnLanCommandSent) . PHP_EOL;
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
        foreach ($this->layoutFactory->query(null, ['status' => 3]) as $layout) {
            /* @var \Xibo\Entity\Layout $layout */
            try {
                $layout->xlfToDisk(['notify' => true]);
            } catch (\Exception $e) {
                $this->log->error('Maintenance cannot build Layout %d, %s.', $layout->layoutId, $e->getMessage());
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
        $this->libraryController->removeExpiredFiles();
        $this->libraryController->removeTempFiles();

        $this->runMessage .= ' - Done' . PHP_EOL . PHP_EOL;
    }

    /**
     * Check library usage
     */
    private function checkLibraryUsage()
    {
        $libraryLimit = $this->config->GetSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;

        if ($libraryLimit <= 0)
            return;

        $results = $this->store->select('SELECT IFNULL(SUM(FileSize), 0) AS SumSize FROM media', []);

        $size = $this->sanitizer->int($results[0]['SumSize']);

        if ($size >= $libraryLimit) {
            // Create a notification if we don't already have one today for this display.
            $subject = __('Library allowance exceeded');
            $date = $this->date->parse();

            if (count($this->notificationFactory->getBySubjectAndDate($subject, $this->date->getLocalDate($date->startOfDay(), 'U'), $this->date->getLocalDate($date->addDay(1)->startOfDay(), 'U'))) <= 0) {

                $body = __(sprintf('Library allowance of %s exceeded. Used %s', ByteFormatter::format($libraryLimit), ByteFormatter::format($size)));

                $notification = $this->notificationFactory->createSystemNotification(
                    $subject,
                    $body,
                    $this->date->parse()
                );

                $notification->save();

                $this->log->critical($subject);
            }
        }
    }

    /**
     * Checks to see if there are any overrequested files.
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
              AND `requiredfile`.type <> :excludedType
              AND display.lastAccessed > :lastAccessed
              AND `requiredfile`.complete = 0
            GROUP BY display.displayId, display.display
        ', [
            'factor' => 3,
            'excludedType' => 'W',
            'lastAccessed' => $this->date->parse()->subDay()->format('U')
        ]);

        foreach ($items as $item) {
            // Create a notification if we don't already have one today for this display.
            $subject = sprintf(__('%s is downloading %d files too many times'), $this->sanitizer->string($item['display']), $this->sanitizer->int($item['countFiles']));
            $date = $this->date->parse();

            if (count($this->notificationFactory->getBySubjectAndDate($subject, $this->date->getLocalDate($date->startOfDay(), 'U'), $this->date->getLocalDate($date->addDay(1)->startOfDay(), 'U'))) <= 0) {

                $body = sprintf(__('Please check the bandwidth graphs and display status for %s to investigate the issue.'), $this->sanitizer->string($item['display']));

                $notification = $this->notificationFactory->createSystemNotification(
                    $subject,
                    $body,
                    $this->date->parse()
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
            } catch (XiboException $xiboException) {
                $this->log->error('Maintenance cannot update Playlist ' . $playlist->playlistId . ', ' . $xiboException->getMessage());
            }
        }

        $this->runMessage .= ' - Done' . PHP_EOL . PHP_EOL;
    }
}