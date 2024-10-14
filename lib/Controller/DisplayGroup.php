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
namespace Xibo\Controller;

use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\Display;
use Xibo\Event\DisplayGroupLoadEvent;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\FolderFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\TagFactory;
use Xibo\Service\PlayerActionServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\XMR\ChangeLayoutAction;
use Xibo\XMR\CollectNowAction;
use Xibo\XMR\CommandAction;
use Xibo\XMR\OverlayLayoutAction;
use Xibo\XMR\PlayerActionException;
use Xibo\XMR\RevertToSchedule;
use Xibo\XMR\ScheduleCriteriaUpdateAction;
use Xibo\XMR\TriggerWebhookAction;

/**
 * Class DisplayGroup
 * @package Xibo\Controller
 */
class DisplayGroup extends Base
{
    /**
     * @var PlayerActionServiceInterface
     */
    private $playerAction;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * @var ModuleFactory
     */
    private $moduleFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var CommandFactory
     */
    private $commandFactory;

    /**
     * @var TagFactory
     */
    private $tagFactory;

    /**
     * @var CampaignFactory
     */
    private $campaignFactory;

    /** @var FolderFactory */
    private $folderFactory;

    /**
     * Set common dependencies.
     * @param PlayerActionServiceInterface $playerAction
     * @param DisplayFactory $displayFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @param LayoutFactory $layoutFactory
     * @param ModuleFactory $moduleFactory
     * @param MediaFactory $mediaFactory
     * @param CommandFactory $commandFactory
     * @param TagFactory $tagFactory
     * @param CampaignFactory $campaignFactory
     * @param FolderFactory $folderFactory
     */
    public function __construct(
        $playerAction,
        $displayFactory,
        $displayGroupFactory,
        $layoutFactory,
        $moduleFactory,
        $mediaFactory,
        $commandFactory,
        $tagFactory,
        $campaignFactory,
        $folderFactory
    ) {
        $this->playerAction = $playerAction;
        $this->displayFactory = $displayFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->layoutFactory = $layoutFactory;
        $this->moduleFactory = $moduleFactory;
        $this->mediaFactory = $mediaFactory;
        $this->commandFactory = $commandFactory;
        $this->tagFactory = $tagFactory;
        $this->campaignFactory = $campaignFactory;
        $this->folderFactory = $folderFactory;
    }

    /**
     * Display Group Page Render
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'displaygroup-page';

        return $this->render($request, $response);
    }

    /**
     * @SWG\Get(
     *  path="/displaygroup",
     *  summary="Get Display Groups",
     *  tags={"displayGroup"},
     *  operationId="displayGroupSearch",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      in="query",
     *      description="Filter by DisplayGroup Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="displayGroup",
     *      in="query",
     *      description="Filter by DisplayGroup Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="query",
     *      description="Filter by DisplayGroups containing a specific display",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="nestedDisplayId",
     *      in="query",
     *      description="Filter by DisplayGroups containing a specific display in there nesting",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dynamicCriteria",
     *      in="query",
     *      description="Filter by DisplayGroups containing a specific dynamic criteria",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="query",
     *      description="Filter by tags",
     *      type="string",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="exactTags",
     *      in="query",
     *      description="A flag indicating whether to treat the tags filter as an exact match",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="logicalOperator",
     *      in="query",
     *      description="When filtering by multiple Tags, which logical operator should be used? AND|OR",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isDisplaySpecific",
     *      in="query",
     *      description="Filter by whether the Display Group belongs to a Display or is user created",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="forSchedule",
     *      in="query",
     *      description="Should the list be refined for only those groups the User can Schedule against?",
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
     *      description="a successful response",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/DisplayGroup")
     *      ),
     *      @SWG\Header(
     *          header="X-Total-Count",
     *          description="The total number of records",
     *          type="integer"
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function grid(Request $request, Response $response)
    {
        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());

        $filter = [
            'displayGroupId' => $parsedQueryParams->getInt('displayGroupId'),
            'displayGroupIds' => $parsedQueryParams->getIntArray('displayGroupIds'),
            'displayGroup' => $parsedQueryParams->getString('displayGroup'),
            'useRegexForName' => $parsedQueryParams->getCheckbox('useRegexForName'),
            'displayId' => $parsedQueryParams->getInt('displayId'),
            'nestedDisplayId' => $parsedQueryParams->getInt('nestedDisplayId'),
            'dynamicCriteria' => $parsedQueryParams->getString('dynamicCriteria'),
            'tags' => $parsedQueryParams->getString('tags'),
            'exactTags' => $parsedQueryParams->getCheckbox('exactTags'),
            'isDisplaySpecific' => $parsedQueryParams->getInt('isDisplaySpecific'),
            'displayGroupIdMembers' => $parsedQueryParams->getInt('displayGroupIdMembers'),
            'userId' => $parsedQueryParams->getInt('userId'),
            'isDynamic' => $parsedQueryParams->getInt('isDynamic'),
            'folderId' => $parsedQueryParams->getInt('folderId'),
            'logicalOperator' => $parsedQueryParams->getString('logicalOperator'),
            'logicalOperatorName' => $parsedQueryParams->getString('logicalOperatorName'),
            'displayIdMember' => $parsedQueryParams->getInt('displayIdMember'),
        ];

        $scheduleWithView = ($this->getConfig()->getSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 1);

        $displayGroups = $this->displayGroupFactory->query(
            $this->gridRenderSort($parsedQueryParams),
            $this->gridRenderFilter($filter, $parsedQueryParams)
        );

        foreach ($displayGroups as $group) {
            /* @var \Xibo\Entity\DisplayGroup $group */

            // Check to see if we're getting this data for a Schedule attempt, or for a general list
            if ($parsedQueryParams->getCheckbox('forSchedule') == 1) {
                // Can't schedule with view, but no edit permissions
                if (!$scheduleWithView && !$this->getUser()->checkEditable($group)) {
                    continue;
                }
            }

            if ($this->isApi($request)) {
                continue;
            }

            $group->includeProperty('buttons');

            if ($this->getUser()->featureEnabled('displaygroup.modify')
                && $this->getUser()->checkEditable($group)
            ) {
                // Show the edit button, members button
                if ($group->isDynamic == 0) {
                    // Group Members
                    $group->buttons[] = [
                        'id' => 'displaygroup_button_group_members',
                        'url' => $this->urlFor(
                            $request,
                            'displayGroup.members.form',
                            ['id' => $group->displayGroupId]
                        ),
                        'text' => __('Members')
                    ];

                    $group->buttons[] = ['divider' => true];
                }

                // Edit
                $group->buttons[] = [
                    'id' => 'displaygroup_button_edit',
                    'url' => $this->urlFor($request, 'displayGroup.edit.form', ['id' => $group->displayGroupId]),
                    'text' => __('Edit')
                ];

                $group->buttons[] = [
                    'id' => 'displaygroup_button_copy',
                    'url' => $this->urlFor($request, 'displayGroup.copy.form', ['id' => $group->displayGroupId]),
                    'text' => __('Copy')
                ];

                if ($this->getUser()->featureEnabled('folder.view')) {
                    // Select Folder
                    $group->buttons[] = [
                        'id' => 'displaygroup_button_selectfolder',
                        'url' => $this->urlFor(
                            $request,
                            'displayGroup.selectfolder.form',
                            ['id' => $group->displayGroupId]
                        ),
                        'text' => __('Select Folder'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            [
                                'name' => 'commit-url',
                                'value' => $this->urlFor(
                                    $request,
                                    'displayGroup.selectfolder',
                                    ['id' => $group->displayGroupId]
                                )
                            ],
                            ['name' => 'commit-method', 'value' => 'put'],
                            ['name' => 'id', 'value' => 'displaygroup_button_selectfolder'],
                            ['name' => 'text', 'value' => __('Move to Folder')],
                            ['name' => 'rowtitle', 'value' => $group->displayGroup],
                            ['name' => 'form-callback', 'value' => 'moveFolderMultiSelectFormOpen']
                        ]
                    ];
                }
            }

