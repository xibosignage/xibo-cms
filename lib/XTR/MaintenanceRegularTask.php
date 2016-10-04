<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (MaintenanceRegularTask.php)
 */


namespace Xibo\XTR;
use Xibo\Helper\WakeOnLan;

/**
 * Class MaintenanceRegularTask
 * @package Xibo\XTR
 */
class MaintenanceRegularTask implements TaskInterface
{
    use TaskTrait;

    /** @inheritdoc */
    public function run()
    {
        $this->runMessage = '# ' . __('Regular Maintenance') . PHP_EOL . PHP_EOL;

        $this->displayDownEmailAlerts();

        $this->licenceSlotValidation();

        $this->wakeOnLan();

        $this->buildLayouts();

        $this->tidyLibrary();
    }

    /**
     * Display Down email alerts
     */
    private function displayDownEmailAlerts()
    {
        $this->runMessage .= '## ' . __('Email Alerts') . PHP_EOL;

        $emailAlerts = ($this->config->GetSetting("MAINTENANCE_EMAIL_ALERTS") == 'On');
        $alwaysAlert = ($this->config->GetSetting("MAINTENANCE_ALWAYS_ALERT") == 'On');
        $alertForViewUsers = ($this->config->GetSetting('MAINTENANCE_ALERTS_FOR_VIEW_USERS') == 1);

        foreach ($this->app->container->get('\Xibo\Controller\Display')->setApp($this->app)->validateDisplays($this->displayFactory->query()) as $display) {
            /* @var \Xibo\Entity\Display $display */
            // Is this the first time this display has gone "off-line"
            $displayGoneOffline = ($display->loggedIn == 1);

            // Should we send an email?
            if ($emailAlerts) {
                // Alerts enabled for this display
                if ($display->emailAlert == 1) {
                    // Display just gone offline, or always alert
                    if ($displayGoneOffline || $alwaysAlert) {
                        // Fields for email
                        $subject = sprintf(__("Email Alert for Display %s"), $display->display);
                        $body = sprintf(__("Display %s with ID %d was last seen at %s."), $display->display, $display->displayId, $this->date->getLocalDate($display->lastAccessed));

                        // Add to system
                        $notification = $this->notificationFactory->createEmpty();
                        $notification->subject = $subject;
                        $notification->body = $body;
                        $notification->createdDt = $this->date->getLocalDate(null, 'U');
                        $notification->releaseDt = $this->date->getLocalDate(null, 'U');
                        $notification->isEmail = 1;
                        $notification->isInterrupt = 0;
                        $notification->userId = $this->user->userId;
                        $notification->isSystem = 1;

                        // Add the system notifications group - if there is one.
                        foreach ($this->userGroupFactory->getSystemNotificationGroups() as $group) {
                            /* @var \Xibo\Entity\UserGroup $group */
                            $notification->assignUserGroup($group);
                        }

                        // Get a list of people that have view access to the display?
                        if ($alertForViewUsers) {

                            foreach ($this->userGroupFactory->getByDisplayGroupId($display->displayGroupId) as $group) {
                                /* @var \Xibo\Entity\UserGroup $group */
                                $notification->assignUserGroup($group);
                            }
                        }

                        $notification->save();

                        $this->runMessage .= ' - A' . PHP_EOL;
                    } else {
                        $this->runMessage .= ' - U' . PHP_EOL;
                    }
                }
                else {
                    // Alert disabled for this display
                    $this->runMessage .= ' - D' . PHP_EOL;
                }
            }
            else {
                // Email alerts disabled globally
                $this->runMessage .= ' - X' . PHP_EOL;
            }
        }
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
                    else
                        $this->runMessage .= ' - ' . $display->display . ' Display already awake. Previous WOL send time: ' . $this->date->getLocalDate($display->lastWakeOnLanCommandSent) . PHP_EOL;
                }
                else
                    $this->runMessage .= ' - ' . $display->display . ' Sleeping' . PHP_EOL;

                $this->runMessage .= ' - ' . $display->display . ' N/A' . PHP_EOL;
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
                $layout->xlfToDisk(['notify' => false]);
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
        /** @var \Xibo\Controller\Library $libraryController */
        $libraryController = $this->app->container->get('\Xibo\Controller\Library');
        $libraryController->removeExpiredFiles();
        $libraryController->removeTempFiles();

        $this->runMessage .= ' - Done' . PHP_EOL . PHP_EOL;
    }
}