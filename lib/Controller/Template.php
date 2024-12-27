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

use Parsedown;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\SearchResult;
use Xibo\Entity\SearchResults;
use Xibo\Event\TemplateProviderEvent;
use Xibo\Event\TemplateProviderListEvent;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\TagFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Template
 * @package Xibo\Controller
 */
class Template extends Base
{
    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var TagFactory
     */
    private $tagFactory;

    /**
     * @var \Xibo\Factory\ResolutionFactory
     */
    private $resolutionFactory;

    /**
     * Set common dependencies.
     * @param LayoutFactory $layoutFactory
     * @param TagFactory $tagFactory
     * @param \Xibo\Factory\ResolutionFactory $resolutionFactory
     */
    public function __construct($layoutFactory, $tagFactory, $resolutionFactory)
    {
        $this->layoutFactory = $layoutFactory;
        $this->tagFactory = $tagFactory;
        $this->resolutionFactory = $resolutionFactory;
    }

    /**
     * Display page logic
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    function displayPage(Request $request, Response $response)
    {
        // Call to render the template
        $this->getState()->template = 'template-page';

        return $this->render($request, $response);
    }

    /**
     * Data grid
     *
     * @SWG\Get(
     *  path="/template",
     *  operationId="templateSearch",
     *  tags={"template"},
     *  summary="Template Search",
     *  description="Search templates this user has access to",
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Layout")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function grid(Request $request, Response $response)
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());
        // Embed?
        $embed = ($sanitizedQueryParams->getString('embed') != null)
            ? explode(',', $sanitizedQueryParams->getString('embed'))
            : [];

        $templates = $this->layoutFactory->query($this->gridRenderSort($sanitizedQueryParams), $this->gridRenderFilter([
            'excludeTemplates' => 0,
            'tags' => $sanitizedQueryParams->getString('tags'),
            'layoutId' => $sanitizedQueryParams->getInt('templateId'),
            'layout' => $sanitizedQueryParams->getString('template'),
            'useRegexForName' => $sanitizedQueryParams->getCheckbox('useRegexForName'),
            'folderId' => $sanitizedQueryParams->getInt('folderId'),
            'logicalOperator' => $sanitizedQueryParams->getString('logicalOperator'),
            'logicalOperatorName' => $sanitizedQueryParams->getString('logicalOperatorName'),
        ], $sanitizedQueryParams));

        foreach ($templates as $template) {
            /* @var \Xibo\Entity\Layout $template */

            if (in_array('regions', $embed)) {
                $template->load([
                    'loadPlaylists' => in_array('playlists', $embed),
                    'loadCampaigns' => in_array('campaigns', $embed),
                    'loadPermissions' => in_array('permissions', $embed),
                    'loadTags' => in_array('tags', $embed),
                    'loadWidgets' => in_array('widgets', $embed)
                ]);
            }

            if ($this->isApi($request)) {
                continue;
            }

            $template->includeProperty('buttons');

            // Thumbnail
            $template->setUnmatchedProperty('thumbnail', '');
            if (file_exists($template->getThumbnailUri())) {
                $template->setUnmatchedProperty(
                    'thumbnail',
                    $this->urlFor($request, 'layout.download.thumbnail', ['id' => $template->layoutId])
                );
            }

            // Parse down for description
            $template->setUnmatchedProperty(
                'descriptionWithMarkup',
                Parsedown::instance()->setSafeMode(true)->text($template->description),
            );

