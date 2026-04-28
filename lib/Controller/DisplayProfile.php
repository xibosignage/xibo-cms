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

use Carbon\Carbon;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Stash\Interfaces\PoolInterface;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class DisplayProfile
 * @package Xibo\Controller
 */
class DisplayProfile extends Base
{
    use DisplayProfileConfigFields;

    /** @var  PoolInterface */
    private $pool;

    /**
     * @var DayPartFactory
     */
    private $dayPartFactory;

    /**
     * @var DisplayProfileFactory
     */
    private $displayProfileFactory;

    /**
     * @var CommandFactory
     */
    private $commandFactory;

    /** @var PlayerVersionFactory */
    private $playerVersionFactory;

    /**
     * Set common dependencies.
     * @param PoolInterface $pool
     * @param DisplayProfileFactory $displayProfileFactory
     * @param CommandFactory $commandFactory
     * @param PlayerVersionFactory $playerVersionFactory
     * @param DayPartFactory $dayPartFactory
     */
    public function __construct($pool, $displayProfileFactory, $commandFactory, $playerVersionFactory, $dayPartFactory)
    {
        $this->pool = $pool;
        $this->displayProfileFactory = $displayProfileFactory;
        $this->commandFactory = $commandFactory;
        $this->playerVersionFactory = $playerVersionFactory;
        $this->dayPartFactory = $dayPartFactory;
    }

