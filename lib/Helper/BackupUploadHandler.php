<?php

namespace Xibo\Helper;

use Exception;

class BackupUploadHandler extends BlueImpUploadHandler
{
    protected function handle_form_data($file, $index)
    {
        // Handle form data, e.g. $_REQUEST['description'][$index]
        $fileName = $file->name;

        Log::debug('Upload complete for ' . $fileName . '.');

        // Upload and Save
        try {
            // Move the uploaded file to a temporary location in the library
            $destination = tempnam(Config::GetSetting('LIBRARY_LOCATION') . 'temp/', 'dmp');
            rename($fileName, $destination);

            global $dbuser;
            global $dbpass;
            global $dbname;

            // Push the file into msqldump
            exec('mysql --user=' . $dbuser . ' --password=' . $dbpass . ' ' . $dbname . ' < ' . escapeshellarg($fileName) . ' ');

            Log::notice('mysql --user=' . $dbuser . ' --password=' . $dbpass . ' ' . $dbname . ' < ' . escapeshellarg($fileName) . ' ' );

            unlink($destination);

        } catch (Exception $e) {
            Log::error('Error uploading media: %s', $e->getMessage());
            Log::debug($e->getTraceAsString());

            $file->error = $e->getMessage();

            $this->options['controller']->getApp()->commit = false;
        }
    }
}