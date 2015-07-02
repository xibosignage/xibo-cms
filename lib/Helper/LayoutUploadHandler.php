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
            $layout = LayoutFactory::createFromZip(
                Config::GetSetting('LIBRARY_LOCATION') . 'temp/' . $fileName,
                $_REQUEST['layout'][$index],
                $this->options['userId'],
                $_REQUEST['template'][$index],
                $_REQUEST['replaceExisting'][$index],
                $_REQUEST['importTags'][$index]
            );

            $layout->save();

            @unlink(Config::GetSetting('LIBRARY_LOCATION') . 'temp/' . $fileName);

        } catch (Exception $e) {
            $file->error = $e->getMessage();
        }
    }
}