<?php
/**
 * Copyright (C) 2018 Xibo Signage Ltd
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

use Xibo\Entity\Media;
use Xibo\Entity\PlayerVersion;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

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
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
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
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $pool, $mediaFactory, $playerVersionFactory, $displayProfileFactory, $moduleFactory, $layoutFactory, $widgetFactory, $displayGroupFactory, $displayFactory, $scheduleFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

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
     */
    function displayPage()
    {
        $this->getState()->template = 'playersoftware-page';
        $this->getState()->setData([
            'versions' => $this->playerVersionFactory->getDistinctVersion(),
            'validExt' => implode('|', $this->moduleFactory->getValidExtensions(['type' => 'playersoftware'])),
            'warningLabel' => __("Please set Player Software Version")
        ]);
    }

    /**
     * @throws NotFoundException
     */
    function grid()
    {
        $user = $this->getUser();

        $filter = [
            'playerType' => $this->getSanitizer()->getString('playerType'),
            'playerVersion' => $this->getSanitizer()->getString('playerVersion'),
            'playerCode' => $this->getSanitizer()->getInt('playerCode'),
            'versionId' => $this->getSanitizer()->getInt('versionId'),
            'mediaId' => $this->getSanitizer()->getInt('mediaId'),
            'playerShowVersion' => $this->getSanitizer()->getString('playerShowVersion'),
            'useRegexForName' => $this->getSanitizer()->getCheckbox('useRegexForName')
        ];

        $versions = $this->playerVersionFactory->query($this->gridRenderSort(), $this->gridRenderFilter($filter));

        // add row buttons
        foreach ($versions as $version) {
            if ($this->isApi())
                break;

            $media = $this->mediaFactory->getById($version->mediaId);
            $version->includeProperty('buttons');
            $version->buttons = [];

            // Buttons
            if ($user->checkEditable($media)) {
                // Edit
                $version->buttons[] = [
                    'id' => 'content_button_edit',
                    'url' => $this->urlFor('playersoftware.edit.form', ['id' => $version->versionId]),
                    'text' => __('Edit')
                ];
            }

            if ($user->checkDeleteable($media)) {
                // Delete Button
                $version->buttons[] = array(
                    'id' => 'content_button_delete',
                    'url' => $this->urlFor('playersoftware.delete.form', ['id' => $version->versionId]),
                    'text' => __('Delete')
                );
            }

            if ($user->checkPermissionsModifyable($media)) {
                // Permissions
                $version->buttons[] = array(
                    'id' => 'content_button_permissions',
                    'url' => $this->urlFor('user.permissions.form', ['entity' => 'Media', 'id' => $media->mediaId]),
                    'text' => __('Permissions')
                );
            }

            // Download
            $version->buttons[] = array(
                'id' => 'content_button_download',
                'linkType' => '_self', 'external' => true,
                'url' => $this->urlFor('library.download', ['id' => $media->mediaId]) . '?attachment=' . $media->fileName,
                'text' => __('Download')
            );
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->playerVersionFactory->countLast();
        $this->getState()->setData($versions);
    }

    /**
     * Version Delete Form
     * @param int $versionId
     * @throws NotFoundException
     * @throws XiboException
     */
    public function deleteForm($versionId)
    {
        $version = $this->playerVersionFactory->getById($versionId);
        $media = $this->mediaFactory->getById($version->mediaId);

        if (!$this->getUser()->checkDeleteable($media))
            throw new AccessDeniedException();

        $version->load();

        $this->getState()->template = 'playersoftware-form-delete';
        $this->getState()->setData([
            'version' => $version,
            'help' => $this->getHelp()->link('Player Software', 'Delete')
        ]);
    }

    /**
     * Delete Version
     * @param int $versionId
     *
     * @throws NotFoundException
     * @throws XiboException
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
    public function delete($versionId)
    {
        /** @var PlayerVersion $version */
        $version = $this->playerVersionFactory->getById($versionId);
        /** @var Media $media */
        $media = $this->mediaFactory->getById($version->mediaId);

        if (!$this->getUser()->checkDeleteable($media))
            throw new AccessDeniedException();

        $version->load();
        $media->load();

        // Delete
        $version->delete();
        $media->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory, $this->displayFactory, $this->scheduleFactory, $this->playerVersionFactory);
        $media->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $version->playerShowVersion)
        ]);
    }

    /**
     * Edit Form
     * @param int $versionId
     * @throws NotFoundException
     */
    public function editForm($versionId)
    {
        $version = $this->playerVersionFactory->getById($versionId);
        $media = $this->mediaFactory->getById($version->mediaId);

        if (!$this->getUser()->checkEditable($media))
            throw new AccessDeniedException();

        $this->getState()->template = 'playersoftware-form-edit';
        $this->getState()->setData([
            'media' => $media,
            'version' => $version,
            'validExtensions' => implode('|', $this->moduleFactory->getValidExtensions(['type' => 'playersoftware'])),
            'help' => $this->getHelp()->link('Player Software', 'Edit')
        ]);
    }

    /**
     * Edit Player Version
     * @param int $versionId
     *
     * @throws NotFoundException
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
    public function edit($versionId)
    {
        $version = $this->playerVersionFactory->getById($versionId);
        $media = $this->mediaFactory->getById($version->mediaId);

        if (!$this->getUser()->checkEditable($media))
            throw new AccessDeniedException();

        $version->version = $this->getSanitizer()->getString('version');
        $version->code = $this->getSanitizer()->getInt('code');
        $version->playerShowVersion = $this->getSanitizer()->getString('playerShowVersion');

        $version->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $version->playerShowVersion),
            'id' => $version->versionId,
            'data' => $version
        ]);
    }

    /**
     * Install Route for SSSP XML
     * @throws \Xibo\Exception\NotFoundException
     */
    public function getSsspInstall()
    {
        // Get the default SSSP display profile
        $profile = $this->displayProfileFactory->getDefaultByType('sssp');

        // See if it has a version file (if not or we can't load it, 404)
        $mediaId = $profile->getSetting('versionMediaId');

        if ($mediaId !== null) {
            $media = $this->mediaFactory->getById($mediaId);

            $versionInformation = $this->playerVersionFactory->getByMediaId($mediaId);

            $this->outputSsspXml($versionInformation->version . '.' . $versionInformation->code, $media->fileSize);
        } else {
            $app = $this->getApp();
            $app->status(404);
        }

        $this->setNoOutput(true);
    }

    /**
     * Install Route for SSSP WGT
     * @throws \Xibo\Exception\NotFoundException
     */
    public function getSsspInstallDownload()
    {
        // Get the default SSSP display profile
        $profile = $this->displayProfileFactory->getDefaultByType('sssp');

        // See if it has a version file (if not or we can't load it, 404)
        $mediaId = $profile->getSetting('versionMediaId');

        if ($mediaId !== null) {
            $media = $this->mediaFactory->getById($mediaId);

            // Create a widget from media and call getResource on it
            $widget = $this->moduleFactory->createWithMedia($media);
            $widget->getResource(0);

        } else {
            $app = $this->getApp();
            $app->status(404);
        }

        $this->setNoOutput(true);
    }

    /**
     * Upgrade Route for SSSP XML
     * @param string $nonce
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Exception\ConfigurationException
     */
    public function getSssp($nonce)
    {
        // Use the cache to get the displayId for this nonce
        $cache = $this->pool->getItem('/playerVersion/' . $nonce);

        if ($cache->isMiss()) {
            $app = $this->getApp();
            $app->status(404);
            $this->setNoOutput(true);
            return;
        }

        $displayId = $cache->get();

        // Get the Display
        $display = $this->displayFactory->getById($displayId);

        // Check if display is SSSP, throw Exception if it's not
        if ($display->clientType != 'sssp') {
            throw new InvalidArgumentException(__('File available only for SSSP displays'), 'clientType');
        }

        // Add the correct header
        $app = $this->getApp();
        $app->response()->header('content-type', 'application/xml');

        // get the media ID from display profile
        $mediaId = $display->getSetting('versionMediaId', null, ['displayOverride' => true]);

        if ($mediaId !== null) {
            $media = $this->mediaFactory->getById($mediaId);

            $versionInformation = $this->playerVersionFactory->getByMediaId($mediaId);

            $this->outputSsspXml($versionInformation->version . '.' . $versionInformation->code, $media->fileSize);
        } else {
            $app = $this->getApp();
            $app->status(404);
        }

        $this->setNoOutput(true);
    }

    /**
     * Upgrade Route for SSSP WGT
     * @param string $nonce
     * @throws NotFoundException
     */
    public function getVersionFile($nonce)
    {
        // Use the cache to get the displayId for this nonce
        $cache = $this->pool->getItem('/playerVersion/' . $nonce);

        if ($cache->isMiss()) {
            $app = $this->getApp();
            $app->status(404);
            $this->setNoOutput(true);
            return;
        }

        $displayId = $cache->get();

        // Get display and media
        $display = $this->displayFactory->getById($displayId);
        $mediaId = $display->getSetting('versionMediaId', null, ['displayOverride' => true]);

        if ($mediaId !== null) {
            $media = $this->mediaFactory->getById($mediaId);
            // Create a widget from media and call getResource on it
            $widget = $this->moduleFactory->createWithMedia($media);
            $widget->getResource($displayId);
        } else {
            $app = $this->getApp();
            $app->status(404);
        }

        $this->setNoOutput(true);
    }

    /**
     * Output the SSP XML
     * @param $version
     * @param $size
     * @param $widgetName
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

        $this->getApp()->response()->header('Content-Type', 'application/xml');

        echo $ssspDocument->saveXML();
    }
}