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
namespace Xibo\Controller;

use Carbon\Carbon;
use Parsedown;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Routing\RouteContext;
use Stash\Interfaces\PoolInterface;
use Stash\Item;
use Xibo\Entity\Region;
use Xibo\Entity\Session;
use Xibo\Entity\Widget;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\ResolutionFactory;
use Xibo\Factory\TagFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\LayoutUploadHandler;
use Xibo\Helper\Profiler;
use Xibo\Helper\SendFile;
use Xibo\Service\MediaService;
use Xibo\Service\MediaServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Widget\ModuleWidget;

/**
 * Class Layout
 * @package Xibo\Controller
 *
 */
class Layout extends Base
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var UserFactory
     */
    private $userFactory;

    /**
     * @var ResolutionFactory
     */
    private $resolutionFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var ModuleFactory
     */
    private $moduleFactory;

    /**
     * @var UserGroupFactory
     */
    private $userGroupFactory;

    /**
     * @var TagFactory
     */
    private $tagFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /** @var  DataSetFactory */
    private $dataSetFactory;

    /** @var  CampaignFactory */
    private $campaignFactory;

    /** @var  DisplayGroupFactory */
    private $displayGroupFactory;

    /** @var PoolInterface */
    private $pool;

    /** @var MediaServiceInterface */
    private $mediaService;

    /**
     * Set common dependencies.
     * @param Session $session
     * @param UserFactory $userFactory
     * @param ResolutionFactory $resolutionFactory
     * @param LayoutFactory $layoutFactory
     * @param ModuleFactory $moduleFactory
     * @param UserGroupFactory $userGroupFactory
     * @param TagFactory $tagFactory
     * @param MediaFactory $mediaFactory
     * @param DataSetFactory $dataSetFactory
     * @param CampaignFactory $campaignFactory
     * @param $displayGroupFactory
     */
    public function __construct($session, $userFactory, $resolutionFactory, $layoutFactory, $moduleFactory, $userGroupFactory, $tagFactory, $mediaFactory, $dataSetFactory, $campaignFactory, $displayGroupFactory, $pool, MediaServiceInterface $mediaService)
    {
        $this->session = $session;
        $this->userFactory = $userFactory;
        $this->resolutionFactory = $resolutionFactory;
        $this->layoutFactory = $layoutFactory;
        $this->moduleFactory = $moduleFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->tagFactory = $tagFactory;
        $this->mediaFactory = $mediaFactory;
        $this->dataSetFactory = $dataSetFactory;
        $this->campaignFactory = $campaignFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->pool = $pool;
        $this->mediaService = $mediaService;
    }

    /**
     * @return LayoutFactory
     */
    public function getLayoutFactory()
    {
        return $this->layoutFactory;
    }

    /**
     * @return DataSetFactory
     */
    public function getDataSetFactory()
    {
        return $this->dataSetFactory;
    }

    /**
     * Displays the Layout Page
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    function displayPage(Request $request, Response $response)
    {
        // Call to render the template
        $this->getState()->template = 'layout-page';
        $this->getState()->setData([
            'users' => $this->userFactory->query(),
            'groups' => $this->userGroupFactory->query(),
            'displayGroups' => $this->displayGroupFactory->query(null, ['isDisplaySpecific' => -1])
        ]);

        return $this->render($request, $response);
    }

    /**
     * Display the Layout Designer
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function displayDesigner(Request $request, Response $response, $id)
    {
        $layout = $this->layoutFactory->loadById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        // Get the parent layout if it's editable
        if ($layout->isEditable()) {
            // Get the Layout using the Draft ID
            $layout = $this->layoutFactory->getByParentId($id);
        }

        // Work out our resolution
        if ($layout->schemaVersion < 2)
            $resolution = $this->resolutionFactory->getByDesignerDimensions($layout->width, $layout->height);
        else
            $resolution = $this->resolutionFactory->getByDimensions($layout->width, $layout->height);

        $moduleFactory = $this->moduleFactory;
        $isTemplate = $layout->hasTag('template');

        // Set up any JavaScript translations
        $data = [
            'layout' => $layout,
            'resolution' => $resolution,
            'isTemplate' => $isTemplate,
            'zoom' => $sanitizedParams->getDouble('zoom', ['default' => $this->getUser()->getOptionValue('defaultDesignerZoom', 1)]),
            'users' => $this->userFactory->query(),
            'modules' => array_map(function($element) use ($moduleFactory) {
                    $module = $moduleFactory->createForInstall($element->class);
                    $module->setModule($element);
                    return $module;
                }, $moduleFactory->getAssignableModules())
        ];

        // Call the render the template
        $this->getState()->template = 'layout-designer-page';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * Add a Layout
     * @SWG\Post(
     *  path="/layout",
     *  operationId="layoutAdd",
     *  tags={"layout"},
     *  summary="Add a Layout",
     *  description="Add a new Layout to the CMS",
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="The layout name",
     *      type="string",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="The layout description",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="formData",
     *      description="If the Layout should be created with a Template, provide the ID, otherwise don't provide",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="resolutionId",
     *      in="formData",
     *      description="If a Template is not provided, provide the resolutionId for this Layout.",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="returnDraft",
     *      in="formData",
     *      description="Should we return the Draft Layout or the Published Layout on Success?",
     *      type="boolean",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="code",
     *      in="formData",
     *      description="Code identifier for this Layout",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="formData",
     *      description="Folder ID to which this object should be assigned to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    function add(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $name = $sanitizedParams->getString('name');
        $description = $sanitizedParams->getString('description');
        $templateId = $sanitizedParams->getInt('layoutId');
        $resolutionId = $sanitizedParams->getInt('resolutionId');
        $enableStat = $sanitizedParams->getCheckbox('enableStat');
        $autoApplyTransitions = $sanitizedParams->getCheckbox('autoApplyTransitions');
        $code = $sanitizedParams->getString('code', ['defaultOnEmptyString' => true]);
        $folderId = $sanitizedParams->getInt('folderId', ['default' => 1]);

        if ($this->getUser()->featureEnabled('tag.tagging')) {
            $tags = $this->tagFactory->tagsFromString($sanitizedParams->getString('tags'));
        } else {
            $tags = [];
        }

        $template = null;

        if ($templateId != 0) {
            // Load the template
            $template = $this->layoutFactory->loadById($templateId);
            $template->load();

            // Empty all of the ID's
            $layout = clone $template;

            // Overwrite our new properties
            $layout->layout = $name;
            $layout->description = $description;
            $layout->code = $code;

            // Create some tags (overwriting the old ones)
            if ($this->getUser()->featureEnabled('tag.tagging')) {
                $layout->tags = $tags;
            }

            // Set the owner
            $layout->setOwner($this->getUser()->userId);

            // Ensure we have Playlists for each region
            foreach ($layout->regions as $region) {
                // Set the ownership of this region to the user creating from template
                $region->setOwner($this->getUser()->userId, true);
            }
        } else {
            $layout = $this->layoutFactory->createFromResolution(
                $resolutionId,
                $this->getUser()->userId,
                $name,
                $description,
                $tags,
                $code
            );
        }

        // Set layout enableStat flag
        $layout->enableStat = $enableStat;

        // Set auto apply transitions flag
        $layout->autoApplyTransitions = $autoApplyTransitions;

        // set folderId
        $layout->folderId = $folderId;

        // Save
        $layout->save();

        if ($templateId != null && $template !== null) {
            $layout->copyActions($layout, $template);
            // set Layout original values to current values
            $layout->setOriginals();
        }

        foreach ($layout->regions as $region) {
            /* @var Region $region */

            if ($templateId != null && $template !== null) {
                // Match our original region id to the id in the parent layout
                $original = $template->getRegion($region->getOriginalValue('regionId'));

                // Make sure Playlist closure table from the published one are copied over
                $original->getPlaylist()->cloneClosureTable($region->getPlaylist()->playlistId);

                // set Region original values to current values
                $region->setOriginals();
                foreach ($region->regionPlaylist->widgets as $widget) {
                    // set Widget original values to current values
                    $widget->setOriginals();
                }
            }
            $campaign = $this->campaignFactory->getById($layout->campaignId);

            $playlist = $region->getPlaylist();
            $playlist->folderId = $campaign->folderId;
            $playlist->permissionsFolderId = $campaign->permissionsFolderId;
            $playlist->save();
        }

        $this->getLog()->debug('Layout Added');

        // Automatically checkout the new layout for edit
        $layout = $this->layoutFactory->checkoutLayout($layout, $sanitizedParams->getCheckbox('returnDraft'));

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $layout->layout),
            'id' => $layout->layoutId,
            'data' => $layout
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Layout
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Put(
     *  path="/layout/{layoutId}",
     *  operationId="layoutEdit",
     *  summary="Edit Layout",
     *  description="Edit a Layout",
     *  tags={"layout"},
     *  @SWG\Parameter(
     *      name="layoutId",
     *      type="integer",
     *      in="path",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="The Layout Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="The Layout Description",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="A comma separated list of Tags",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="retired",
     *      in="formData",
     *      description="A flag indicating whether this Layout is retired.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="enableStat",
     *      in="formData",
     *      description="Flag indicating whether the Layout stat is enabled",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="code",
     *      in="formData",
     *      description="Code identifier for this Layout",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="formData",
     *      description="Folder ID to which this object should be assigned to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout")
     *  )
     * )
     */
    function edit(Request $request, Response $response, $id)
    {
        $layout = $this->layoutFactory->getById($id);
        $isTemplate = false;
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $folderChanged = false;
        $nameChanged = false;

        // check if we're dealing with the template
        $currentTags = explode(',', $layout->tags);
        foreach ($currentTags as $tag) {
            if ($tag === 'template') {
                $isTemplate = true;
            }
        }

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        // Make sure we're not a draft
        if ($layout->isChild()) {
            throw new InvalidArgumentException(__('Cannot edit Layout properties on a Draft'), 'layoutId');
        }

        $layout->layout = $sanitizedParams->getString('name');
        $layout->description = $sanitizedParams->getString('description');

        if ($this->getUser()->featureEnabled('tag.tagging')) {
            $layout->replaceTags($this->tagFactory->tagsFromString($sanitizedParams->getString('tags')));
        }

        $layout->retired = $sanitizedParams->getCheckbox('retired');
        $layout->enableStat = $sanitizedParams->getCheckbox('enableStat');
        $layout->code = $sanitizedParams->getString('code');
        $layout->folderId = $sanitizedParams->getInt('folderId', ['default' => $layout->folderId]);

        $tags = $sanitizedParams->getString('tags');
        $tagsArray = explode(',', $tags);

        if (!$isTemplate) {
            foreach ($tagsArray as $tag) {
                if ($tag === 'template') {
                    throw new InvalidArgumentException(__('Cannot assign a Template tag to a Layout, to create a template use the Save Template button instead.'), 'tags');
                }
            }
        }

        if ($layout->hasPropertyChanged('folderId')) {
            $folderChanged = true;
        }

        if ($layout->hasPropertyChanged('layout')) {
            $nameChanged = true;
        }

        // Save
        $layout->save([
            'saveLayout' => true,
            'saveRegions' => false,
            'saveTags' => true,
            'setBuildRequired' => false,
            'notify' => false
        ]);

        if ($folderChanged || $nameChanged) {
            // permissionsFolderId depends on the Campaign, hence why we need to get the edited Layout back here
            $editedLayout = $this->layoutFactory->getById($layout->layoutId);

            // this will return the original Layout we edited and its draft
            $layouts = $this->layoutFactory->getByCampaignId($layout->campaignId, true, true);

            foreach ($layouts as $savedLayout) {
                // if we changed the name of the original Layout, updated its draft name as well
                if ($savedLayout->isChild() && $nameChanged) {
                    $savedLayout->layout =  $editedLayout->layout;
                    $savedLayout->save([
                        'saveLayout' => true,
                        'saveRegions' => false,
                        'saveTags' => false,
                        'setBuildRequired' => false,
                        'notify' => false
                    ]);
                }

                // if the folder changed on original Layout, make sure we keep its regionPlaylists and draft regionPlaylists updated
                if ($folderChanged) {
                    $savedLayout->load();
                    foreach ($savedLayout->regions as $region) {
                        $playlist = $region->getPlaylist();
                        $playlist->folderId = $editedLayout->folderId;
                        $playlist->permissionsFolderId = $editedLayout->permissionsFolderId;
                        $playlist->save();
                    }
                }
            }
        }

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $layout->layout),
            'id' => $layout->layoutId,
            'data' => $layout
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Layout Background
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Put(
     *  path="/layout/background/{layoutId}",
     *  operationId="layoutEditBackground",
     *  summary="Edit Layout Background",
     *  description="Edit a Layout Background",
     *  tags={"layout"},
     *  @SWG\Parameter(
     *      name="layoutId",
     *      type="integer",
     *      in="path",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="backgroundColor",
     *      in="formData",
     *      description="A HEX color to use as the background color of this Layout.",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="backgroundImageId",
     *      in="formData",
     *      description="A media ID to use as the background image for this Layout.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="backgroundzIndex",
     *      in="formData",
     *      description="The Layer Number to use for the background.",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="resolutionId",
     *      in="formData",
     *      description="The Resolution ID to use on this Layout.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout")
     *  )
     * )
     */
    function editBackground(Request $request, Response $response, $id)
    {
        $layout = $this->layoutFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout)) {
            throw new AccessDeniedException();
        }

        // Check that this Layout is a Draft
        if (!$layout->isChild()) {
            throw new InvalidArgumentException(__('This Layout is not a Draft, please checkout.'), 'layoutId');
        }

        $layout->backgroundColor = $sanitizedParams->getString('backgroundColor');
        $layout->backgroundImageId = $sanitizedParams->getInt('backgroundImageId');
        $layout->backgroundzIndex = $sanitizedParams->getInt('backgroundzIndex');
        $layout->autoApplyTransitions = $sanitizedParams->getCheckbox('autoApplyTransitions');

        // Resolution
        $saveRegions = false;
        $resolution = $this->resolutionFactory->getById($sanitizedParams->getInt('resolutionId'));

        if ($layout->width != $resolution->width || $layout->height != $resolution->height) {
            $saveRegions = true;
            $layout->width = $resolution->width;
            $layout->height = $resolution->height;
        }

        // Save
        $layout->save([
            'saveLayout' => true,
            'saveRegions' => $saveRegions,
            'saveTags' => true,
            'setBuildRequired' => true,
            'notify' => false
        ]);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $layout->layout),
            'id' => $layout->layoutId,
            'data' => $layout
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Layout Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    function deleteForm(Request $request, Response $response, $id)
    {
        $layout = $this->layoutFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($layout))
            throw new AccessDeniedException(__('You do not have permissions to delete this layout'));

        $data = [
            'layout' => $layout,
            'help' => [
                'delete' => $this->getHelp()->link('Layout', 'Delete')
            ]
        ];

        $this->getState()->template = 'layout-form-delete';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * Retire Layout Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function retireForm(Request $request, Response $response, $id)
    {
        $layout = $this->layoutFactory->getById($id);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout)) {
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));
        }

        $data = [
            'layout' => $layout,
            'help' => [
                'delete' => $this->getHelp()->link('Layout', 'Retire')
            ]
        ];

        $this->getState()->template = 'layout-form-retire';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * Deletes a layout
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
     *  path="/layout/{layoutId}",
     *  operationId="layoutDelete",
     *  tags={"layout"},
     *  summary="Delete Layout",
     *  description="Delete a Layout",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout ID to Delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    function delete(Request $request, Response $response, $id)
    {
        $layout = $this->layoutFactory->loadById($id);

        if (!$this->getUser()->checkDeleteable($layout)) {
            throw new AccessDeniedException(__('You do not have permissions to delete this layout'));
        }

        // Make sure we're not a draft
        if ($layout->isChild()) {
            throw new InvalidArgumentException(__('Cannot delete Layout from its Draft, delete the parent'), 'layoutId');
        }

        $layout->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $layout->layout)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Retires a layout
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Put(
     *  path="/layout/retire/{layoutId}",
     *  operationId="layoutRetire",
     *  tags={"layout"},
     *  summary="Retire Layout",
     *  description="Retire a Layout so that it isn't available to Schedule. Existing Layouts will still be played",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    function retire(Request $request, Response $response, $id)
    {
        $layout = $this->layoutFactory->getById($id);

        if (!$this->getUser()->checkEditable($layout)) {
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));
        }

        // Make sure we're not a draft
        if ($layout->isChild()) {
            throw new InvalidArgumentException(__('Cannot modify Layout from its Draft'), 'layoutId');
        }

        // Make sure we aren't the global default
        if ($layout->layoutId == $this->getConfig()->getSetting('DEFAULT_LAYOUT')) {
            throw new InvalidArgumentException(__('This Layout is used as the global default and cannot be retired'),
                'layoutId');
        }

        $layout->retired = 1;
        $layout->save([
            'saveLayout' => true,
            'saveRegions' => false,
            'saveTags' => false,
            'setBuildRequired' => false
        ]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Retired %s'), $layout->layout)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Unretire Layout Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function unretireForm(Request $request, Response $response, $id)
    {
        $layout = $this->layoutFactory->getById($id);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout)) {
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));
        }

        $data = [
            'layout' => $layout,
            'help' => $this->getHelp()->link('Layout', 'Retire')
        ];

        $this->getState()->template = 'layout-form-unretire';
        $this->getState()->setData($data);

        return $this->render($request, $response);

    }

    /**
     * Unretires a layout
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Put(
     *  path="/layout/unretire/{layoutId}",
     *  operationId="layoutUnretire",
     *  tags={"layout"},
     *  summary="Unretire Layout",
     *  description="Retire a Layout so that it isn't available to Schedule. Existing Layouts will still be played",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    function unretire(Request $request, Response $response, $id)
    {
        $layout = $this->layoutFactory->getById($id);

        if (!$this->getUser()->checkEditable($layout)) {
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));
        }

        // Make sure we're not a draft
        if ($layout->isChild()) {
            throw new InvalidArgumentException(__('Cannot modify Layout from its Draft'), 'layoutId');
        }

        $layout->retired = 0;
        $layout->save([
            'saveLayout' => true,
            'saveRegions' => false,
            'saveTags' => false,
            'setBuildRequired' => false,
            'notify' => false
        ]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Unretired %s'), $layout->layout)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Set Enable Stats Collection of a layout
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Put(
     *  path="/layout/setenablestat/{layoutId}",
     *  operationId="layoutSetEnableStat",
     *  tags={"layout"},
     *  summary="Enable Stats Collection",
     *  description="Set Enable Stats Collection? to use for the collection of Proof of Play statistics for a Layout.",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="enableStat",
     *      in="formData",
     *      description="Flag indicating whether the Layout stat is enabled",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    function setEnableStat(Request $request, Response $response, $id)
    {
        $layout = $this->layoutFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($layout)) {
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));
        }

        // Make sure we're not a draft
        if ($layout->isChild()) {
            throw new InvalidArgumentException(__('Cannot modify Layout from its Draft'), 'layoutId');
        }

        $enableStat = $sanitizedParams->getCheckbox('enableStat');

        $layout->enableStat = $enableStat;
        $layout->save(['saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('For Layout %s Enable Stats Collection is set to %s'), $layout->layout, ($layout->enableStat == 1) ? __('On') : __('Off'))
        ]);

        return $this->render($request, $response);
    }

    /**
     * Set Enable Stat Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function setEnableStatForm(Request $request, Response $response, $id)
    {
        $layout = $this->layoutFactory->getById($id);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout)) {
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));
        }

        $data = [
            'layout' => $layout,
            'help' => $this->getHelp()->link('Layout', 'EnableStat')
        ];

        $this->getState()->template = 'layout-form-setenablestat';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * Shows the Layout Grid
     *
     * @SWG\Get(
     *  path="/layout",
     *  operationId="layoutSearch",
     *  tags={"layout"},
     *  summary="Search Layouts",
     *  description="Search for Layouts viewable by this user",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="query",
     *      description="Filter by Layout Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="parentId",
     *      in="query",
     *      description="Filter by parent Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="showDrafts",
     *      in="query",
     *      description="Flag indicating whether to show drafts",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="layout",
     *      in="query",
     *      description="Filter by partial Layout name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="userId",
     *      in="query",
     *      description="Filter by user Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="retired",
     *      in="query",
     *      description="Filter by retired flag",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="tags",
     *      in="query",
     *      description="Filter by Tags",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="exactTags",
     *      in="query",
     *      description="A flag indicating whether to treat the tags filter as an exact match",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ownerUserGroupId",
     *      in="query",
     *      description="Filter by users in this UserGroupId",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="publishedStatusId",
     *      in="query",
     *      description="Filter by published status id, 1 - Published, 2 - Draft",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="embed",
     *      in="query",
     *      description="Embed related data such as regions, playlists, widgets, tags, campaigns, permissions",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="campaignId",
     *      in="query",
     *      description="Get all Layouts for a given campaignId",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="query",
     *      description="Filter by Folder ID",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Layout")
     *      )
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Support\Exception\ConfigurationException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    function grid(Request $request, Response $response)
    {
        $this->getState()->template = 'grid';

        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());
        // Should we parse the description into markdown
        $showDescriptionId = $parsedQueryParams->getInt('showDescriptionId');

        // We might need to embed some extra content into the response if the "Show Description"
        // is set to media listing
        if ($showDescriptionId === 3) {
            $embed = ['regions', 'playlists', 'widgets'];
        } else {
            // Embed?
            $embed = ($parsedQueryParams->getString('embed') != null) ? explode(',', $parsedQueryParams->getString('embed')) : [];
        }

        // Get all layouts
        $layouts = $this->layoutFactory->query($this->gridRenderSort($parsedQueryParams), $this->gridRenderFilter([
            'layout' => $parsedQueryParams->getString('layout'),
            'useRegexForName' => $parsedQueryParams->getCheckbox('useRegexForName'),
            'userId' => $parsedQueryParams->getInt('userId'),
            'retired' => $parsedQueryParams->getInt('retired'),
            'tags' => $parsedQueryParams->getString('tags'),
            'exactTags' => $parsedQueryParams->getCheckbox('exactTags'),
            'filterLayoutStatusId' => $parsedQueryParams->getInt('layoutStatusId'),
            'layoutId' => $parsedQueryParams->getInt('layoutId'),
            'parentId' => $parsedQueryParams->getInt('parentId'),
            'showDrafts' => $parsedQueryParams->getInt('showDrafts'),
            'ownerUserGroupId' => $parsedQueryParams->getInt('ownerUserGroupId'),
            'mediaLike' => $parsedQueryParams->getString('mediaLike'),
            'publishedStatusId' => $parsedQueryParams->getInt('publishedStatusId'),
            'activeDisplayGroupId' => $parsedQueryParams->getInt('activeDisplayGroupId'),
            'campaignId' => $parsedQueryParams->getInt('campaignId'),
            'folderId' => $parsedQueryParams->getInt('folderId'),
            'codeLike' => $parsedQueryParams->getString('codeLike'),
            'orientation' => $parsedQueryParams->getString('orientation', ['defaultOnEmptyString' => true]),
            'onlyMyLayouts' => $parsedQueryParams->getCheckbox('onlyMyLayouts')
        ], $parsedQueryParams));

        foreach ($layouts as $layout) {
            /* @var \Xibo\Entity\Layout $layout */

            if (in_array('regions', $embed)) {
                $layout->load([
                    'loadPlaylists' => in_array('playlists', $embed),
                    'loadCampaigns' => in_array('campaigns', $embed),
                    'loadPermissions' => in_array('permissions', $embed),
                    'loadTags' => in_array('tags', $embed),
                    'loadWidgets' => in_array('widgets', $embed),
                    'loadActions' => in_array('actions', $embed)
                ]);
            }

            // Populate the status message
            $layout->getStatusMessage();

            // Add Locking information
            $layout = $this->layoutFactory->decorateLockedProperties($layout);

            // Annotate each Widget with its validity, tags and permissions
            if (in_array('widget_validity', $embed) || in_array('tags', $embed) || in_array('permissions', $embed)) { 
                foreach ($layout->getAllWidgets() as $widget) {
                    /* @var Widget $widget */
                    $module = $this->moduleFactory->createWithWidget($widget);

                    $widget->name = $module->getName();

                    // Augment with tags
                    $widget->tags = $module->getMediaTags();

                    // Add widget module type name
                    $widget->moduleName = $module->getModuleName();

                    // apply default transitions to a dynamic parameters on widget object.
                    if ($layout->autoApplyTransitions == 1) {
                        $widgetTransIn = $widget->getOptionValue('transIn', $this->getConfig()->getSetting('DEFAULT_TRANSITION_IN'));
                        $widgetTransOut = $widget->getOptionValue('transOut', $this->getConfig()->getSetting('DEFAULT_TRANSITION_OUT'));
                        $widgetTransInDuration = $widget->getOptionValue('transInDuration', $this->getConfig()->getSetting('DEFAULT_TRANSITION_DURATION'));
                        $widgetTransOutDuration = $widget->getOptionValue('transOutDuration', $this->getConfig()->getSetting('DEFAULT_TRANSITION_DURATION'));
                    } else {
                        $widgetTransIn = $widget->getOptionValue('transIn', null);
                        $widgetTransOut = $widget->getOptionValue('transOut', null);
                        $widgetTransInDuration = $widget->getOptionValue('transInDuration', null);
                        $widgetTransOutDuration = $widget->getOptionValue('transOutDuration', null);
                    }

                    $widget->transitionIn = $widgetTransIn;
                    $widget->transitionOut = $widgetTransOut;
                    $widget->transitionDurationIn = $widgetTransInDuration;
                    $widget->transitionDurationOut = $widgetTransOutDuration;

                    if (in_array('permissions', $embed)) {
                        // Augment with editable flag
                        $widget->isEditable = $this->getUser()->checkEditable($widget);

                        // Augment with deletable flag
                        $widget->isDeletable = $this->getUser()->checkDeleteable($widget);

                        // Augment with permissions flag
                        $widget->isPermissionsModifiable = $this->getUser()->checkPermissionsModifyable($widget);
                    }

                    if (in_array('widget_validity', $embed)) {
                        try {
                            $widget->isValid = (int)$module->isValid();
                        } catch (GeneralException $xiboException) {
                            $widget->isValid = 0;
                        }
                    }
                }

                $allRegions = array_merge($layout->regions, $layout->drawers);

                // Augment regions with permissions
                foreach ($allRegions as $region) {
                    if (in_array('permissions', $embed)) {
                        // Augment with editable flag
                        $region->isEditable = $this->getUser()->checkEditable($region);

                         // Augment with deletable flag
                        $region->isDeletable = $this->getUser()->checkDeleteable($region);

                        // Augment with permissions flag
                        $region->isPermissionsModifiable = $this->getUser()->checkPermissionsModifyable($region);
                    }
                }

            }

            if ($this->isApi($request)) {
                continue;
            }

            $layout->includeProperty('buttons');
            //$layout->excludeProperty('regions');

            $layout->thumbnail = '';

            if ($layout->backgroundImageId != 0) {
                $download = $this->urlFor($request,'layout.download.background', ['id' => $layout->layoutId]) . '?preview=1' . '&backgroundImageId=' . $layout->backgroundImageId;
                $layout->thumbnail = '<a class="img-replace" data-toggle="lightbox" data-type="image" href="' . $download . '"><img src="' . $download . '&width=100&height=56" /></i></a>';
            }

            // Fix up the description
            $layout->descriptionFormatted = $layout->description;

            if ($layout->description != '') {
                if ($showDescriptionId == 1) {
                    // Parse down for description
                    $layout->descriptionFormatted = Parsedown::instance()->text($layout->description);
                } else if ($showDescriptionId == 2) {
                    $layout->descriptionFormatted = strtok($layout->description, "\n");
                }
            }

            if ($showDescriptionId === 3) {
                // Load in the entire object model - creating module objects so that we can get the name of each
                // widget and its items.
                foreach ($layout->regions as $region) {
                    foreach ($region->getPlaylist()->widgets as $widget) {
                        /* @var Widget $widget */
                        $widget->module = $this->moduleFactory->createWithWidget($widget, $region);
                    }
                }

                // provide our layout object to a template to render immediately
                $layout->descriptionFormatted = $this->renderTemplateToString('layout-page-grid-widgetlist',
                    (array)$layout);
            }

            switch ($layout->status) {

                case ModuleWidget::$STATUS_VALID:
                    $layout->statusDescription = __('This Layout is ready to play');
                    break;

                case ModuleWidget::$STATUS_PLAYER:
                    $layout->statusDescription = __('There are items on this Layout that can only be assessed by the Display');
                    break;

                case 3:
                    $layout->statusDescription = __('This Layout has not been built yet');
                    break;

                default:
                    $layout->statusDescription = __('This Layout is invalid and should not be scheduled');
            }

            switch ($layout->enableStat) {

                case 1:
                    $layout->enableStatDescription = __('This Layout has enable stat collection set to ON');
                    break;

                default:
                    $layout->enableStatDescription = __('This Layout has enable stat collection set to OFF');
            }

            // Published status, draft with set publishedDate
            $layout->publishedStatusFuture = __('Publishing %s');
            $layout->publishedStatusFailed = __('Publish failed ');

            // Check if user has view permissions to the schedule now page - for layout designer to show/hide Schedule Now button
            $layout->scheduleNowPermission = $this->getUser()->featureEnabled('schedule.now');

            // Add some buttons for this row
            if ($this->getUser()->featureEnabled('layout.modify')
                && $this->getUser()->checkEditable($layout)
            ) {
                // Design Button
                $layout->buttons[] = array(
                    'id' => 'layout_button_design',
                    'linkType' => '_self', 'external' => true,
                    'url' => $this->urlFor($request,'layout.designer', array('id' => $layout->layoutId)),
                    'text' => __('Design')
                );

                // Should we show a publish/discard button?
                if ($layout->isEditable()) {

                    $layout->buttons[] = ['divider' => true];

                    $layout->buttons[] = array(
                        'id' => 'layout_button_publish',
                        'url' => $this->urlFor($request,'layout.publish.form', ['id' => $layout->layoutId]),
                        'text' => __('Publish')
                    );

                    $layout->buttons[] = array(
                        'id' => 'layout_button_discard',
                        'url' => $this->urlFor($request,'layout.discard.form', ['id' => $layout->layoutId]),
                        'text' => __('Discard')
                    );

                    $layout->buttons[] = ['divider' => true];
                } else {
                    $layout->buttons[] = ['divider' => true];

                    // Checkout Button
                    $layout->buttons[] = array(
                        'id' => 'layout_button_checkout',
                        'url' => $this->urlFor($request,'layout.checkout.form', ['id' => $layout->layoutId]),
                        'text' => __('Checkout'),
                        'dataAttributes' => [
                            ['name' => 'auto-submit', 'value' => true],
                            ['name' => 'commit-url', 'value' => $this->urlFor($request,'layout.checkout', ['id' => $layout->layoutId])],
                            ['name' => 'commit-method', 'value' => 'PUT']
                        ]
                    );

                    $layout->buttons[] = ['divider' => true];
                }
            }

            // Preview
            if ($this->getUser()->featureEnabled('layout.view')) {
                $layout->buttons[] = array(
                    'id' => 'layout_button_preview',
                    'external' => true,
                    'url' => '#',
                    'onclick' => 'createMiniLayoutPreview("' . $this->urlFor($request, 'layout.preview', ['id' => $layout->layoutId]) . '");',
                    'text' => __('Preview Layout')
                );

                $layout->buttons[] = ['divider' => true];
            }

            // Schedule Now
            if ($this->getUser()->featureEnabled('schedule.now')) {
                $layout->buttons[] = array(
                    'id' => 'layout_button_schedulenow',
                    'url' => $this->urlFor($request,'schedule.now.form', ['id' => $layout->campaignId, 'from' => 'Layout']),
                    'text' => __('Schedule Now')
                );
            }

            // Assign to Campaign
            if ($this->getUser()->featureEnabled('campaign.modify')) {
                $layout->buttons[] = array(
                    'id' => 'layout_button_assignTo_campaign',
                    'url' => $this->urlFor($request,'layout.assignTo.campaign.form', ['id' => $layout->layoutId]),
                    'text' => __('Assign to Campaign')
                );
            }

            $layout->buttons[] = ['divider' => true];

            if ($this->getUser()->featureEnabled('playlist.view')) {
                $layout->buttons[] = [
                    'id' => 'layout_button_playlist_jump',
                    'linkType' => '_self', 'external' => true,
                    'url' => $this->urlFor($request, 'playlist.view') .'?layoutId=' . $layout->layoutId,
                    'text' => __('Jump to Playlists included on this Layout')
                ];
            }

            if ($this->getUser()->featureEnabled('campaign.view')) {
                $layout->buttons[] = [
                    'id' => 'layout_button_campaign_jump',
                    'linkType' => '_self', 'external' => true,
                    'url' => $this->urlFor($request, 'campaign.view') .'?layoutId=' . $layout->layoutId,
                    'text' => __('Jump to Campaigns containing this Layout')
                ];
            }

            if ($this->getUser()->featureEnabled('library.view')) {
                $layout->buttons[] = [
                    'id' => 'layout_button_media_jump',
                    'linkType' => '_self', 'external' => true,
                    'url' => $this->urlFor($request, 'library.view') .'?layoutId=' . $layout->layoutId,
                    'text' => __('Jump to Media included on this Layout')
                ];
            }

            $layout->buttons[] = ['divider' => true];

            // Only proceed if we have edit permissions
            if ($this->getUser()->featureEnabled('layout.modify')
                && $this->getUser()->checkEditable($layout)
            ) {
                // Edit Button
                $layout->buttons[] = array(
                    'id' => 'layout_button_edit',
                    'url' => $this->urlFor($request,'layout.edit.form', ['id' => $layout->layoutId]),
                    'text' => __('Edit')
                );

                if ($this->getUser()->featureEnabled('folder.view')) {
                    // Select Folder
                    $layout->buttons[] = [
                        'id' => 'campaign_button_selectfolder',
                        'url' => $this->urlFor($request,'campaign.selectfolder.form', ['id' => $layout->campaignId]),
                        'text' => __('Select Folder'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            ['name' => 'commit-url', 'value' => $this->urlFor($request,'campaign.selectfolder', ['id' => $layout->campaignId])],
                            ['name' => 'commit-method', 'value' => 'put'],
                            ['name' => 'id', 'value' => 'campaign_button_selectfolder'],
                            ['name' => 'text', 'value' => __('Move to Folder')],
                            ['name' => 'rowtitle', 'value' => $layout->layout],
                            ['name' => 'form-callback', 'value' => 'moveFolderMultiSelectFormOpen']
                        ]
                    ];
                }

                // Copy Button
                $layout->buttons[] = array(
                    'id' => 'layout_button_copy',
                    'url' => $this->urlFor($request,'layout.copy.form', ['id' => $layout->layoutId]),
                    'text' => __('Copy')
                );

                // Retire Button
                if ($layout->retired == 0) {
                    $layout->buttons[] = [
                        'id' => 'layout_button_retire',
                        'url' => $this->urlFor($request,'layout.retire.form', ['id' => $layout->layoutId]),
                        'text' => __('Retire'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            ['name' => 'commit-url', 'value' => $this->urlFor($request,'layout.retire', ['id' => $layout->layoutId])],
                            ['name' => 'commit-method', 'value' => 'put'],
                            ['name' => 'id', 'value' => 'layout_button_retire'],
                            ['name' => 'text', 'value' => __('Retire')],
                            ['name' => 'sort-group', 'value' => 1],
                            ['name' => 'rowtitle', 'value' => $layout->layout]
                        ]
                    ];
                } else {
                    $layout->buttons[] = array(
                        'id' => 'layout_button_unretire',
                        'url' => $this->urlFor($request,'layout.unretire.form', ['id' => $layout->layoutId]),
                        'text' => __('Unretire'),
                    );
                }

                // Extra buttons if have delete permissions
                if ($this->getUser()->checkDeleteable($layout)) {
                    // Delete Button
                    $layout->buttons[] = [
                        'id' => 'layout_button_delete',
                        'url' => $this->urlFor($request,'layout.delete.form', ['id' => $layout->layoutId]),
                        'text' => __('Delete'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            ['name' => 'commit-url', 'value' => $this->urlFor($request,'layout.delete', ['id' => $layout->layoutId])],
                            ['name' => 'commit-method', 'value' => 'delete'],
                            ['name' => 'id', 'value' => 'layout_button_delete'],
                            ['name' => 'text', 'value' => __('Delete')],
                            ['name' => 'sort-group', 'value' => 1],
                            ['name' => 'rowtitle', 'value' => $layout->layout]
                        ]
                    ];
                }

                // Set Enable Stat
                $layout->buttons[] = [
                    'id' => 'layout_button_setenablestat',
                    'url' => $this->urlFor($request,'layout.setenablestat.form', ['id' => $layout->layoutId]),
                    'text' => __('Enable stats collection?'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'commit-url', 'value' => $this->urlFor($request,'layout.setenablestat', ['id' => $layout->layoutId])],
                        ['name' => 'commit-method', 'value' => 'put'],
                        ['name' => 'id', 'value' => 'layout_button_setenablestat'],
                        ['name' => 'text', 'value' => __('Enable stats collection?')],
                        ['name' => 'rowtitle', 'value' => $layout->layout],
                        ['name' => 'form-callback', 'value' => 'setEnableStatMultiSelectFormOpen']
                    ]
                ];

                $layout->buttons[] = ['divider' => true];

                if ($this->getUser()->featureEnabled('template.modify') && !$layout->isEditable()) {
                    // Save template button
                    $layout->buttons[] = array(
                        'id' => 'layout_button_save_template',
                        'url' => $this->urlFor($request,'template.from.layout.form', ['id' => $layout->layoutId]),
                        'text' => __('Save Template')
                    );
                }

                // Export Button
                if ($this->getUser()->featureEnabled('layout.export')) {
                    $layout->buttons[] = array(
                        'id' => 'layout_button_export',
                        'url' => $this->urlFor($request, 'layout.export.form', ['id' => $layout->layoutId]),
                        'text' => __('Export')
                    );
                }

                // Extra buttons if we have modify permissions
                if ($this->getUser()->checkPermissionsModifyable($layout)) {
                    // Permissions button
                    $layout->buttons[] = [
                        'id' => 'layout_button_permissions',
                        'url' => $this->urlFor($request,'user.permissions.form', ['entity' => 'Campaign', 'id' => $layout->campaignId]),
                        'text' => __('Share'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            ['name' => 'commit-url', 'value' => $this->urlFor($request,'user.permissions.multi', ['entity' => 'Campaign', 'id' => $layout->campaignId])],
                            ['name' => 'commit-method', 'value' => 'post'],
                            ['name' => 'id', 'value' => 'layout_button_permissions'],
                            ['name' => 'text', 'value' => __('Share')],
                            ['name' => 'rowtitle', 'value' => $layout->layout],
                            ['name' => 'sort-group', 'value' => 2],
                            ['name' => 'custom-handler', 'value' => 'XiboMultiSelectPermissionsFormOpen'],
                            ['name' => 'custom-handler-url', 'value' => $this->urlFor($request,'user.permissions.multi.form', ['entity' => 'Campaign'])],
                            ['name' => 'content-id-name', 'value' => 'campaignId']
                        ]
                    ];
                }
            }
        }

        // Store the table rows
        $this->getState()->recordsTotal = $this->layoutFactory->countLast();
        $this->getState()->setData($layouts);

        return $this->render($request, $response);
    }

    /**
     * Displays an Add/Edit form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    function addForm(Request $request, Response $response)
    {
        $this->getState()->template = 'layout-form-add';
        $this->getState()->setData([
            'layouts' => $this->layoutFactory->query(['layout'], ['excludeTemplates' => 0, 'tags' => 'template']),
            'resolutions' => $this->resolutionFactory->query(['resolution']),
            'help' => $this->getHelp()->link('Layout', 'Add')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    function editForm(Request $request, Response $response, $id)
    {
        // Get the layout
        $layout = $this->layoutFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($layout)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'layout-form-edit';
        $this->getState()->setData([
            'layout' => $layout,
            'tags' => $this->tagFactory->getTagsWithValues($layout),
            'help' => $this->getHelp()->link('Layout', 'Edit')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    function editBackgroundForm(Request $request, Response $response, $id)
    {
        // Get the layout
        $layout = $this->layoutFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Check Permissions
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();
            
        // Edits always happen on Drafts, get the draft Layout using the Parent Layout ID
        if ($layout->schemaVersion < 2) {
            $resolution = $this->resolutionFactory->getByDesignerDimensions($layout->width, $layout->height);
        } else {
            $resolution = $this->resolutionFactory->getByDimensions($layout->width, $layout->height);
        }

        // If we have a background image, output it
        $backgroundId = $sanitizedParams->getInt('backgroundOverride', ['default' => $layout->backgroundImageId]);
        $backgrounds = ($backgroundId != null) ? [$this->mediaFactory->getById($backgroundId)] : [];

        $this->getState()->template = 'layout-form-background';
        $this->getState()->setData([
            'layout' => $layout,
            'resolution' => $resolution,
            'resolutions' => $this->resolutionFactory->query(['resolution'], ['withCurrent' => $resolution->resolutionId]),
            'backgroundId' => $backgroundId,
            'backgrounds' => $backgrounds,
            'help' => $this->getHelp()->link('Layout', 'Edit')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Copy layout form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function copyForm(Request $request, Response $response, $id)
    {
        // Get the layout
        $layout = $this->layoutFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkViewable($layout))
            throw new AccessDeniedException();

        $this->getState()->template = 'layout-form-copy';
        $this->getState()->setData([
            'layout' => $layout,
            'help' => $this->getHelp()->link('Layout', 'Copy')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Copies a layout
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ConfigurationException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @SWG\Post(
     *  path="/layout/copy/{layoutId}",
     *  operationId="layoutCopy",
     *  tags={"layout"},
     *  summary="Copy Layout",
     *  description="Copy a Layout, providing a new name if applicable",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout ID to Copy",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="The name for the new Layout",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="The Description for the new Layout",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="copyMediaFiles",
     *      in="formData",
     *      description="Flag indicating whether to make new Copies of all Media Files assigned to the Layout being Copied",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     */
    public function copy(Request $request, Response $response, $id)
    {
        // Get the layout
        $originalLayout = $this->layoutFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Check Permissions
        if (!$this->getUser()->checkViewable($originalLayout)) {
            throw new AccessDeniedException();
        }

        // Make sure we're not a draft
        if ($originalLayout->isChild()) {
            throw new InvalidArgumentException(__('Cannot copy a Draft Layout'), 'layoutId');
        }

        // Load the layout for Copy
        $originalLayout->load(['loadTags' => false]);

        // Clone
        $layout = clone $originalLayout;
        $tags = $this->tagFactory->getTagsWithValues($layout);

        $this->getLog()->debug('Tag values from original layout: ' . $tags);

        $layout->layout = $sanitizedParams->getString('name');
        $layout->description = $sanitizedParams->getString('description');
        $layout->replaceTags($this->tagFactory->tagsFromString($tags));
        $layout->setOwner($this->getUser()->userId, true);

        // Copy the media on the layout and change the assignments.
        // https://github.com/xibosignage/xibo/issues/1283
        if ($sanitizedParams->getCheckbox('copyMediaFiles') == 1) {
            // track which Media Id we already copied
            $copiedMediaIds = [];
            foreach ($layout->getAllWidgets() as $widget) {
                // Copy the media
                    if ( $widget->type === 'image' || $widget->type === 'video' || $widget->type === 'pdf' || $widget->type === 'powerpoint' || $widget->type === 'audio' ) {
                        $oldMedia = $this->mediaFactory->getById($widget->getPrimaryMediaId());

                        // check if we already cloned this media, if not, do it and add it the array
                        if (!array_key_exists($oldMedia->mediaId, $copiedMediaIds)) {
                            $media = clone $oldMedia;
                            $media->setOwner($this->getUser()->userId);
                            $media->save();
                            $copiedMediaIds[$oldMedia->mediaId] = $media->mediaId;
                        } else {
                            // if we already cloned that media, look it up and assign to Widget.
                            $mediaId = $copiedMediaIds[$oldMedia->mediaId];
                            $media = $this->mediaFactory->getById($mediaId);
                        }

                        $widget->unassignMedia($oldMedia->mediaId);
                        $widget->assignMedia($media->mediaId);

                        // Update the widget option with the new ID
                        $widget->setOptionValue('uri', 'attrib', $media->storedAs);
                    }
            }

            // Also handle the background image, if there is one
            if ($layout->backgroundImageId != 0) {
                $oldMedia = $this->mediaFactory->getById($layout->backgroundImageId);
                // check if we already cloned this media, if not, do it and add it the array
                if (!array_key_exists($oldMedia->mediaId, $copiedMediaIds)) {
                    $media = clone $oldMedia;
                    $media->setOwner($this->getUser()->userId);
                    $media->save();
                    $copiedMediaIds[$oldMedia->mediaId] = $media->mediaId;
                } else {
                    // if we already cloned that media, look it up and assign to Layout backgroundImage.
                    $mediaId = $copiedMediaIds[$oldMedia->mediaId];
                    $media = $this->mediaFactory->getById($mediaId);
                }

                $layout->backgroundImageId = $media->mediaId;
            }
        }

        // Save the new layout
        $layout->save();

        $allRegions = array_merge($layout->regions, $layout->drawers);

        // this will adjusted source/target Ids in the copied layout
        $layout->copyActions($layout, $originalLayout);

        // Sub-Playlist
        /** @var Region $region */
        foreach ($allRegions as $region) {
            // Match our original region id to the id in the parent layout
            $original = $originalLayout->getRegionOrDrawer($region->getOriginalValue('regionId'));

            // Make sure Playlist closure table from the published one are copied over
            $original->getPlaylist()->cloneClosureTable($region->getPlaylist()->playlistId);
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Copied as %s'), $layout->layout),
            'id' => $layout->layoutId,
            'data' => $layout
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Post(
     *  path="/layout/{layoutId}/tag",
     *  operationId="layoutTag",
     *  tags={"layout"},
     *  summary="Tag Layout",
     *  description="Tag a Layout with one or more tags",
     * @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout Id to Tag",
     *      type="integer",
     *      required=true
     *   ),
     * @SWG\Parameter(
     *      name="tag",
     *      in="formData",
     *      description="An array of tags",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="string")
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout")
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function tag(Request $request, Response $response, $id)
    {
        // Edit permission
        // Get the layout
        $layout = $this->layoutFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Check Permissions
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        // Make sure we're not a draft
        if ($layout->isChild())
            throw new InvalidArgumentException(__('Cannot manage tags on a Draft Layout'), 'layoutId');

        $tags = $sanitizedParams->getArray('tag');

        if (count($tags) <= 0) {
            throw new InvalidArgumentException(__('No tags to assign'));
        }

        foreach ($tags as $tag) {
            $layout->assignTag($this->tagFactory->tagFromString($tag));
        }

        $layout->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Tagged %s'), $layout->layout),
            'id' => $layout->layoutId,
            'data' => $layout
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Post(
     *  path="/layout/{layoutId}/untag",
     *  operationId="layoutUntag",
     *  tags={"layout"},
     *  summary="Untag Layout",
     *  description="Untag a Layout with one or more tags",
     * @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout Id to Untag",
     *      type="integer",
     *      required=true
     *   ),
     * @SWG\Parameter(
     *      name="tag",
     *      in="formData",
     *      description="An array of tags",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="string")
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout")
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function untag(Request $request, Response $response, $id)
    {
        // Edit permission
        // Get the layout
        $layout = $this->layoutFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Check Permissions
        if (!$this->getUser()->checkEditable($layout))
            throw new AccessDeniedException();

        // Make sure we're not a draft
        if ($layout->isChild())
            throw new InvalidArgumentException(__('Cannot manage tags on a Draft Layout'), 'layoutId');

        $tags = $sanitizedParams->getArray('tag');

        if (count($tags) <= 0)
            throw new InvalidArgumentException(__('No tags to unassign'), 'tag');

        foreach ($tags as $tag) {
            $layout->unassignTag($this->tagFactory->tagFromString($tag));
        }

        $layout->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Untagged %s'), $layout->layout),
            'id' => $layout->layoutId,
            'data' => $layout
        ]);

        return $this->render($request, $response);
    }

    /**
     * Layout Status
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Get(
     *  path="/layout/status/{layoutId}",
     *  operationId="layoutStatus",
     *  tags={"layout"},
     *  summary="Layout Status",
     *  description="Calculate the Layout status and return a Layout",
     * @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout Id to get the status",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout")
     *  )
     * )
     */
    public function status(Request $request, Response $response, $id)
    {
        // Get the layout
        $layout = $this->layoutFactory->concurrentRequestLock($this->layoutFactory->getById($id));
        $layout = $this->layoutFactory->decorateLockedProperties($layout);
        $layout->xlfToDisk();

        switch ($layout->status) {

            case ModuleWidget::$STATUS_VALID:
                $status = __('This Layout is ready to play');
                break;

            case ModuleWidget::$STATUS_PLAYER:
                $status = __('There are items on this Layout that can only be assessed by the Display');
                break;

            case 3:
                $status = __('This Layout has not been built yet');
                break;

            default:
                $status = __('This Layout is invalid and should not be scheduled');
        }

        // We want a different return depending on whether we are arriving through the API or WEB routes
        if ($this->isApi($request)) {

            $this->getState()->hydrate([
                'httpStatus' => 200,
                'message' => $status,
                'id' => $layout->status,
                'data' => $layout
            ]);

        } else {

            $this->getState()->html = $status;
            $this->getState()->extra = [
                'status' => $layout->status,
                'duration' => $layout->duration,
                'statusMessage' => $layout->getStatusMessage(),
                'isLocked' => $layout->isLocked
            ];

            $this->getState()->success = true;
            $this->session->refreshExpiry = false;
        }

        // Release lock
        $this->layoutFactory->concurrentRequestRelease($layout);

        return $this->render($request, $response);
    }

    /**
     * Export Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function exportForm(Request $request, Response $response, $id)
    {
        // Get the layout
        $layout = $this->layoutFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkViewable($layout))
            throw new AccessDeniedException();

        // Make sure we're not a draft
        if ($layout->isChild())
            throw new InvalidArgumentException(__('Cannot manage tags on a Draft Layout'), 'layoutId');

        // Render the form
        $this->getState()->template = 'layout-form-export';
        $this->getState()->setData([
            'layout' => $layout,
            'saveAs' => 'export_' . preg_replace('/[^a-z0-9]+/', '-', strtolower($layout->layout))
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function export(Request $request, Response $response, $id)
    {
        $this->setNoOutput(true);

        // Get the layout
        $layout = $this->layoutFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Check Permissions
        if (!$this->getUser()->checkViewable($layout)) {
            throw new AccessDeniedException();
        }

        // Make sure we're not a draft
        if ($layout->isChild()) {
            throw new InvalidArgumentException(__('Cannot manage tags on a Draft Layout'), 'layoutId');
        }

        // Save As?
        $saveAs = $sanitizedParams->getString('saveAs');

        // Make sure our file name is reasonable
        if (empty($saveAs)) {
            $saveAs = 'export_' . preg_replace('/[^a-z0-9]+/', '-', strtolower($layout->layout));
        } else {
            $saveAs = preg_replace('/[^a-z0-9]+/', '-', strtolower($saveAs));
        }

        $fileName = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/' . $saveAs . '.zip';
        $layout->toZip($this->dataSetFactory, $fileName, ['includeData' => ($sanitizedParams->getCheckbox('includeData')== 1)]);

        return $this->render($request, SendFile::decorateResponse(
            $response,
            $this->getConfig()->getSetting('SENDFILE_MODE'),
            $fileName
        ));
    }

    /**
     * TODO: Not sure how to document this.
     * SWG\Post(
     *  path="/layout/import",
     *  operationId="layoutImport",
     *  tags={"layout"},
     *  summary="Import Layout",
     *  description="Upload and Import a Layout",
     *  consumes="multipart/form-data",
     *  SWG\Parameter(
     *      name="file",
     *      in="formData",
     *      description="The file",
     *      type="file",
     *      required=true
     *   ),
     * @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws \Xibo\Support\Exception\ConfigurationException
     */
    public function import(Request $request, Response $response)
    {
        $this->getLog()->debug('Import Layout');

        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        MediaService::ensureLibraryExists($this->getConfig()->getSetting('LIBRARY_LOCATION'));

        // Make sure there is room in the library
        $libraryLimit = $this->getConfig()->getSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;

        $options = [
            'userId' => $this->getUser()->userId,
            'controller' => $this,
            'dataSetFactory' => $this->getDataSetFactory(),
            'upload_dir' => $libraryFolder . 'temp/',
            'download_via_php' => true,
            'script_url' => $this->urlFor($request,'layout.import'),
            'upload_url' => $this->urlFor($request,'layout.import'),
            'image_versions' => [],
            'accept_file_types' => '/\.zip$/i',
            'libraryLimit' => $libraryLimit,
            'libraryQuotaFull' => ($libraryLimit > 0 && $this->mediaService->libraryUsage() > $libraryLimit),
            'routeParser' => RouteContext::fromRequest($request)->getRouteParser(),
            'mediaService' => $this->mediaService
        ];

        $this->setNoOutput(true);

        // Hand off to the Upload Handler provided by jquery-file-upload
        new LayoutUploadHandler($options);

        return $response;
    }

    /**
     * Gets a file from the library
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function downloadBackground(Request $request, Response $response, $id)
    {
        $this->getLog()->debug('Layout Download background request for layoutId ' . $id);

        $layout = $this->layoutFactory->getById($id);

        if (!$this->getUser()->checkViewable($layout)) {
            throw new AccessDeniedException();
        }

        if ($layout->backgroundImageId == null) {
            throw new NotFoundException();
        }

        // This media may not be viewable, but we won't check it because the user has permission to view the
        // layout that it is assigned to.
        $media = $this->mediaFactory->getById($layout->backgroundImageId);

        // Make a media module
        $widget = $this->moduleFactory->createWithMedia($media);

        if ($widget->getModule()->regionSpecific == 1) {
            throw new NotFoundException(__('Cannot download non-region specific module'));
        }

        $response = $widget->download($request, $response);

        $this->setNoOutput(true);
        return $this->render($request, $response);
    }

    /**
     * Assign to Campaign Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function assignToCampaignForm(Request $request, Response $response, $id)
    {
        // Get the layout
        $layout = $this->layoutFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkViewable($layout)) {
            throw new AccessDeniedException();
        }

        // Render the form
        $this->getState()->template = 'layout-form-assign-to-campaign';
        $this->getState()->setData([
            'layout' => $layout,
            'campaigns' => $this->campaignFactory->query()
        ]);

        return $this->render($request, $response);
    }

    /**
     * Checkout Layout Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function checkoutForm(Request $request, Response $response, $id)
    {
        $layout = $this->layoutFactory->getById($id);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout)) {
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));
        }

        $data = ['layout' => $layout];

        $this->getState()->template = 'layout-form-checkout';
        $this->getState()->autoSubmit = $this->getAutoSubmit('layoutCheckoutForm');
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * Checkout Layout
     *
     * @SWG\Put(
     *  path="/layout/checkout/{layoutId}",
     *  operationId="layoutCheckout",
     *  tags={"layout"},
     *  summary="Checkout Layout",
     *  description="Checkout a Layout so that it can be edited. The original Layout will still be played",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout")
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function checkout(Request $request, Response $response, $id)
    {
        $layout = $this->layoutFactory->getById($id);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout)) {
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));
        }

        // Can't checkout a Layout which can already be edited
        if ($layout->isEditable()) {
            throw new InvalidArgumentException(__('Layout is already checked out'), 'statusId');
        }

        // Checkout this Layout
        $draft = $this->layoutFactory->checkoutLayout($layout);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'message' => sprintf(__('Checked out %s'), $layout->layout),
            'id' => $draft->layoutId,
            'data' => $draft
        ]);

        return $this->render($request, $response);
    }

    /**
     * Publish Layout Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function publishForm(Request $request, Response $response, $id)
    {
        $layout = $this->layoutFactory->getById($id);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout)) {
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));
        }

        $data = ['layout' => $layout];

        $this->getState()->template = 'layout-form-publish';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * Publish Layout
     *
     * @SWG\Put(
     *  path="/layout/publish/{layoutId}",
     *  operationId="layoutPublish",
     *  tags={"layout"},
     *  summary="Publish Layout",
     *  description="Publish a Layout, discarding the original",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="publishNow",
     *      in="formData",
     *      description="Flag, indicating whether to publish layout now",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="publishDate",
     *      in="formData",
     *      description="The date/time at which layout should be published",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout")
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function publish(Request $request, Response $response, $id)
    {
        Profiler::start('Layout::publish', $this->getLog());
        $layout = $this->layoutFactory->concurrentRequestLock($this->layoutFactory->getById($id));
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $publishDate = $sanitizedParams->getDate('publishDate');
        $publishNow = $sanitizedParams->getCheckbox('publishNow');

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout)) {
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));
        }

        // if we have publish date update it in database
        if (isset($publishDate) && !$publishNow) {
            $layout->setPublishedDate($publishDate);
        }

        // We want to take the draft layout, and update the campaign links to point to the draft, then remove the
        // parent.
        if ($publishNow || (isset($publishDate) && $publishDate->format('U') <  Carbon::now()->format('U')) ) {
            $draft = $this->layoutFactory->getByParentId($id);
            $draft->publishDraft();
            $draft->load();

            // We also build the XLF at this point, and if we have a problem we prevent publishing and raise as an
            // error message
            $draft->xlfToDisk(['notify' => true, 'exceptionOnError' => true, 'exceptionOnEmptyRegion' => false]);

            // Return
            $this->getState()->hydrate([
                'httpStatus' => 200,
                'message' => sprintf(__('Published %s'), $draft->layout),
                'data' => $draft
            ]);
        } else {
            // Return
            $this->getState()->hydrate([
                'httpStatus' => 200,
                'message' => sprintf(__('Layout will be published on %s'), $publishDate),
                'data' => $layout
            ]);
        }

        Profiler::end('Layout::publish', $this->getLog());

        // Release lock
        $this->layoutFactory->concurrentRequestRelease($layout);

        return $this->render($request, $response);
    }

    /**
     * Discard Layout Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function discardForm(Request $request, Response $response, $id)
    {
        $layout = $this->layoutFactory->getById($id);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout)) {
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));
        }

        $data = ['layout' => $layout];

        $this->getState()->template = 'layout-form-discard';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * Discard Layout
     *
     * @SWG\Put(
     *  path="/layout/discard/{layoutId}",
     *  operationId="layoutDiscard",
     *  tags={"layout"},
     *  summary="Discard Layout",
     *  description="Discard a Layout restoring the original",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Layout")
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function discard(Request $request, Response $response, $id)
    {
        $layout = $this->layoutFactory->getById($id);

        // Make sure we have permission
        if (!$this->getUser()->checkEditable($layout)) {
            throw new AccessDeniedException(__('You do not have permissions to edit this layout'));
        }

        // Make sure the Layout is checked out to begin with
        if (!$layout->isEditable()) {
            throw new InvalidArgumentException(__('Layout is not checked out'), 'statusId');
        }

        $draft = $this->layoutFactory->getByParentId($id);
        $draft->discardDraft();

        // The parent is no longer a draft
        $layout->publishedStatusId = 1;

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'message' => sprintf(__('Discarded %s'), $draft->layout),
            'data' => $layout
        ]);

        return $this->render($request, $response);
    }

    /**
     * Query the Database for all Code identifiers assigned to Layouts.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function getLayoutCodes(Request $request, Response $response)
    {
        $parsedParams = $this->getSanitizer($request->getQueryParams());
        $codes = $this->layoutFactory->getLayoutCodes($this->gridRenderFilter([], $parsedParams));

        // Store the table rows
        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->layoutFactory->countLast();
        $this->getState()->setData($codes);

        return $this->render($request, $response);
    }

    /**
     * Release the Layout Lock on specified layoutId, Super Admin only.
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function releaseLock(Request $request, Response $response, $id)
    {
        if (!$this->getUser()->isSuperAdmin()) {
            throw new InvalidArgumentException(__('This function is available only to Super Admins.'));
        }

        /** @var Item $lock */
        $lock = $this->pool->getItem('locks/layout/' . $id);
        $lock->set([]);
        $lock->save();

        return $this->render($request, $response);
    }
}
