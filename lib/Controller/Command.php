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
use Xibo\Event\CommandDeleteEvent;
use Xibo\Factory\CommandFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class Command
 * Command Controller
 * @package Xibo\Controller
 */
class Command extends Base
{
    public function __construct(
        private readonly CommandFactory $commandFactory,
    ) {
    }

    #[OA\Get(
        path: '/command',
        operationId: 'commandSearch',
        description: 'Search this users Commands',
        summary: 'Command Search',
        tags: ['command']
    )]
    #[OA\Parameter(
        name: 'commandId',
        description: 'Filter by Command Id',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'command',
        description: 'Filter by Command Name',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'code',
        description: 'Filter by Command Code',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'keyword',
        description: 'Filter by keyword (searches command name and id)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'useRegexForName',
        description: 'Flag (0,1). When filtering by multiple commands in command filter, should we use regex?', // phpcs:ignore
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'useRegexForCode',
        description: 'Flag (0,1). When filtering by multiple codes in code filter, should we use regex?', // phpcs:ignore
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'logicalOperatorName',
        description: 'When filtering by multiple commands in command filter, which logical operator should be used? AND|OR', // phpcs:ignore
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'logicalOperatorCode',
        description: 'When filtering by multiple codes in code filter, which logical operator should be used? AND|OR', // phpcs:ignore
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'type',
        description: 'Filter by player type (e.g. android, windows, linux, chromeOS, lg). Returns commands available on that type plus commands with no type restriction.', // phpcs:ignore
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
            enum: ['commandId', 'command', 'code', 'description', 'groupsWithPermissions']
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
            items: new OA\Items(ref: '#/components/schemas/Command')
        )
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @return Response|ResponseInterface
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function grid(Request $request, Response $response): Response|ResponseInterface
    {
        $sanitizedParams = $this->getSanitizer($request->getQueryParams());

        $commands = $this->commandFactory->query(
            $this->gridRenderSort($sanitizedParams, $this->isJson($request)),
            $this->getCommandFilters($sanitizedParams)
        );

        foreach ($commands as $command) {
            $command->setUnmatchedProperty('userPermissions', $this->getUser()->getPermission($command));
        }

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $this->commandFactory->countLast())
            ->withJson($commands);
    }

    #[OA\Get(
        path: '/command/{commandId}',
        operationId: 'commandSearchById',
        description: 'Get the Command object specified by the provided commandId',
        summary: 'Search Commands by ID',
        tags: ['command']
    )]
    #[OA\Parameter(
        name: 'commandId',
        description: 'Numeric ID of the Command to get',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/Command')
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return Response|ResponseInterface
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function searchById(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $command = $this->commandFactory->getById($id);
        $command->setUnmatchedProperty('userPermissions', $this->getUser()->getPermission($command));

        return $response
            ->withStatus(200)
            ->withJson($command);
    }

    #[OA\Post(
        path: '/command',
        operationId: 'commandAdd',
        description: 'Add a Command',
        summary: 'Command Add',
        tags: ['command']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['command', 'code'],
                properties: [
                    new OA\Property(
                        property: 'command',
                        description: 'The Command Name',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'description',
                        description: 'A description for the command',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'code',
                        description: 'A unique code for this command',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'commandString',
                        description: 'The Command String for this Command. Can be overridden on Display Settings.', // phpcs:ignore
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'validationString',
                        description: 'The Validation String for this Command. Can be overridden on Display Settings.', // phpcs:ignore
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'availableOn',
                        description: 'An array of Player types this Command is available on, empty for all.', // phpcs:ignore
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'createAlertOn',
                        description: 'On command execution, when should a Display alert be created? success, failure, always or never', // phpcs:ignore
                        type: 'string'
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
        content: new OA\JsonContent(ref: '#/components/schemas/Command')
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @return Response|ResponseInterface
     * @throws GeneralException
     */
    public function add(Request $request, Response $response): Response|ResponseInterface
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $command = $this->commandFactory->createEmpty();
        $command->command = $sanitizedParams->getString('command');
        $command->description = $sanitizedParams->getString('description');
        $command->code = $sanitizedParams->getString('code');
        $command->userId = $this->getUser()->userId;
        $command->commandString = $sanitizedParams->getString('commandString');
        $command->validationString = $sanitizedParams->getString('validationString');
        $command->createAlertOn = $sanitizedParams->getString('createAlertOn', ['default' => 'never']);
        $availableOn = $sanitizedParams->getArray('availableOn');
        if (empty($availableOn)) {
            $command->availableOn = null;
        } else {
            $command->availableOn = implode(',', $availableOn);
        }
        $command->save();

        return $response->withStatus(201)->withJson($command);
    }

    #[OA\Put(
        path: '/command/{commandId}',
        operationId: 'commandEdit',
        description: 'Edit the provided command',
        summary: 'Edit Command',
        tags: ['command']
    )]
    #[OA\Parameter(
        name: 'commandId',
        description: 'The Command Id to Edit',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                required: ['command'],
                properties: [
                    new OA\Property(
                        property: 'command',
                        description: 'The Command Name',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'description',
                        description: 'A description for the command',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'commandString',
                        description: 'The Command String for this Command. Can be overridden on Display Settings.', // phpcs:ignore
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'validationString',
                        description: 'The Validation String for this Command. Can be overridden on Display Settings.', // phpcs:ignore
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'availableOn',
                        description: 'An array of Player types this Command is available on, empty for all.', // phpcs:ignore
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'createAlertOn',
                        description: 'On command execution, when should a Display alert be created? success, failure, always or never', // phpcs:ignore
                        type: 'string'
                    )
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/Command')
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function edit(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $command = $this->commandFactory->getById($id);

        if (!$this->getUser()->checkEditable($command)) {
            throw new AccessDeniedException();
        }

        $command->command = $sanitizedParams->getString('command');
        $command->description = $sanitizedParams->getString('description');
        $command->commandString = $sanitizedParams->getString('commandString');
        $command->validationString = $sanitizedParams->getString('validationString');
        $command->createAlertOn = $sanitizedParams->getString('createAlertOn', ['default' => 'never']);
        $availableOn = $sanitizedParams->getArray('availableOn');
        if (empty($availableOn)) {
            $command->availableOn = null;
        } else {
            $command->availableOn = implode(',', $availableOn);
        }
        $command->save();

        return $response->withStatus(200)->withJson($command);
    }

    #[OA\Delete(
        path: '/command/{commandId}',
        operationId: 'commandDelete',
        description: 'Delete the provided command',
        summary: 'Delete Command',
        tags: ['command']
    )]
    #[OA\Parameter(
        name: 'commandId',
        description: 'The Command Id to Delete',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 204,
        description: 'successful operation'
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return Response|ResponseInterface
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function delete(Request $request, Response $response, int $id): Response|ResponseInterface
    {
        $command = $this->commandFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($command)) {
            throw new AccessDeniedException();
        }

        $this->getDispatcher()->dispatch(new CommandDeleteEvent($command), CommandDeleteEvent::$NAME);

        $command->delete();

        return $response->withStatus(204);
    }

    private function getCommandFilters(SanitizerInterface $sanitizedParams): array
    {
        return $this->gridRenderFilter([
            'commandId'           => $sanitizedParams->getInt('commandId'),
            'command'             => $sanitizedParams->getString('command'),
            'code'                => $sanitizedParams->getString('code'),
            'keyword'             => $sanitizedParams->getString('keyword'),
            'useRegexForName'     => $sanitizedParams->getCheckbox('useRegexForName'),
            'useRegexForCode'     => $sanitizedParams->getCheckbox('useRegexForCode'),
            'logicalOperatorName' => $sanitizedParams->getString('logicalOperatorName'),
            'logicalOperatorCode' => $sanitizedParams->getString('logicalOperatorCode'),
            'type'                => $sanitizedParams->getString('type'),
        ], $sanitizedParams);
    }
}
