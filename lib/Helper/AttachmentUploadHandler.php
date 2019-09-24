<?php

namespace Xibo\Helper;

/**
 * Class AttachmentUploadHandler
 * @package Xibo\Helper
 */
class AttachmentUploadHandler extends BlueImpUploadHandler
{
    /**
     * @param $file
     * @param $index
     */
    protected function handle_form_data($file, $index)
    {
        $controller = $this->options['controller'];
        /* @var \Xibo\Controller\Notification $controller */

        $controller->getLog()->debug('Upload complete for name: ' . $file->name);
    }
}