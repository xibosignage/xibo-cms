<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */


namespace Xibo\Controller;

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\Media;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Factory\MediaFactory;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\MediaServiceInterface;
use Xibo\Storage\LibraryFileJournal;
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

    /** @var LibraryStorageRegistryInterface */
    private $libraryStorageRegistry;

    /**
     * Set common dependencies.
     * @param StorageServiceInterface $store
     * @param MediaFactory $mediaFactory
     * @param MediaServiceInterface $mediaService
     * @param LibraryStorageRegistryInterface $libraryStorageRegistry
     */
    public function __construct(
        $store,
        $mediaFactory,
        MediaServiceInterface $mediaService,
        LibraryStorageRegistryInterface $libraryStorageRegistry
    ) {
        $this->store = $store;
        $this->mediaFactory = $mediaFactory;
        $this->mediaService = $mediaService;
        $this->libraryStorageRegistry = $libraryStorageRegistry;
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

        // Pending journalled work, carried out before this request journals its own.
        $journalled = LibraryFileJournal::processAllPending(
            $this->store,
            $this->libraryStorageRegistry,
            $this->getLog()->getLoggerInterface()
        );

        // Remove temporary files
        $this->mediaService->removeTempFiles();

        [$media, $unusedRevisions, $unusedMedia] = $this->mediaFactory->getTidyCandidates(
            $tidyOldRevisions == 1,
            $cleanUnusedFiles == 1,
            $tidyGenericFiles == 1
        );

        // Video covers ({mediaId}_videocover.png) belong to their video, not to a storedAs.
        $mediaIds = array_flip(array_map('intval', array_column($media, 'mediaid')));

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

            // Keep the cover of a video that still exists
            if (Media::isVideoCoverOf($file, $mediaIds)) {
                continue;
            }

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
            'countJournalled' => $journalled['deleted'],
            'countJournalFailed' => $journalled['failed'],
            'initiator' => $this->getUser()->userId
        ]);

        // Return
        $this->getState()->hydrate([
            'message' => __('Library Tidy Complete'),
            'data' => [
                'tidied' => $i + $journalled['deleted']
            ]
        ]);

        return $this->render($request, $response);
    }
}