            if ($this->getUser()->featureEnabled('template.modify')
                && $this->getUser()->checkEditable($template)
            ) {
                // Design Button
                $template->buttons[] = [
                    'id' => 'layout_button_design',
                    'linkType' => '_self', 'external' => true,
                    'url' => $this->urlFor(
                        $request,
                        'layout.designer',
                        ['id' => $template->layoutId]
                    ) . '?isTemplateEditor=1',
                    'text' => __('Alter Template')
                ];

                if ($template->isEditable()) {
                    $template->buttons[] = ['divider' => true];

                    $template->buttons[] = array(
                        'id' => 'layout_button_publish',
                        'url' => $this->urlFor($request, 'layout.publish.form', ['id' => $template->layoutId]),
                        'text' => __('Publish')
                    );

                    $template->buttons[] = array(
                        'id' => 'layout_button_discard',
                        'url' => $this->urlFor($request, 'layout.discard.form', ['id' => $template->layoutId]),
                        'text' => __('Discard')
                    );

                    $template->buttons[] = ['divider' => true];
                } else {
                    $template->buttons[] = ['divider' => true];

                    // Checkout Button
                    $template->buttons[] = array(
                        'id' => 'layout_button_checkout',
                        'url' => $this->urlFor($request, 'layout.checkout.form', ['id' => $template->layoutId]),
                        'text' => __('Checkout'),
                        'dataAttributes' => [
                            ['name' => 'auto-submit', 'value' => true],
                            [
                                'name' => 'commit-url',
                                'value' => $this->urlFor(
                                    $request,
                                    'layout.checkout',
                                    ['id' => $template->layoutId]
                                )
                            ],
                            ['name' => 'commit-method', 'value' => 'PUT']
                        ]
                    );

                    $template->buttons[] = ['divider' => true];
                }

                // Edit Button
                $template->buttons[] = array(
                    'id' => 'layout_button_edit',
                    'url' => $this->urlFor($request, 'template.edit.form', ['id' => $template->layoutId]),
                    'text' => __('Edit')
                );

                // Select Folder
                if ($this->getUser()->featureEnabled('folder.view')) {
                    $template->buttons[] = [
                        'id' => 'campaign_button_selectfolder',
                        'url' => $this->urlFor($request, 'campaign.selectfolder.form', ['id' => $template->campaignId]),
                        'text' => __('Select Folder'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            [
                                'name' => 'commit-url',
                                'value' => $this->urlFor(
                                    $request,
                                    'campaign.selectfolder',
                                    ['id' => $template->campaignId]
                                )
                            ],
                            ['name' => 'commit-method', 'value' => 'put'],
                            ['name' => 'id', 'value' => 'campaign_button_selectfolder'],
                            ['name' => 'text', 'value' => __('Move to Folder')],
                            ['name' => 'rowtitle', 'value' => $template->layout],
                            ['name' => 'form-callback', 'value' => 'moveFolderMultiSelectFormOpen']
                        ]
                    ];
                }

                // Copy Button
                $template->buttons[] = array(
                    'id' => 'layout_button_copy',
                    'url' => $this->urlFor($request, 'layout.copy.form', ['id' => $template->layoutId]),
                    'text' => __('Copy')
                );
            }

            // Extra buttons if have delete permissions
            if ($this->getUser()->featureEnabled('template.modify')
                && $this->getUser()->checkDeleteable($template)) {
                // Delete Button
                $template->buttons[] = [
                    'id' => 'layout_button_delete',
                    'url' => $this->urlFor($request, 'layout.delete.form', ['id' => $template->layoutId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor(
                                $request,
                                'layout.delete',
                                ['id' => $template->layoutId]
                            )
                        ],
                        ['name' => 'commit-method', 'value' => 'delete'],
                        ['name' => 'id', 'value' => 'layout_button_delete'],
                        ['name' => 'text', 'value' => __('Delete')],
                        ['name' => 'sort-group', 'value' => 1],
                        ['name' => 'rowtitle', 'value' => $template->layout]
                    ]
                ];
            }

            $template->buttons[] = ['divider' => true];

            // Extra buttons if we have modify permissions
            if ($this->getUser()->featureEnabled('template.modify')
                && $this->getUser()->checkPermissionsModifyable($template)) {
                // Permissions button
                $template->buttons[] = [
                    'id' => 'layout_button_permissions',
                    'url' => $this->urlFor(
                        $request,
                        'user.permissions.form',
                        ['entity' => 'Campaign', 'id' => $template->campaignId]
                    ) . '?nameOverride=' . __('Template'),
                    'text' => __('Share'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        [
                            'name' => 'commit-url',
                            'value' => $this->urlFor(
                                $request,
                                'user.permissions.multi',
                                ['entity' => 'Campaign', 'id' => $template->campaignId]
                            )
                        ],
                        ['name' => 'commit-method', 'value' => 'post'],
                        ['name' => 'id', 'value' => 'layout_button_permissions'],
                        ['name' => 'text', 'value' => __('Share')],
                        ['name' => 'rowtitle', 'value' => $template->layout],
                        ['name' => 'sort-group', 'value' => 2],
                        ['name' => 'custom-handler', 'value' => 'XiboMultiSelectPermissionsFormOpen'],
                        [
                            'name' => 'custom-handler-url',
                            'value' => $this->urlFor(
                                $request,
                                'user.permissions.multi.form',
                                ['entity' => 'Campaign']
                            )
                        ],
                        ['name' => 'content-id-name', 'value' => 'campaignId']
                    ]
                ];
            }

