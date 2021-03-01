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
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\FolderFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\TagFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Campaign
 * @package Xibo\Controller
 */
class Campaign extends Base
{
    /**
     * @var CampaignFactory
     */
    private $campaignFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var TagFactory
     */
    private $tagFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var UserGroupFactory
     */
    private $userGroupFactory;

    /** @var FolderFactory */
    private $folderFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param ConfigServiceInterface $config
     * @param CampaignFactory $campaignFactory
     * @param LayoutFactory $layoutFactory
     * @param PermissionFactory $permissionFactory
     * @param UserGroupFactory $userGroupFactory
     * @param TagFactory $tagFactory
     * @param Twig $view
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $config, $campaignFactory, $layoutFactory, $permissionFactory, $userGroupFactory, $tagFactory, Twig $view, $folderFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $config, $view);

        $this->campaignFactory = $campaignFactory;
        $this->layoutFactory = $layoutFactory;
        $this->permissionFactory = $permissionFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->tagFactory = $tagFactory;
        $this->folderFactory = $folderFactory;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     */
    public function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'campaign-page';

        return $this->render($request, $response);
    }

    /**
     * Returns a Grid of Campaigns
     *
     * @SWG\Get(
     *  path="/campaign",
     *  operationId="campaignSearch",
     *  tags={"campaign"},
     *  summary="Search Campaigns",
     *  description="Search all Campaigns this user has access to",
     *  @SWG\Parameter(
     *      name="campaignId",
     *      in="query",
     *      description="Filter by Campaign Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="query",
     *      description="Filter by Name",
     *      type="string",
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
     *      name="hasLayouts",
     *      in="query",
     *      description="Filter by has layouts",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isLayoutSpecific",
     *      in="query",
     *      description="Filter by whether this Campaign is specific to a Layout or User added",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="retired",
     *      in="query",
     *      description="Filter by retired",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="totalDuration",
     *      in="query",
     *      description="Should we total the duration?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="embed",
     *      in="query",
     *      description="Embed related data such as layouts, permissions, tags and events",
     *      type="string",
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
     *          @SWG\Items(ref="#/definitions/Campaign")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws ControllerNotImplemented
     * @throws NotFoundException
     */
    public function grid(Request $request, Response $response)
    {
        $parsedParams = $this->getSanitizer($request->getQueryParams());
        $filter = [
            'campaignId' => $parsedParams->getInt('campaignId'),
            'name' => $parsedParams->getString('name'),
            'useRegexForName' => $parsedParams->getCheckbox('useRegexForName'),
            'tags' => $parsedParams->getString('tags'),
            'hasLayouts' => $parsedParams->getInt('hasLayouts'),
            'isLayoutSpecific' => $parsedParams->getInt('isLayoutSpecific'),
            'retired' => $parsedParams->getInt('retired'),
            'folderId' => $parsedParams->getInt('folderId')
        ];

        $options = [
            'totalDuration' => $parsedParams->getInt('totalDuration', ['default' => 1]),
        ];

        $embed = ($parsedParams->getString('embed') !== null) ? explode(',', $parsedParams->getString('embed')) : [];

        $campaigns = $this->campaignFactory->query($this->gridRenderSort($parsedParams), $this->gridRenderFilter($filter, $parsedParams), $options);

        foreach ($campaigns as $campaign) {
            /* @var \Xibo\Entity\Campaign $campaign */

            if (count($embed) > 0) {
                $campaign->setChildObjectDependencies($this->layoutFactory);
                $campaign->load([
                    'loadPermissions' => in_array('permissions', $embed),
                    'loadLayouts' => in_array('layouts', $embed),
                    'loadTags' => in_array('tags', $embed),
                    'loadEvents' => in_array('events', $embed)
                ]);
            } else {
                $campaign->excludeProperty('layouts');
            }

            if ($this->isApi($request)) {
                break;
            }

            $campaign->includeProperty('buttons');
            $campaign->buttons = [];

            // Schedule Now
            if ($this->getUser()->featureEnabled('schedule.now')) {
                $campaign->buttons[] = array(
                    'id' => 'campaign_button_schedulenow',
                    'url' => $this->urlFor($request,'schedule.now.form', ['id' => $campaign->campaignId, 'from' => 'Campaign']),
                    'text' => __('Schedule Now')
                );
            }

            // Preview
            if ($this->getUser()->featureEnabled(['layout.view', 'campaign.view'], true)) {
                $campaign->buttons[] = array(
                    'id' => 'campaign_button_preview',
                    'linkType' => '_blank',
                    'external' => true,
                    'url' => $this->urlFor($request, 'campaign.preview', ['id' => $campaign->campaignId]),
                    'text' => __('Preview Campaign')
                );
            }

            // Buttons based on permissions
            if ($this->getUser()->featureEnabled('campaign.modify')
                && $this->getUser()->checkEditable($campaign)
            ) {
                $campaign->buttons[] = ['divider' => true];

                // Edit the Campaign
                $campaign->buttons[] = array(
                    'id' => 'campaign_button_edit',
                    'url' => $this->urlFor($request,'campaign.edit.form', ['id' => $campaign->campaignId]),
                    'text' => __('Edit')
                );

                if ($this->getUser()->featureEnabled('folder.view')) {
                    // Select Folder
                    $campaign->buttons[] = [
                        'id' => 'campaign_button_selectfolder',
                        'url' => $this->urlFor($request,'campaign.selectfolder.form', ['id' => $campaign->campaignId]),
                        'text' => __('Select Folder'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            ['name' => 'commit-url', 'value' => $this->urlFor($request,'campaign.selectfolder', ['id' => $campaign->campaignId])],
                            ['name' => 'commit-method', 'value' => 'put'],
                            ['name' => 'id', 'value' => 'campaign_button_selectfolder'],
                            ['name' => 'text', 'value' => __('Move to Folder')],
                            ['name' => 'rowtitle', 'value' => $campaign->campaign],
                            ['name' => 'form-callback', 'value' => 'moveFolderMultiSelectFormOpen']
                        ]
                    ];
                }

                // Copy the campaign
                $campaign->buttons[] = [
                    'id' => 'campaign_button_copy',
                    'url' => $this->urlFor($request,'campaign.copy.form', ['id' => $campaign->campaignId]),
                    'text' => __('Copy')
                ];
            } else {
                $campaign->buttons[] = ['divider' => true];
            }

            if ($this->getUser()->featureEnabled('campaign.modify') &&
                $this->getUser()->checkDeleteable($campaign)
            ) {
                // Delete Campaign
                $campaign->buttons[] = [
                    'id' => 'campaign_button_delete',
                    'url' => $this->urlFor($request,'campaign.delete.form', ['id' => $campaign->campaignId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'commit-url', 'value' => $this->urlFor($request,'campaign.delete', ['id' => $campaign->campaignId])],
                        ['name' => 'commit-method', 'value' => 'delete'],
                        ['name' => 'id', 'value' => 'campaign_button_delete'],
                        ['name' => 'text', 'value' => __('Delete')],
                        ['name' => 'sort-group', 'value' => 1],
                        ['name' => 'rowtitle', 'value' => $campaign->campaign]
                    ]
                ];
            }

            if ($this->getUser()->featureEnabled('campaign.modify') &&
                $this->getUser()->checkPermissionsModifyable($campaign)
            ) {
                $campaign->buttons[] = ['divider' => true];

                // Permissions for Campaign
                $campaign->buttons[] = [
                    'id' => 'campaign_button_permissions',
                    'url' => $this->urlFor($request,'user.permissions.form', ['entity' => 'Campaign', 'id' => $campaign->campaignId]),
                    'text' => __('Share'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'commit-url', 'value' => $this->urlFor($request,'user.permissions.multi', ['entity' => 'Campaign', 'id' => $campaign->campaignId])],
                        ['name' => 'commit-method', 'value' => 'post'],
                        ['name' => 'id', 'value' => 'campaign_button_permissions'],
                        ['name' => 'text', 'value' => __('Share')],
                        ['name' => 'rowtitle', 'value' => $campaign->campaign],
                        ['name' => 'sort-group', 'value' => 2],
                        ['name' => 'custom-handler', 'value' => 'XiboMultiSelectPermissionsFormOpen'],
                        ['name' => 'custom-handler-url', 'value' => $this->urlFor($request,'user.permissions.multi.form', ['entity' => 'Campaign'])],
                        ['name' => 'content-id-name', 'value' => 'campaignId']
                    ]
                ];
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->campaignFactory->countLast();
        $this->getState()->setData($campaigns);

        return $this->render($request, $response);
    }

    /**
     * Campaign Add Form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     */
    public function addForm(Request $request, Response $response)
    {
        // Load layouts
        $layouts = [];

        $this->getState()->template = 'campaign-form-add';
        $this->getState()->setData([
            'layouts' => $layouts,
            'help' => $this->getHelp()->link('Campaign', 'Add')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Add a Campaign
     *
     * @SWG\Post(
     *  path="/campaign",
     *  operationId="campaignAdd",
     *  tags={"campaign"},
     *  summary="Add Campaign",
     *  description="Add a Campaign",
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Name for this Campaign",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="formData",
     *      description="Folder ID to which this object should be assigned to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="layoutIds",
     *      in="formData",
     *      description="An array of layoutIds to assign to this Campaign, in order.",
     *      type="array",
     *      @SWG\Items(type="integer"),
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Campaign"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function add(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $campaign = $this->campaignFactory->create($sanitizedParams->getString('name'), $this->getUser()->userId, $sanitizedParams->getString('tags'), $sanitizedParams->getInt('folderId', ['default' => 1]));

        if ($this->getUser()->featureEnabled('folder.view')) {
            $folder = $this->folderFactory->getById($campaign->folderId);
            $campaign->permissionsFolderId = ($folder->getPermissionFolderId() == null) ? $folder->id : $folder->getPermissionFolderId();
        } else {
            $campaign->permissionsFolderId = 1;
        }

        // Assign layouts?
        foreach ($sanitizedParams->getIntArray('layoutIds', ['default' => []]) as $layoutId) {
            // Check permissions.
            $layout = $this->layoutFactory->getById($layoutId);

            if (!$this->getUser()->checkViewable($layout)) {
                throw new AccessDeniedException(__('You do not have permission to assign this Layout'));
            }

            // Assign.
            $campaign->assignLayout($layout);
        }

        // All done, save.
        $campaign->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $campaign->campaign),
            'id' => $campaign->campaignId,
            'data' => $campaign
        ]);

        return $this->render($request, $response);
    }

    /**
     * Campaign Edit Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function editForm(Request $request, Response $response, $id)
    {
        $campaign = $this->campaignFactory->getById($id);

        if (!$this->getUser()->checkEditable($campaign)) {
            throw new AccessDeniedException();
        }

        // Load layouts
        $layouts = [];
        foreach ($this->layoutFactory->getByCampaignId($id, false) as $layout) {
            if (!$this->getUser()->checkViewable($layout)) {
                // Hide all layout details from the user
                $emptyLayout = $this->layoutFactory->createEmpty();
                $emptyLayout->layoutId = $layout->layoutId;
                $emptyLayout->layout = __('Layout');
                $emptyLayout->locked = true;

                $layouts[] = $emptyLayout;
            } else {
                $layouts[] = $layout;
            }
        }

        $this->getState()->template = 'campaign-form-edit';
        $this->getState()->setData([
            'campaign' => $campaign,
            'layouts' => $layouts,
            'help' => $this->getHelp()->link('Campaign', 'Edit'),
            'tags' => $this->tagFactory->getTagsWithValues($campaign)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit a Campaign
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @SWG\Put(
     *  path="/campaign/{campaignId}",
     *  operationId="campaignEdit",
     *  tags={"campaign"},
     *  summary="Edit Campaign",
     *  description="Edit an existing Campaign",
     *  @SWG\Parameter(
     *      name="campaignId",
     *      in="path",
     *      description="The Campaign ID to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Name for this Campaign",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="formData",
     *      description="Folder ID to which this object should be assigned to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="manageLayouts",
     *      in="formData",
     *      description="Flag indicating whether to manage layouts or not. Default to no.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="layoutIds",
     *      in="formData",
     *      description="An array of layoutIds to assign to this Campaign, in order.",
     *      type="array",
     *      @SWG\Items(type="integer"),
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Campaign")
     *  )
     * )
     */
    public function edit(Request $request, Response $response, $id)
    {
        $campaign = $this->campaignFactory->getById($id);
        $parsedRequestParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($campaign)) {
            throw new AccessDeniedException();
        }

        $campaign->campaign = $parsedRequestParams->getString('name');
        $campaign->folderId = $parsedRequestParams->getInt('folderId', ['default' => $campaign->folderId]);

        if ($campaign->hasPropertyChanged('folderId')) {
            $folder = $this->folderFactory->getById($campaign->folderId);
            $campaign->permissionsFolderId = ($folder->getPermissionFolderId() == null) ? $folder->id : $folder->getPermissionFolderId();
        }

        // Assign layouts?
        if ($parsedRequestParams->getCheckbox('manageLayouts') === 1) {
            // Fully decorate our Campaign
            $campaign->setChildObjectDependencies($this->layoutFactory);

            // Remove all we've currently got assigned, keeping track of them for sharing check
            $originalLayoutAssignments = array_map(function($element) {
                return $element->layoutId;
            }, $campaign->getLayouts());

            $campaign->unassignAllLayouts();

            foreach ($parsedRequestParams->getIntArray('layoutIds', ['default' => []]) as $layoutId) {
                // Check permissions.
                $layout = $this->layoutFactory->getById($layoutId);

                if (!$this->getUser()->checkViewable($layout) && !in_array($layoutId, $originalLayoutAssignments)) {
                    throw new AccessDeniedException(__('You are trying to assign a Layout that is not shared with you.'));
                }

                // Assign.
                $campaign->assignLayout($layout);
            }
        }

        if ($this->getUser()->featureEnabled('tag.tagging')) {
            $campaign->replaceTags($this->tagFactory->tagsFromString($parsedRequestParams->getString('tags')));
            $campaign->save([
                'saveTags' => true
            ]);
        } else {
            $campaign->save([
                'saveTags' => false
            ]);
        }

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $campaign->campaign),
            'id' => $campaign->campaignId,
            'data' => $campaign
        ]);

        return $this->render($request, $response);
    }

    /**
     * Shows the Delete Group Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    function deleteForm(Request $request, Response $response, $id)
    {
        $campaign = $this->campaignFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($campaign))
            throw new AccessDeniedException();

        $this->getState()->template = 'campaign-form-delete';
        $this->getState()->setData([
            'campaign' => $campaign,
            'help' => $this->getHelp()->link('Campaign', 'Delete')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Campaign
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @SWG\Delete(
     *  path="/campaign/{campaignId}",
     *  operationId="campaignDelete",
     *  tags={"campaign"},
     *  summary="Delete Campaign",
     *  description="Delete an existing Campaign",
     *  @SWG\Parameter(
     *      name="campaignId",
     *      in="path",
     *      description="The Campaign ID to Delete",
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
        $campaign = $this->campaignFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($campaign)) {
            throw new AccessDeniedException();
        }

        $campaign->setChildObjectDependencies($this->layoutFactory);

        $campaign->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $campaign->campaign)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Layouts form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function layoutsForm(Request $request, Response $response, $id)
    {
        $campaign = $this->campaignFactory->getById($id);

        if (!$this->getUser()->checkEditable($campaign)) {
            throw new AccessDeniedException();
        }

        $layouts = [];
        foreach ($this->layoutFactory->getByCampaignId($id, false) as $layout) {
            if (!$this->getUser()->checkViewable($layout)) {
                // Hide all layout details from the user
                $emptyLayout = $this->layoutFactory->createEmpty();
                $emptyLayout->layoutId = $layout->layoutId;
                $emptyLayout->layout = __('Layout');
                $emptyLayout->locked = true;

                $layouts[] = $emptyLayout;
            } else {
                $layouts[] = $layout;
            }
        }

        $this->getState()->template = 'campaign-form-layouts';
        $this->getState()->setData([
            'campaign' => $campaign,
            'layouts' => $layouts,
            'help' => $this->getHelp()->link('Campaign', 'Layouts')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Assigns a layout to a Campaign
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @SWG\Post(
     *  path="/campaign/layout/assign/{campaignId}",
     *  operationId="campaignAssignLayout",
     *  tags={"campaign"},
     *  summary="Assign Layout",
     *  description="Assign a Layout to a Campaign. Please note that as of v3.0.0 this API no longer accepts multiple layoutIds.",
     *  @SWG\Parameter(
     *      name="campaignId",
     *      in="path",
     *      description="The Campaign ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="formData",
     *      description="Layout ID to Assign: Please note that as of v3.0.0 this API no longer accepts multiple layoutIds.",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function assignLayout( Request $request, Response $response, $id)
    {
        $this->getLog()->debug('assignLayout with campaignId ' . $id);

        $campaign = $this->campaignFactory->getById($id);

        if (!$this->getUser()->checkEditable($campaign)) {
            throw new AccessDeniedException();
        }

        // Make sure this is a non-layout specific campaign
        if ($campaign->isLayoutSpecific == 1) {
            throw new InvalidArgumentException(__('You cannot change the assignment of a Layout Specific Campaign'), 'campaignId');
        }

        $campaign->setChildObjectDependencies($this->layoutFactory);

        // Get the layout we want to add
        $params = $this->getSanitizer($request->getParams());
        $layout = $this->layoutFactory->getById(
            $params->getInt('layoutId', [
                'throw' => function() {
                    throw new InvalidArgumentException(__('Please select a Layout to assign.'), 'layoutId');
                }
            ])
        );

        if (!$this->getUser()->checkViewable($layout)) {
            throw new AccessDeniedException(__('You do not have permission to assign the provided Layout'));
        }

        // Assign to the campaign
        $campaign->assignLayout($layout);
        $campaign->save(['validate' => false, 'saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Assigned Layouts to %s'), $campaign->campaign)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Returns a Campaign's preview
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function preview(Request $request, Response $response, $id)
    {
        $campaign = $this->campaignFactory->getById($id);
        $layouts = $this->layoutFactory->getByCampaignId($id);
        $duration = 0 ;
        $extendedLayouts = [];

        foreach ($layouts as $layout)
        {
            $duration += $layout->duration;
            $extendedLayouts[] = [
                'layout' => $layout,
                'duration' => $layout->duration,
                'previewOptions' => [
                    'getXlfUrl' => $this->urlFor($request,'layout.getXlf', ['id' => $layout->layoutId]),
                    'getResourceUrl' => $this->urlFor($request,'module.getResource', ['regionId' => ':regionId', 'id' => ':id']),
                    'libraryDownloadUrl' => $this->urlFor($request,'library.download', ['id' => ':id']),
                    'layoutBackgroundDownloadUrl' => $this->urlFor($request,'layout.download.background', ['id' => ':id']),
                    'loaderUrl' => $this->getConfig()->uri('img/loader.gif')
                ]
            ];
        }
        $this->getState()->template = 'campaign-preview';
        $this->getState()->setData([
            'campaign' => $campaign,
            'help' => $this->getHelp()->link('Campaign', 'Preview'),
            'layouts' => $layouts,
            'duration' => $duration,
            'extendedLayouts' => $extendedLayouts
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function copyForm(Request $request, Response $response, $id)
    {
        // get the Campaign
        $campaign = $this->campaignFactory->getById($id);

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $campaign->ownerId) {
            throw new AccessDeniedException(__('You do not have permission to copy this Campaign'));
        }

        $this->getState()->template = 'campaign-form-copy';
        $this->getState()->setData([
            'campaign' => $campaign
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function copy(Request $request, Response $response, $id)
    {
        // get the Campaign
        $campaign = $this->campaignFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // get the Layouts assigned to the original Campaign
        $layouts = $this->layoutFactory->getByCampaignId($campaign->campaignId, false);

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $campaign->ownerId) {
            throw new AccessDeniedException(__('You do not have permission to copy this Campaign'));
        }

        $newCampaign = clone $campaign;
        $newCampaign->campaign = $sanitizedParams->getString('name');

        // assign the same layouts to the new Campaign
        foreach ($layouts as $layout) {
            $newCampaign->assignLayout($layout);
        }

        $newCampaign->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $newCampaign->campaign),
            'id' => $newCampaign->campaignId,
            'data' => $newCampaign
        ]);

        return $this->render($request, $response);
    }

    /**
     * Select Folder Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function selectFolderForm(Request $request, Response $response, $id)
    {
        // Get the Campaign
        $campaign = $this->campaignFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($campaign)) {
            throw new AccessDeniedException();
        }

        $data = [
            'campaign' => $campaign
        ];

        $this->getState()->template = 'campaign-form-selectfolder';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * Select Folder
     *
     * @SWG\Put(
     *  path="/campaign/{id}/selectfolder",
     *  operationId="campaignSelectFolder",
     *  tags={"campaign"},
     *  summary="Campaign Select folder",
     *  description="Select Folder for Campaign, can also be used with Layout specific Campaign ID",
     *  @SWG\Parameter(
     *      name="campaignId",
     *      in="path",
     *      description="The Campaign ID or Layout specific Campaign ID",
     *      type="integer",
     *      required=true
     *   ),
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
     *      @SWG\Schema(ref="#/definitions/Campaign")
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
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     *
     */
    public function selectFolder(Request $request, Response $response, $id)
    {
        // Get the Campaign
        $campaign = $this->campaignFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($campaign)) {
            throw new AccessDeniedException();
        }

        $folderId = $this->getSanitizer($request->getParams())->getInt('folderId');

        $campaign->folderId = $folderId;
        $folder = $this->folderFactory->getById($campaign->folderId);
        $campaign->permissionsFolderId = ($folder->getPermissionFolderId() == null) ? $folder->id : $folder->getPermissionFolderId();

        if ($campaign->isLayoutSpecific === 1) {
            $layouts = $this->layoutFactory->getByCampaignId($campaign->campaignId);

            foreach ($layouts as $layout) {
                $layout->load();
                foreach ($layout->regions as $region) {
                    /* @var Region $region */
                    $playlist = $region->getPlaylist();
                    $playlist->folderId = $campaign->folderId;
                    $playlist->permissionsFolderId = $campaign->permissionsFolderId;
                    $playlist->save();
                }
            }
        }

        // Save
        $campaign->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Layout %s moved to Folder %s'), $campaign->campaign, $folder->text)
        ]);

        return $this->render($request, $response);
    }
}
