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
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Event\DisplayGroupLoadEvent;
use Xibo\Event\TagAddEvent;
use Xibo\Event\TagDeleteEvent;
use Xibo\Event\TagEditEvent;
use Xibo\Event\TriggerTaskEvent;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\TagFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class Tag
 * @package Xibo\Controller
 */
class Tag extends Base
{
    public function __construct(
        private readonly DisplayGroupFactory $displayGroupFactory,
        private readonly LayoutFactory $layoutFactory,
        private readonly TagFactory $tagFactory,
        private readonly MediaFactory $mediaFactory,
        private readonly CampaignFactory $campaignFactory,
        private readonly PlaylistFactory $playlistFactory
    ) {
    }

    #[OA\Get(
        path: '/tag',
        operationId: 'tagSearch',
        description: 'Search for Tags viewable by this user',
        summary: 'Search Tags',
        tags: ['tags']
    )]
    #[OA\Parameter(
        name: 'tagId',
        description: 'Filter by Tag Id',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'keyword',
        description: 'Filter by Tag name and options',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'tag',
        description: 'Filter by partial Tag',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'exactTag',
        description: 'Filter by exact Tag',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'isSystem',
        description: 'Filter by isSystem flag',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'isRequired',
        description: 'Filter by isRequired flag',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'haveOptions',
        description: 'Set to 1 to show only results that have options set',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Specifies which field the results are sorted by. Used together with sortDir',
        in: 'query',
        required: false,
        schema: new OA\Schema(
            type: 'string',
            enum: [
                'tagId',
                'tag',
                'isSystem',
                'options',
                'isRequired',
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
        headers: [
            new OA\Header(
                header: 'X-Total-Count',
                description: 'The total number of records',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Tag')
        )
    )]
    /**
     * Tag Search
     *
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function grid(Request $request, Response $response): Response|ResponseInterface
    {
        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());

        $tags = $this->tagFactory->query(
            $this->gridRenderSort($sanitizedQueryParams, $this->isJson($request)),
            $this->getTagFilters($sanitizedQueryParams)
        );

        if ($this->isJson($request) || $this->isApi($request)) {
            return $response
                ->withStatus(200)
                ->withHeader('X-Total-Count', $this->tagFactory->countLast())
                ->withJson($tags);
        } else {
            // TODO remove once all pages/forms using tags are updated.
            $this->getState()->template = 'grid';
            $this->getState()->recordsTotal = $this->tagFactory->countLast();
            $this->getState()->setData($tags);

            return $this->render($request, $response);
        }
    }

    #[OA\Get(
        path: '/tag/{tagId}',
        operationId: 'TagSearchById',
        description: 'Get the Tag object specified by the provided tagId',
        summary: 'Tag search by ID',
        tags: ['tags']
    )]
    #[OA\Parameter(
        name: 'tagId',
        description: 'Numeric ID of the Tag to get',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/Tag')
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return Response|ResponseInterface
     * @throws NotFoundException
     */
    public function searchById(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $tag = $this->tagFactory->getById($id, 1);

        return $response
            ->withStatus(200)
            ->withJson($tag);
    }

    #[OA\Post(
        path: '/tag',
        operationId: 'tagAdd',
        description: 'Add a new Tag',
        summary: 'Add a new Tag',
        tags: ['tags']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(property: 'name', description: 'Tag name', type: 'string'),
                    new OA\Property(
                        property: 'isRequired',
                        description: 'A flag indicating whether value selection on assignment is required',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'options',
                        description: 'A comma separated string of Tag options',
                        type: 'string'
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'successful operation',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Tag')
        )
    )]
    /**
     * Add a Tag
     *
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function add(Request $request, Response $response): Response|ResponseInterface
    {
        if (!$this->getUser()->isSuperAdmin()) {
            throw new AccessDeniedException();
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());

        $values = [];
        $tag = $this->tagFactory->create($sanitizedParams->getString('name'));
        $tag->options = [];
        $tag->isRequired = $sanitizedParams->getCheckbox('isRequired');
        $optionValues = $sanitizedParams->getString('options');

        if ($optionValues != '') {
            $optionValuesArray = explode(',', $optionValues);
            foreach ($optionValuesArray as $options) {
                $values[] = $options;
            }
            $tag->options = json_encode($values);
        } else {
            $tag->options = null;
        }

        $tag->save();

        // dispatch Tag add event
        $event = new TagAddEvent($tag->tagId);
        $this->getDispatcher()->dispatch($event, $event::$NAME);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $tag->tag),
            'id' => $tag->tagId,
            'data' => $tag
        ]);

        return $this->render($request, $response);
    }


    #[OA\Get(
        path: '/tag/usage/{tagId}',
        operationId: 'TagUsageReport',
        description: 'Get the records for the Tag item usage report',
        summary: 'Get Tag Item Usage Report',
        tags: ['tags']
    )]
    #[OA\Parameter(
        name: 'tagId',
        description: 'Numeric ID of the Tag to get',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Specifies which field the results are sorted by. Used together with sortDir',
        in: 'query',
        required: false,
        schema: new OA\Schema(
            type: 'string',
            enum: [
                'entityId',
                'type',
                'name',
                'value',
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
        headers: [
            new OA\Header(
                header: 'X-Total-Count',
                description: 'The total number of records',
                schema: new OA\Schema(type: 'integer')
            )
        ],
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response|ResponseInterface
     * @throws ControllerNotImplemented
     * @throws GeneralException
     */
    public function usage(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());

        $filter = [
            'tagId' => $id,
            'allTags' => 1
        ];

        $entries = $this->tagFactory->getAllLinks(
            $this->gridRenderSort($sanitizedParams, $this->isJson($request)),
            $this->gridRenderFilter($filter, $sanitizedQueryParams)
        );

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $this->tagFactory->countLast())
            ->withJson($entries);
    }

    #[OA\Put(
        path: '/tag/{tagId}',
        operationId: 'tagEdit',
        description: 'Edit existing Tag',
        summary: 'Edit existing Tag',
        tags: ['tags']
    )]
    #[OA\Parameter(
        name: 'tagId',
        description: 'The Tag ID to Edit',
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
                    new OA\Property(property: 'name', description: 'Tag name', type: 'string'),
                    new OA\Property(
                        property: 'isRequired',
                        description: 'A flag indicating whether value selection on assignment is required',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'options',
                        description: 'A comma separated string of Tag options',
                        type: 'string'
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'successful operation',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Tag')
        )
    )]
    /**
     * Edit a Tag
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function edit(Request $request, Response $response, $id): Response|ResponseInterface
    {
        if (!$this->getUser()->isSuperAdmin()) {
            throw new AccessDeniedException();
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());
        $tag = $this->tagFactory->getById($id);

        if ($tag->isSystem === 1) {
            throw new AccessDeniedException(__('Access denied System tags cannot be edited'));
        }

        if (isset($tag->options)) {
            $tagOptionsCurrent = implode(',', json_decode($tag->options));
            $tagOptionsArrayCurrent = explode(',', $tagOptionsCurrent);
        }

        $values = [];

        $oldTag = $tag->tag;
        $tag->tag = $sanitizedParams->getString('name');
        $tag->isRequired = $sanitizedParams->getCheckbox('isRequired');
        $optionValues = $sanitizedParams->getString('options');

        if ($optionValues != '') {
            $optionValuesArray = explode(',', $optionValues);
            foreach ($optionValuesArray as $option) {
                $values[] = trim($option);
            }
            $tag->options = json_encode($values);
        } else {
            $tag->options = null;
        }

        // if option were changed, we need to compare the array of options before and after edit
        if ($tag->hasPropertyChanged('options')) {
            if (isset($tagOptionsArrayCurrent)) {
                if (isset($tag->options)) {
                    $tagOptions = implode(',', json_decode($tag->options));
                    $tagOptionsArray = explode(',', $tagOptions);
                } else {
                    $tagOptionsArray = [];
                }

                // compare array of options before and after the Tag edit was made
                $tagValuesToRemove = array_diff($tagOptionsArrayCurrent, $tagOptionsArray);

                // go through every element of the new array and set the value to null if removed value was assigned to one of the lktag tables
                $tag->updateTagValues($tagValuesToRemove);
            }
        }

        $tag->save();

        // dispatch Tag edit event
        $event = new TagEditEvent($tag->tagId, $oldTag, $tag->tag);
        $this->getDispatcher()->dispatch($event, $event::$NAME);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Edited %s'), $tag->tag),
            'id' => $tag->tagId,
            'data' => $tag
        ]);

        return $this->render($request, $response);
    }

    #[OA\Delete(
        path: '/tag/{tagId}',
        operationId: 'tagDelete',
        description: 'Delete a Tag',
        summary: 'Delete Tag',
        tags: ['tags']
    )]
    #[OA\Parameter(
        name: 'tagId',
        description: 'The Tag ID to delete',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * Delete Tag
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ConfigurationException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function delete(Request $request, Response $response, $id): Response|ResponseInterface
    {
        if (!$this->getUser()->isSuperAdmin()) {
            throw new AccessDeniedException();
        }

        $tag = $this->tagFactory->getById($id);

        if ($tag->isSystem === 1) {
            throw new AccessDeniedException(__('Access denied System tags cannot be deleted'));
        }

        // Dispatch delete event, remove this tag links in all lktag tables.
        $event = new TagDeleteEvent($tag->tagId);
        $this->getDispatcher()->dispatch($event, $event::$NAME);
        // tag delete, remove the record from tag table
        $tag->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $tag->tag)
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function loadTagOptions(Request $request, Response $response): Response|ResponseInterface
    {
        $tagName = $this->getSanitizer($request->getParams())->getString('name');

        try {
            $tag = $this->tagFactory->getByTag($tagName);
        } catch (NotFoundException $e) {
            // User provided new tag, which is fine
            $tag = null;
        }

        $this->getState()->setData([
            'tag' => ($tag === null) ? null : $tag
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ConfigurationException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function editMultiple(Request $request, Response $response): Response|ResponseInterface
    {
        // Handle permissions
        if (!$this->getUser()->featureEnabled('tag.tagging')) {
            throw new AccessDeniedException();
        }

        $sanitizedParams = $this->getSanitizer($request->getParams());

        $targetType = $sanitizedParams->getString('targetType');
        $targetIds = $sanitizedParams->getString('targetIds');
        $tagsToAdd = $sanitizedParams->getString('addTags');
        $tagsToRemove = $sanitizedParams->getString('removeTags');

        // check if we need to do anything first
        if ($tagsToAdd != '' || $tagsToRemove != '') {
            // covert comma separated string of ids into array
            $targetIdsArray = explode(',', $targetIds);

            // get tags to assign and unassign
            $tags = $this->tagFactory->tagsFromString($tagsToAdd);
            $untags = $this->tagFactory->tagsFromString($tagsToRemove);

            // depending on the type we need different factory
            $entityFactory = match ($targetType) {
                'layout' => $this->layoutFactory,
                'playlist' => $this->playlistFactory,
                'media' => $this->mediaFactory,
                'campaign' => $this->campaignFactory,
                'displayGroup', 'display' => $this->displayGroupFactory,
                default => throw new InvalidArgumentException(
                    __('Edit multiple tags is not supported on this item'),
                    'targetType'
                ),
            };

            foreach ($targetIdsArray as $id) {
                // get the entity by provided id, for display we need different function
                $this->getLog()->debug('editMultiple: lookup using id: ' . $id . ' for type: ' . $targetType);
                if ($targetType === 'display') {
                    $entity = $entityFactory->getDisplaySpecificByDisplayId($id);
                } else {
                    $entity = $entityFactory->getById($id);
                }

                if ($targetType === 'display' || $targetType === 'displaygroup') {
                    $this->getDispatcher()->dispatch(new DisplayGroupLoadEvent($entity), DisplayGroupLoadEvent::$NAME);
                }

                foreach ($untags as $untag) {
                    $entity->unassignTag($untag);
                }

                // go through tags and adjust assignments.
                foreach ($tags as $tag) {
                    $entity->assignTag($tag);
                }

                $entity->save(['isTagEdit' => true]);
            }

            // Once we're done, and if we're a Display entity, we need to calculate the dynamic display groups
            if ($targetType === 'display') {
                // Background update.
                $this->getDispatcher()->dispatch(
                    new TriggerTaskEvent('\Xibo\XTR\MaintenanceRegularTask', 'DYNAMIC_DISPLAY_GROUP_ASSESSED'),
                    TriggerTaskEvent::$NAME
                );
            }
        } else {
            $this->getLog()->debug('Tags were not changed');
        }

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => __('Tags Edited')
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param SanitizerInterface $sanitizedQueryParams
     * @return array
     */
    private function getTagFilters(SanitizerInterface $sanitizedQueryParams): array
    {
        return $this->gridRenderFilter([
            'tagId' => $sanitizedQueryParams->getInt('tagId'),
            'keyword' => $sanitizedQueryParams->getString('keyword'),
            'tag' => $sanitizedQueryParams->getString('tag'),
            'useRegexForName' => $sanitizedQueryParams->getCheckbox('useRegexForName'),
            'isSystem' => $sanitizedQueryParams->getCheckbox('isSystem'),
            'isRequired' => $sanitizedQueryParams->getCheckbox('isRequired'),
            'haveOptions' => $sanitizedQueryParams->getCheckbox('haveOptions'),
            'allTags' => $sanitizedQueryParams->getInt('allTags'),
            'logicalOperatorName' => $sanitizedQueryParams->getString('logicalOperatorName'),
        ], $sanitizedQueryParams);
    }
}
