<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Maintenance.php)
 */


namespace Xibo\Controller;


use Xibo\Entity\Media;
use Xibo\Entity\User;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\ControllerNotImplemented;
use Xibo\Exception\LibraryFullException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\UserFactory;
use Xibo\Helper\BackupUploadHandler;
use Xibo\Helper\Config;
use Xibo\Helper\Date;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Theme;
use Xibo\Helper\WakeOnLan;
use Xibo\Storage\PDOConnect;

class Maintenance extends Base
{
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
        if (Config::GetSetting("MAINTENANCE_ENABLED")=="Off") {
            print "<h1>" . __("Maintenance Disabled") . "</h1>";
            print __("Maintenance tasks are disabled at the moment. Please enable them in the &quot;Settings&quot; dialog.");

        } else {
            $quick = (Sanitize::getCheckbox('quick') == 1);

            // Set defaults that don't match on purpose!
            $key = 1;
            $aKey = 2;
            $pKey = 3;

            if (Config::GetSetting("MAINTENANCE_ENABLED")=="Protected") {
                // Check that the magic parameter is set
                $key = Config::GetSetting("MAINTENANCE_KEY");

                // Get key from POST or from ARGV
                $pKey = Sanitize::getString('key');
                if(isset($argv[1]))
                {
                    $aKey = Sanitize::string($argv[1]);
                }
            }

            if (($aKey == $key) || ($pKey == $key) || (Config::GetSetting("MAINTENANCE_ENABLED")=="On")) {
                // Email Alerts
                // Note that email alerts for displays coming back online are triggered directly from
                // the XMDS service.

                print "<h1>" . __("Email Alerts") . "</h1>";

                $emailAlerts = (Config::GetSetting("MAINTENANCE_EMAIL_ALERTS") == 'On');
                $alwaysAlert = (Config::GetSetting("MAINTENANCE_ALWAYS_ALERT") == 'On');
                $alertForViewUsers = (Config::GetSetting('MAINTENANCE_ALERTS_FOR_VIEW_USERS') == 1);

                $msgTo = Config::GetSetting("mail_to");
                $msgFrom = Config::GetSetting("mail_from");

                foreach (Display::validateDisplays(DisplayFactory::query()) as $display) {
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
                                $body = sprintf(__("Display %s with ID %d was last seen at %s."), $display->display, $display->displayId, Date::getLocalDate($display->lastAccessed));

                                // Get a list of people that have view access to the display?
                                if ($alertForViewUsers) {
                                    foreach (UserFactory::getByDisplayGroupId($display->displayGroupId) as $user) {
                                        /* @var User $user */
                                        if ($user->email != '') {
                                            // Send them an email
                                            $mail = new \PHPMailer();
                                            $mail->From = $msgFrom;
                                            $mail->FromName = Theme::getConfig('theme_name');
                                            $mail->Subject = $subject;
                                            $mail->addAddress($user->email);

                                            // Body
                                            $mail->Body = $body;

                                            if (!$mail->send())
                                                Log::error('Unable to send Display Up mail to %s', $user->email);
                                        }
                                    }
                                }

                                // Send to the original admin contact
                                $mail = new \PHPMailer();
                                $mail->From = $msgFrom;
                                $mail->FromName = Theme::getConfig('theme_name');
                                $mail->Subject = $subject;
                                $mail->addAddress($msgTo);

                                // Body
                                $mail->Body = $body;

                                if (!$mail->send()) {
                                    echo 'A';
                                } else {
                                    echo 'E';
                                }

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
                if (!$quick && Config::GetSetting("MAINTENANCE_LOG_MAXAGE") != 0) {

                    $maxage = date("Y-m-d H:i:s", time() - (86400 * Sanitize::int(Config::GetSetting("MAINTENANCE_LOG_MAXAGE"))));

                    try {
                        $dbh = PDOConnect::init();

                        $sth = $dbh->prepare('DELETE FROM `log` WHERE logdate < :maxage');
                        $sth->execute(array(
                            'maxage' => $maxage
                        ));

                        print __('Done.');
                    }
                    catch (\PDOException $e) {
                        Log::error($e->getMessage());
                    }
                }
                else {
                    print "-&gt;" . __("Disabled") . "<br/>\n";
                }
                // Stats Tidy
                print "<h1>" . __("Tidy Stats") . "</h1>";
                if (!$quick &&  Config::GetSetting("MAINTENANCE_STAT_MAXAGE") != 0) {

                    $maxage = date("Y-m-d H:i:s",time() - (86400 * Sanitize::int(Config::GetSetting("MAINTENANCE_STAT_MAXAGE"))));

                    try {
                        $dbh = PDOConnect::init();

                        $sth = $dbh->prepare('DELETE FROM `stat` WHERE statDate < :maxage');
                        $sth->execute(array(
                            'maxage' => $maxage
                        ));

                        print __('Done.');
                    }
                    catch (\PDOException $e) {
                        Log::error($e->getMessage());
                    }
                }
                else {
                    print "-&gt;" . __("Disabled") . "<br/>\n";
                }

                // Validate Display Licence Slots
                $maxDisplays = Config::GetSetting('MAX_LICENSED_DISPLAYS');

                if ($maxDisplays > 0) {
                    print '<h1>' . __('Licence Slot Validation') . '</h1>';

                    // Get a list of all displays
                    try {
                        $dbh = PDOConnect::init();
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
                        Log::error($e);
                    }
                }

                // Wake On LAN
                print '<h1>' . __('Wake On LAN') . '</h1>';

                // Create a display object to use later
                $displayObject = new Display();

                try {
                    $dbh = PDOConnect::init();

                    // Get a list of all displays which have WOL enabled
                    $sth = $dbh->prepare('SELECT DisplayID, Display, WakeOnLanTime, LastWakeOnLanCommandSent FROM `display` WHERE WakeOnLan = 1');
                    $sth->execute(array());

                    foreach(DisplayFactory::query(null, ['wakeOnLan' => 1]) as $display) {

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

                                Log::notice('About to send WOL packet to ' . $display->broadCastAddress . ' with Mac Address ' . $display->macAddress);

                                try {
                                    WakeOnLan::TransmitWakeOnLan($display->macAddress, $display->secureOn, $display->broadCastAddress, $display->cidr, '9');
                                    print $display->display . ':Sent WOL Message. Previous WOL send time: ' . Date::getLocalDate($display->lastWakeOnLanCommandSent) . '<br/>\n';

                                    $display->lastWakeOnLanCommandSent = time();
                                    $display->save(['validate' => false, 'audit' => true]);
                                }
                                catch (\Exception $e) {
                                    print $display->display . ':Error=' . $displayObject->GetErrorMessage() . '<br/>\n';
                                }
                            }
                            else
                                print $display->display . ':Display already awake. Previous WOL send time: ' . Date::getLocalDate($display->lastWakeOnLanCommandSent) . '<br/>\n';
                        }
                        else
                            print $display->display . ':Sleeping<br/>\n';
                        print $display->display . ':N/A<br/>\n';
                    }

                    print __('Done.');
                }
                catch (\PDOException $e) {
                    Log::error($e->getMessage());
                }

                // Keep tidy
                Library::removeExpiredFiles();
                Library::removeTempFiles();

                // Install module files
                if (!$quick) {
                    Log::debug('Installing Module Files');
                    Library::installAllModuleFiles();
                }
            }
            else {
                print __("Maintenance key invalid.");
            }
        }

        // Output HTML Footers
        print "\n  </body>\n";
        print "</html>";

        Log::debug('Maintenance Complete');

        // No output
        $this->setNoOutput(true);
    }

    /**
     * Tidy Library Form
     */
    public function tidyLibraryForm()
    {
        $this->getState()->template = 'maintenance-form-tidy';
        $this->getState()->setData([
            'help' => Help::Link('Settings', 'TidyLibrary')
        ]);
    }

    /**
     * Tidies up the library
     */
    public function tidyLibrary()
    {
        $tidyOldRevisions = Sanitize::getCheckBox('tidyOldRevisions');
        $cleanUnusedFiles = Sanitize::getCheckbox('cleanUnusedFiles');

        if (Config::GetSetting('SETTING_LIBRARY_TIDY_ENABLED') != 1)
            throw new AccessDeniedException(__('Sorry this function is disabled.'));

        // Also run a script to tidy up orphaned media in the library
        $library = Config::GetSetting('LIBRARY_LOCATION');
        Log::debug('Library Location: ' . $library);

        // Remove temporary files
        Library::removeTempFiles();

        $media = array();
        $unusedMedia = array();
        $unusedRevisions = array();

        // Run a query to get an array containing all of the media in the library
        $sql = '
            SELECT media.mediaid, media.storedAs, media.type, media.isedited,
                SUM(CASE WHEN IFNULL(lkwidgetmedia.widgetId, 0) = 0 THEN 0 ELSE 1 END) AS UsedInLayoutCount,
                SUM(CASE WHEN IFNULL(lkmediadisplaygroup.id, 0) = 0 THEN 0 ELSE 1 END) AS UsedInDisplayCount
              FROM `media`
                LEFT OUTER JOIN `lkwidgetmedia`
                ON lkwidgetmedia.mediaid = media.mediaid
                LEFT OUTER JOIN `lkmediadisplaygroup`
                ON lkmediadisplaygroup.mediaid = media.mediaid
            GROUP BY media.mediaid, media.storedAs, media.type, media.isedited
          ';

        foreach (PDOConnect::select($sql, []) as $row) {
            $media[$row['storedAs']] = $row;

            // Ignore any module files or fonts
            if ($row['type'] == 'module' || $row['type'] == 'font')
                continue;

            // Collect media revisions that aren't used
            if ($tidyOldRevisions && $row['UsedInLayoutCount'] <= 0 && $row['UsedInDisplayCount'] <= 0 && $row['isedited'] > 0) {
                $unusedRevisions[$row['storedAs']] = $row;
            }
            // Collect any files that aren't used
            else if ($cleanUnusedFiles && $row['UsedInLayoutCount'] <= 0 && $row['UsedInDisplayCount'] <= 0) {
                $unusedMedia[$row['storedAs']] = $row;
            }
        }

        //Log::debug(var_export($media, true));
        //Log::debug(var_export($unusedMedia, true));

        $i = 0;

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
                Log::debug('Deleting file: ' . $file);

                // If not, delete it
                Media::unlink($file);
            }
            else if (array_key_exists($file, $unusedRevisions)) {
                // It exists but isn't being used any more
                Log::debug('Deleting unused revision media: ' . $media[$file]['mediaid']);

                MediaFactory::getById($media[$file]['mediaid'])->delete();
            }
            else if (array_key_exists($file, $unusedMedia)) {
                // It exists but isn't being used any more
                Log::debug('Deleting unused media: ' . $media[$file]['mediaid']);

                MediaFactory::getById($media[$file]['mediaid'])->delete();
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
        $libraryLocation = Config::GetSetting('LIBRARY_LOCATION') . 'temp/';
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
        Log::debug($zipFile);
        $zip = new \ZipArchive();
        $zip->open($zipFile, \ZIPARCHIVE::OVERWRITE);
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
        if (Config::GetSetting('SENDFILE_MODE') == 'Apache') {
            header("X-Sendfile: $zipFile");
            $this->getApp()->halt(200);
        }
        // Send via Nginx X-Accel-Redirect?
        if (Config::GetSetting('SENDFILE_MODE') == 'Nginx') {
            header("X-Accel-Redirect: /download/temp/" . basename($zipFile));
            $this->getApp()->halt(200);
        }

        // Return the file with PHP
        readfile($zipFile);

        $this->setNoOutput(true);
    }

    /**
     * Show an upload form to restore a database dump file
     */
    public function importForm()
    {
        $response = $this->getState();

        if (Config::GetSetting('SETTING_IMPORT_ENABLED') != 1)
            throw new AccessDeniedException(__('Sorry this function is disabled.'));

        // Check we have permission to do this
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $msgDumpFile = __('Backup File');
        $msgWarn = __('Warning: Importing a file here will overwrite your existing database. This action cannot be reversed.');
        $msgMore = __('Select a file to import and then click the import button below. You will be taken to another page where the file will be imported.');
        $msgInfo = __('Please note: The folder location for mysqldump must be available in your path environment variable for this to work and the php "exec" command must be enabled.');

        $form = <<<FORM
        <p>$msgWarn</p>
        <p>$msgInfo</p>
        <form id="file_upload" method="post" action="index.php?p=admin&q=RestoreDatabase" enctype="multipart/form-data">
            <table>
                <tr>
                    <td><label for="file">$msgDumpFile<span class="required">*</span></label></td>
                    <td>
                        <input type="file" name="dumpFile" />
                    </td>
                </tr>
            </table>
        </form>
        <p>$msgMore</p>
FORM;
        $response->SetFormRequestResponse($form, __('Import Database Backup'), '550px', '375px');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Import'), '$("#file_upload").submit()');

    }

    /**
     * Restore the Database
     */
    public function import()
    {
        if (Config::GetSetting('SETTING_IMPORT_ENABLED') != 1)
            trigger_error(__('Sorry this function is disabled.'), E_USER_ERROR);

        $libraryFolder = Config::GetSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        Library::ensureLibraryExists();

        $options = array(
            'userId' => $this->getUser()->userId,
            'controller' => $this,
            'upload_dir' => $libraryFolder . 'temp/',
            'download_via_php' => true,
            'script_url' => $this->urlFor('maintenance.import'),
            'upload_url' => $this->urlFor('maintenance.import'),
            'image_versions' => array(),
            'accept_file_types' => '/\.tar.gz/i'
        );

        // Make sure there is room in the library
        $libraryLimit = Config::GetSetting('LIBRARY_SIZE_LIMIT_KB');

        if ($libraryLimit > 0 && Library::libraryUsage() > $libraryLimit)
            throw new LibraryFullException(sprintf(__('Your library is full. Library Limit: %s K'), $libraryLimit));

        // Check for a user quota
        $this->getUser()->isQuotaFullByUser();

        try {
            // Hand off to the Upload Handler provided by jquery-file-upload
            new BackupUploadHandler($options);

        } catch (\Exception $e) {
            // We must not issue an error, the file upload return should have the error object already
            $this->app->commit = false;
        }

        $this->setNoOutput(true);
    }
}