            if ($this->getUser()->featureEnabled('displaygroup.modify')
                && $this->getUser()->checkDeleteable($group)
            ) {
                // Show the delete button
                $group->buttons[] = [
                    'id' => 'displaygroup_button_delete',
                    'url' => $this->urlFor($request, 'displayGroup.delete.form', ['id' => $group->displayGroupId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor(
                                $request,
                                'displayGroup.delete',
                                ['id' => $group->displayGroupId]
                            )
                        ],
                        ['name' => 'commit-method', 'value' => 'delete'],
                        ['name' => 'id', 'value' => 'displaygroup_button_delete'],
                        ['name' => 'text', 'value' => __('Delete')],
                        ['name' => 'sort-group', 'value' => 1],
                        ['name' => 'rowtitle', 'value' => $group->displayGroup],
                        ['name' => 'form-callback', 'value' => 'setDeleteMultiSelectFormOpen'],
                        ['name' => 'form-confirm', 'value' => true]
                    ]
                ];
            }

            if ($this->getUser()->featureEnabled('displaygroup.modify')
                && $this->getUser()->checkEditable($group)
            ) {
                $group->buttons[] = ['divider' => true];

                // File Associations
                $group->buttons[] = [
                    'id' => 'displaygroup_button_fileassociations',
                    'url' => $this->urlFor($request, 'displayGroup.media.form', ['id' => $group->displayGroupId]),
                    'text' => __('Assign Files')
                ];

                // Layout Assignments
                $group->buttons[] = [
                    'id' => 'displaygroup_button_layout_associations',
                    'url' => $this->urlFor($request, 'displayGroup.layout.form', ['id' => $group->displayGroupId]),
                    'text' => __('Assign Layouts')
                ];
            }

