<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
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
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Service\DownloadService;
use Xibo\Service\MediaService;
use Xibo\Service\MediaServiceInterface;
use Xibo\Service\UploadService;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
* Class PlayerSoftware
* @package Xibo\Controller
*/
class PlayerSoftware extends Base
{
    /** @var \Stash\Interfaces\PoolInterface */
    private $pool;

    /** @var  DisplayProfileFactory */
    private $displayProfileFactory;

    /** @var  PlayerVersionFactory */
    private $playerVersionFactory;

    /** @var  DisplayFactory */
    private $displayFactory;
    /**
     * @var MediaServiceInterface
     */
    private $mediaService;

    /**
     * Notification constructor.
     * @param MediaFactory $mediaFactory
     * @param PlayerVersionFactory $playerVersionFactory
     * @param DisplayProfileFactory $displayProfileFactory
     * @param ModuleFactory $moduleFactory
     * @param DisplayFactory $displayFactory
     */
    public function __construct($pool, $playerVersionFactory, $displayProfileFactory, $displayFactory)
    {
        $this->pool = $pool;
        $this->playerVersionFactory = $playerVersionFactory;
        $this->displayProfileFactory = $displayProfileFactory;
        $this->displayFactory = $displayFactory;
    }

    public function getPlayerVersionFactory() : PlayerVersionFactory
    {
        return $this->playerVersionFactory;
    }

