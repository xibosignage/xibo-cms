<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2012-2014 Daniel Garner
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
use Xibo\Entity\Permission;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\XiboException;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\TagFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

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

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param CampaignFactory $campaignFactory
     * @param LayoutFactory $layoutFactory
     * @param PermissionFactory $permissionFactory
     * @param UserGroupFactory $userGroupFactory
     * @param TagFactory $tagFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $campaignFactory, $layoutFactory, $permissionFactory, $userGroupFactory, $tagFactory, Twig $view)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config, $view);

        $this->campaignFactory = $campaignFactory;
        $this->layoutFactory = $layoutFactory;
        $this->permissionFactory = $permissionFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->tagFactory = $tagFactory;
    }

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
     *      in="formData",
     *      description="Filter by Campaign Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Filter by Name",
     *      type="string",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="Filter by Tags",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="hasLayouts",
     *      in="formData",
     *      description="Filter by has layouts",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isLayoutSpecific",
     *      in="formData",
     *      description="Filter by whether this Campaign is specific to a Layout or User added",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="retired",
     *      in="formData",
     *      description="Filter by retired",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="totalDuration",
     *      in="formData",
     *      description="Should we total the duration?",
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
     */
    public function grid(Request $request, Response $response)
    {
        $parsedParams = $this->getSanitizer($request->getQueryParams());
        $filter = [
            'campaignId' => $parsedParams->getInt('campaignId'),
            'name' => $parsedParams->getString('name'),
            'tags' => $parsedParams->getString('tags'),
            'hasLayouts' => $parsedParams->getInt('hasLayouts'),
            'isLayoutSpecific' => $parsedParams->getInt('isLayoutSpecific'),
            'retired' => $parsedParams->getInt('retired')
        ];

        $options = [
            'totalDuration' => $parsedParams->getInt('totalDuration', ['default' => 1]),
        ];

        $campaigns = $this->campaignFactory->query($this->gridRenderSort($request), $this->gridRenderFilter($filter, $request), $options, $request);

        foreach ($campaigns as $campaign) {
            /* @var \Xibo\Entity\Campaign $campaign */

            if ($this->isApi($request))
                break;

            $campaign->includeProperty('buttons');
            $campaign->buttons = [];

            // Schedule Now
            $campaign->buttons[] = array(
                'id' => 'campaign_button_schedulenow',
                'url' => $this->urlFor($request,'schedule.now.form', ['id' => $campaign->campaignId, 'from' => 'Campaign']),
                'text' => __('Schedule Now')
            );

            // Preview
            $campaign->buttons[] = array(
                'id' => 'campaign_button_preview',
                'linkType' => '_blank',
                'external' => true,
                'url' => $this->urlFor($request,'campaign.preview', ['id' => $campaign->campaignId]),
                'text' => __('Preview Campaign')
            );

            // Buttons based on permissions
            if ($this->getUser($request)->checkEditable($campaign)) {

                $campaign->buttons[] = ['divider' => true];

                // Edit the Campaign
                $campaign->buttons[] = array(
                    'id' => 'campaign_button_edit',
                    'url' => $this->urlFor($request,'campaign.edit.form', ['id' => $campaign->campaignId]),
                    'text' => __('Edit')
                );
            } else {
                $campaign->buttons[] = ['divider' => true];
            }

            if ($this->getUser($request)->checkDeleteable($campaign)) {
                // Delete Campaign
                $campaign->buttons[] = array(
                    'id' => 'campaign_button_delete',
                    'url' => $this->urlFor($request,'campaign.delete.form', ['id' => $campaign->campaignId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor($request,'campaign.delete', ['id' => $campaign->campaignId])),
                        array('name' => 'commit-method', 'value' => 'delete'),
                        array('name' => 'id', 'value' => 'campaign_button_delete'),
                        array('name' => 'text', 'value' => __('Delete')),
                        array('name' => 'rowtitle', 'value' => $campaign->campaign)
                    )
                );
            }

            if ($this->getUser($request)->checkPermissionsModifyable($campaign)) {

                $campaign->buttons[] = ['divider' => true];

                // Permissions for Campaign
                $campaign->buttons[] = array(
                    'id' => 'campaign_button_permissions',
                    'url' => $this->urlFor($request,'user.permissions.form', ['entity' => 'Campaign', 'id' => $campaign->campaignId]),
                    'text' => __('Permissions')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->campaignFactory->countLast();
        $this->getState()->setData($campaigns);

        return $this->render($request, $response);
    }

    /**
     * Campaign Add Form
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
     */
    public function add(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $campaign = $this->campaignFactory->create($sanitizedParams->getString('name'), $this->getUser($request)->userId, $sanitizedParams->getString('tags'));
        $campaign->save();

        // Permissions
        foreach ($this->permissionFactory->createForNewEntity($this->getUser($request), get_class($campaign), $campaign->getId(), $this->getConfig()->getSetting('LAYOUT_DEFAULT'), $this->userGroupFactory) as $permission) {
            /* @var Permission $permission */
            $permission->save();
        }

        // Assign layouts
        $this->assignLayout($request, $response, $campaign->campaignId);

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
     * @param int $campaignId
     */
    public function editForm(Request $request, Response $response, $id)
    {
        $campaign = $this->campaignFactory->getById($id);

        $tags = '';

        $arrayOfTags = array_filter(explode(',', $campaign->tags));
        $arrayOfTagValues = array_filter(explode(',', $campaign->tagValues));

        for ($i=0; $i<count($arrayOfTags); $i++) {
            if (isset($arrayOfTags[$i]) && (isset($arrayOfTagValues[$i]) && $arrayOfTagValues[$i] != 'NULL' )) {
                $tags .= $arrayOfTags[$i] . '|' . $arrayOfTagValues[$i];
                $tags .= ',';
            } else {
                $tags .= $arrayOfTags[$i] . ',';
            }
        }

        if (!$this->getUser($request)->checkEditable($campaign)) {
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
            'tags' => $tags
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit a Campaign
     * @param int $campaignId
     *
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
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Campaign")
     *  )
     * )
     * @throws XiboException
     */
    public function edit(Request $request, Response $response, $id)
    {
        $campaign = $this->campaignFactory->getById($id);
        $parsedRequestParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser($request)->checkEditable($campaign)) {
            throw new AccessDeniedException();
        }

        $campaign->campaign = $parsedRequestParams->getString('name');
        $campaign->replaceTags($this->tagFactory->tagsFromString($parsedRequestParams->getString('tags')));
        $campaign->save([
            'saveTags' => true
        ]);

        // Assign layouts
        $this->assignLayout($request, $response, $campaign->campaignId);

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
     * @param int $campaignId
     */
    function deleteForm(Request $request, Response $response, $id)
    {
        $campaign = $this->campaignFactory->getById($id);

        if (!$this->getUser($request)->checkDeleteable($campaign))
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
     * @param int $campaignId
     *
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
     *
     * @throws XiboException
     */
    public function delete(Request $request, Response $response, $id)
    {
        $campaign = $this->campaignFactory->getById($id);

        if (!$this->getUser($request)->checkDeleteable($campaign))
            throw new AccessDeniedException();

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
     * @param int $campaignId
     * @throws XiboException
     */
    public function layoutsForm(Request $request, Response $response, $id)
    {
        $campaign = $this->campaignFactory->getById($id);

        if (!$this->getUser($request)->checkEditable($campaign))
            throw new AccessDeniedException();

        $layouts = [];
        foreach ($this->layoutFactory->getByCampaignId($id, false) as $layout) {
            if (!$this->getUser($request)->checkViewable($layout)) {
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
     * Model to use for supplying key/value pairs to arrays
     * @SWG\Definition(
     *  definition="LayoutAssignmentArray",
     *  @SWG\Property(
     *      property="layoutId",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="displayOrder",
     *      type="integer"
     *  )
     * )
     */

    /**
     * Assigns a layout to a Campaign
     * @param Request $request
     * @param Response $response
     * @param int $campaignId
     *
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws InvalidArgumentException
     * @throws XiboException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     * @throws \Xibo\Exception\NotFoundException
     * @SWG\Post(
     *  path="/campaign/layout/assign/{campaignId}",
     *  operationId="campaignAssignLayout",
     *  tags={"campaign"},
     *  summary="Assign Layouts",
     *  description="Assign Layouts to a Campaign",
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
     *      description="Array of Layout ID/Display Orders to Assign",
     *      type="array",
     *      required=true,
     *      @SWG\Items(
     *          ref="#/definitions/LayoutAssignmentArray"
     *      )
     *   ),
     *  @SWG\Parameter(
     *      name="unassignLayoutId",
     *      in="formData",
     *      description="Array of Layout ID/Display Orders to unassign",
     *      type="array",
     *      required=false,
     *      @SWG\Items(
     *          ref="#/definitions/LayoutAssignmentArray"
     *      )
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     */
    public function assignLayout( Request $request, Response $response, $campaignId)
    {
        $this->getLog()->debug('assignLayout with campaignId ' . $campaignId);

        $campaign = $this->campaignFactory->getById($campaignId);

        if (!$this->getUser($request)->checkEditable($campaign))
            throw new AccessDeniedException();

        // Make sure this is a non-layout specific campaign
        if ($campaign->isLayoutSpecific == 1)
            throw new InvalidArgumentException(__('You cannot change the assignment of a Layout Specific Campaign'),'campaignId');

        $campaign->setChildObjectDependencies($this->layoutFactory);

        // Track whether we've made any changes.
        $changesMade = false;

        // Check our permissions to see each one
        $layouts = $request->getParam('layoutId', null);
        $layouts = is_array($layouts) ? $layouts : [];

        $this->getLog()->debug(sprintf('There are %d Layouts to assign', count($layouts)));

        foreach ($layouts as $object) {
            $sanitizedObject = $this->getSanitizer($object);
            $layout = $this->layoutFactory->getById($sanitizedObject->getInt('layoutId'));

            // Check to see if this layout is already assigned
            // if it is, then we have permission to move it around
            if (!$this->getUser($request)->checkViewable($layout) && !$campaign->isLayoutAssigned($layout))
                throw new AccessDeniedException(__('You do not have permission to assign the provided Layout'));

            // Make sure we're not a draft
            if ($layout->isChild())
                throw new InvalidArgumentException('Cannot assign a Draft Layout to a Campaign', 'layoutId');

            // Make sure this layout is not a template - for API, in web ui templates are not available for assignment
            $tags = $layout->tags;
            $tagsArray = explode(',', $tags);

            foreach ($tagsArray as $tag) {
                if ($tag === 'template') {
                    throw new InvalidArgumentException('Cannot assign a Template to a Campaign', 'layoutId');
                }
            }

            // Set the Display Order
            $layout->displayOrder = $sanitizedObject->getInt('displayOrder');

            // Assign it
            $campaign->assignLayout($layout);

            $changesMade = true;
        }

        // Run through the layouts to unassign
        $layouts = $request->getParam('unassignLayoutId', null);
        $layouts = is_array($layouts) ? $layouts : [];
        
        $this->getLog()->debug('There are %d Layouts to unassign', count($layouts));
        
        foreach ($layouts as $object) {
            $sanitizedObject = $this->getSanitizer($object);
            $layout = $this->layoutFactory->getById($sanitizedObject->getInt('layoutId'));

            if (!$this->getUser()->checkViewable($layout) && !$campaign->isLayoutAssigned($layout))
                throw new AccessDeniedException(__('You do not have permission to assign the provided Layout'));

            // Set the Display Order
            $layout->displayOrder = $sanitizedObject->getInt('displayOrder');

            // Unassign it
            $campaign->unassignLayout($layout);

            $changesMade = true;
        }

        // Save the campaign
        if ($changesMade) {
            $campaign->save(['validate' => false, 'saveTags' => false]);
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Assigned Layouts to %s'), $campaign->campaign)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Unassign a layout to a Campaign
     * @param int $campaignId
     *
     * SWG\Post(
     *  path="/campaign/layout/unassign/{campaignId}",
     *  operationId="campaignUnassignLayout",
     *  tags={"campaign"},
     *  summary="Unassign Layouts",
     *  description="Unassign Layouts from a Campaign",
     *  SWG\Parameter(
     *      name="campaignId",
     *      in="path",
     *      description="The Campaign ID",
     *      type="integer",
     *      required=true
     *   ),
     *  SWG\Parameter(
     *      name="layoutId",
     *      in="formData",
     *      description="Array of Layout IDs to Unassign",
     *      type="array",
     *      required=true,
     *      SWG\Items(
     *          ref="#/definitions/LayoutAssignmentArray"
     *      )
     *   ),
     *  SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws XiboException
     */
    public function unassignLayout(Request $request, Response $response, $id)
    {
        $campaign = $this->campaignFactory->getById($id);

        if (!$this->getUser($request)->checkEditable($campaign)) {
            throw new AccessDeniedException();
        }

        // Make sure this is a non-layout specific campaign
        if ($campaign->isLayoutSpecific == 1) {
            throw new InvalidArgumentException(__('You cannot change the assignment of a Layout Specific Campaign'),
                'campaignId');
        }

        $campaign->setChildObjectDependencies($this->layoutFactory);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $layouts = $sanitizedParams->getIntArray('layoutId');

        if (count($layouts) <= 0)
            throw new \InvalidArgumentException(__('Layouts not provided'));

        // Check our permissions to see each one
        $layouts = $request->getParam('layoutId', null);
        $layouts = is_array($layouts) ? $layouts : [];
        foreach ($layouts as $object) {
            $layout = $this->layoutFactory->getById($sanitizedParams->getInt('layoutId', $object));

            if (!$this->getUser()->checkViewable($layout) && !$campaign->isLayoutAssigned($layout))
                throw new AccessDeniedException(__('You do not have permission to assign the provided Layout'));

            // Set the Display Order
            $layout->displayOrder = $sanitizedParams->getInt('displayOrder', $object);

            // Unassign it
            $campaign->unassignLayout($layout);
        }

        $campaign->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Unassigned Layouts from %s'), $campaign->campaign)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Returns a Campaign's preview
     * @param int $campaignId
     */
    public function preview(Request $request, Response $response, $id)
    {
        $campaign = $this->campaignFactory->getById($id);
        $layouts = $this->layoutFactory->getByCampaignId($id);
        $duration = 0 ;
        $extendedLayouts = [];

        foreach($layouts as $layout)
        {
            $duration += $layout->duration;
            $extendedLayouts[] = ['layout' => $layout,
                                  'duration' => $layout->duration,
                                  'previewOptions' => [
                                      'getXlfUrl' => $this->urlFor($request,'layout.getXlf', ['id' => $layout->layoutId]),
                                      'getResourceUrl' => $this->urlFor($request,'module.getResource'),
                                      'libraryDownloadUrl' => $this->urlFor($request,'library.download'),
                                      'layoutBackgroundDownloadUrl' => $this->urlFor($request,'layout.download.background'),
                                      'loaderUrl' => $this->getConfig()->uri('img/loader.gif')]
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
}