            if ($this->getUser()->featureEnabled('layout.export')) {
                $template->buttons[] = ['divider' => true];

                // Export Button
                $template->buttons[] = array(
                    'id' => 'layout_button_export',
                    'linkType' => '_self',
                    'external' => true,
                    'url' => $this->urlFor($request, 'layout.export', ['id' => $template->layoutId]),
                    'text' => __('Export')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->layoutFactory->countLast();
        $this->getState()->setData($templates);

        return $this->render($request, $response);
    }

    /**
     * Data grid
     *
     * @SWG\Get(
     *  path="/template/search",
     *  operationId="templateSearchAll",
     *  tags={"template"},
     *  summary="Template Search All",
     *  description="Search all templates from local and connectors",
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/SearchResult")
     *      )
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function search(Request $request, Response $response)
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());
        $provider = $sanitizedQueryParams->getString('provider', ['default' => 'both']);

        $searchResults = new SearchResults();
        if ($provider === 'both' || $provider === 'local') {
            $templates = $this->layoutFactory->query(['layout'], $this->gridRenderFilter([
                'excludeTemplates' => 0,
                'layout' => $sanitizedQueryParams->getString('template'),
                'folderId' => $sanitizedQueryParams->getInt('folderId'),
                'orientation' => $sanitizedQueryParams->getString('orientation', ['defaultOnEmptyString' => true]),
                'publishedStatusId' => 1
            ], $sanitizedQueryParams));

            foreach ($templates as $template) {
                $searchResult = new SearchResult();
                $searchResult->id = $template->layoutId;
                $searchResult->source = 'local';
                $searchResult->title = $template->layout;

                // Handle the description
                $searchResult->description = '';
                if (!empty($template->description)) {
                    $searchResult->description = Parsedown::instance()->setSafeMode(true)->line($template->description);
                }
                $searchResult->orientation = $template->orientation;
                $searchResult->width = $template->width;
                $searchResult->height = $template->height;

                if (!empty($template->tags)) {
                    foreach ($template->getTags() as $tag) {
                        if ($tag->tag === 'template') {
                            continue;
                        }
                        $searchResult->tags[] = $tag->tag;
                    }
                }

                // Thumbnail
                $searchResult->thumbnail = '';
                if (file_exists($template->getThumbnailUri())) {
                    $searchResult->thumbnail = $this->urlFor(
                        $request,
                        'layout.download.thumbnail',
                        ['id' => $template->layoutId]
                    );
                }

                $searchResults->data[] = $searchResult;
            }
        }

        if ($provider === 'both' || $provider === 'remote') {
            // Hand off to any other providers that may want to provide results.
            $event = new TemplateProviderEvent(
                $searchResults,
                $sanitizedQueryParams->getInt('start', ['default' => 0]),
                $sanitizedQueryParams->getInt('length', ['default' => 15]),
                $sanitizedQueryParams->getString('template'),
                $sanitizedQueryParams->getString('orientation'),
            );

            $this->getLog()->debug('Dispatching event. ' . $event->getName());
            try {
                $this->getDispatcher()->dispatch($event, $event->getName());
            } catch (\Exception $exception) {
                $this->getLog()->error('Template search: Exception in dispatched event: ' . $exception->getMessage());
                $this->getLog()->debug($exception->getTraceAsString());
            }
        }
        return $response->withJson($searchResults);
    }

    /**
     * Template Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    function addTemplateForm(Request $request, Response $response, $id)
    {
        // Get the layout
        $layout = $this->layoutFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkViewable($layout)) {
            throw new AccessDeniedException(__('You do not have permissions to view this layout'));
        }

        $this->getState()->template = 'template-form-add-from-layout';
        $this->getState()->setData([
            'layout' => $layout,
        ]);

        return $this->render($request, $response);
    }
    /**
     * Add a Template
     * @SWG\Post(
     *  path="/template",
     *  operationId="templateAdd",
     *  tags={"template"},
     *  summary="Add a Template",
     *  description="Add a new Template to the CMS",
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
        $resolutionId = $sanitizedParams->getInt('resolutionId');
        $enableStat = $sanitizedParams->getCheckbox('enableStat');
        $autoApplyTransitions = $sanitizedParams->getCheckbox('autoApplyTransitions');
        $folderId = $sanitizedParams->getInt('folderId');

        if ($folderId === 1) {
            $this->checkRootFolderAllowSave();
        }

        if (empty($folderId) || !$this->getUser()->featureEnabled('folder.view')) {
            $folderId = $this->getUser()->homeFolderId;
        }

        // Tags
        if ($this->getUser()->featureEnabled('tag.tagging')) {
            $tags = $this->tagFactory->tagsFromString($sanitizedParams->getString('tags'));
        } else {
            $tags = [];
        }
        $tags[] = $this->tagFactory->tagFromString('template');

        $layout = $this->layoutFactory->createFromResolution($resolutionId,
            $this->getUser()->userId,
            $name,
            $description,
            $tags,
            null
        );

        // Set layout enableStat flag
        $layout->enableStat = $enableStat;

        // Set auto apply transitions flag
        $layout->autoApplyTransitions = $autoApplyTransitions;

        // Set folderId
        $layout->folderId = $folderId;

        // Save
        $layout->save();

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
     * Add template
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\NotFoundException
     * @SWG\Post(
     *  path="/template/{layoutId}",
     *  operationId="template.add.from.layout",
     *  tags={"template"},
     *  summary="Add a template from a Layout",
     *  description="Use the provided layout as a base for a new template",
     *  @SWG\Parameter(
     *      name="layoutId",
     *      in="path",
     *      description="The Layout ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="includeWidgets",
     *      in="formData",
     *      description="Flag indicating whether to include the widgets in the Template",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="The Template Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="tags",
     *      in="formData",
     *      description="Comma separated list of Tags for the template",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="A description of the Template",
     *      type="string",
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
     */
    function addFromLayout(Request $request, Response $response, $id)
    {
        // Get the layout
        $layout = $this->layoutFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkViewable($layout)) {
            throw new AccessDeniedException(__('You do not have permissions to view this layout'));
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());
        // Should the copy include the widgets
        $includeWidgets = ($sanitizedParams->getCheckbox('includeWidgets') == 1);

        // Load without anything
        $layout->load([
            'loadPlaylists' => true,
            'loadWidgets' => $includeWidgets,
            'playlistIncludeRegionAssignments' => false,
            'loadTags' => false,
            'loadPermissions' => false,
            'loadCampaigns' => false
        ]);
        $originalLayout = $layout;

        $layout = clone $layout;

        $layout->layout = $sanitizedParams->getString('name');
        if ($this->getUser()->featureEnabled('tag.tagging')) {
            $layout->updateTagLinks($this->tagFactory->tagsFromString($sanitizedParams->getString('tags')));
        } else {
            $layout->tags = [];
        }
        $layout->assignTag($this->tagFactory->tagFromString('template'));

        $layout->description = $sanitizedParams->getString('description');
        $layout->folderId = $sanitizedParams->getInt('folderId');

        if ($layout->folderId === 1) {
            $this->checkRootFolderAllowSave();
        }

        $layout->setOwner($this->getUser()->userId, true);
        $layout->save();

        if ($includeWidgets) {
            // Sub-Playlist
            foreach ($layout->regions as $region) {
                // Match our original region id to the id in the parent layout
                $original = $originalLayout->getRegion($region->getOriginalValue('regionId'));

                // Make sure Playlist closure table from the published one are copied over
                $original->getPlaylist()->cloneClosureTable($region->getPlaylist()->playlistId);
            }
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Saved %s'), $layout->layout),
            'id' => $layout->layoutId,
            'data' => $layout
        ]);

        return $this->render($request, $response);
    }

    /**
     * Displays an Add/Edit form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    function addForm(Request $request, Response $response)
    {
        $this->getState()->template = 'template-form-add';
        $this->getState()->setData([
            'resolutions' => $this->resolutionFactory->query(['resolution']),
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
    public function editForm(Request $request, Response $response, $id)
    {
        // Get the layout
        $template = $this->layoutFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($template)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'template-form-edit';
        $this->getState()->setData([
            'layout' => $template,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Get list of Template providers with their details.
     *
     * @param Request $request
     * @param Response $response
     * @return Response|ResponseInterface
     */
    public function providersList(Request $request, Response $response): Response|\Psr\Http\Message\ResponseInterface
    {
        $event = new TemplateProviderListEvent();
        $this->getDispatcher()->dispatch($event, $event->getName());

        $providers = $event->getProviders();

        return $response->withJson($providers);
    }
}
