<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Maintenance.php)
 */


namespace Xibo\Controller;


use Xibo\Entity\Media;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\ControllerNotImplemented;
use Xibo\Exception\LibraryFullException;
use Xibo\Factory\MediaFactory;
use Xibo\Helper\BackupUploadHandler;
use Xibo\Helper\Config;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class Maintenance extends Base
{
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

        // Dump the files in the temp folder
        foreach (scandir($library . 'temp') as $item) {
            if ($item == '.' || $item == '..')
                continue;

            Log::debug('Deleting temp file: ' . $item);

            unlink($library . 'temp' . DIRECTORY_SEPARATOR . $item);
        }

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