    public function useMediaService(MediaServiceInterface $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function getMediaService(): MediaServiceInterface
    {
        return $this->mediaService->setUser($this->getUser());
    }

    /**
     * Displays the page logic
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'playersoftware-page';
        $this->getState()->setData([
            'types' => array_map(function ($element) {
                return $element->jsonSerialize();
            }, $this->playerVersionFactory->getDistinctType()),
            'versions' => $this->playerVersionFactory->getDistinctVersion(),
            'validExt' => implode('|', $this->getValidExtensions()),
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    function grid(Request $request, Response $response)
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getParams());

        $filter = [
            'playerType' => $sanitizedQueryParams->getString('playerType'),
            'playerVersion' => $sanitizedQueryParams->getString('playerVersion'),
            'playerCode' => $sanitizedQueryParams->getInt('playerCode'),
            'versionId' => $sanitizedQueryParams->getInt('versionId'),
            'useRegexForName' => $sanitizedQueryParams->getCheckbox('useRegexForName'),
            'playerShowVersion' => $sanitizedQueryParams->getString('playerShowVersion')
        ];

        $versions = $this->playerVersionFactory->query($this->gridRenderSort($sanitizedQueryParams), $this->gridRenderFilter($filter, $sanitizedQueryParams));

        // add row buttons
        foreach ($versions as $version) {
            $version->setUnmatchedProperty('fileSizeFormatted', ByteFormatter::format($version->size));
            if ($this->isApi($request)) {
                continue;
            }

            $version->includeProperty('buttons');
            $version->buttons = [];

            // Buttons

            // Edit
            $version->buttons[] = [
                'id' => 'content_button_edit',
                'url' => $this->urlFor($request, 'playersoftware.edit.form', ['id' => $version->versionId]),
                'text' => __('Edit')
            ];

            // Delete Button
            $version->buttons[] = [
                'id' => 'content_button_delete',
                'url' => $this->urlFor($request, 'playersoftware.delete.form', ['id' => $version->versionId]),
                'text' => __('Delete'),
                'multi-select' => true,
                'dataAttributes' => [
                    [
                        'name' => 'commit-url',
                        'value' => $this->urlFor($request, 'playersoftware.delete', ['id' => $version->versionId])
                    ],
                    ['name' => 'commit-method', 'value' => 'delete'],
                    ['name' => 'id', 'value' => 'content_button_delete'],
                    ['name' => 'text', 'value' => __('Delete')],
                    ['name' => 'sort-group', 'value' => 1],
                    ['name' => 'rowtitle', 'value' => $version->fileName]
                ]
            ];


            // Download
            $version->buttons[] = array(
                'id' => 'content_button_download',
                'linkType' => '_self',
                'external' => true,
                'url' => $this->urlFor($request, 'playersoftware.download', ['id' => $version->versionId]) . '?attachment=' . $version->fileName,
                'text' => __('Download')
            );
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->playerVersionFactory->countLast();
        $this->getState()->setData($versions);

        return $this->render($request, $response);
    }

    /**
     * Version Delete Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function deleteForm(Request $request, Response $response, $id)
    {
        $version = $this->playerVersionFactory->getById($id);

        $version->load();

        $this->getState()->template = 'playersoftware-form-delete';
        $this->getState()->setData([
            'version' => $version,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Version
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Delete(
     *  path="/playersoftware/{versionId}",
     *  operationId="playerSoftwareDelete",
     *  tags={"Player Software"},
     *  summary="Delete Version",
     *  description="Delete Version file from the Library and Player Versions table",
     *  @SWG\Parameter(
     *      name="versionId",
     *      in="path",
     *      description="The Version ID to Delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function delete(Request $request, Response $response, $id)
    {
        $version = $this->playerVersionFactory->getById($id);

        $version->load();

        // Unset player version from Display Profile
        $displayProfiles = $this->displayProfileFactory->query();

        foreach ($displayProfiles as $displayProfile) {
            if (in_array($displayProfile->type, ['android', 'lg', 'sssp'])) {
                $currentVersionId = $displayProfile->getSetting('versionMediaId');

                if ($currentVersionId === $version->versionId) {
                    $displayProfile->setSetting('versionMediaId', null);
                    $displayProfile->save();
                }
            } else if ($displayProfile->type === 'chromeOS') {
                $currentVersionId = $displayProfile->getSetting('playerVersionId');

                if ($currentVersionId === $version->versionId) {
                    $displayProfile->setSetting('playerVersionId', null);
                    $displayProfile->save();
                }
            }
        }

        // Delete
        $version->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $version->playerShowVersion)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function editForm(Request $request, Response $response, $id)
    {
        $version = $this->playerVersionFactory->getById($id);

        $this->getState()->template = 'playersoftware-form-edit';
        $this->getState()->setData([
            'version' => $version,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Player Version
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Put(
     *  path="/playersoftware/{versionId}",
     *  operationId="playersoftwareEdit",
     *  tags={"Player Software"},
     *  summary="Edit Player Version",
     *  description="Edit a Player Version file information",
     *  @SWG\Parameter(
     *      name="versionId",
     *      in="path",
     *      description="The Version ID to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="playerShowVersion",
     *      in="formData",
     *      description="The Name of the player version application, this will be displayed in Version dropdowns in Display Profile and Display",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="version",
     *      in="formData",
     *      description="The Version number",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="code",
     *      in="formData",
     *      description="The Code number",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Media")
     *  )
     * )
     */
    public function edit(Request $request, Response $response, $id)
    {
        $version = $this->playerVersionFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $version->version = $sanitizedParams->getString('version');
        $version->code = $sanitizedParams->getInt('code');
        $version->playerShowVersion = $sanitizedParams->getString('playerShowVersion');
        $version->modifiedBy = $this->getUser()->userName;

        $version->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $version->playerShowVersion),
            'id' => $version->versionId,
            'data' => $version
        ]);

        return $this->render($request, $response);
    }

    /**
     * Install Route for SSSP XML
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function getSsspInstall(Request $request, Response $response)
    {
        // Get the default SSSP display profile
        $profile = $this->displayProfileFactory->getDefaultByType('sssp');

        // See if it has a version file (if not or we can't load it, 404)
        $versionId = $profile->getSetting('versionMediaId');

        if ($versionId !== null) {
            $version = $this->playerVersionFactory->getById($versionId);

            $xml = $this->outputSsspXml($version->version . '.' . $version->code, $version->size);
            $response = $response
                ->withHeader('Content-Type', 'application/xml')
                ->write($xml);
        } else {
            return $response->withStatus(404);
        }

        $this->setNoOutput(true);
        return $this->render($request, $response);
    }

    /**
     * Install Route for SSSP WGT
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function getSsspInstallDownload(Request $request, Response $response)
    {
        // Get the default SSSP display profile
        $profile = $this->displayProfileFactory->getDefaultByType('sssp');

        // See if it has a version file (if not, or we can't load it, 404)
        $versionId = $profile->getSetting('versionMediaId');

        if ($versionId !== null) {
            $response = $this->download($request, $response, $versionId);
        } else {
            return $response->withStatus(404);
        }

        $this->setNoOutput();

        return $this->render($request, $response);
    }

    /**
     * Upgrade Route for SSSP XML
     * @param Request $request
     * @param Response $response
     * @param $nonce
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function getSssp(Request $request, Response $response, $nonce)
    {
        // Use the cache to get the displayId for this nonce
        $cache = $this->pool->getItem('/playerVersion/' . $nonce);

        if ($cache->isMiss()) {
            $response = $response->withStatus(404);
            $this->setNoOutput(true);
            return $this->render($request, $response);
        }

        $displayId = $cache->get();

        // Get the Display
        $display = $this->displayFactory->getById($displayId);

        // Check if display is SSSP, throw Exception if it's not
        if ($display->clientType != 'sssp') {
            throw new InvalidArgumentException(__('File available only for SSSP displays'), 'clientType');
        }

        // Add the correct header
        $response = $response->withHeader('content-type', 'application/xml');

        // get the media ID from display profile
        $versionId = $display->getSetting('versionMediaId', null, ['displayOverride' => true]);

        if ($versionId !== null) {
            $versionInformation = $this->playerVersionFactory->getById($versionId);

            $xml = $this->outputSsspXml($versionInformation->version . '.' . $versionInformation->code, $versionInformation->size);
            $response = $response->write($xml);
        } else {
            return $response->withStatus(404);
        }

        $this->setNoOutput(true);

        return $this->render($request, $response);
    }

    /**
     * Upgrade Route for SSSP WGT
     * @param Request $request
     * @param Response $response
     * @param $nonce
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function getVersionFile(Request $request, Response $response, $nonce)
    {
        // Use the cache to get the displayId for this nonce
        $cache = $this->pool->getItem('/playerVersion/' . $nonce);

        if ($cache->isMiss()) {
            $response = $response->withStatus(404);
            $this->setNoOutput(true);
            return $this->render($request, $response);
        }

        $displayId = $cache->get();

        // Get display and media
        $display = $this->displayFactory->getById($displayId);
        $versionId = $display->getSetting('versionMediaId', null, ['displayOverride' => true]);

        if ($versionId !== null) {
            $response = $this->download($request, $response, $versionId);
        } else {
            return $response->withStatus(404);
        }

        $this->setNoOutput(true);
        return $this->render($request, $response);
    }

    /**
     * Player Software Upload
     *
     * @SWG\Post(
     *  path="/playersoftware",
     *  operationId="playersoftwareUpload",
     *  tags={"Player Software"},
     *  summary="Player Software Upload",
     *  description="Upload a new Player version file",
     *  @SWG\Parameter(
     *      name="files",
     *      in="formData",
     *      description="The Uploaded File",
     *      type="file",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function add(Request $request, Response $response)
    {
        if (!$this->getUser()->featureEnabled('playersoftware.add')) {
            throw new AccessDeniedException();
        }

        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        MediaService::ensureLibraryExists($libraryFolder);
        $validExt = $this->getValidExtensions();

        // Make sure there is room in the library
        $libraryLimit = $this->getConfig()->getSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;

        $options = [
            'accept_file_types' => '/\.' . implode('|', $validExt) . '$/i',
            'libraryLimit' => $libraryLimit,
            'libraryQuotaFull' => ($libraryLimit > 0 && $this->getMediaService()->libraryUsage() > $libraryLimit),
        ];

        // Output handled by UploadHandler
        $this->setNoOutput(true);

        $this->getLog()->debug('Hand off to Upload Handler with options: ' . json_encode($options));

        // Hand off to the Upload Handler provided by jquery-file-upload
        $uploadService = new UploadService($libraryFolder . 'temp/', $options, $this->getLog(), $this->getState());
        $uploadHandler = $uploadService->createUploadHandler();

        $uploadHandler->setPostProcessor(function ($file, $uploadHandler) use ($libraryFolder, $request) {
            // Return right away if the file already has an error.
            if (!empty($file->error)) {
                $this->getState()->setCommitState(false);
                return $file;
            }

            $this->getUser()->isQuotaFullByUser(true);

            // Get the uploaded file and move it to the right place
            $filePath = $libraryFolder . 'temp/' . $file->fileName;

            // Add the Player Software record
            $playerSoftware = $this->getPlayerVersionFactory()->createEmpty();
            $playerSoftware->modifiedBy = $this->getUser()->userName;

            // SoC players have issues parsing fileNames with spaces in them
            // replace any unexpected character in fileName with -
            $playerSoftware->fileName = preg_replace('/[^a-zA-Z0-9_.]+/', '-', $file->fileName);
            $playerSoftware->size = filesize($filePath);
            $playerSoftware->md5 = md5_file($filePath);
            $playerSoftware->decorateRecord();

            // if the name was provided on upload use that here.
            if (!empty($file->name)) {
                $playerSoftware->playerShowVersion = $file->name;
            }

            $playerSoftware->save();

            // Test to ensure the final file size is the same as the file size we're expecting
            if ($file->size != $playerSoftware->size) {
                throw new InvalidArgumentException(
                    __('Sorry this is a corrupted upload, the file size doesn\'t match what we\'re expecting.'),
                    'size'
                );
            }

            // everything is fine, move the file from temp folder.
            rename($filePath, $libraryFolder . 'playersoftware/' . $playerSoftware->fileName);

            // Unpack if necessary
            $playerSoftware->unpack($libraryFolder, $request);

            // return
            $file->id = $playerSoftware->versionId;
            $file->md5 = $playerSoftware->md5;
            $file->name = $playerSoftware->fileName;

            return $file;
        });

        $uploadHandler->post();

        // Explicitly set the Content-Type header to application/json
        $response = $response->withHeader('Content-Type', 'application/json');

        return $this->render($request, $response);
    }

    /**
     * @SWG\Get(
     *  path="/playersoftware/download/{id}",
     *  operationId="playersoftwareDownload",
     *  tags={"Player Software"},
     *  summary="Download Player Version file",
     *  description="Download Player Version file",
     *  produces={"application/octet-stream"},
     *  @SWG\Parameter(
     *      name="id",
     *      in="path",
     *      description="The Player Version ID to Download",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(type="file"),
     *      @SWG\Header(
     *          header="X-Sendfile",
     *          description="Apache Send file header - if enabled.",
     *          type="string"
     *      ),
     *      @SWG\Header(
     *          header="X-Accel-Redirect",
     *          description="nginx send file header - if enabled.",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function download(Request $request, Response $response, $id)
    {
        $playerVersion = $this->playerVersionFactory->getById($id);

        $this->getLog()->debug('Download request for player software versionId: ' . $id);

        $library = $this->getConfig()->getSetting('LIBRARY_LOCATION');
        $sendFileMode = $this->getConfig()->getSetting('SENDFILE_MODE');
        $libraryPath = $library . 'playersoftware' . DIRECTORY_SEPARATOR . $playerVersion->fileName;
        $attachmentName = urlencode($playerVersion->fileName);

        $downLoadService = new DownloadService($libraryPath, $sendFileMode);
        $downLoadService->useLogger($this->getLog()->getLoggerInterface());

        return $downLoadService->returnFile(
            $response,
            $attachmentName,
            '/download/playersoftware/' . $playerVersion->fileName
        );
    }

    /**
     * Output the SSSP XML
     * @param $version
     * @param $size
     * @return string
     */
    private function outputSsspXml($version, $size)
    {
        // create sssp_config XML file with provided information
        $ssspDocument = new \DOMDocument('1.0', 'UTF-8');
        $versionNode = $ssspDocument->createElement('widget');
        $version = $ssspDocument->createElement('ver', $version);
        $size = $ssspDocument->createElement('size', $size);

        // Our widget name is always sssp_dl (this is appended to both the install and upgrade routes)
        $name = $ssspDocument->createElement('widgetname', 'sssp_dl');

        $ssspDocument->appendChild($versionNode);
        $versionNode->appendChild($version);
        $versionNode->appendChild($size);
        $versionNode->appendChild($name);
        $versionNode->appendChild($ssspDocument->createElement('webtype', 'tizen'));
        $ssspDocument->formatOutput = true;

        return $ssspDocument->saveXML();
    }

    /**
     * @return string[]
     */
    private function getValidExtensions()
    {
        return ['apk', 'ipk', 'wgt', 'chrome'];
    }
}
