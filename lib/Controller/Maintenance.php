<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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


namespace Xibo\Controller;

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Factory\MediaFactory;
use Xibo\Service\MediaServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;

/**
 * Class Maintenance
 * @package Xibo\Controller
 */
class Maintenance extends Base
{
    /** @var  StorageServiceInterface */
    private $store;

    /** @var  MediaFactory */
    private $mediaFactory;

    /** @var MediaServiceInterface */
    private $mediaService;

    /**
     * Set common dependencies.
     * @param StorageServiceInterface $store
     * @param MediaFactory $mediaFactory
     * @param MediaServiceInterface $mediaService
     */
    public function __construct($store, $mediaFactory, MediaServiceInterface $mediaService)
    {
        $this->store = $store;
        $this->mediaFactory = $mediaFactory;
        $this->mediaService = $mediaService;
    }

    /**
     * Tidy Library Form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     */
    public function tidyLibraryForm(Request $request, Response $response)
    {
        $this->getState()->template = 'maintenance-form-tidy';
        return $this->render($request, $response);
    }

    /**
     * Tidies up the library
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function tidyLibrary(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $tidyOldRevisions = $sanitizedParams->getCheckbox('tidyOldRevisions');
        $cleanUnusedFiles = $sanitizedParams->getCheckbox('cleanUnusedFiles');
        $tidyGenericFiles = $sanitizedParams->getCheckbox('tidyGenericFiles');

        if ($this->getConfig()->getSetting('SETTING_LIBRARY_TIDY_ENABLED') != 1) {
            throw new AccessDeniedException(__('Sorry this function is disabled.'));
        }

        $this->getLog()->audit('Media', 0, 'Tidy library started from Settings', [
            'tidyOldRevisions' => $tidyOldRevisions,
            'cleanUnusedFiles' => $cleanUnusedFiles,
            'tidyGenericFiles' => $tidyGenericFiles,
            'initiator' => $this->getUser()->userId
        ]);

        // Also run a script to tidy up orphaned media in the library
        $library = $this->getConfig()->getSetting('LIBRARY_LOCATION');
        $this->getLog()->debug('Library Location: ' . $library);

        // Remove temporary files
        $this->mediaService->removeTempFiles();

        $media = [];
        $unusedMedia = [];
        $unusedRevisions = [];

        // DataSets with library images
        $dataSetSql = '
            SELECT dataset.dataSetId, datasetcolumn.heading
              FROM dataset
                INNER JOIN datasetcolumn
                ON datasetcolumn.DataSetID = dataset.DataSetID
             WHERE DataTypeID = 5 AND DataSetColumnTypeID <> 2;
        ';

        $dataSets = $this->store->select($dataSetSql, []);

        // Run a query to get an array containing all of the media in the library
        // this must contain ALL media, so that we can delete files in the storage that aren;t in the table
        $sql = '
            SELECT media.mediaid, media.storedAs, media.type, media.isedited,
                SUM(CASE WHEN IFNULL(lkwidgetmedia.widgetId, 0) = 0 THEN 0 ELSE 1 END) AS UsedInLayoutCount,
                SUM(CASE WHEN IFNULL(lkmediadisplaygroup.mediaId, 0) = 0 THEN 0 ELSE 1 END) AS UsedInDisplayCount,
                SUM(CASE WHEN IFNULL(layout.layoutId, 0) = 0 THEN 0 ELSE 1 END) AS UsedInBackgroundImageCount,
                SUM(CASE WHEN IFNULL(menu_category.menuCategoryId, 0) = 0 THEN 0 ELSE 1 END) AS UsedInMenuBoardCategoryCount,
                SUM(CASE WHEN IFNULL(menu_product.menuProductId, 0) = 0 THEN 0 ELSE 1 END) AS UsedInMenuBoardProductCount
        ';

        if (count($dataSets) > 0) {
            $sql .= ' , SUM(CASE WHEN IFNULL(dataSetImages.mediaId, 0) = 0 THEN 0 ELSE 1 END) AS UsedInDataSetCount ';
        } else {
            $sql .= ' , 0 AS UsedInDataSetCount ';
        }

        $sql .= '
              FROM `media`
                LEFT OUTER JOIN `lkwidgetmedia`
                ON lkwidgetmedia.mediaid = media.mediaid
                LEFT OUTER JOIN `lkmediadisplaygroup`
                ON lkmediadisplaygroup.mediaid = media.mediaid
                LEFT OUTER JOIN `layout`
                ON `layout`.backgroundImageId = `media`.mediaId
                LEFT OUTER JOIN `menu_category`
                ON `menu_category`.mediaId = `media`.mediaId
                LEFT OUTER JOIN `menu_product`
                ON `menu_product`.mediaId = `media`.mediaId
         ';

        if (count($dataSets) > 0) {

            $sql .= ' LEFT OUTER JOIN (';

            $first = true;
            foreach ($dataSets as $dataSet) {
            $sanitizedDataSet = $this->getSanitizer($dataSet);
                if (!$first)
                    $sql .= ' UNION ALL ';

                $first = false;

                $dataSetId = $sanitizedDataSet->getInt('dataSetId');
                $heading = $sanitizedDataSet->getString('heading');

                $sql .= ' SELECT `' . $heading . '` AS mediaId FROM `dataset_' . $dataSetId . '`';
            }

            $sql .= ') dataSetImages 
                ON dataSetImages.mediaId = `media`.mediaId
            ';
        }

        $sql .= '
            GROUP BY media.mediaid, media.storedAs, media.type, media.isedited
        ';

        foreach ($this->store->select($sql, []) as $row) {
            $media[$row['storedAs']] = $row;
            $sanitizedRow = $this->getSanitizer($row);

            $type = $sanitizedRow->getString('type');

            // Ignore any module files or fonts
            if ($type == 'module'
                || $type == 'font'
                || $type == 'playersoftware'
                || ($type == 'genericfile' && $tidyGenericFiles != 1)
            ) {
                continue;
            }

            // Collect media revisions that aren't used
            if ($tidyOldRevisions && $this->isSafeToDelete($row) && $row['isedited'] > 0) {
                $unusedRevisions[$row['storedAs']] = $row;
            }
            // Collect any files that aren't used
            else if ($cleanUnusedFiles && $this->isSafeToDelete($row)) {
                $unusedMedia[$row['storedAs']] = $row;
            }
        }

        $i = 0;

        // Library location
        $libraryLocation = $this->getConfig()->getSetting("LIBRARY_LOCATION");

        // Get a list of all media files
        foreach(scandir($library) as $file) {

            if ($file == '.' || $file == '..')
                continue;

            if (is_dir($library . $file))
                continue;

            // Ignore thumbnails
            if (strstr($file, 'tn_'))
                continue;

            // Ignore XLF files
            if (strstr($file, '.xlf'))
                continue;

            $i++;

            // Is this file in the system anywhere?
            if (!array_key_exists($file, $media)) {
                // Totally missing
                $this->getLog()->alert('tidyLibrary: Deleting file which is not in the media table: ' . $file);

                // If not, delete it
                unlink($libraryLocation . $file);
            } else if (array_key_exists($file, $unusedRevisions)) {
                // It exists but isn't being used anymore
                $this->getLog()->alert('tidyLibrary: Deleting unused revision media: ' . $media[$file]['mediaid']);

                $mediaToDelete = $this->mediaFactory->getById($media[$file]['mediaid']);
                $this->getDispatcher()->dispatch(new MediaDeleteEvent($mediaToDelete), MediaDeleteEvent::$NAME);
                $mediaToDelete->delete();
            } else if (array_key_exists($file, $unusedMedia)) {
                // It exists but isn't being used anymore
                $this->getLog()->alert('tidyLibrary: Deleting unused media: ' . $media[$file]['mediaid']);

                $mediaToDelete = $this->mediaFactory->getById($media[$file]['mediaid']);
                $this->getDispatcher()->dispatch(new MediaDeleteEvent($mediaToDelete), MediaDeleteEvent::$NAME);
                $mediaToDelete->delete();
            } else {
                $i--;
            }
        }

        $this->getLog()->audit('Media', 0, 'Tidy library from settings complete', [
            'countDeleted' => $i,
            'initiator' => $this->getUser()->userId
        ]);

        // Return
        $this->getState()->hydrate([
            'message' => __('Library Tidy Complete'),
            'data' => [
                'tidied' => $i
            ]
        ]);

        return $this->render($request, $response);
    }

    private function isSafeToDelete($row): bool
    {
        return ($row['UsedInLayoutCount'] <= 0
            && $row['UsedInDisplayCount'] <= 0
            && $row['UsedInBackgroundImageCount'] <= 0
            && $row['UsedInDataSetCount'] <= 0
            && $row['UsedInMenuBoardCategoryCount'] <= 0
            && $row['UsedInMenuBoardProductCount'] <= 0
        );
    }
}