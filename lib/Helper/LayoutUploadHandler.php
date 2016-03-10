<?php

namespace Xibo\Helper;

use Exception;
use Xibo\Entity\Layout;

class LayoutUploadHandler extends BlueImpUploadHandler
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
            $name = isset($_REQUEST['name']) ? $_REQUEST['name'][$index] : '';
            $template = isset($_REQUEST['template']) ? $_REQUEST['template'][$index] : 0;
            $replaceExisting = isset($_REQUEST['replaceExisting']) ? $_REQUEST['replaceExisting'][$index] : 0;
            $importTags = isset($_REQUEST['importTags']) ? $_REQUEST['importTags'][$index] : 0;

            /* @var Layout $layout */
            $layout = $controller->getFactoryService()->get('LayoutFactory')->createFromZip(
                $controller->getConfig()->GetSetting('LIBRARY_LOCATION') . 'temp/' . $fileName,
                $name,
                $this->options['userId'],
                $template,
                $replaceExisting,
                $importTags
            );

            $layout->save();

            @unlink($controller->getConfig()->GetSetting('LIBRARY_LOCATION') . 'temp/' . $fileName);

            // Set the name for the return
            $file->name = $layout->layout;

        } catch (Exception $e) {
            $controller->getLog()->error('Error uploading media: %s', $e->getMessage());
            $controller->getLog()->debug($e->getTraceAsString());

            $file->error = $e->getMessage();

            $controller->getApp()->commit = false;
        }
    }
}