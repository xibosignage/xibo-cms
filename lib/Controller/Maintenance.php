<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Maintenance.php)
 */


namespace Xibo\Controller;


use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Layout;
use Xibo\Entity\UserGroup;
use Xibo\Entity\UserNotification;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\ControllerNotImplemented;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\UpgradeFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\UserNotificationFactory;
use Xibo\Helper\WakeOnLan;
use Xibo\Service\ConfigService;
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
    /** @var  StorageServiceInterface */
    private $store;

    /** @var  PoolInterface */
    private $pool;

    /** @var  UserFactory */
    private $userFactory;

    /** @var  UserGroupFactory */
    private $userGroupFactory;

    /** @var  LayoutFactory */
    private $layoutFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var  UpgradeFactory */
    private $upgradeFactory;

    /** @var  MediaFactory */
    private $mediaFactory;

    /** @var  NotificationFactory */
    private $notificationFactory;

    /** @var  UserNotificationFactory */
    private $userNotificationFactory;

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
     * @param PoolInterface $pool
     * @param UserFactory $userFactory
     * @param UserGroupFactory $userGroupFactory
     * @param LayoutFactory $layoutFactory
     * @param DisplayFactory $displayFactory
     * @param UpgradeFactory $upgradeFactory
     * @param MediaFactory $mediaFactory
     * @param NotificationFactory $notificationFactory
     * @param UserNotificationFactory $userNotificationFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $pool, $userFactory, $userGroupFactory, $layoutFactory, $displayFactory, $upgradeFactory, $mediaFactory, $notificationFactory, $userNotificationFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->store = $store;
        $this->userGroupFactory = $userGroupFactory;
        $this->pool = $pool;
        $this->userFactory = $userFactory;
        $this->layoutFactory = $layoutFactory;
        $this->displayFactory = $displayFactory;
        $this->upgradeFactory = $upgradeFactory;
        $this->mediaFactory = $mediaFactory;
        $this->notificationFactory = $notificationFactory;
        $this->userNotificationFactory = $userNotificationFactory;
    }


    public function run()
    {
        // Always start a transaction
        $this->store->getConnection()->beginTransaction();

        // Output HTML Headers
        print '<html>';
        print '  <head>';
        print '    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />';
        print '    <title>Maintenance</title>';
        print '  </head>';
        print '<body>';

        // Should the Scheduled Task script be running at all?
        if ($this->getConfig()->GetSetting("MAINTENANCE_ENABLED")=="Off") {
            print "<h1>" . __("Maintenance Disabled") . "</h1>";
            print __("Maintenance tasks are disabled at the moment. Please enable them in the &quot;Settings&quot; dialog.");

        } else {
            $quick = ($this->getSanitizer()->getCheckbox('quick') == 1);

            // Set defaults that don't match on purpose!
            $key = 1;
            $aKey = 2;
            $pKey = 3;

            if ($this->getConfig()->GetSetting("MAINTENANCE_ENABLED")=="Protected") {
                // Check that the magic parameter is set
                $key = $this->getConfig()->GetSetting("MAINTENANCE_KEY");

                // Get key from arguments
                $pKey = $this->getSanitizer()->getString('key');

                // If we arrive from the console, then set aKey == key so that we bypass the key check.
                if ($this->getApp()->getName() == 'console')
                    $aKey = $key;
            }

            if (($aKey == $key) || ($pKey == $key) || ($this->getConfig()->GetSetting("MAINTENANCE_ENABLED")=="On")) {

                // Upgrade
                // Is there a pending upgrade (i.e. are there any pending upgrade steps).
                if ($this->getConfig()->isUpgradePending()) {
                    $steps = $this->upgradeFactory->getIncomplete();

                    if (count($steps) <= 0) {

                        // Insert pending upgrade steps.
                        $steps = $this->upgradeFactory->createSteps(DBVERSION, ConfigService::$WEBSITE_VERSION);

                        foreach ($steps as $step) {
                            /* @var \Xibo\Entity\Upgrade $step */
                            $step->save();
                        }
                    }

                    // Cycle through the steps until done
                    set_time_limit(0);

                    foreach ($steps as $upgradeStep) {
                        /* @var \Xibo\Entity\Upgrade $upgradeStep */
                        try {
                            $upgradeStep->doStep();
                            $upgradeStep->complete = 1;
                            $upgradeStep->lastTryDate = $this->getDate()->parse()->format('U');
                            $upgradeStep->save();
                        }
                        catch (\Exception $e) {
                            $upgradeStep->lastTryDate = $this->getDate()->parse()->format('U');
                            $upgradeStep->save();
                            $this->getLog()->error('Unable to run upgrade step. Message = %s', $e->getMessage());
                            $this->getLog()->error($e->getTraceAsString());

                            throw new ConfigurationException($e->getMessage());
                        }
                    }
                }

                // Email Alerts
                // Note that email alerts for displays coming back online are triggered directly from
                // the XMDS service.

                print "<h1>" . __("Email Alerts") . "</h1>";

                $emailAlerts = ($this->getConfig()->GetSetting("MAINTENANCE_EMAIL_ALERTS") == 'On');
                $alwaysAlert = ($this->getConfig()->GetSetting("MAINTENANCE_ALWAYS_ALERT") == 'On');
                $alertForViewUsers = ($this->getConfig()->GetSetting('MAINTENANCE_ALERTS_FOR_VIEW_USERS') == 1);

                foreach ($this->getApp()->container->get('\Xibo\Controller\Display')->setApp($this->getApp())->validateDisplays($this->displayFactory->query()) as $display) {
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
                                $body = sprintf(__("Display %s with ID %d was last seen at %s."), $display->display, $display->displayId, $this->getDate()->getLocalDate($display->lastAccessed));

                                // Add to system
                                $notification = $this->notificationFactory->createEmpty();
                                $notification->subject = $subject;
                                $notification->body = $body;
                                $notification->createdDt = $this->getDate()->getLocalDate(null, 'U');
                                $notification->releaseDt = $this->getDate()->getLocalDate(null, 'U');
                                $notification->isEmail = 1;
                                $notification->isInterrupt = 0;
                                $notification->userId = $this->getUser()->userId;
                                $notification->isSystem = 1;

                                // Add the system notifications group - if there is one.
                                foreach ($this->userGroupFactory->getSystemNotificationGroups() as $group) {
                                    /* @var UserGroup $group */
                                    $notification->assignUserGroup($group);
                                }

                                // Get a list of people that have view access to the display?
                                if ($alertForViewUsers) {

                                    foreach ($this->userGroupFactory->getByDisplayGroupId($display->displayGroupId) as $group) {
                                        /* @var UserGroup $group */
                                        $notification->assignUserGroup($group);
                                    }
                                }

                                $notification->save();

                                echo 'A';
                            } else {
                                echo 'U';
                            }
                        }
                        else {
                            // Alert disabled for this display
                            print "D";
                        }
                    }
                    else {
                        // Email alerts disabled globally
                        print "X";
                    }
                }

                // Log Tidy
                print "<h1>" . __("Tidy Logs") . "</h1>";
                if (!$quick && $this->getConfig()->GetSetting("MAINTENANCE_LOG_MAXAGE") != 0) {

                    $maxage = date("Y-m-d H:i:s", time() - (86400 * $this->getSanitizer()->int($this->getConfig()->GetSetting("MAINTENANCE_LOG_MAXAGE"))));

                    try {
                        $dbh = $this->store->getConnection();

                        $sth = $dbh->prepare('DELETE FROM `log` WHERE logdate < :maxage');
                        $sth->execute(array(
                            'maxage' => $maxage
                        ));

                        print __('Done.');
                    }
                    catch (\PDOException $e) {
                        $this->getLog()->error($e->getMessage());
                    }
                }
                else {
                    print "-&gt;" . __("Disabled") . "<br/>\n";
                }

                //
                // Stats Tidy
                //
                print "<h1>" . __("Tidy Stats") . "</h1>";
                if (!$quick &&  $this->getConfig()->GetSetting("MAINTENANCE_STAT_MAXAGE") != 0) {

                    $maxage = date("Y-m-d H:i:s",time() - (86400 * $this->getSanitizer()->int($this->getConfig()->GetSetting("MAINTENANCE_STAT_MAXAGE"))));

                    try {
                        $dbh = $this->store->getConnection();

                        $sth = $dbh->prepare('DELETE FROM `stat` WHERE statDate < :maxage');
                        $sth->execute(array(
                            'maxage' => $maxage
                        ));

                        print __('Done.');
                    }
                    catch (\PDOException $e) {
                        $this->getLog()->error($e->getMessage());
                    }
                }
                else {
                    print "-&gt;" . __("Disabled") . "<br/>\n";
                }

                //
                // Tidy the Cache
                //
                echo '<h1>' . __('Tidy Cache') . '</h1>';
                if (!$quick) {
                    $this->pool->purge();
                } else {
                    echo '-&gt;' . __('Disabled') . '<br/>\n';
                }
                echo __('Done.');

                //
                // Validate Display Licence Slots
                //
                $maxDisplays = $this->getConfig()->GetSetting('MAX_LICENSED_DISPLAYS');

                if ($maxDisplays > 0) {
                    print '<h1>' . __('Licence Slot Validation') . '</h1>';

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

                                echo sprintf(__('Disabling %s'), $display['display']) . '<br/>' . PHP_EOL;
                                $update->execute(['displayId' => $display['displayId']]);

                                $difference--;
                            }
                        }
                        else {
                            echo __('Done.');
                        }
                    }
                    catch (\Exception $e) {
                        $this->getLog()->error($e);
                    }
                }

                // Wake On LAN
                print '<h1>' . __('Wake On LAN') . '</h1>';

                try {
                    $dbh = $this->store->getConnection();

                    // Get a list of all displays which have WOL enabled
                    $sth = $dbh->prepare('SELECT DisplayID, Display, WakeOnLanTime, LastWakeOnLanCommandSent FROM `display` WHERE WakeOnLan = 1');
                    $sth->execute(array());

                    foreach($this->displayFactory->query(null, ['wakeOnLan' => 1]) as $display) {

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

                                $this->getLog()->notice('About to send WOL packet to ' . $display->broadCastAddress . ' with Mac Address ' . $display->macAddress);

                                try {
                                    WakeOnLan::TransmitWakeOnLan($display->macAddress, $display->secureOn, $display->broadCastAddress, $display->cidr, '9', $this->getLog());
                                    print $display->display . ':Sent WOL Message. Previous WOL send time: ' . $this->getDate()->getLocalDate($display->lastWakeOnLanCommandSent) . '<br/>\n';

                                    $display->lastWakeOnLanCommandSent = time();
                                    $display->save(['validate' => false, 'audit' => true]);
                                }
                                catch (\Exception $e) {
                                    print $display->display . ':Error=' . $e->getMessage() . '<br/>\n';
                                }
                            }
                            else
                                print $display->display . ':Display already awake. Previous WOL send time: ' . $this->getDate()->getLocalDate($display->lastWakeOnLanCommandSent) . '<br/>\n';
                        }
                        else
                            print $display->display . ':Sleeping<br/>\n';
                        print $display->display . ':N/A<br/>\n';
                    }

                    print __('Done.');
                }
                catch (\PDOException $e) {
                    $this->getLog()->error($e->getMessage());
                }

                echo '<h1>' . __('Import Layouts') . '</h1>';

                if (!$this->getConfig()->isUpgradePending() && $this->getConfig()->GetSetting('DEFAULTS_IMPORTED') == 0) {

                    $folder = PROJECT_ROOT . '/web/' . $this->getConfig()->uri('layouts', true);

                    foreach (array_diff(scandir($folder), array('..', '.')) as $file) {
                        if (stripos($file, '.zip')) {
                            $layout = $this->layoutFactory->createFromZip($folder . '/' . $file, null, 1, false, false, true, false, true, $this->getApp()->container->get('\Xibo\Controller\Library')->setApp($this->getApp()));
                            $layout->save([
                                'audit' => false
                            ]);
                        }
                    }

                    // Install files
                    $this->getApp()->container->get('\Xibo\Controller\Library')->installAllModuleFiles();

                    $this->getConfig()->ChangeSetting('DEFAULTS_IMPORTED', 1);

                    print __('Done.');
                } else {
                    print __('Not Required.');
                }

                print '<h1>' . __('Build Layouts') . '</h1>';

                // Build Layouts
                foreach ($this->layoutFactory->query(null, ['status' => 3]) as $layout) {
                    /* @var Layout $layout */
                    try {
                        $layout->xlfToDisk(['notify' => false]);
                    } catch (\Exception $e) {
                        $this->getLog()->error('Maintenance cannot build Layout %d, %s.', $layout->layoutId, $e->getMessage());
                    }
                }
                print __('Done.');

                print '<h1>' . __('Tidy Library') . '</h1>';
                // Keep tidy
                /** @var Library $libraryController */
                $libraryController = $this->getApp()->container->get('\Xibo\Controller\Library');
                $libraryController->removeExpiredFiles();
                $libraryController->removeTempFiles();

                // Install module files
                if (!$quick) {
                    $this->getLog()->debug('Installing Module Files');
                    $libraryController->installAllModuleFiles();
                }
                print __('Done.');

                // Handle queue of notifications to email.
                print '<h1>' . __('Email Notifications') . '</h1>';

                $msgFrom = $this->getConfig()->GetSetting("mail_from");

                $this->getLog()->debug('Notification Queue sending from %s', $msgFrom);

                foreach ($this->userNotificationFactory->getEmailQueue() as $notification) {
                    /** @var UserNotification $notification */

                    $this->getLog()->debug('Notification found: %d', $notification->notificationId);

                    if ($notification->isSystem == 1)
                        $notification->email = $this->getUser()->email;

                    if ($notification->email != '') {

                        $this->getLog()->debug('Sending Notification email to %s.', $notification->email);

                        // Send them an email
                        $mail = new \PHPMailer();
                        $mail->From = $msgFrom;
                        $mail->FromName = $this->getConfig()->getThemeConfig('theme_name');
                        $mail->Subject = $notification->subject;
                        $mail->addAddress($notification->email);

                        // Body
                        $mail->isHTML(true);
                        $mail->AltBody = $notification->body;
                        $mail->Body = $this->generateEmailBody($notification->subject, $notification->body);

                        if (!$mail->send()) {
                            $this->getLog()->error('Unable to send email notification mail to %s', $notification->email);
                            echo 'E';
                        } else {
                            echo 'A';
                        }

                        $this->getLog()->debug('Marking notification as sent');
                    } else {
                        $this->getLog()->error('Discarding NotificationId %d as no email address could be resolved.', $notification->notificationId);
                    }

                    // Mark as sent
                    $notification->setEmailed($this->getDate()->getLocalDate(null, 'U'));
                    $notification->save();
                }
                print __('Done.');
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
     * Generate an email body
     * @param $subject
     * @param $body
     * @return string
     * @throws ConfigurationException
     */
    private function generateEmailBody($subject, $body)
    {
        // Generate Body
        // Start an object buffer
        ob_start();

        // Render the template
        $this->getApp()->render('email-template.twig', ['config' => $this->getConfig(), 'subject' => $subject, 'body' => $body]);

        $body = ob_get_contents();

        ob_end_clean();

        return $body;
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

        if ($this->getConfig()->GetSetting('SETTING_LIBRARY_TIDY_ENABLED') != 1)
            throw new AccessDeniedException(__('Sorry this function is disabled.'));

        // Also run a script to tidy up orphaned media in the library
        $library = $this->getConfig()->GetSetting('LIBRARY_LOCATION');
        $this->getLog()->debug('Library Location: ' . $library);

        // Remove temporary files
        $this->getApp()->container->get('\Xibo\Controller\Library')->removeTempFiles();

        $media = array();
        $unusedMedia = array();
        $unusedRevisions = array();

        // Run a query to get an array containing all of the media in the library
        $sql = '
            SELECT media.mediaid, media.storedAs, media.type, media.isedited,
                SUM(CASE WHEN IFNULL(lkwidgetmedia.widgetId, 0) = 0 THEN 0 ELSE 1 END) AS UsedInLayoutCount,
                SUM(CASE WHEN IFNULL(lkmediadisplaygroup.id, 0) = 0 THEN 0 ELSE 1 END) AS UsedInDisplayCount,
                SUM(CASE WHEN IFNULL(layout.layoutId, 0) = 0 THEN 0 ELSE 1 END) AS UsedInBackgroundImageCount
              FROM `media`
                LEFT OUTER JOIN `lkwidgetmedia`
                ON lkwidgetmedia.mediaid = media.mediaid
                LEFT OUTER JOIN `lkmediadisplaygroup`
                ON lkmediadisplaygroup.mediaid = media.mediaid
                LEFT OUTER JOIN `layout`
                ON `layout`.backgroundImageId = `media`.mediaId
            GROUP BY media.mediaid, media.storedAs, media.type, media.isedited
          ';

        foreach ($this->store->select($sql, []) as $row) {
            $media[$row['storedAs']] = $row;

            // Ignore any module files or fonts
            if ($row['type'] == 'module' || $row['type'] == 'font')
                continue;

            // Collect media revisions that aren't used
            if ($tidyOldRevisions && $row['UsedInLayoutCount'] <= 0 && $row['UsedInDisplayCount'] <= 0 && $row['UsedInBackgroundImageCount'] <= 0 && $row['isedited'] > 0) {
                $unusedRevisions[$row['storedAs']] = $row;
            }
            // Collect any files that aren't used
            else if ($cleanUnusedFiles && $row['UsedInLayoutCount'] <= 0 && $row['UsedInDisplayCount'] <= 0 && $row['UsedInBackgroundImageCount'] <= 0) {
                $unusedMedia[$row['storedAs']] = $row;
            }
        }

        $i = 0;

        // Library location
        $libraryLocation = $this->getConfig()->GetSetting("LIBRARY_LOCATION");

        // Get a list of all media files
        foreach(scandir($library) as $file) {

            if ($file == '.' || $file == '..')
                continue;

            if (is_dir($library . $file))
                continue;

            // Ignore thumbnails
            if (strstr($file, 'tn_') || strstr($file, 'bg_'))
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

                $this->mediaFactory->getById($media[$file]['mediaid'])->delete();
            }
            else if (array_key_exists($file, $unusedMedia)) {
                // It exists but isn't being used any more
                $this->getLog()->debug('Deleting unused media: ' . $media[$file]['mediaid']);

                $this->mediaFactory->getById($media[$file]['mediaid'])->delete();
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

    /**
     * Export Form
     */
    public function exportForm()
    {
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $this->getState()->template = 'maintenance-form-export';
    }

    /**
     * Backup the Database
     */
    public function export()
    {
        // Check we can run mysql
        if (!function_exists('exec'))
            throw new ControllerNotImplemented(__('Exec is not available.'));

        // Global database variables to seed into exec
        global $dbhost;
        global $dbuser;
        global $dbpass;
        global $dbname;

        // get temporary file
        $libraryLocation = $this->getConfig()->GetSetting('LIBRARY_LOCATION') . 'temp/';
        $fileNameStructure = $libraryLocation . 'structure.dump';
        $fileNameData = $libraryLocation . 'data.dump';
        $zipFile = $libraryLocation . 'database.tar.gz';

        // Run mysqldump structure to a temporary file
        $command = 'mysqldump --opt --host=' . $dbhost . ' --user=' . $dbuser . ' --password=' . addslashes($dbpass) . ' ' . $dbname . ' --no-data > ' . escapeshellarg($fileNameStructure) . ' ';
        exec($command);

        // Run mysqldump data to a temporary file
        $command = 'mysqldump --opt --host=' . $dbhost . ' --user=' . $dbuser . ' --password=' . addslashes($dbpass) . ' ' . $dbname . ' --ignore-table=' . $dbname . '.log --ignore-table=' . $dbname . '.oauth_log  > ' . escapeshellarg($fileNameData) . ' ';
        exec($command);

        // Check it worked
        if (!file_exists($fileNameStructure) || !file_exists($fileNameData))
            throw new ConfigurationException(__('Database dump failed.'));

        // Zippy
        $this->getLog()->debug($zipFile);
        $zip = new \ZipArchive();
        $zip->open($zipFile, \ZipArchive::OVERWRITE);
        $zip->addFile($fileNameStructure, 'structure.dump');
        $zip->addFile($fileNameData, 'data.dump');
        $zip->close();

        // Remove the dump file
        unlink($fileNameStructure);
        unlink($fileNameData);

        // Uncomment only if you are having permission issues
        // chmod($zipFile, 0777);

        // Push file back to browser
        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }

        $size = filesize($zipFile);

        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"" . basename($zipFile) . "\"");
        header('Content-Length: ' . $size);

        // Send via Apache X-Sendfile header?
        if ($this->getConfig()->GetSetting('SENDFILE_MODE') == 'Apache') {
            header("X-Sendfile: $zipFile");
            $this->getApp()->halt(200);
        }
        // Send via Nginx X-Accel-Redirect?
        if ($this->getConfig()->GetSetting('SENDFILE_MODE') == 'Nginx') {
            header("X-Accel-Redirect: /download/temp/" . basename($zipFile));
            $this->getApp()->halt(200);
        }

        // Return the file with PHP
        readfile($zipFile);

        $this->setNoOutput(true);
    }
}