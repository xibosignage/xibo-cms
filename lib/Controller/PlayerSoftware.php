<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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

namespace Xibo\Controller;

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Views\Twig;
use Xibo\Entity\Media;
use Xibo\Entity\PlayerVersion;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
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

    /** @var  MediaFactory */
    private $mediaFactory;

    /** @var  ModuleFactory */
    private $moduleFactory;

    /** @var  PlayerVersionFactory */
    private $playerVersionFactory;

    /** @var  LayoutFactory */
    private $layoutFactory;

    /** @var  WidgetFactory */
    private $widgetFactory;

    /** @var  DisplayGroupFactory */
    private $displayGroupFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var  ScheduleFactory */
    private $scheduleFactory;

    /**
     * Notification constructor.
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param ConfigServiceInterface $config
     * @param \Stash\Interfaces\PoolInterface $pool
     * @param MediaFactory $mediaFactory
     * @param PlayerVersionFactory $playerVersionFactory
     * @param DisplayProfileFactory $displayProfileFactory
     * @param ModuleFactory $moduleFactory
     * @param LayoutFactory $layoutFactory
     * @param WidgetFactory $widgetFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @param DisplayFactory $displayFactory
     * @param ScheduleFactory $scheduleFactory
     * @param Twig $view
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $config, $pool, $mediaFactory, $playerVersionFactory, $displayProfileFactory, $moduleFactory, $layoutFactory, $widgetFactory, $displayGroupFactory, $displayFactory, $scheduleFactory, Twig $view)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $config, $view);

        $this->pool = $pool;
        $this->mediaFactory = $mediaFactory;
        $this->playerVersionFactory = $playerVersionFactory;
        $this->displayProfileFactory = $displayProfileFactory;
        $this->moduleFactory = $moduleFactory;
        $this->layoutFactory = $layoutFactory;
        $this->widgetFactory = $widgetFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->displayFactory = $displayFactory;
        $this->scheduleFactory = $scheduleFactory;
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
            'versions' => $this->playerVersionFactory->getDistinctVersion(),
            'validExt' => implode('|', $this->moduleFactory->getValidExtensions(['type' => 'playersoftware'])),
            'warningLabel' => __("Please set Player Software Version")
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
        $user = $this->getUser();
        $sanitizedQueryParams = $this->getSanitizer($request->getParams());

        $filter = [
            'playerType' => $sanitizedQueryParams->getString('playerType'),
            'playerVersion' => $sanitizedQueryParams->getString('playerVersion'),
            'playerCode' => $sanitizedQueryParams->getInt('playerCode'),
            'versionId' => $sanitizedQueryParams->getInt('versionId'),
            'mediaId' => $sanitizedQueryParams->getInt('mediaId'),
            'useRegexForName' => $sanitizedQueryParams->getCheckbox('useRegexForName'),
            'playerShowVersion' => $sanitizedQueryParams->getString('playerShowVersion')
        ];

        $versions = $this->playerVersionFactory->query($this->gridRenderSort($sanitizedQueryParams), $this->gridRenderFilter($filter, $sanitizedQueryParams));

        // add row buttons
        foreach ($versions as $version) {
            if ($this->isApi($request))
                break;

            $media = $this->mediaFactory->getById($version->mediaId);
            $version->includeProperty('buttons');
            $version->buttons = [];

            // Buttons
            if ($user->checkEditable($media)) {
                // Edit
                $version->buttons[] = [
                    'id' => 'content_button_edit',
                    'url' => $this->urlFor($request,'playersoftware.edit.form', ['id' => $version->versionId]),
                    'text' => __('Edit')
                ];
            }

            if ($user->checkDeleteable($media)) {
                // Delete Button
                $version->buttons[] = array(
                    'id' => 'content_button_delete',
                    'url' => $this->urlFor($request,'playersoftware.delete.form', ['id' => $version->versionId]),
                    'text' => __('Delete')
                );
            }

            if ($user->checkPermissionsModifyable($media)) {
                // Permissions
                $version->buttons[] = [
                    'id' => 'content_button_permissions',
                    'url' => $this->urlFor($request,'user.permissions.form', ['entity' => 'Media', 'id' => $media->mediaId]),
                    'text' => __('Share'),
                    'dataAttributes' => [
                        ['name' => 'commit-url', 'value' => $this->urlFor($request,'user.permissions.multi', ['entity' => 'Media', 'id' => $media->mediaId])],
                        ['name' => 'commit-method', 'value' => 'post'],
                        ['name' => 'id', 'value' => 'content_button_permissions'],
                        ['name' => 'text', 'value' => __('Share')],
                        ['name' => 'rowtitle', 'value' => $media->name],
                        ['name' => 'sort-group', 'value' => 2],
                        ['name' => 'custom-handler', 'value' => 'XiboMultiSelectPermissionsFormOpen'],
                        ['name' => 'custom-handler-url', 'value' => $this->urlFor($request,'user.permissions.multi.form', ['entity' => 'Media'])],
                        ['name' => 'content-id-name', 'value' => 'mediaId']
                    ]
                ];
            }

            // Download
            $version->buttons[] = array(
                'id' => 'content_button_download',
                'linkType' => '_self', 'external' => true,
                'url' => $this->urlFor($request,'library.download', ['id' => $media->mediaId]) . '?attachment=' . $media->fileName,
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
        $media = $this->mediaFactory->getById($version->mediaId);

        if (!$this->getUser()->checkDeleteable($media)) {
            throw new AccessDeniedException();
        }

        $version->load();

        $this->getState()->template = 'playersoftware-form-delete';
        $this->getState()->setData([
            'version' => $version,
            'help' => $this->getHelp()->link('Player Software', 'Delete')
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
        /** @var PlayerVersion $version */
        $version = $this->playerVersionFactory->getById($id);
        /** @var Media $media */
        $media = $this->mediaFactory->getById($version->mediaId);

        if (!$this->getUser()->checkDeleteable($media)) {
            throw new AccessDeniedException();
        }

        $version->load();
        $media->load();

        // Unset player version from Display Profile
        $displayProfiles = $this->displayProfileFactory->query();

        foreach($displayProfiles as $displayProfile) {
            if (in_array($displayProfile->type, ['android', 'lg', 'sssp'])) {

                $currentVersionId = $displayProfile->getSetting('versionMediaId');

                if ($currentVersionId === $media->mediaId) {
                    $displayProfile->setSetting('versionMediaId', null);
                    $displayProfile->save();
                }
            }
        }

        // Delete
        $version->delete();
        $media->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory, $this->displayFactory, $this->scheduleFactory, $this->playerVersionFactory);
        $media->delete();

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
        $media = $this->mediaFactory->getById($version->mediaId);

        if (!$this->getUser()->checkEditable($media)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'playersoftware-form-edit';
        $this->getState()->setData([
            'media' => $media,
            'version' => $version,
            'validExtensions' => implode('|', $this->moduleFactory->getValidExtensions(['type' => 'playersoftware'])),
            'help' => $this->getHelp()->link('Player Software', 'Edit')
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
        $media = $this->mediaFactory->getById($version->mediaId);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($media)) {
            throw new AccessDeniedException();
        }

        $version->version = $sanitizedParams->getString('version');
        $version->code = $sanitizedParams->getInt('code');
        $version->playerShowVersion = $sanitizedParams->getString('playerShowVersion');

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
        $mediaId = $profile->getSetting('versionMediaId');

        if ($mediaId !== null) {
            $media = $this->mediaFactory->getById($mediaId);

            $versionInformation = $this->playerVersionFactory->getByMediaId($mediaId);

            $xml = $this->outputSsspXml($versionInformation->version . '.' . $versionInformation->code, $media->fileSize);
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

        // See if it has a version file (if not or we can't load it, 404)
        $mediaId = $profile->getSetting('versionMediaId');

        if ($mediaId !== null) {
            $media = $this->mediaFactory->getById($mediaId);

            // Create a widget from media and call getResource on it
            $widget = $this->moduleFactory->createWithMedia($media);
            $response = $widget->download($request, $response);

        } else {
            return $response->withStatus(404);
        }

        $this->setNoOutput(true);

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
        $mediaId = $display->getSetting('versionMediaId', null, ['displayOverride' => true]);

        if ($mediaId !== null) {
            $media = $this->mediaFactory->getById($mediaId);

            $versionInformation = $this->playerVersionFactory->getByMediaId($mediaId);

            $xml = $this->outputSsspXml($versionInformation->version . '.' . $versionInformation->code, $media->fileSize);
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
        $mediaId = $display->getSetting('versionMediaId', null, ['displayOverride' => true]);

        if ($mediaId !== null) {
            $media = $this->mediaFactory->getById($mediaId);
            // Create a widget from media and call getResource on it
            $widget = $this->moduleFactory->createWithMedia($media);
            $response = $widget->download($request, $response);
        } else {
            return $response->withStatus(404);
        }

        $this->setNoOutput(true);
        return $this->render($request, $response);
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
}