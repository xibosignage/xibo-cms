<?php

namespace Xibo\Helper;

use Exception;
use Xibo\Factory\LayoutFactory;

class LayoutUploadHandler extends BlueImpUploadHandler
{
    protected function handle_form_data($file, $index)
    {
        // Handle form data, e.g. $_REQUEST['description'][$index]
        $fileName = $file->name;

        Log::debug('Upload complete for ' . $fileName . '.');

        // Upload and Save
        try {
            $name = isset($_REQUEST['name']) ? $_REQUEST['name'][$index] : '';
            $template = isset($_REQUEST['template']) ? $_REQUEST['template'][$index] : 0;
            $replaceExisting = isset($_REQUEST['replaceExisting']) ? $_REQUEST['replaceExisting'][$index] : 0;
            $importTags = isset($_REQUEST['importTags']) ? $_REQUEST['importTags'][$index] : 0;

            $layout = LayoutFactory::createFromZip(
                Config::GetSetting('LIBRARY_LOCATION') . 'temp/' . $fileName,
                $name,
                $this->options['userId'],
                $template,
                $replaceExisting,
                $importTags
            );

            $layout->save();

            @unlink(Config::GetSetting('LIBRARY_LOCATION') . 'temp/' . $fileName);

            // Set the name for the return
            $file->name = $layout->layout;

        } catch (Exception $e) {
            Log::error('Error uploading media: %s', $e->getMessage());
            Log::debug($e->getTraceAsString());

            $file->error = $e->getMessage();

            $this->options['controller']->getApp()->commit = false;
        }
    }
}