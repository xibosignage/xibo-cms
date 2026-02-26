<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

use OpenApi\Attributes as OA;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\FolderFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\TagFactory;
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

    /** @var FolderFactory */
    private $folderFactory;

    /** @var \Xibo\Factory\DisplayGroupFactory */
    private $displayGroupFactory;

    /**
     * Set common dependencies.
     * @param CampaignFactory $campaignFactory
     * @param LayoutFactory $layoutFactory
     * @param TagFactory $tagFactory
     * @param FolderFactory $folderFactory
     */
    public function __construct($campaignFactory, $layoutFactory, $tagFactory, $folderFactory, $displayGroupFactory)
    {
        $this->campaignFactory = $campaignFactory;
        $this->layoutFactory = $layoutFactory;
        $this->tagFactory = $tagFactory;
        $this->folderFactory = $folderFactory;
        $this->displayGroupFactory = $displayGroupFactory;
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
     * Display the Campaign Builder
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     */
    public function displayCampaignBuilder(Request $request, Response $response, $id)
    {
        $campaign = $this->campaignFactory->getById($id);
        if (!$this->getUser()->checkEditable($campaign)) {
            throw new AccessDeniedException();
        }

        if ($campaign->type !== 'ad') {
            throw new InvalidArgumentException(__('This campaign is not compatible with the Campaign builder'));
        }

        // Load in our current display groups for the form.
        $displayGroups = [];
        $displayGroupIds = $campaign->loadDisplayGroupIds();
        foreach ($displayGroupIds as $displayGroupId) {
            $displayGroups[] = $this->displayGroupFactory->getById($displayGroupId);
        }

        // Work out the percentage complete/target.
        $progress = $campaign->getProgress();

        $this->getState()->template = 'campaign-builder';
        $this->getState()->setData([
            'campaign' => $campaign,
            'displayGroupIds' => $displayGroupIds,
            'displayGroups' => $displayGroups,
            'stats' => [
                'complete' => round($progress->progressTime, 2),
                'target' => round($progress->progressTarget, 2),
            ],
        ]);
        return $this->render($request, $response);
    }

    #[OA\Get(
        path: '/campaign',
        operationId: 'campaignSearch',
        description: 'Search all Campaigns this user has access to',
        summary: 'Search Campaigns',
        tags: ['campaign']
    )]
    #[OA\Parameter(
        name: 'campaignId',
        description: 'Filter by Campaign Id',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'name',
        description: 'Filter by Name',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'tags',
        description: 'Filter by Tags',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'exactTags',
        description: 'A flag indicating whether to treat the tags filter as an exact match',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'logicalOperator',
        description: 'When filtering by multiple Tags, which logical operator should be used? AND|OR',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'hasLayouts',
        description: 'Filter by has layouts',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'isLayoutSpecific',
        description: 'Filter by whether this Campaign is specific to a Layout or User added',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'retired',
        description: 'Filter by retired',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'totalDuration',
        description: 'Should we total the duration?',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'embed',
        description: 'Embed related data such as layouts, permissions, tags and events',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'folderId',
        description: 'Filter by Folder ID',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Campaign'))
    )]
    /**
     * Returns a Grid of Campaigns
     *
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
            'type' => $parsedParams->getString('type'),
            'name' => $parsedParams->getString('name'),
            'useRegexForName' => $parsedParams->getCheckbox('useRegexForName'),
            'tags' => $parsedParams->getString('tags'),
            'exactTags' => $parsedParams->getCheckbox('exactTags'),
            'hasLayouts' => $parsedParams->getInt('hasLayouts'),
            'isLayoutSpecific' => $parsedParams->getInt('isLayoutSpecific'),
            'retired' => $parsedParams->getInt('retired'),
            'folderId' => $parsedParams->getInt('folderId'),
            'totalDuration' => $parsedParams->getInt('totalDuration', ['default' => 1]),
            'cyclePlaybackEnabled' => $parsedParams->getInt('cyclePlaybackEnabled'),
            'layoutId' => $parsedParams->getInt('layoutId'),
            'logicalOperator' => $parsedParams->getString('logicalOperator'),
            'logicalOperatorName' => $parsedParams->getString('logicalOperatorName'),
            'excludeMedia' => $parsedParams->getInt('excludeMedia'),
        ];

        $embed = ($parsedParams->getString('embed') !== null) ? explode(',', $parsedParams->getString('embed')) : [];

        $campaigns = $this->campaignFactory->query(
            $this->gridRenderSort($parsedParams),
            $this->gridRenderFilter($filter, $parsedParams)
        );

        foreach ($campaigns as $campaign) {
            /* @var \Xibo\Entity\Campaign $campaign */
            if (count($embed) > 0) {
                if (in_array('layouts', $embed)) {
                    $campaign->loadLayouts();
                }

                $campaign->load([
                    'loadPermissions' => in_array('permissions', $embed),
                    'loadTags' => in_array('tags', $embed),
                    'loadEvents' => in_array('events', $embed)
                ]);
            } else {
                $campaign->excludeProperty('layouts');
            }

            if ($this->isApi($request)) {
                continue;
            }

            $campaign->includeProperty('buttons');
            $campaign->buttons = [];

            // Schedule
            if ($this->getUser()->featureEnabled('schedule.add') && $campaign->type === 'list') {
                $campaign->buttons[] = [
                    'id' => 'campaign_button_schedule',
                    'url' => $this->urlFor(
                        $request,
                        'schedule.add.form',
                        ['id' => $campaign->campaignId, 'from' => 'Campaign']
                    ),
                    'text' => __('Schedule')
                ];
            }

            // Preview
            if ($this->getUser()->featureEnabled(['layout.view', 'campaign.view'], true)
                && $campaign->type === 'list'
            ) {
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
                if (count($campaign->buttons) > 0) {
                    $campaign->buttons[] = ['divider' => true];
                }

                // Edit the Campaign
                if ($campaign->type === 'list') {
                    $campaign->buttons[] = array(
                        'id' => 'campaign_button_edit',
                        'url' => $this->urlFor($request, 'campaign.edit.form', ['id' => $campaign->campaignId]),
                        'text' => __('Edit'),
                    );
                } else if ($campaign->type === 'ad' && $this->getUser()->featureEnabled('ad.campaign')) {
                    $campaign->buttons[] = [
                        'id' => 'campaign_button_edit',
                        'linkType' => '_self',
                        'external' => true,
                        'url' => $this->urlFor($request, 'campaign.builder', ['id' => $campaign->campaignId]),
                        'text' => __('Edit'),
                    ];
                }

                if ($this->getUser()->featureEnabled('folder.view')) {
                    // Select Folder
                    $campaign->buttons[] = [
                        'id' => 'campaign_button_selectfolder',
                        'url' => $this->urlFor(
                            $request,
                            'campaign.selectfolder.form',
                            ['id' => $campaign->campaignId]
                        ),
                        'text' => __('Select Folder'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            [
                                'name' => 'commit-url',
                                'value' => $this->urlFor(
                                    $request,
                                    'campaign.selectfolder',
                                    ['id' => $campaign->campaignId]
                                )
                            ],
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
                    'url' => $this->urlFor(
                        $request,
                        'campaign.copy.form',
                        ['id' => $campaign->campaignId]
                    ),
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
                    'url' => $this->urlFor(
                        $request,
                        'campaign.delete.form',
                        ['id' => $campaign->campaignId]
                    ),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor(
                                $request,
                                'campaign.delete',
                                ['id' => $campaign->campaignId]
                            )
                        ],
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
        ]);

        return $this->render($request, $response);
    }

    #[OA\Post(
        path: '/campaign',
        operationId: 'campaignAdd',
        description: 'Add a Campaign',
        summary: 'Add Campaign',
        tags: ['campaign']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['type', 'name'],
                properties: [
                    new OA\Property(property: 'type', description: 'Type of campaign, either list|ad', type: 'string'),
                    new OA\Property(property: 'name', description: 'Name for this Campaign', type: 'string'),
                    new OA\Property(
                        property: 'folderId',
                        description: 'Folder ID to which this object should be assigned to',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'layoutIds',
                        description: 'An array of layoutIds to assign to this Campaign, in order.',
                        type: 'array',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(
                        property: 'cyclePlaybackEnabled',
                        description: 'When cycle based playback is enabled only 1 Layout from this Campaign will be played each time it is in a Schedule loop. The same Layout will be shown until the \'Play count\' is achieved.', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'playCount',
                        description: 'In cycle based playback, how many plays should each Layout have before moving on?', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'listPlayOrder',
                        description: 'In layout list, how should campaigns in the schedule with the same play order be played?', // phpcs:ignore
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'targetType',
                        description: 'For ad campaigns, how do we measure the target? plays|budget|imp',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'target',
                        description: 'For ad campaigns, what is the target count for playback over the entire campaign',
                        type: 'integer'
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'successful operation',
        headers: [
            new OA\Header(
                header: 'Location',
                description: 'Location of the new record',
                schema: new OA\Schema(type: 'string')
            )
        ],
        content: new OA\JsonContent(ref: '#/components/schemas/Campaign')
    )]
    /**
     * Add a Campaign
     *
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

        // Folders
        $folderId = $sanitizedParams->getInt('folderId');
        if ($folderId === 1) {
            $this->checkRootFolderAllowSave();
        }

        if (empty($folderId) || !$this->getUser()->featureEnabled('folder.view')) {
            $folderId = $this->getUser()->homeFolderId;
        }

        $folder = $this->folderFactory->getById($folderId, 0);

        // Campaign type
        if ($this->getUser()->featureEnabled('ad.campaign')) {
            // We use a default to avoid a breaking change in a minor release.
            $type = $sanitizedParams->getString('type', ['default' => 'list']);
        } else {
            $type = 'list';
        }

        // Create Campaign
        $campaign = $this->campaignFactory->create(
            $type,
            $sanitizedParams->getString('name'),
            $this->getUser()->userId,
            $folder->getId()
        );
        $campaign->permissionsFolderId = $folder->getPermissionFolderIdOrThis();

        if ($this->getUser()->featureEnabled('tag.tagging')) {
            if (is_array($sanitizedParams->getParam('tags'))) {
                $tags = $this->tagFactory->tagsFromJson($sanitizedParams->getArray('tags'));
            } else {
                $tags = $this->tagFactory->tagsFromString($sanitizedParams->getString('tags'));
            }

            $campaign->updateTagLinks($tags);
        }

        // Cycle based playback
        if ($campaign->type === 'list') {
            $campaign->cyclePlaybackEnabled = $sanitizedParams->getCheckbox('cyclePlaybackEnabled');
            $campaign->playCount = ($campaign->cyclePlaybackEnabled) ? $sanitizedParams->getInt('playCount') : null;

            // For compatibility with existing API implementations we set a default here.
            $campaign->listPlayOrder = ($campaign->cyclePlaybackEnabled)
                    ? 'block'
                    : $sanitizedParams->getString('listPlayOrder', ['default' => 'round']);
        } else if ($campaign->type === 'ad') {
            $campaign->targetType = $sanitizedParams->getString('targetType');
            $campaign->target = $sanitizedParams->getInt('target');
            $campaign->listPlayOrder = 'round';
        }

        // Assign layouts?
        foreach ($sanitizedParams->getIntArray('layoutIds', ['default' => []]) as $layoutId) {
            // Can't assign layouts to an ad campaign during creation
            if ($campaign->type === 'ad') {
                throw new InvalidArgumentException(
                    __('Cannot assign layouts to an ad campaign during its creation'),
                    'layoutIds'
                );
            }

            // Check permissions.
            $layout = $this->layoutFactory->getById($layoutId);

            if (!$this->getUser()->checkViewable($layout)) {
                throw new AccessDeniedException(__('You do not have permission to assign this Layout'));
            }

            // Make sure we can assign this layout
            $this->checkLayoutAssignable($layout);

            // Assign.
            $campaign->assignLayout($layout->layoutId);
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
        foreach ($campaign->loadLayouts() as $layout) {
            // TODO: more efficient way than loading an entire layout just to check permissions?
            if (!$this->getUser()->checkViewable($this->layoutFactory->getById($layout->layoutId))) {
                // Hide all layout details from the user
                $layout->layout = __('Layout');
                $layout->setUnmatchedProperty('locked', true);
            } else {
                $layout->setUnmatchedProperty('locked', false);
            }
            $layouts[] = $layout;
        }

        $this->getState()->template = 'campaign-form-edit';
        $this->getState()->setData([
            'campaign' => $campaign,
            'layouts' => $layouts,
        ]);

        return $this->render($request, $response);
    }

    #[OA\Put(
        path: '/campaign/{campaignId}',
        operationId: 'campaignEdit',
        description: 'Edit an existing Campaign',
        summary: 'Edit Campaign',
        tags: ['campaign']
    )]
    #[OA\Parameter(
        name: 'campaignId',
        description: 'The Campaign ID to Edit',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', description: 'Name for this Campaign', type: 'string'),
                    new OA\Property(
                        property: 'folderId',
                        description: 'Folder ID to which this object should be assigned to',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'manageLayouts',
                        description: 'Flag indicating whether to manage layouts or not. Default to no.',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'layoutIds',
                        description: 'An array of layoutIds to assign to this Campaign, in order.',
                        type: 'array',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(
                        property: 'cyclePlaybackEnabled',
                        description: 'When cycle based playback is enabled only 1 Layout from this Campaign will be played each time it is in a Schedule loop. The same Layout will be shown until the \'Play count\' is achieved.', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'playCount',
                        description: 'In cycle based playback, how many plays should each Layout have before moving on?', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'listPlayOrder',
                        description: 'In layout list, how should campaigns in the schedule with the same play order be played?', // phpcs:ignore
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'targetType',
                        description: 'For ad campaigns, how do we measure the target? plays|budget|imp',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'target',
                        description: 'For ad campaigns, what is the target count for playback over the entire campaign',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'startDt',
                        description: 'For ad campaigns, what is the start date',
                        type: 'string',
                        format: 'date-time'
                    ),
                    new OA\Property(
                        property: 'endDt',
                        description: 'For ad campaigns, what is the start date',
                        type: 'string',
                        format: 'date-time'
                    ),
                    new OA\Property(
                        property: 'displayGroupIds[]',
                        description: 'For ad campaigns, which display groups should the campaign be run on?',
                        type: 'array',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(property: 'ref1', description: 'An optional reference field', type: 'string'),
                    new OA\Property(property: 'ref2', description: 'An optional reference field', type: 'string'),
                    new OA\Property(property: 'ref3', description: 'An optional reference field', type: 'string'),
                    new OA\Property(property: 'ref4', description: 'An optional reference field', type: 'string'),
                    new OA\Property(property: 'ref5', description: 'An optional reference field', type: 'string')
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/Campaign')
    )]
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
        $campaign->modifiedBy = $this->getUser()->getId();

        if ($campaign->hasPropertyChanged('folderId')) {
            if ($campaign->folderId === 1) {
                $this->checkRootFolderAllowSave();
            }
            $folder = $this->folderFactory->getById($campaign->folderId);
            $campaign->permissionsFolderId = $folder->getPermissionFolderIdOrThis();
        }

        // Reference fields
        $campaign->ref1 = $parsedRequestParams->getString('ref1');
        $campaign->ref2 = $parsedRequestParams->getString('ref2');
        $campaign->ref3 = $parsedRequestParams->getString('ref3');
        $campaign->ref4 = $parsedRequestParams->getString('ref4');
        $campaign->ref5 = $parsedRequestParams->getString('ref5');

        // What type of campaign are we editing?
        if ($campaign->type === 'ad') {
            // Ad campaign
            // -----------
            $campaign->startDt = $parsedRequestParams->getDate('startDt')?->format('U');
            $campaign->endDt = $parsedRequestParams->getDate('endDt')?->format('U');
            $campaign->targetType = $parsedRequestParams->getString('targetType');
            $campaign->target = $parsedRequestParams->getInt('target');

            // Display groups
            $displayGroupIds = [];
            foreach ($parsedRequestParams->getIntArray('displayGroupIds', ['default' => []]) as $displayGroupId) {
                $displayGroup = $this->displayGroupFactory->getById($displayGroupId);
                if (!$this->getUser()->checkViewable($displayGroup)) {
                    throw new AccessDeniedException();
                }
                $displayGroupIds[] = $displayGroup->displayGroupId;
            }

            $campaign->replaceDisplayGroupIds($displayGroupIds);
        } else {
            // Cycle based playback
            $campaign->cyclePlaybackEnabled = $parsedRequestParams->getCheckbox('cyclePlaybackEnabled');
            $campaign->playCount = $campaign->cyclePlaybackEnabled ? $parsedRequestParams->getInt('playCount') : null;

            // For compatibility with existing API implementations we keep the current value as default if not provided
            $campaign->listPlayOrder = ($campaign->cyclePlaybackEnabled)
                ? 'block'
                : $parsedRequestParams->getString('listPlayOrder', ['default' => $campaign->listPlayOrder]);

            // Assign layouts?
            if ($parsedRequestParams->getCheckbox('manageLayouts') === 1) {
                // Fully decorate our Campaign
                $campaign->loadLayouts();

                // Remove all we've currently got assigned, keeping track of them for sharing check
                $originalLayoutAssignments = array_map(function ($element) {
                    return $element->layoutId;
                }, $campaign->loadLayouts());

                $campaign->unassignAllLayouts();

                foreach ($parsedRequestParams->getIntArray('layoutIds', ['default' => []]) as $layoutId) {
                    // Check permissions.
                    $layout = $this->layoutFactory->getById($layoutId);

                    if (!$this->getUser()->checkViewable($layout) && !in_array($layoutId, $originalLayoutAssignments)) {
                        throw new AccessDeniedException(
                            __('You are trying to assign a Layout that is not shared with you.')
                        );
                    }

                    // Make sure we can assign this layout
                    $this->checkLayoutAssignable($layout);

                    // Assign.
                    $campaign->assignLayout($layout->layoutId);
                }
            }
        }

        // Tags
        // ----
        if ($this->getUser()->featureEnabled('tag.tagging')) {
            if (is_array($parsedRequestParams->getParam('tags'))) {
                $tags = $this->tagFactory->tagsFromJson($parsedRequestParams->getArray('tags'));
            } else {
                $tags = $this->tagFactory->tagsFromString($parsedRequestParams->getString('tags'));
            }

            $campaign->updateTagLinks($tags);
        }

        // Save the campaign.
        $campaign->save();

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

        if (!$this->getUser()->checkDeleteable($campaign)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'campaign-form-delete';
        $this->getState()->setData([
            'campaign' => $campaign,
        ]);

        return $this->render($request, $response);
    }

    #[OA\Delete(
        path: '/campaign/{campaignId}',
        operationId: 'campaignDelete',
        description: 'Delete an existing Campaign',
        summary: 'Delete Campaign',
        tags: ['campaign']
    )]
    #[OA\Parameter(
        name: 'campaignId',
        description: 'The Campaign ID to Delete',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
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
     */
    public function delete(Request $request, Response $response, $id)
    {
        $campaign = $this->campaignFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($campaign)) {
            throw new AccessDeniedException();
        }

        $campaign->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $campaign->campaign)
        ]);

        return $this->render($request, $response);
    }

    #[OA\Post(
        path: '/campaign/layout/assign/{campaignId}',
        operationId: 'campaignAssignLayout',
        description: 'Assign a Layout to a Campaign. Please note that as of v3.0.0 this API no longer accepts multiple layoutIds.', // phpcs:ignore
        summary: 'Assign Layout',
        tags: ['campaign']
    )]
    #[OA\Parameter(
        name: 'campaignId',
        description: 'The Campaign ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['layoutId'],
                properties: [
                    new OA\Property(
                        property: 'layoutId',
                        description: 'Layout ID to Assign: Please note that as of v3.0.0 this API no longer accepts multiple layoutIds.', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'daysOfWeek[]',
                        description: 'Ad campaigns: restrict this to certain days of the week (iso week)',
                        type: 'array',
                        items: new OA\Items(type: 'integer')
                    ),
                    new OA\Property(
                        property: 'dayPartId',
                        description: 'Ad campaigns: restrict this to a day part',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'geoFence',
                        description: 'Ad campaigns: restrict this to a geofence',
                        type: 'string'
                    )
                ]
            )
        )
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
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
     */
    public function assignLayout(Request $request, Response $response, $id)
    {
        $this->getLog()->debug('assignLayout with campaignId ' . $id);

        $campaign = $this->campaignFactory->getById($id);
        if (!$this->getUser()->checkEditable($campaign)) {
            throw new AccessDeniedException();
        }

        // Make sure this is a non-layout specific campaign
        if ($campaign->isLayoutSpecific == 1) {
            throw new InvalidArgumentException(
                __('You cannot change the assignment of a Layout Specific Campaign'),
                'campaignId'
            );
        }

        // Load our existing layouts
        $campaign->loadLayouts();

        // Get the layout we want to add
        $params = $this->getSanitizer($request->getParams());
        $layout = $this->layoutFactory->getById(
            $params->getInt('layoutId', [
                'throw' => function () {
                    throw new InvalidArgumentException(__('Please select a Layout to assign.'), 'layoutId');
                }
            ])
        );

        if (!$this->getUser()->checkViewable($layout)) {
            throw new AccessDeniedException(__('You do not have permission to assign the provided Layout'));
        }

        // Make sure we can assign this layout
        $this->checkLayoutAssignable($layout);

        // If we are an ad campaign, then expect some other parameters.
        $daysOfWeek = $params->getIntArray('daysOfWeek');
        $daysOfWeek = (empty($daysOfWeek)) ? null : implode(',', $daysOfWeek);

        // Assign to the campaign
        $campaign->assignLayout(
            $layout->layoutId,
            null,
            $params->getInt('dayPartId'),
            $daysOfWeek,
            $params->getString('geoFence')
        );
        $campaign->save(['validate' => false, 'saveTags' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Assigned Layouts to %s'), $campaign->campaign)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Remove Layout Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function removeLayoutForm(Request $request, Response $response, $id)
    {
        $this->getLog()->debug('removeLayoutForm: ' . $id);

        $campaign = $this->campaignFactory->getById($id);
        if (!$this->getUser()->checkEditable($campaign)) {
            throw new AccessDeniedException();
        }
        $campaign->loadLayouts();

        $this->getState()->template = 'campaign-form-layout-delete';
        $this->getState()->setData([
            'campaign' => $campaign,
            'layout' => $campaign->getLayoutAt($this->getSanitizer($request->getParams())->getInt('displayOrder')),
        ]);

        return $this->render($request, $response);
    }

    #[OA\Delete(
        path: '/campaign/layout/remove/{campaignId}',
        operationId: 'campaignRemoveLayout',
        description: 'Remove a Layout from a Campaign.',
        summary: 'Remove Layout',
        tags: ['campaign']
    )]
    #[OA\Parameter(
        name: 'campaignId',
        description: 'The Campaign ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['layoutId'],
                properties: [
                    new OA\Property(property: 'layoutId', description: 'Layout ID to remove', type: 'integer'),
                    new OA\Property(
                        property: 'displayOrder',
                        description: 'The display order. Omit to remove all occurences of the layout',
                        type: 'integer'
                    )
                ]
            )
        )
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * Remove a layout from a Campaign
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function removeLayout(Request $request, Response $response, $id)
    {
        $this->getLog()->debug('removeLayout with campaignId ' . $id);

        $campaign = $this->campaignFactory->getById($id);
        if (!$this->getUser()->checkEditable($campaign)) {
            throw new AccessDeniedException();
        }

        // Make sure this is a non-layout specific campaign
        if ($campaign->isLayoutSpecific == 1) {
            throw new InvalidArgumentException(
                __('You cannot change the assignment of a Layout Specific Campaign'),
                'campaignId'
            );
        }

        $params = $this->getSanitizer($request->getParams());
        $layoutId = $params->getInt('layoutId', [
            'throw' => function () {
                throw new InvalidArgumentException(__('Please provide a layout'), 'layoutId');
            },
            ['rules' => ['notEmpty']],
        ]);
        $displayOrder = $params->getInt('displayOrder');

        // Load our existing layouts
        $campaign->loadLayouts();

        $campaign->unassignLayout($layoutId, $displayOrder);
        $campaign->save(['validate' => false]);

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

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $campaign->ownerId) {
            throw new AccessDeniedException(__('You do not have permission to copy this Campaign'));
        }

        $newCampaign = clone $campaign;
        $newCampaign->campaign = $sanitizedParams->getString('name');

        // assign the same layouts to the new Campaign
        foreach ($campaign->loadLayouts() as $layout) {
            $newCampaign->assignLayout(
                $layout->layoutId,
                $layout->displayOrder,
                $layout->dayPartId,
                $layout->daysOfWeek,
                $layout->geoFence
            );
        }

        $newCampaign->updateTagLinks($this->tagFactory->tagsFromString($campaign->getTagString()));

        // is the original campaign an ad campaign?
        if ($campaign->type === 'ad') {
            // assign the same displays to the new Campaign
            $newCampaign->replaceDisplayGroupIds($campaign->loadDisplayGroupIds());
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

    #[OA\Put(
        path: '/campaign/{id}/selectfolder',
        operationId: 'campaignSelectFolder',
        description: 'Select Folder for Campaign, can also be used with Layout specific Campaign ID', // phpcs:ignore
        summary: 'Campaign Select folder',
        tags: ['campaign']
    )]
    #[OA\Parameter(
        name: 'campaignId',
        description: 'The Campaign ID or Layout specific Campaign ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(
                        property: 'folderId',
                        description: 'Folder ID to which this object should be assigned to',
                        type: 'integer'
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/Campaign')
    )]
    /**
     * Select Folder
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

        if ($folderId === 1) {
            $this->checkRootFolderAllowSave();
        }

        $campaign->folderId = $folderId;
        $folder = $this->folderFactory->getById($campaign->folderId);
        $campaign->permissionsFolderId = ($folder->getPermissionFolderId() == null)
            ? $folder->id
            : $folder->getPermissionFolderId();

        if ($campaign->isLayoutSpecific === 1) {
            $layouts = $this->layoutFactory->getByCampaignId($campaign->campaignId, true, true);

            foreach ($layouts as $layout) {
                $layout->load();
                $allRegions = array_merge($layout->regions, $layout->drawers);

                foreach ($allRegions as $region) {
                    $playlist = $region->getPlaylist();
                    $playlist->folderId = $campaign->folderId;
                    $playlist->permissionsFolderId = $campaign->permissionsFolderId;
                    $playlist->save();
                }
            }
        }

        // Save
        $campaign->save([
            'validate' => false,
            'notify' => false,
            'collectNow' => false,
            'saveTags' => false
        ]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Layout %s moved to Folder %s'), $campaign->campaign, $folder->text)
        ]);

        return $this->render($request, $response);
    }

    /**
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    private function checkLayoutAssignable(\Xibo\Entity\Layout $layout)
    {
        // Make sure we're not a draft
        if ($layout->isChild()) {
            throw new InvalidArgumentException(__('Cannot assign a Draft Layout to a Campaign'), 'layoutId');
        }

        // Make sure this layout is not a template - for API, in web ui templates are not available for assignment
        if ($layout->isTemplate()) {
            throw new InvalidArgumentException(__('Cannot assign a Template to a Campaign'), 'layoutId');
        }
    }
}
