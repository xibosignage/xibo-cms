<?php

namespace Xibo\Helper;

use Exception;
use Xibo\Entity\Layout;
use Xibo\Exception\LibraryFullException;

/**
 * Class LayoutUploadHandler
 * @package Xibo\Helper
 */
class LayoutUploadHandler extends BlueImpUploadHandler
{
    /**
     * @param $file
     * @param $index
     * @throws \Xibo\Exception\ConfigurationException
     */
    protected function handle_form_data($file, $index)
    {
        $controller = $this->options['controller'];
        /* @var \Xibo\Controller\Layout $controller */

        // Handle form data, e.g. $_REQUEST['description'][$index]
        $fileName = $file->name;

        $controller->getLog()->debug('Upload complete for ' . $fileName . '.');

        // Upload and Save
        try {
            // Check Library
            if ($this->options['libraryQuotaFull'])
                throw new LibraryFullException(sprintf(__('Your library is full. Library Limit: %s K'), $this->options['libraryLimit']));

            // Check for a user quota
            $controller->getUser()->isQuotaFullByUser();

            // Parse parameters
            $name = isset($_REQUEST['name']) ? $_REQUEST['name'][$index] : '';
            $template = isset($_REQUEST['template']) ? $_REQUEST['template'][$index] : 0;
            $replaceExisting = isset($_REQUEST['replaceExisting']) ? $_REQUEST['replaceExisting'][$index] : 0;
            $importTags = isset($_REQUEST['importTags']) ? $_REQUEST['importTags'][$index] : 0;
            $useExistingDataSets = isset($_REQUEST['useExistingDataSets']) ? $_REQUEST['useExistingDataSets'][$index] : 0;
            $importDataSetData = isset($_REQUEST['importDataSetData']) ? $_REQUEST['importDataSetData'][$index] : 0;

            /* @var Layout $layout */
            $layout = $controller->getLayoutFactory()->createFromZip(
                $controller->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/' . $fileName,
                $name,
                $this->options['userId'],
                $template,
                $replaceExisting,
                $importTags,
                $useExistingDataSets,
                $importDataSetData,
                $this->options['libraryController']
            );

            $layout->save();
            $layout->managePlaylistClosureTable($layout);

            @unlink($controller->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/' . $fileName);

            // Set the name for the return
            $file->name = $layout->layout;
            $file->id = $layout->layoutId;

        } catch (Exception $e) {
            $controller->getLog()->error('Error importing Layout: %s', $e->getMessage());
            $controller->getLog()->debug($e->getTraceAsString());

            $file->error = $e->getMessage();

            $controller->getApp()->commit = false;
        }
    }
}