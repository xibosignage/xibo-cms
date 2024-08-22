<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
    protected function handleFormData($file, $index)
    {
        /* @var \Xibo\Controller\Layout $controller */
        $controller = $this->options['controller'];

        /* @var SanitizerService $sanitizerService */
        $sanitizerService = $this->options['sanitizerService'];

        // Handle form data, e.g. $_REQUEST['description'][$index]
        $fileName = $file->name;

        $controller->getLog()->debug('Upload complete for ' . $fileName . '.');

        // Upload and Save
        try {
            // Check Library
            if ($this->options['libraryQuotaFull']) {
                throw new LibraryFullException(sprintf(
                    __('Your library is full. Library Limit: %s K'),
                    $this->options['libraryLimit']
                ));
            }

            // Check for a user quota
            $controller->getUser()->isQuotaFullByUser();
            $params = $sanitizerService->getSanitizer($_REQUEST);

            // Parse parameters
            $name = htmlspecialchars($params->getArray('name')[$index]);
            $tags = $controller->getUser()->featureEnabled('tag.tagging')
                ? htmlspecialchars($params->getArray('tags')[$index])
                : '';
            $template = $params->getCheckbox('template', ['default' => 0]);
            $replaceExisting = $params->getCheckbox('replaceExisting', ['default' => 0]);
            $importTags = $params->getCheckbox('importTags', ['default' => 0]);
            $useExistingDataSets = $params->getCheckbox('useExistingDataSets', ['default' => 0]);
            $importDataSetData = $params->getCheckbox('importDataSetData', ['default' => 0]);
            $importFallback = $params->getCheckbox('importFallback', ['default' => 0]);

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
                $this->options['mediaService'],
                $this->options['folderId'],
            );

            // set folderId, permissionFolderId is handled on Layout specific Campaign record.
            $layout->folderId = $this->options['folderId'];

            $layout->save(['saveActions' => false, 'import' => true]);

            if (!empty($layout->getUnmatchedProperty('thumbnail'))) {
                rename($layout->getUnmatchedProperty('thumbnail'), $layout->getThumbnailUri());
            }
            $layout->managePlaylistClosureTable();
            $layout->manageActions();

            // Handle widget data
            $fallback = $layout->getUnmatchedProperty('fallback');
            if ($importFallback == 1 && $fallback !== null) {
                /** @var \Xibo\Factory\WidgetDataFactory $widgetDataFactory */
                $widgetDataFactory = $this->options['widgetDataFactory'];
                foreach ($layout->getAllWidgets() as $widget) {
                    // Did this widget have fallback data included in its export?
                    if (array_key_exists($widget->tempWidgetId, $fallback)) {
                        foreach ($fallback[$widget->tempWidgetId] as $item) {
                            // We create the widget data with the new widgetId
                            $widgetDataFactory
                                ->create(
                                    $widget->widgetId,
                                    $item['data'] ?? [],
                                    intval($item['displayOrder'] ?? 1),
                                )
                                ->save();
                        }
                    }
                }
            }

            @unlink($controller->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/' . $fileName);

            // Set the name for the return
            $file->name = $layout->layout;
            $file->id = $layout->layoutId;
        } catch (Exception $e) {
            $controller->getLog()->error(sprintf('Error importing Layout: %s', $e->getMessage()));
            $controller->getLog()->debug($e->getTraceAsString());

            $file->error = $e->getMessage();

            // Don't commit
            $controller->getState()->setCommitState(false);
        }
    }
}
