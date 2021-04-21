<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Xibo\Helper;

use Exception;
use Xibo\Entity\Layout;
use Xibo\Support\Exception\LibraryFullException;

/**
 * Class LayoutUploadHandler
 * @package Xibo\Helper
 */
class LayoutUploadHandler extends BlueImpUploadHandler
{
    /**
     * @param $file
     * @param $index
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
            $tags = $controller->getUser()->featureEnabled('tag.tagging')
                ? isset($_REQUEST['tags']) ? $_REQUEST['tags'][$index] : ''
                : '';
            $template = isset($_REQUEST['template']) ? $_REQUEST['template'][$index] : 0;
            $replaceExisting = isset($_REQUEST['replaceExisting']) ? $_REQUEST['replaceExisting'][$index] : 0;
            $importTags = isset($_REQUEST['importTags']) ? $_REQUEST['importTags'][$index] : 0;
            $useExistingDataSets = isset($_REQUEST['useExistingDataSets']) ? $_REQUEST['useExistingDataSets'][$index] : 0;
            $importDataSetData = isset($_REQUEST['importDataSetData']) ? $_REQUEST['importDataSetData'][$index] : 0;
            $folderId = isset($_REQUEST['folderId']) ? $_REQUEST['folderId'] : 1;

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
                $this->options['dataSetFactory'],
                $tags,
                $this->options['routeParser'],
                $this->options['mediaService']
            );

            // set folderId, permissionFolderId is handled on Layout specific Campaign record.
            $layout->folderId = $folderId;

            $layout->save(['saveActions' => false, 'import' => $importTags]);
            $layout->managePlaylistClosureTable($layout);
            $layout->manageActions($layout);

            @unlink($controller->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/' . $fileName);

            // Set the name for the return
            $file->name = $layout->layout;
            $file->id = $layout->layoutId;

        } catch (Exception $e) {
            $controller->getLog()->error(sprintf('Error importing Layout: %s', $e->getMessage()));
            $controller->getLog()->debug($e->getTraceAsString());

            $file->error = $e->getMessage();

            // TODO for this the getState() had to be changed to public, we should do it in a better way I think.
            $controller->getState()->setCommitState(false);
        }
    }
}