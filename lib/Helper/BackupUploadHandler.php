<?php

namespace Xibo\Helper;

use Exception;

/**
 * Class BackupUploadHandler
 * @package Xibo\Helper
 */
class BackupUploadHandler extends BlueImpUploadHandler
{
    protected function handle_form_data($file, $index)
    {
        $controller = $this->options['controller'];
        /* @var \Xibo\Controller\Base $controller */

        // Handle form data, e.g. $_REQUEST['description'][$index]
        $fileName = $file->name;

        $controller->getLog()->debug('Upload complete for ' . $fileName . '.');

        // Upload and Save
        try {
            // Move the uploaded file to a temporary location in the library
            $destination = tempnam($controller->getConfig()->GetSetting('LIBRARY_LOCATION') . 'temp/', 'dmp');
            rename($fileName, $destination);

            global $dbuser;
            global $dbpass;
            global $dbname;

            // Push the file into msqldump
            exec('mysql --user=' . $dbuser . ' --password=' . $dbpass . ' ' . $dbname . ' < ' . escapeshellarg($fileName) . ' ');

            $controller->getLog()->notice('mysql --user=' . $dbuser . ' --password=' . $dbpass . ' ' . $dbname . ' < ' . escapeshellarg($fileName) . ' ' );

            unlink($destination);

        } catch (Exception $e) {
            $controller->getLog()->error('Error uploading media: %s', $e->getMessage());
            $controller->getLog()->debug($e->getTraceAsString());

            $file->error = $e->getMessage();

            $controller->getApp()->commit = false;
        }
    }
}