    #[OA\Get(
        path: '/displayprofile',
        operationId: 'displayProfileSearch',
        description: 'Search this users Display Profiles',
        summary: 'Display Profile Search',
        tags: ['displayprofile']
    )]
    #[OA\Parameter(
        name: 'displayProfileId',
        description: 'Filter by DisplayProfile Id',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'displayProfile',
        description: 'Filter by DisplayProfile Name',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'type',
        description: 'Filter by DisplayProfile Type (windows|android|lg)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'embed',
        description: 'Embed related data such as config,commands,configWithDefault',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'keyword',
        description: 'Filter by display profile name, ID, or type',
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
                'displayProfileId',
                'name',
                'type',
                'isDefault',
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
            items: new OA\Items(ref: '#/components/schemas/DisplayProfile')
        )
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws NotFoundException
     * @throws GeneralException
     */
    public function grid(Request $request, Response $response): Response|ResponseInterface
    {
        $parsedQueryParams = $this->getSanitizer($request->getQueryParams());

        $embed = ($parsedQueryParams->getString('embed') != null)
            ? explode(',', $parsedQueryParams->getString('embed'))
            : [];

        $displayProfileSortQuery = $this->gridRenderSort(
            $parsedQueryParams,
            $this->isJson($request),
            'displayProfileId'
        );

        $displayProfileFilterQuery = $this->getDisplayProfileFilterQuery($parsedQueryParams);

        $profiles = $this->displayProfileFactory->query(
            $displayProfileSortQuery,
            $displayProfileFilterQuery
        );

        foreach ($profiles as $profile) {
            $this->decorateDisplayProfileProperties($profile, $embed);
        }

        $recordsTotal = $this->displayProfileFactory->countLast();

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $recordsTotal)
            ->withJson($profiles);
    }

    #[OA\Get(
        path: '/displayprofile/{id}',
        operationId: 'displayProfileSearchById',
        description: 'Get the Display Profile object specified by the provided displayProfileId',
        summary: 'Display Profile Search by ID',
        tags: ['displayprofile']
    )]
    #[OA\Parameter(
        name: 'displayProfileId',
        description: 'Numeric ID of the Display Profile to get',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/DisplayProfile')
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
        $displayProfile = $this->displayProfileFactory->getById($id, false);

        $displayProfile->setUnmatchedProperty('userPermissions', $this->getUser()->getPermission($displayProfile));

        return $response
            ->withStatus(200)
            ->withJson($displayProfile);
    }

    #[OA\Post(
        path: '/displayprofile',
        operationId: 'displayProfileAdd',
        description: 'Add a Display Profile',
        summary: 'Add Display Profile',
        tags: ['displayprofile']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['name', 'type', 'isDefault'],
                properties: [
                    new OA\Property(property: 'name', description: 'The Name of the Display Profile', type: 'string'),
                    new OA\Property(
                        property: 'type',
                        description: 'The Client Type this Profile will apply to',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'isDefault',
                        description: 'Flag indicating if this is the default profile for the client type',
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
        content: new OA\JsonContent(ref: '#/components/schemas/DisplayProfile')
    )]
    /**
     * Display Profile Add
     *
     * @param Request $request
     * @param Response $response
     * @return ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     */
    public function add(Request $request, Response $response): Response|ResponseInterface
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $displayProfile = $this->displayProfileFactory->createEmpty();
        $displayProfile->name = $sanitizedParams->getString('name');
        $displayProfile->type = $sanitizedParams->getString('type');
        $displayProfile->isDefault = $sanitizedParams->getCheckbox('isDefault');
        $displayProfile->userId = $this->getUser()->userId;
        $displayProfile->isCustom = $this->displayProfileFactory->isCustomType($displayProfile->type);

        // We do not set any config at this point, so that unless the user chooses to edit the display profile
        // our defaults in the Display Profile Factory take effect
        $displayProfile->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $displayProfile->name),
            'id' => $displayProfile->displayProfileId,
            'data' => $displayProfile
        ]);

        return $this->render($request, $response);
    }

    #[OA\Put(
        path: '/displayprofile/{displayProfileId}',
        operationId: 'displayProfileEdit',
        description: 'Edit a Display Profile',
        summary: 'Edit Display Profile',
        tags: ['displayprofile']
    )]
    #[OA\Parameter(
        name: 'displayProfileId',
        description: 'The Display Profile ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['name', 'type', 'isDefault'],
                properties: [
                    new OA\Property(property: 'name', description: 'The Name of the Display Profile', type: 'string'),
                    new OA\Property(
                        property: 'type',
                        description: 'The Client Type this Profile will apply to',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'isDefault',
                        description: 'Flag indicating if this is the default profile for the client type',
                        type: 'integer'
                    )
                ]
            )
        )
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * Edit
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     */
    public function edit(Request $request, Response $response, $id): Response|ResponseInterface
    {
        // Create a form out of the config object.
        $displayProfile = $this->displayProfileFactory->getById($id);

        $parsedParams = $this->getSanitizer($request->getParams());

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId) {
            throw new AccessDeniedException(__('You do not have permission to edit this profile'));
        }

        $displayProfile->name = $parsedParams->getString('name');
        $displayProfile->isDefault = $parsedParams->getCheckbox('isDefault');

        // Track changes to versionMediaId
        $originalPlayerVersionId = $displayProfile->getSetting('playerVersionId');

        // Different fields for each client type
        $this->editConfigFields($displayProfile, $parsedParams);

        // Capture and update commands
        foreach ($this->commandFactory->query() as $command) {
            if ($parsedParams->getString('commandString_' . $command->commandId) != null) {
                // Set and assign the command
                $command->commandString = $parsedParams->getString('commandString_' . $command->commandId);
                $command->validationString = $parsedParams->getString('validationString_' . $command->commandId);
                $command->createAlertOn = $parsedParams->getString('createAlertOn_' . $command->commandId);

                $displayProfile->assignCommand($command);
            } else {
                $displayProfile->unassignCommand($command);
            }
        }

        // If we are chromeOS and the default profile, has the player version changed?
        if ($displayProfile->type === 'chromeOS'
            && ($displayProfile->isDefault || $displayProfile->hasPropertyChanged('isDefault'))
            && ($originalPlayerVersionId !== $displayProfile->getSetting('playerVersionId'))
        ) {
            $this->getLog()->debug('edit: updating symlink to the latest chromeOS version');

            // Update a symlink to the new player version.
            try {
                $version = $this->playerVersionFactory->getById($displayProfile->getSetting('playerVersionId'));
                $version->setActive();
            } catch (NotFoundException) {
                $this->getLog()->error('edit: Player version does not exist');
            }
        }

        // Save the changes
        $displayProfile->save();

        // Clear the display cached
        $this->pool->deleteItem('display/');

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $displayProfile->name),
            'id' => $displayProfile->displayProfileId,
            'data' => $displayProfile
        ]);

        return $this->render($request, $response);
    }

    #[OA\Delete(
        path: '/displayprofile/{displayProfileId}',
        operationId: 'displayProfileDelete',
        description: 'Delete an existing Display Profile',
        summary: 'Delete Display Profile',
        tags: ['displayprofile']
    )]
    #[OA\Parameter(
        name: 'displayProfileId',
        description: 'The Display Profile ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * Delete Display Profile
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     */
    public function delete(Request $request, Response $response, $id): Response|ResponseInterface
    {
        // Create a form out of the config object.
        $displayProfile = $this->displayProfileFactory->getById($id);

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId) {
            throw new AccessDeniedException(__('You do not have permission to delete this profile'));
        }

        $displayProfile->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $displayProfile->name)
        ]);

        return $this->render($request, $response);
    }

    #[OA\Post(
        path: '/displayprofile/{displayProfileId}/copy',
        operationId: 'displayProfileCopy',
        description: 'Copy an existing Display Profile',
        summary: 'Copy Display Profile',
        tags: ['displayprofile']
    )]
    #[OA\Parameter(
        name: 'displayProfileId',
        description: 'The Display Profile ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'name',
        description: 'The name for the copy',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string')
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
        content: new OA\JsonContent(ref: '#/components/schemas/DisplayProfile')
    )]
    /**
     * Copy Display Profile
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws NotFoundException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     */
    public function copy(Request $request, Response $response, $id): Response|ResponseInterface
    {
        // Create a form out of the config object.
        $displayProfile = $this->displayProfileFactory->getById($id);

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId) {
            throw new AccessDeniedException(__('You do not have permission to delete this profile'));
        }

        // clear DisplayProfileId, commands and set isDefault to 0
        $new = clone $displayProfile;
        $new->name = $this->getSanitizer($request->getParams())->getString('name');

        foreach ($displayProfile->commands as $command) {
            /* @var \Xibo\Entity\Command $command */
            if (!empty($command->commandStringDisplayProfile)) {
                // if the original Display Profile has a commandString
                // assign this command with the same commandString to new Display Profile
                // commands with only default commandString are not directly assigned to Display profile
                $command->commandString = $command->commandStringDisplayProfile;
                $command->validationString = $command->validationStringDisplayProfile;
                $new->assignCommand($command);
            }
        }

        $new->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $new->name),
            'id' => $new->displayProfileId,
            'data' => $new
        ]);

        return $this->render($request, $response);
    }

    /**
     * List of display profile types
     * @param Response $response
     * @return Response
     */
    public function getDisplayProfileTypes(Response $response): Response
    {
        return $response->withJson($this->displayProfileFactory->getAvailableTypes());
    }

    /**
     * Get the display profile filters
     * @param $parsedQueryParams
     * @return array
     */
    private function getDisplayProfileFilterQuery($parsedQueryParams): array
    {
        return $this->gridRenderFilter([
            'displayProfileId' => $parsedQueryParams->getInt('displayProfileId'),
            'displayProfile' => $parsedQueryParams->getString('displayProfile'),
            'useRegexForName' => $parsedQueryParams->getCheckbox('useRegexForName'),
            'type' => $parsedQueryParams->getString('type'),
            'logicalOperatorName' => $parsedQueryParams->getString('logicalOperatorName'),
            'keyword' => $parsedQueryParams->getString('keyword')
        ], $parsedQueryParams);
    }

    /**
     * Decorate display profile properties
     * @param $profile
     * @param $embed
     * @return void
     * @throws InvalidArgumentException
     */
    private function decorateDisplayProfileProperties($profile, $embed): void
    {
        // Load the config
        $profile->load([
            'loadConfig' => in_array('config', $embed),
            'loadCommands' => in_array('commands', $embed)
        ]);

        if (in_array('configWithDefault', $embed)) {
            $profile->includeProperty('configDefault');
        }

        if (!in_array('config', $embed)) {
            $profile->excludeProperty('config');
        }

        $profile->setUnmatchedProperty('userPermissions', $this->getUser()->getPermission($profile));
    }
}