            if ($this->getUser()->featureEnabled('displaygroup.modify')
                && $this->getUser()->checkPermissionsModifyable($group)
            ) {
                // Show the modify permissions button
                $group->buttons[] = [
                    'id' => 'displaygroup_button_permissions',
                    'url' => $this->urlFor(
                        $request,
                        'user.permissions.form',
                        ['entity' => 'DisplayGroup', 'id' => $group->displayGroupId]
                    ),
                    'text' => __('Share'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor(
                                $request,
                                'user.permissions.multi',
                                ['entity' => 'DisplayGroup', 'id' => $group->displayGroupId]
                            )
                        ],
                        ['name' => 'commit-method', 'value' => 'post'],
                        ['name' => 'id', 'value' => 'displaygroup_button_permissions'],
                        ['name' => 'text', 'value' => __('Share')],
                        ['name' => 'rowtitle', 'value' => $group->displayGroup],
                        ['name' => 'sort-group', 'value' => 2],
                        ['name' => 'custom-handler', 'value' => 'XiboMultiSelectPermissionsFormOpen'],
                        [
                            'name' => 'custom-handler-url',
                            'value' => $this->urlFor(
                                $request,
                                'user.permissions.multi.form',
                                ['entity' => 'DisplayGroup']
                            )
                        ],
                        ['name' => 'content-id-name', 'value' => 'displayGroupId']
                    ]
                ];
            }

            // Check if limited view access is allowed
            if (($this->getUser()->featureEnabled('displaygroup.modify') && $this->getUser()->checkEditable($group))
                || $this->getUser()->featureEnabled('displaygroup.limitedView')
            ) {

                if ($this->getUser()->checkEditable($group)) {
                    $group->buttons[] = ['divider' => true];
                }

                // Send command
                $group->buttons[] = [
                    'id' => 'displaygroup_button_command',
                    'url' => $this->urlFor($request, 'displayGroup.command.form', ['id' => $group->displayGroupId]),
                    'text' => __('Send Command'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor(
                                $request,
                                'displayGroup.action.command',
                                ['id' => $group->displayGroupId]
                            )
                        ],
                        ['name' => 'commit-method', 'value' => 'post'],
                        ['name' => 'id', 'value' => 'displaygroup_button_command'],
                        ['name' => 'text', 'value' => __('Send Command')],
                        ['name' => 'sort-group', 'value' => 3],
                        ['name' => 'rowtitle', 'value' => $group->displayGroup],
                        ['name' => 'form-callback', 'value' => 'sendCommandMultiSelectFormOpen']
                    ]
                ];

                // Collect Now
                $group->buttons[] = [
                    'id' => 'displaygroup_button_collectNow',
                    'url' => $this->urlFor($request, 'displayGroup.collectNow.form', ['id' => $group->displayGroupId]),
                    'text' => __('Collect Now'),
                    'dataAttributes' => [
                        ['name' => 'auto-submit', 'value' => true],
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor(
                                $request,
                                'displayGroup.action.collectNow',
                                ['id' => $group->displayGroupId]
                            )
                        ],
                    ]
                ];

                if ($this->getUser()->checkEditable($group)) {
                    // Trigger webhook
                    $group->buttons[] = [
                        'id' => 'displaygroup_button_trigger_webhook',
                        'url' => $this->urlFor(
                            $request,
                            'displayGroup.trigger.webhook.form',
                            ['id' => $group->displayGroupId]
                        ),
                        'text' => __('Trigger a web hook'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            [
                                'name' => 'commit-url',
                                'value' => $this->urlFor(
                                    $request,
                                    'displayGroup.action.trigger.webhook',
                                    ['id' => $group->displayGroupId]
                                )
                            ],
                            ['name' => 'commit-method', 'value' => 'post'],
                            ['name' => 'id', 'value' => 'displaygroup_button_trigger_webhook'],
                            ['name' => 'text', 'value' => __('Trigger a web hook')],
                            ['name' => 'rowtitle', 'value' => $group->displayGroup],
                            ['name' => 'form-callback', 'value' => 'triggerWebhookMultiSelectFormOpen']
                        ]
                    ];
                }
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->displayGroupFactory->countLast();
        $this->getState()->setData($displayGroups);

        return $this->render($request, $response);
    }

    /**
     * Shows an add form for a display group
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function addForm(Request $request, Response $response)
    {
        $this->getState()->template = 'displaygroup-form-add';
        return $this->render($request, $response);
    }

    /**
     * Shows an edit form for a display group
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
        $displayGroup = $this->displayGroupFactory->getById($id);

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'displaygroup-form-edit';
        $this->getState()->setData([
            'displayGroup' => $displayGroup,
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
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function deleteForm(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($displayGroup)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'displaygroup-form-delete';
        $this->getState()->setData([
            'displayGroup' => $displayGroup,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Display Group Members form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function membersForm(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        // Displays in Group
        $displaysAssigned = $this->displayFactory->getByDisplayGroupId($displayGroup->displayGroupId);
        // Get all the DisplayGroups assigned to this Group directly
        $groupsAssigned = $this->displayGroupFactory->getByParentId($displayGroup->displayGroupId);

        $this->getState()->template = 'displaygroup-form-members';
        $this->getState()->setData([
            'displayGroup' => $displayGroup,
            'extra' => [
                'displaysAssigned' => $displaysAssigned,
                'displayGroupsAssigned' => $groupsAssigned
            ],
            'tree' => $this->displayGroupFactory->getRelationShipTree($id),
        ]);

        return $this->render($request, $response);
    }

    /**
     * Adds a Display Group
     * @SWG\Post(
     *  path="/displaygroup",
     *  operationId="displayGroupAdd",
     *  tags={"displayGroup"},
     *  summary="Add a Display Group",
     *  description="Add a new Display Group to the CMS",
     *  @SWG\Parameter(
     *      name="displayGroup",
     *      in="formData",
     *      description="The Display Group Name",
     *      type="string",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="The Display Group Description",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="A comma separated list of tags for this item",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isDynamic",
     *      in="formData",
     *      description="Flag indicating whether this DisplayGroup is Dynamic",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dynamicCriteria",
     *      in="formData",
     *      description="The filter criteria for this dynamic group.
     * A comma separated set of regular expressions to apply",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="logicalOperatorName",
     *      in="formData",
     *      description="When filtering by multiple dynamic criteria, which logical operator should be used? AND|OR",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dynamicCriteriaTags",
     *      in="formData",
     *      description="The filter criteria for this dynamic group.
     * A comma separated set of regular expressions to apply",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="exactTags",
     *      in="formData",
     *      description="When filtering by Tags, should we use exact match?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="logicalOperator",
     *      in="formData",
     *      description="When filtering by Tags, which logical operator should be used? AND|OR",
     *      type="string",
     *      required=false
     *   ),
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
     *      @SWG\Schema(ref="#/definitions/DisplayGroup"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new DisplayGroup",
     *          type="string"
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function add(Request $request, Response $response)
    {
        $displayGroup = $this->displayGroupFactory->createEmpty();
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $displayGroup->displayGroup = $sanitizedParams->getString('displayGroup');
        $displayGroup->description = $sanitizedParams->getString('description');
        $displayGroup->isDynamic = $sanitizedParams->getCheckbox('isDynamic');
        $displayGroup->dynamicCriteria = $sanitizedParams->getString('dynamicCriteria');
        $displayGroup->dynamicCriteriaLogicalOperator = $sanitizedParams->getString('logicalOperatorName');
        $displayGroup->folderId = $sanitizedParams->getInt('folderId');
        $displayGroup->ref1 = $sanitizedParams->getString('ref1');
        $displayGroup->ref2 = $sanitizedParams->getString('ref2');
        $displayGroup->ref3 = $sanitizedParams->getString('ref3');
        $displayGroup->ref4 = $sanitizedParams->getString('ref4');
        $displayGroup->ref5 = $sanitizedParams->getString('ref5');

        if ($displayGroup->folderId === 1) {
            $this->checkRootFolderAllowSave();
        }

        if (empty($displayGroup->folderId)) {
            $displayGroup->folderId = $this->getUser()->homeFolderId;
        }

        if ($this->getUser()->featureEnabled('folder.view')) {
            $folder = $this->folderFactory->getById($displayGroup->folderId);
            $displayGroup->permissionsFolderId = $folder->getPermissionFolderIdOrThis();
        } else {
            $displayGroup->permissionsFolderId = 1;
        }

        if ($this->getUser()->featureEnabled('tag.tagging')) {
            if (is_array($sanitizedParams->getParam('tags'))) {
                $tags = $this->tagFactory->tagsFromJson($sanitizedParams->getArray('tags'));
            } else {
                $tags = $this->tagFactory->tagsFromString($sanitizedParams->getString('tags'));
            }

            $displayGroup->updateTagLinks($tags);
            $displayGroup->dynamicCriteriaTags = $sanitizedParams->getString('dynamicCriteriaTags');
            $displayGroup->dynamicCriteriaExactTags = $sanitizedParams->getCheckbox('exactTags');
            $displayGroup->dynamicCriteriaTagsLogicalOperator = $sanitizedParams->getString('logicalOperator');
        }

        if ($displayGroup->isDynamic === 1) {
            $displayGroup->setDisplayFactory($this->displayFactory);
        }

        $displayGroup->userId = $this->getUser()->userId;
        $displayGroup->save();

        // Return
        $this->getState()->hydrate([
            'httpState' => 201,
            'message' => sprintf(__('Added %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId,
            'data' => $displayGroup
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edits a Display Group
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Put(
     *  path="/displaygroup/{displayGroupId}",
     *  operationId="displayGroupEdit",
     *  tags={"displayGroup"},
     *  summary="Edit a Display Group",
     *  description="Edit an existing Display Group identified by its Id",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The displayGroupId to edit.",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="displayGroup",
     *      in="formData",
     *      description="The Display Group Name",
     *      type="string",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="The Display Group Description",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="A comma separated list of tags for this item",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isDynamic",
     *      in="formData",
     *      description="Flag indicating whether this DisplayGroup is Dynamic",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dynamicCriteria",
     *      in="formData",
     *      description="The filter criteria for this dynamic group.
     * A command separated set of regular expressions to apply",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="logicalOperatorName",
     *      in="formData",
     *      description="When filtering by multiple dynamic criteria, which logical operator should be used? AND|OR",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dynamicCriteriaTags",
     *      in="formData",
     *      description="The filter criteria for this dynamic group.
     * A comma separated set of regular expressions to apply",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="exactTags",
     *      in="formData",
     *      description="When filtering by Tags, should we use exact match?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="logicalOperator",
     *      in="formData",
     *      description="When filtering by Tags, which logical operator should be used? AND|OR",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="formData",
     *      description="Folder ID to which this object should be assigned to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref1",
     *      in="formData",
     *      description="Reference 1",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref2",
     *      in="formData",
     *      description="Reference 2",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref3",
     *      in="formData",
     *      description="Reference 3",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref4",
     *      in="formData",
     *      description="Reference 4",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ref5",
     *      in="formData",
     *      description="Reference 5",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DisplayGroup")
     *  )
     * )
     */
    public function edit(Request $request,Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);
        $parsedRequestParams = $this->getSanitizer($request->getParams());
        $preEditIsDynamic = $displayGroup->getOriginalValue('isDynamic');

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        $displayGroup->load();
        $this->getDispatcher()->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);
        $displayGroup->displayGroup = $parsedRequestParams->getString('displayGroup');
        $displayGroup->description = $parsedRequestParams->getString('description');
        $displayGroup->isDynamic = $parsedRequestParams->getCheckbox('isDynamic');
        $displayGroup->dynamicCriteria = ($displayGroup->isDynamic == 1)
            ? $parsedRequestParams->getString('dynamicCriteria')
            : null;
        $displayGroup->dynamicCriteriaLogicalOperator = ($displayGroup->isDynamic == 1)
            ? $parsedRequestParams->getString('logicalOperatorName')
            : 'OR';
        $displayGroup->folderId = $parsedRequestParams->getInt('folderId', ['default' => $displayGroup->folderId]);

        $displayGroup->ref1 = $parsedRequestParams->getString('ref1');
        $displayGroup->ref2 = $parsedRequestParams->getString('ref2');
        $displayGroup->ref3 = $parsedRequestParams->getString('ref3');
        $displayGroup->ref4 = $parsedRequestParams->getString('ref4');
        $displayGroup->ref5 = $parsedRequestParams->getString('ref5');

        if ($displayGroup->hasPropertyChanged('folderId')) {
            if ($displayGroup->folderId === 1) {
                $this->checkRootFolderAllowSave();
            }
            $folder = $this->folderFactory->getById($displayGroup->folderId);
            $displayGroup->permissionsFolderId = $folder->getPermissionFolderIdOrThis();
        }

        if ($this->getUser()->featureEnabled('tag.tagging')) {
            if (is_array($parsedRequestParams->getParam('tags'))) {
                $tags = $this->tagFactory->tagsFromJson($parsedRequestParams->getArray('tags'));
            } else {
                $tags = $this->tagFactory->tagsFromString($parsedRequestParams->getString('tags'));
            }

            $displayGroup->updateTagLinks($tags);
            $displayGroup->dynamicCriteriaTags = ($displayGroup->isDynamic == 1)
                ? $parsedRequestParams->getString('dynamicCriteriaTags')
                : null;
            $displayGroup->dynamicCriteriaExactTags = ($displayGroup->isDynamic == 1)
                ? $parsedRequestParams->getCheckbox('exactTags')
                : 0;
            $displayGroup->dynamicCriteriaTagsLogicalOperator = ($displayGroup->isDynamic == 1)
                ? $parsedRequestParams->getString('logicalOperator')
                : 'OR';
        }

        // if we have changed the type from dynamic to non-dynamic or other way around, clear display/dg members
        if ($preEditIsDynamic != $displayGroup->isDynamic) {
            $this->getLog()->debug(
                'Display Group Id ' . $displayGroup->displayGroupId
                . ' switched is dynamic from ' . $preEditIsDynamic
                . ' To ' . $displayGroup->isDynamic . ' Clearing members for this Display Group.'
            );
            // get an array of assigned displays
            $membersDisplays = $this->displayFactory->getByDisplayGroupId($id);

            // get an array of assigned display groups
            $membersDisplayGroups = $this->displayGroupFactory->getByParentId($id);

            // unassign Displays
            foreach ($membersDisplays as $display) {
                $displayGroup->unassignDisplay($display);
            }

            // unassign Display Groups
            foreach ($membersDisplayGroups as $dg) {
                $displayGroup->unassignDisplayGroup($dg);
            }
        }

        $displayGroup->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId,
            'data' => $displayGroup
        ]);

        return $this->render($request, $response);
    }

    /**
     * Deletes a Group
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Delete(
     *  path="/displaygroup/{displayGroupId}",
     *  operationId="displayGroupDelete",
     *  tags={"displayGroup"},
     *  summary="Delete a Display Group",
     *  description="Delete an existing Display Group identified by its Id",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The displayGroupId to delete",
     *      required=true
     *  ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    function delete(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);
        $displayGroup->load();

        if (!$this->getUser()->checkDeleteable($displayGroup)) {
            throw new AccessDeniedException();
        }

        if ($displayGroup->isDisplaySpecific == 1) {
            throw new AccessDeniedException(__('Displays should be deleted using the Display delete operation'));
        }

        $this->getDispatcher()->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);
        $displayGroup->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $displayGroup->displayGroup)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Sets the Members of a group
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/display/assign",
     *  operationId="displayGroupDisplayAssign",
     *  tags={"displayGroup"},
     *  summary="Assign one or more Displays to a Display Group",
     *  description="Adds the provided Displays to the Display Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The Display Group to assign to",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="displayId",
     *      type="array",
     *      in="formData",
     *      description="The Display Ids to assign",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Parameter(
     *      name="unassignDisplayId",
     *      in="formData",
     *      description="An optional array of Display IDs to unassign",
     *      type="array",
     *      required=false,
     *      @SWG\Items(type="integer")
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function assignDisplay(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if ($displayGroup->isDisplaySpecific == 1) {
            throw new InvalidArgumentException(
                __('This is a Display specific Display Group and its assignments cannot be modified.'),
                'displayGroupId'
            );
        }

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        if ($displayGroup->isDynamic == 1) {
            throw new InvalidArgumentException(
                __('Displays cannot be manually assigned to a Dynamic Group'),
                'isDynamic'
            );
        }

        $displayGroup->load();
        $this->getDispatcher()->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);

        $this->getLog()->debug('assignDisplay: displayGroupId loaded: ' . $displayGroup->displayGroupId);

        // Keep track of displays we've changed so that we can notify.
        $modifiedDisplays = [];

        // Support both an array and a single int.
        $displays = $sanitizedParams->getParam('displayId');
        if (is_numeric($displays)) {
            $displays = [$sanitizedParams->getInt('displayId')];
        } else {
            $displays = $sanitizedParams->getIntArray('displayId', ['default' => []]);
        }

        foreach ($displays as $displayId) {
            $display = $this->displayFactory->getById($displayId);

            if (!$this->getUser()->checkViewable($this->displayGroupFactory->getById($display->displayGroupId))) {
                throw new AccessDeniedException(__('Access Denied to Display'));
            }

            $displayGroup->assignDisplay($display);

            // Store so that we can flag as incomplete
            if (!in_array($display, $modifiedDisplays)) {
                $modifiedDisplays[] = $display;
            }
        }

        // Have we been provided with unassign id's as well?
        $displays = $sanitizedParams->getParam('unassignDisplayId');
        if (is_numeric($displays)) {
            $displays = [$sanitizedParams->getInt('unassignDisplayId')];
        } else {
            $displays = $sanitizedParams->getIntArray('unassignDisplayId', ['default' => []]);
        }

        foreach ($displays as $displayId) {
            $display = $this->displayFactory->getById($displayId);

            if (!$this->getUser()->checkViewable($this->displayGroupFactory->getById($display->displayGroupId))) {
                throw new AccessDeniedException(__('Access Denied to Display'));
            }

            $displayGroup->unassignDisplay($display);

            // Store so that we can flag as incomplete
            if (!in_array($display, $modifiedDisplays)) {
                $modifiedDisplays[] = $display;
            }
        }

        // Save the result
        $displayGroup->save(['validate' => false, 'saveTags' => false]);

        // Save the displays themselves
        foreach ($modifiedDisplays as $display) {
            /** @var Display $display */
            $display->notify();
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Displays assigned to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Unassign displays from a Display Group
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/display/unassign",
     *  operationId="displayGroupDisplayUnassign",
     *  tags={"displayGroup"},
     *  summary="Unassigns one or more Displays to a Display Group",
     *  description="Removes the provided Displays from the Display Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The Display Group to unassign from",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="displayId",
     *      type="array",
     *      in="formData",
     *      description="The Display Ids to unassign",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function unassignDisplay(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if ($displayGroup->isDisplaySpecific == 1) {
            throw new InvalidArgumentException(
                __('This is a Display specific Display Group and its assignments cannot be modified.'),
                'displayGroupId'
            );
        }

        $displayGroup->load();
        $this->getDispatcher()->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        if ($displayGroup->isDynamic == 1) {
            throw new InvalidArgumentException(
                __('Displays cannot be manually unassigned to a Dynamic Group'),
                'isDynamic'
            );
        }

        $displays = $sanitizedParams->getIntArray('displayId', ['default' => []]);

        foreach ($displays as $displayId) {
            $display = $this->displayFactory->getById($displayId);

            if (!$this->getUser()->checkViewable($this->displayGroupFactory->getById($display->displayGroupId))) {
                throw new AccessDeniedException(__('Access Denied to Display'));
            }

            $this->getLog()->debug('Unassigning ' . $display->display);

            $displayGroup->unassignDisplay($display);
        }

        $displayGroup->save(['validate' => false, 'saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Displays unassigned from %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Sets the Members of a group
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/displayGroup/assign",
     *  operationId="displayGroupDisplayGroupAssign",
     *  tags={"displayGroup"},
     *  summary="Assign one or more DisplayGroups to a Display Group",
     *  description="Adds the provided DisplayGroups to the Display Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The Display Group to assign to",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="array",
     *      in="formData",
     *      description="The displayGroup Ids to assign",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Parameter(
     *      name="unassignDisplayGroupId",
     *      in="formData",
     *      description="An optional array of displayGroup IDs to unassign",
     *      type="array",
     *      required=false,
     *      @SWG\Items(type="integer")
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function assignDisplayGroup(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if ($displayGroup->isDisplaySpecific == 1) {
            throw new InvalidArgumentException(
                __('This is a Display specific Display Group and its assignments cannot be modified.'),
                'displayGroupId'
            );
        }

        $displayGroup->load();
        $this->getDispatcher()->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        if ($displayGroup->isDynamic == 1) {
            throw new InvalidArgumentException(
                __('DisplayGroups cannot be manually assigned to a Dynamic Group'),
                'isDynamic'
            );
        }

        $displayGroups = $sanitizedParams->getIntArray('displayGroupId', ['default' => []]);

        foreach ($displayGroups as $assignDisplayGroupId) {
            $displayGroupAssign = $this->displayGroupFactory->getById($assignDisplayGroupId);

            if (!$this->getUser()->checkViewable($displayGroupAssign)) {
                throw new AccessDeniedException(__('Access Denied to DisplayGroup'));
            }

            $displayGroup->assignDisplayGroup($displayGroupAssign);
        }

        // Have we been provided with unassign id's as well?
        $displayGroups = $sanitizedParams->getIntArray('unassignDisplayGroupId', ['default' => []]);

        foreach ($displayGroups as $assignDisplayGroupId) {
            $displayGroupUnassign = $this->displayGroupFactory->getById($assignDisplayGroupId);

            if (!$this->getUser()->checkViewable($displayGroupUnassign)) {
                throw new AccessDeniedException(__('Access Denied to DisplayGroup'));
            }

            $displayGroup->unassignDisplayGroup($displayGroupUnassign);
        }

        // Save the result
        $displayGroup->save(['validate' => false, 'saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('DisplayGroups assigned to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Unassign DisplayGroups from a Display Group
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/displayGroup/unassign",
     *  operationId="displayGroupDisplayGroupUnassign",
     *  tags={"displayGroup"},
     *  summary="Unassigns one or more DisplayGroups to a Display Group",
     *  description="Removes the provided DisplayGroups from the Display Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The Display Group to unassign from",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="array",
     *      in="formData",
     *      description="The DisplayGroup Ids to unassign",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function unassignDisplayGroup(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if ($displayGroup->isDisplaySpecific == 1) {
            throw new InvalidArgumentException(
                __('This is a Display specific Display Group and its assignments cannot be modified.'),
                'displayGroupId'
            );
        }

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        $displayGroup->load();
        $this->getDispatcher()->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);

        if ($displayGroup->isDynamic == 1) {
            throw new InvalidArgumentException(
                __('DisplayGroups cannot be manually unassigned to a Dynamic Group'),
                'isDynamic'
            );
        }

        $displayGroups = $sanitizedParams->getIntArray('displayGroupId', ['default' => []]);

        foreach ($displayGroups as $assignDisplayGroupId) {
            $displayGroup->unassignDisplayGroup($this->displayGroupFactory->getById($assignDisplayGroupId));
        }

        $displayGroup->save(['validate' => false, 'saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('DisplayGroups unassigned from %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Media Form (media linked to displays)
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function mediaForm(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        // Load the groups details
        $this->getDispatcher()->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);
        $displayGroup->load();

        $this->getState()->template = 'displaygroup-form-media';
        $this->getState()->setData([
            'displayGroup' => $displayGroup,
            'modules' => $this->moduleFactory->getLibraryModules(),
            'media' => $displayGroup->media,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Assign Media
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/media/assign",
     *  operationId="displayGroupMediaAssign",
     *  tags={"displayGroup"},
     *  summary="Assign one or more Media items to a Display Group",
     *  description="Adds the provided Media to the Display Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The Display Group to assign to",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="mediaId",
     *      type="array",
     *      in="formData",
     *      description="The Media Ids to assign",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Parameter(
     *      name="unassignMediaId",
     *      type="array",
     *      in="formData",
     *      description="Optional array of Media Id to unassign",
     *      required=false,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function assignMedia(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        // Load the groups details
        $this->getDispatcher()->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);
        $displayGroup->load();

        $mediaIds = $sanitizedParams->getIntArray('mediaId', ['default' => []]);

        // Loop through all the media
        foreach ($mediaIds as $mediaId) {

            $media = $this->mediaFactory->getById($mediaId);

            if (!$this->getUser()->checkViewable($media)) {
                throw new AccessDeniedException(__('You have selected media that you no longer have permission to use. Please reload the form.'));
            }

            $displayGroup->assignMedia($media);
        }

        $unassignMediaIds  = $sanitizedParams->getIntArray('unassignMediaId', ['default' => []]);

        // Check for unassign
        foreach ($unassignMediaIds as $mediaId) {
            // Get the media record
            $media = $this->mediaFactory->getById($mediaId);

            if (!$this->getUser()->checkViewable($media)) {
                throw new AccessDeniedException(__('You have selected media that you no longer have permission to use. Please reload the form.'));
            }

            $displayGroup->unassignMedia($media);
        }

        $displayGroup->setCollectRequired(false);
        $displayGroup->save(['validate' => false, 'saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Files assigned to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Unassign Media
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/media/unassign",
     *  operationId="displayGroupMediaUnassign",
     *  tags={"displayGroup"},
     *  summary="Unassign one or more Media items from a Display Group",
     *  description="Removes the provided from the Display Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The Display Group to unassign from",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="mediaId",
     *      type="array",
     *      in="formData",
     *      description="The Media Ids to unassign",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function unassignMedia(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        // Load the groups details
        $this->getDispatcher()->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);
        $displayGroup->load();

        $mediaIds = $sanitizedParams->getIntArray('mediaId', ['default' => []]);

        // Loop through all the media
        foreach ($mediaIds as $mediaId) {
            $displayGroup->unassignMedia($this->mediaFactory->getById($mediaId));
        }

        $displayGroup->setCollectRequired(false);
        $displayGroup->save(['validate' => false, 'saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Files unassigned from %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Layouts Form (layouts linked to displays)
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function LayoutsForm(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        // Load the groups details
        $this->getDispatcher()->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);
        $displayGroup->load();

        $this->getState()->template = 'displaygroup-form-layouts';
        $this->getState()->setData([
            'displayGroup' => $displayGroup,
            'layouts' => $this->layoutFactory->getByDisplayGroupId($displayGroup->displayGroupId),
        ]);

        return $this->render($request, $response);
    }

    /**
     * Assign Layouts
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/layout/assign",
     *  operationId="displayGroupLayoutsAssign",
     *  tags={"displayGroup"},
     *  summary="Assign one or more Layouts items to a Display Group",
     *  description="Adds the provided Layouts to the Display Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The Display Group to assign to",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="layoutId",
     *      type="array",
     *      in="formData",
     *      description="The Layouts Ids to assign",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Parameter(
     *      name="unassignLayoutId",
     *      type="array",
     *      in="formData",
     *      description="Optional array of Layouts Id to unassign",
     *      required=false,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function assignLayouts(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        // Load the groups details
        $this->getDispatcher()->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);
        $displayGroup->load();

        $layoutIds = $sanitizedParams->getIntArray('layoutId', ['default' => []]);

        // Loop through all the Layouts
        foreach ($layoutIds as $layoutId) {

            $layout = $this->layoutFactory->getById($layoutId);

            if (!$this->getUser()->checkViewable($layout)) {
                throw new AccessDeniedException(__('You have selected a layout that you no longer have permission to use. Please reload the form.'));
            }

            $displayGroup->assignLayout($layout);
        }

        // Check for unassign
        foreach ($sanitizedParams->getIntArray('unassignLayoutId', ['default' => []]) as $layoutId) {
            // Get the layout record
            $layout = $this->layoutFactory->getById($layoutId);

            if (!$this->getUser()->checkViewable($layout)) {
                throw new AccessDeniedException(__('You have selected a layout that you no longer have permission to use. Please reload the form.'));
            }

            $displayGroup->unassignLayout($layout);
        }

        $displayGroup->setCollectRequired(false);
        $displayGroup->save(['validate' => false, 'saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Layouts assigned to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Unassign Layout
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/layout/unassign",
     *  operationId="displayGroupLayoutUnassign",
     *  tags={"displayGroup"},
     *  summary="Unassign one or more Layout items from a Display Group",
     *  description="Removes the provided from the Display Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      type="integer",
     *      in="path",
     *      description="The Display Group to unassign from",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="layoutId",
     *      type="array",
     *      in="formData",
     *      description="The Layout Ids to unassign",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function unassignLayouts(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        // Load the groups details
        $this->getDispatcher()->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);
        $displayGroup->load();

        $layoutIds = $sanitizedParams->getIntArray('layoutId', ['default' => []]);

        // Loop through all the media
        foreach ($layoutIds as $layoutId) {
            $this->getLog()->debug('Unassign layoutId ' . $layoutId . ' from ' . $id);
            $displayGroup->unassignLayout($this->layoutFactory->getById($layoutId));
        }

        $displayGroup->setCollectRequired(false);
        $displayGroup->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Layouts unassigned from %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
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
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function collectNowForm(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);

        // Non-destructive edit-only feature; allow limited view access
        if (
            !$this->getUser()->checkEditable($displayGroup)
            && !$this->getUser()->featureEnabled('displays.limitedView')
            && !$this->getUser()->featureEnabled('displaygroup.limitedView')
        ) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'displaygroup-form-collect-now';
        $this->getState()->autoSubmit = $this->getAutoSubmit('displayGroupCollectNow');
        $this->getState()->setData([
            'displayGroup' => $displayGroup
        ]);

        return $this->render($request, $response);
    }

    /**
     * Cause the player to collect now
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/action/collectNow",
     *  operationId="displayGroupActionCollectNow",
     *  tags={"displayGroup"},
     *  summary="Action: Collect Now",
     *  description="Send the collect now action to this DisplayGroup",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      in="path",
     *      description="The display group id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function collectNow(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);

        // Non-destructive edit-only feature; allow limited view access
        if (
            !$this->getUser()->checkEditable($displayGroup)
            && !$this->getUser()->featureEnabled('displays.limitedView')
            && !$this->getUser()->featureEnabled('displaygroup.limitedView')
        ) {
            throw new AccessDeniedException();
        }

        $this->playerAction->sendAction($this->displayFactory->getByDisplayGroupId($id), new CollectNowAction());

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Command Sent to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Cause the player to collect now
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/action/clearStatsAndLogs",
     *  operationId="displayGroupActionClearStatsAndLogs",
     *  tags={"displayGroup"},
     *  summary="Action: Clear Stats and Logs",
     *  description="Clear all stats and logs on this Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      in="path",
     *      description="The display group id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function clearStatsAndLogs(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        $this->playerAction->sendAction($this->displayFactory->getByDisplayGroupId($id), new CollectNowAction());

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Command Sent to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Change to a new Layout
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/action/changeLayout",
     *  operationId="displayGroupActionChangeLayout",
     *  tags={"displayGroup"},
     *  summary="Action: Change Layout",
     *  description="Send a change layout action to the provided Display Group. This will be sent to Displays in that Group via XMR.",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      in="path",
     *      description="This can be either a Display Group or the Display specific Display Group",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="formData",
     *      description="The ID of the Layout to change to. Either this or a campaignId must be provided.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="campaignId",
     *      in="formData",
     *      description="The Layout specific campaignId of the Layout to change to. Either this or a layoutId must be provided.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="The duration in seconds for this Layout change to remain in effect, after which normal scheduling is resumed.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="downloadRequired",
     *      in="formData",
     *      description="Flag indicating whether the player should perform a collect before playing the Layout.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="changeMode",
     *      in="formData",
     *      description="Whether to queue or replace with this action. Queuing will keep the current change layout action and switch after it is finished. If no active change layout action is present, both options are actioned immediately",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function changeLayout(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        // Get the layoutId or campaignId
        $layoutId = $sanitizedParams->getInt('layoutId');
        $campaignId = $sanitizedParams->getInt('campaignId');
        $downloadRequired = ($sanitizedParams->getCheckbox('downloadRequired') == 1);

        if ($layoutId == 0 && $campaignId == 0) {
            throw new InvalidArgumentException(__('Please provide a Layout ID or Campaign ID'), 'layoutId');
        }

        // Check that this user has permissions to see this layout
        if ($layoutId != 0 && $campaignId == 0) {
            $layout = $this->layoutFactory->getById($layoutId);
        } elseif ($layoutId == 0 && $campaignId != 0) {
            $campaign = $this->campaignFactory->getById($campaignId);

            if ($campaign->isLayoutSpecific == 0) {
                throw new NotFoundException(__('Please provide Layout specific campaign ID'));
            }

            $layouts = $this->layoutFactory->getByCampaignId($campaignId);

            if (count($layouts) <= 0) {
                throw new NotFoundException(__('Cannot find layout by campaignId'));
            }

            $layout = $layouts[0];
        } else {
            throw new InvalidArgumentException(__('Please provide Layout id or Campaign id'), 'layoutId');
        }

        if (!$this->getUser()->checkViewable($layout)) {
            throw new AccessDeniedException();
        }

        // Check to see if this layout is assigned to this display group.
        if (count($this->layoutFactory->query(null, ['disableUserCheck' => 1, 'layoutId' => $layout->layoutId, 'displayGroupId' => $id])) <= 0) {
            // Assign
            $this->getDispatcher()->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);
            $displayGroup->load();
            $displayGroup->assignLayout($layout);

            // Don't collect now, this player action will cause a download.
            // notify will still occur if the layout isn't already assigned (which is shouldn't be)
            $displayGroup->setCollectRequired(false);

            $displayGroup->save(['validate' => false, 'saveTags' => false]);

            // Convert into a download required
            $downloadRequired = true;
        } else {
            // The layout may not be built at this point
            if ($downloadRequired) {
                // in this case we should build it and notify before we send the action
                // notify should NOT collect now, as we will do that during our own action.
                $layout = $this->layoutFactory->concurrentRequestLock($layout);
                try {
                    $layout->xlfToDisk(['notify' => true, 'collectNow' => false]);
                } finally {
                    $this->layoutFactory->concurrentRequestRelease($layout);
                }
            }
        }

        // Create and send the player action
        $this->playerAction->sendAction($this->displayFactory->getByDisplayGroupId($id), (new ChangeLayoutAction())->setLayoutDetails(
            $layout->layoutId,
            $sanitizedParams->getInt('duration'),
            $downloadRequired,
            $sanitizedParams->getString('changeMode', ['default' => 'queue'])
        ));

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Command Sent to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Cause the player to revert to its scheduled content
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     *
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/action/revertToSchedule",
     *  operationId="displayGroupActionRevertToSchedule",
     *  tags={"displayGroup"},
     *  summary="Action: Revert to Schedule",
     *  description="Send the revert to schedule action to this DisplayGroup",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      in="path",
     *      description="This can be either a Display Group or the Display specific Display Group",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function revertToSchedule(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        $this->playerAction->sendAction($this->displayFactory->getByDisplayGroupId($id), new RevertToSchedule());

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Command Sent to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Add an Overlay Layout
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/action/overlayLayout",
     *  operationId="displayGroupActionOverlayLayout",
     *  tags={"displayGroup"},
     *  summary="Action: Overlay Layout",
     *  description="Send the overlay layout action to this DisplayGroup, you can pass layoutId or layout specific campaignId",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      in="path",
     *      description="This can be either a Display Group or the Display specific Display Group",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="formData",
     *      description="The ID of the Layout to change to. Either this or a campaignId must be provided.",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="campaignId",
     *      in="formData",
     *      description="The Layout specific campaignId of the Layout to change to. Either this or a layoutId must be provided.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="The duration in seconds for this Overlay to remain in effect",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="downloadRequired",
     *      in="formData",
     *      description="Whether to queue or replace with this action. Queuing will keep the current change layout action and switch after it is finished. If no active change layout action is present, both options are actioned immediately",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function overlayLayout(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        // Get the layoutId
        $layoutId = $sanitizedParams->getInt('layoutId');
        $campaignId = $sanitizedParams->getInt('campaignId');
        $downloadRequired = ($sanitizedParams->getCheckbox('downloadRequired') == 1);

        if ($layoutId == 0 && $campaignId == 0) {
            throw new InvalidArgumentException(__('Please provide a Layout ID or Campaign ID'), 'isDynamic');
        }

        // Check that this user has permissions to see this layout
        if ($layoutId != 0 && $campaignId == 0) {
            $layout = $this->layoutFactory->getById($layoutId);
        } elseif ($layoutId == 0 && $campaignId != 0) {
            $campaign = $this->campaignFactory->getById($campaignId);

            if ($campaign->isLayoutSpecific == 0) {
                throw new NotFoundException(__('Please provide Layout specific campaign ID'));
            }

            $layouts = $this->layoutFactory->getByCampaignId($campaignId);

            if (count($layouts) <= 0) {
                throw new NotFoundException(__('Cannot find layout by campaignId'));
            }

            $layout = $layouts[0];
        } else {
            throw new InvalidArgumentException(__('Please provide Layout id or Campaign id'), 'layoutId');
        }

        if (!$this->getUser()->checkViewable($layout)) {
            throw new AccessDeniedException();
        }

        // Check to see if this layout is assigned to this display group.
        if (count($this->layoutFactory->query(null, ['disableUserCheck' => 1, 'layoutId' => $layout->layoutId, 'displayGroupId' => $id])) <= 0) {
            // Assign
            $this->getDispatcher()->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);
            $displayGroup->load();
            $displayGroup->assignLayout($layout);
            // Don't notify, this player action will cause a download.
            $displayGroup->setCollectRequired(false);
            $displayGroup->save(['validate' => false, 'saveTags' => false]);

            // Convert into a download required
            $downloadRequired = true;
        } else {
            // The layout may not be built at this point
            if ($downloadRequired) {
                // in this case we should build it and notify before we send the action
                // notify should NOT collect now, as we will do that during our own action.
                $layout = $this->layoutFactory->concurrentRequestLock($layout);
                try {
                    $layout->xlfToDisk(['notify' => true, 'collectNow' => false]);
                } finally {
                    $this->layoutFactory->concurrentRequestRelease($layout);
                }
            }
        }

        $this->playerAction->sendAction($this->displayFactory->getByDisplayGroupId($id), (new OverlayLayoutAction())->setLayoutDetails(
            $layout->layoutId,
            $sanitizedParams->getInt('duration'),
            $downloadRequired
        ));

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Command Sent to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Command Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function commandForm(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);

        // Non-destructive edit-only feature; allow limited view access
        if (
            !$this->getUser()->checkEditable($displayGroup)
            && !$this->getUser()->featureEnabled('displaygroup.limitedView')
            && !$this->getUser()->featureEnabled('displays.limitedView')
        ) {
            throw new AccessDeniedException();
        }

        // Are we a Display Specific Group? If so, then we should restrict the List of commands to those available.
        if ($displayGroup->isDisplaySpecific == 1) {
            $display = $this->displayFactory->getByDisplayGroupId($displayGroup->displayGroupId);
            $commands = $this->commandFactory->query(null, ['type' => $display[0]->clientType]);
        } else {
            $commands = $this->commandFactory->query();
        }

        $this->getState()->template = 'displaygroup-form-command';
        $this->getState()->setData([
            'displayGroup' => $displayGroup,
            'commands' => $commands
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
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/action/command",
     *  operationId="displayGroupActionCommand",
     *  tags={"displayGroup"},
     *  summary="Send Command",
     *  description="Send a predefined command to this Group of Displays",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      in="path",
     *      description="The display group id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="commandId",
     *      in="formData",
     *      description="The Command Id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function command(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Non-destructive edit-only feature; allow limited view access
        if (
            !$this->getUser()->checkEditable($displayGroup)
            && !$this->getUser()->featureEnabled('displaygroup.limitedView')
        ) {
            throw new AccessDeniedException();
        }

        $command = $this->commandFactory->getById($sanitizedParams->getInt('commandId'));
        $displays = $this->displayFactory->getByDisplayGroupId($id);

        $this->playerAction->sendAction($displays, (new CommandAction())->setCommandCode($command->code));

        // Update the flag
        foreach ($displays as $display) {
            /* @var \Xibo\Entity\Display $display */
            $display->lastCommandSuccess = 0;
            $display->save(['validate' => false, 'audit' => false]);
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Command Sent to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
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
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function copyForm(Request $request, Response $response, $id)
    {
        // Create a form out of the config object.
        $displayGroup = $this->displayGroupFactory->getById($id);

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayGroup->userId) {
            throw new AccessDeniedException(__('You do not have permission to delete this profile'));
        }

        $this->getState()->template = 'displaygroup-form-copy';
        $this->getState()->setData([
            'displayGroup' => $displayGroup
        ]);

        return $this->render($request, $response);
    }

    /**
     * Copy Display Group
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/copy",
     *  operationId="displayGroupCopy",
     *  tags={"displayGroup"},
     *  summary="Copy Display Group",
     *  description="Copy an existing Display Group",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      in="path",
     *      description="The Display Group ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="displayGroup",
     *      in="formData",
     *      description="The name for the copy",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="The description for the copy",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="copyMembers",
     *      in="formData",
     *      description="Flag indicating whether to copy all display and display group members",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="copyAssignments",
     *      in="formData",
     *      description="Flag indicating whether to copy all layout and media assignments",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="copyTags",
     *      in="formData",
     *      description="Flag indicating whether to copy all tags",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DisplayGroup"),
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
        // get display group object
        $displayGroup = $this->displayGroupFactory->getById($id);
        $displayGroup->setDisplayFactory($this->displayFactory);

        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        // What should we copy?
        $copyMembers = $sanitizedParams->getCheckbox('copyMembers');
        $copyTags = $sanitizedParams->getCheckbox('copyTags');
        $copyAssignments = $sanitizedParams->getCheckbox('copyAssignments');

        // Save loading if we don't need to.
        if ($copyTags || $copyMembers || $copyAssignments) {
            // Load tags
            $displayGroup->load();

            if ($copyMembers || $copyAssignments) {
                // Load the entire display group
                $this->getDispatcher()->dispatch(
                    new DisplayGroupLoadEvent($displayGroup),
                    DisplayGroupLoadEvent::$NAME
                );
            }
        }

        // Copy the group
        $new = clone $displayGroup;
        $new->displayGroup = $sanitizedParams->getString('displayGroup');
        $new->description = $sanitizedParams->getString('description');
        $new->setOwner($this->getUser()->userId);
        $new->clearTags();

        // handle display group members
        if (!$copyMembers) {
            $new->clearDisplays();
            $new->clearDisplayGroups();
        }

        // handle layout and file assignment
        if (!$copyAssignments) {
            $new->clearLayouts()->clearMedia();
        }

        // handle tags
        if ($copyTags) {
            $new->updateTagLinks($displayGroup->tags);
        }

        $new->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $new->displayGroup),
            'id' => $new->displayGroupId,
            'data' => $new
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
        // Get the Display Group
        $displayGroup = $this->displayGroupFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        $data = [
            'displayGroup' => $displayGroup
        ];

        $this->getState()->template = 'displaygroup-form-selectfolder';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     *
     * @SWG\Put(
     *  path="/displaygroup/{id}/selectfolder",
     *  operationId="displayGroupSelectFolder",
     *  tags={"displayGroup"},
     *  summary="Display Group Select folder",
     *  description="Select Folder for Display Group, can also be used with Display specific Display Group ID",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      in="path",
     *      description="The Display Group ID or Display specific Display Group ID",
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
     *      @SWG\Schema(ref="#/definitions/DisplayGroup")
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
    public function selectFolder(Request $request, Response $response, $id)
    {
        // Get the Display Group
        $displayGroup = $this->displayGroupFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        // Folders
        $folderId = $this->getSanitizer($request->getParams())->getInt('folderId');
        if ($folderId === 1) {
            $this->checkRootFolderAllowSave();
        }

        if (empty($folderId) || !$this->getUser()->featureEnabled('folder.view')) {
            $folderId = $this->getUser()->homeFolderId;
        }

        $folder = $this->folderFactory->getById($folderId, 0);
        $displayGroup->folderId = $folder->id;
        $displayGroup->permissionsFolderId = $folder->getPermissionFolderIdOrThis();

        // Save
        $displayGroup->save([
            'manageLinks' => false,
            'manageDisplayLinks' => false,
            'manageDynamicDisplayLinks' => false,
        ]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Display %s moved to Folder %s'), $displayGroup->displayGroup, $folder->text)
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
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function triggerWebhookForm(Request $request, Response $response, $id)
    {
        $displayGroup = $this->displayGroupFactory->getById($id);

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'displaygroup-form-trigger-webhook';
        $this->getState()->setData([
            'displayGroup' => $displayGroup
        ]);

        return $this->render($request, $response);
    }

    /**
     * Send a code to a Player to trigger a web hook associated with provided trigger code.
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @SWG\Post(
     *  path="/displaygroup/{displayGroupId}/action/triggerWebhook",
     *  operationId="displayGroupActionTriggerWebhook",
     *  tags={"displayGroup"},
     *  summary="Action: Trigger Web hook",
     *  description="Send the trigger webhook action to this DisplayGroup",
     *  @SWG\Parameter(
     *      name="displayGroupId",
     *      in="path",
     *      description="The display group id",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="triggerCode",
     *      in="formData",
     *      description="The trigger code that should be sent to the Player",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function triggerWebhook(Request $request, Response $response, $id)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $displayGroup = $this->displayGroupFactory->getById($id);
        $triggerCode = $sanitizedParams->getString('triggerCode');

        if (!$this->getUser()->checkEditable($displayGroup)) {
            throw new AccessDeniedException();
        }

        if ($triggerCode == '') {
            throw new InvalidArgumentException(__('Please provide a Trigger Code'), 'triggerCode');
        }

        $this->playerAction->sendAction(
            $this->displayFactory->getByDisplayGroupId($id),
            new TriggerWebhookAction($triggerCode)
        );

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Command Sent to %s'), $displayGroup->displayGroup),
            'id' => $displayGroup->displayGroupId
        ]);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Post(
     *    path="/displaygroup/criteria[/{displayGroupId}]",
     *    operationId="ScheduleCriteriaUpdate",
     *    tags={"displayGroup"},
     *    summary="Action: Push Criteria Update",
     *    description="Send criteria updates to the specified DisplayGroup or to all displays if displayGroupId is not
     *                  provided.",
     *    @SWG\Parameter(
     *        name="displayGroupId",
     *        in="path",
     *        description="The display group id",
     *        type="integer",
     *        required=true
     *     ),
     *    @SWG\Parameter(
     *        name="criteriaUpdates",
     *        in="body",
     *        description="The criteria updates to send to the Player",
     *        required=true,
     *        @SWG\Schema(
     *            type="array",
     *            @SWG\Items(
     *                type="object",
     *                @SWG\Property(property="metric", type="string"),
     *                @SWG\Property(property="value", type="string"),
     *                @SWG\Property(property="ttl", type="integer")
     *            )
     *        )
     *     ),
     *    @SWG\Response(
     *        response=204,
     *        description="Successful operation"
     *    ),
     *    @SWG\Response(
     *        response=400,
     *        description="Invalid criteria format"
     *    )
     *   )
     *
     * @param Request $request
     * @param Response $response
     * @param int $displayGroupId
     * @return ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     * @throws PlayerActionException
     */
    public function pushCriteriaUpdate(Request $request, Response $response, int $displayGroupId): Response|ResponseInterface
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Get criteria updates
        $criteriaUpdates = $sanitizedParams->getArray('criteriaUpdates');

        // ensure criteria updates exists
        if (empty($criteriaUpdates)) {
            throw new InvalidArgumentException(__('No criteria found.'), 'criteriaUpdates');
        }

        // Initialize array to hold sanitized criteria updates
        $sanitizedCriteriaUpdates = [];

        // Loop through each criterion and sanitize the input
        foreach ($criteriaUpdates as $criteria) {
            $criteriaSanitizer = $this->getSanitizer($criteria);

            // Sanitize and retrieve the metric, value, and ttl
            $metric = $criteriaSanitizer->getString('metric');
            $value = $criteriaSanitizer->getString('value');
            $ttl = $criteriaSanitizer->getInt('ttl');

            // Ensure each criterion has metric, value, and ttl
            if (empty($metric) || empty($value) || !isset($ttl)) {
                // Throw an exception if any of the required fields are missing or empty
                throw new PlayerActionException(
                    __('Invalid criteria format. Metric, value, and ttl must all be present and not empty.')
                );
            }

            // Add sanitized criteria
            $sanitizedCriteriaUpdates[] = [
                'metric' => $metric,
                'value' => $value,
                'ttl' => abs($ttl)
            ];
        }

        // Create and send the player action to displays under the display group
        $this->playerAction->sendAction(
            $this->displayFactory->getByDisplayGroupId($displayGroupId),
            (new ScheduleCriteriaUpdateAction())->setCriteriaUpdates($sanitizedCriteriaUpdates)
        );

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => __('Schedule criteria updates sent to players.'),
            'id' => $displayGroupId
        ]);

        return $this->render($request, $response);
    }
}
