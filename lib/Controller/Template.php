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

    #[OA\Get(
        path: '/template',
        operationId: 'templateSearch',
        description: 'Search templates this user has access to',
        summary: 'Template Search',
        tags: ['template']
    )]
    #[OA\Parameter(
        name: 'embed',
        description: 'Embed related data such as regions, playlists, widgets, tags, campaigns, permissions',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'keyword',
        description: 'Filter by template name, ID, or description',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Specifies which field the results are sorted by. Used together with sortDir',
        in: 'query',
        required: false,
        schema: new OA\Schema(
            type: 'string',
            enum: [
                'layout',
                'owner',
                'publishedStatus',
                'modifiedDt',
                'orientation',
            ]
        )
    )]
    #[OA\Parameter(
        name: 'sortDir',
        description: 'Sort direction',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Layout')
        )
    )]
    /**
     * Data grid
     *
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
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

        $templateSortQuery = $this->gridRenderSort(
            $sanitizedQueryParams,
            $this->isJson($request),
            'layout'
        );

        $templateFilterQuery = $this->getTemplateFilterQuery($sanitizedQueryParams);

        $templates = $this->layoutFactory->query($templateSortQuery, $templateFilterQuery);

        foreach ($templates as $template) {
            $this->loadTemplateRegions($template, $embed);
            $this->decorateTemplateProperties($request, $template);
        }

        $recordsTotal = $this->layoutFactory->countLast();

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $recordsTotal)
            ->withJson($templates);
    }

    #[OA\Get(
        path: '/template/{templateId}',
        operationId: 'templateSearchById',
        description: 'Get the Template object specified by the provided templateId',
        summary: 'Template Search by ID',
        tags: ['template']
    )]
    #[OA\Parameter(
        name: 'templateId',
        description: 'Numeric ID of the Template to get',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'embed',
        description: 'Embed related data such as regions, playlists, widgets, tags, campaigns, permissions',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/Layout')
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return Response|ResponseInterface
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function searchById(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $template = $this->layoutFactory->getById($id, false);

        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());

        // Embed?
        $embed = ($sanitizedQueryParams->getString('embed') != null)
            ? explode(',', $sanitizedQueryParams->getString('embed'))
            : [];

        $this->loadTemplateRegions($template, $embed);
        $this->decorateTemplateProperties($request, $template);

        return $response
            ->withStatus(200)
            ->withJson($template);
    }

    #[OA\Get(
        path: '/template/search',
        operationId: 'templateSearchAll',
        description: 'Search all templates from local and connectors',
        summary: 'Template Search All',
        tags: ['template']
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/SearchResult')
        )
    )]
    /**
     * Data grid
     *
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
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
     * @return ResponseInterface|Response
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

    #[OA\Post(
        path: '/template',
        operationId: 'templateAdd',
        description: 'Add a new Template to the CMS',
        summary: 'Add a Template',
        tags: ['template']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', description: 'The layout name', type: 'string'),
                    new OA\Property(property: 'description', description: 'The layout description', type: 'string'),
                    new OA\Property(
                        property: 'resolutionId',
                        description: 'If a Template is not provided, provide the resolutionId for this Layout.',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'returnDraft',
                        description: 'Should we return the Draft Layout or the Published Layout on Success?',
                        type: 'boolean'
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
        content: new OA\JsonContent(ref: '#/components/schemas/Layout')
    )]
    /**
     * Add a Template
     *
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
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

    #[OA\Post(
        path: '/template/{layoutId}',
        operationId: 'template.add.from.layout',
        description: 'Use the provided layout as a base for a new template',
        summary: 'Add a template from a Layout',
        tags: ['template']
    )]
    #[OA\Parameter(
        name: 'layoutId',
        description: 'The Layout ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['includeWidgets', 'name'],
                properties: [
                    new OA\Property(
                        property: 'includeWidgets',
                        description: 'Flag indicating whether to include the widgets in the Template',
                        type: 'integer'
                    ),
                    new OA\Property(property: 'name', description: 'The Template Name', type: 'string'),
                    new OA\Property(
                        property: 'tags',
                        description: 'Comma separated list of Tags for the template',
                        type: 'string'
                    ),
                    new OA\Property(property: 'description', description: 'A description of the Template', type: 'string')
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
        content: new OA\JsonContent(ref: '#/components/schemas/Layout')
    )]
    /**
     * Add template
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function addFromLayout(Request $request, Response $response, $id): Response
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

        // When saving a layout as a template, we should not include the empty canva region as that requires
        // a widget to be inside it.
        // https://github.com/xibosignage/xibo/issues/3574
        if (!$includeWidgets) {
            $this->getLog()->debug('addFromLayout: widgets have not been included, checking for empty regions');

            $regionsWithWidgets = [];
            foreach ($layout->regions as $region) {
                if ($region->type === 'canvas') {
                    $this->getLog()->debug('addFromLayout: Canvas region excluded from export');
                } else {
                    $regionsWithWidgets[] = $region;
                }
            }
            $layout->regions = $regionsWithWidgets;
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
     * Get list of Template providers with their details.
     *
     * @param Request $request
     * @param Response $response
     * @return Response|ResponseInterface
     */
    public function providersList(Request $request, Response $response): Response|ResponseInterface
    {
        $event = new TemplateProviderListEvent();
        $this->getDispatcher()->dispatch($event, $event->getName());

        $providers = $event->getProviders();

        return $response->withJson($providers);
    }

    /**
     * Get the template filters
     * @param $sanitizedQueryParams
     * @return array
     */
    private function getTemplateFilterQuery($sanitizedQueryParams): array
    {
        return $this->gridRenderFilter([
            'excludeTemplates' => 0,
            'keyword' => $sanitizedQueryParams->getString('keyword'),
            'tags' => $sanitizedQueryParams->getString('tags'),
            'layoutId' => $sanitizedQueryParams->getInt('templateId'),
            'layout' => $sanitizedQueryParams->getString('template'),
            'useRegexForName' => $sanitizedQueryParams->getCheckbox('useRegexForName'),
            'folderId' => $sanitizedQueryParams->getInt('folderId'),
            'logicalOperator' => $sanitizedQueryParams->getString('logicalOperator'),
            'logicalOperatorName' => $sanitizedQueryParams->getString('logicalOperatorName'),
        ], $sanitizedQueryParams);
    }

    /**
     * Loads the regions within the layout
     * @param \Xibo\Entity\Layout $layout
     * @param $embed
     * @return void
     * @throws NotFoundException
     */
    private function loadTemplateRegions(\Xibo\Entity\Layout $template, $embed): void
    {
        if (in_array('regions', $embed)) {
            $template->load([
                'loadPlaylists' => in_array('playlists', $embed),
                'loadCampaigns' => in_array('campaigns', $embed),
                'loadPermissions' => in_array('permissions', $embed),
                'loadTags' => in_array('tags', $embed),
                'loadWidgets' => in_array('widgets', $embed)
            ]);
        }
    }

    /**
     * @param Request $request
     * @param \Xibo\Entity\Layout $template
     * @return void
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    private function decorateTemplateProperties(Request $request, \Xibo\Entity\Layout $template): void
    {
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

        $template->setUnmatchedProperty('userPermissions', $this->getUser()->getPermission($template));
    }
}
