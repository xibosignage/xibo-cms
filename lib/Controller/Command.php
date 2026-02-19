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
use Xibo\Event\CommandDeleteEvent;
use Xibo\Factory\CommandFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Command
 * Command Controller
 * @package Xibo\Controller
 */
class Command extends Base
{
    /**
     * @var CommandFactory
     */
    private $commandFactory;

    /**
     * Set common dependencies.
     * @param CommandFactory $commandFactory
     */
    public function __construct($commandFactory)
    {
        $this->commandFactory = $commandFactory;
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
        $this->getState()->template = 'command-page';

        return $this->render($request, $response);
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
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Command')
        )
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function grid(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $filter = [
            'commandId' => $sanitizedParams->getInt('commandId'),
            'command' => $sanitizedParams->getString('command'),
            'code' => $sanitizedParams->getString('code'),
            'useRegexForName' => $sanitizedParams->getCheckbox('useRegexForName'),
            'useRegexForCode' => $sanitizedParams->getCheckbox('useRegexForCode'),
            'logicalOperatorName' => $sanitizedParams->getString('logicalOperatorName'),
            'logicalOperatorCode' => $sanitizedParams->getString('logicalOperatorCode'),
        ];

        $commands = $this->commandFactory->query(
            $this->gridRenderSort($sanitizedParams),
            $this->gridRenderFilter($filter, $sanitizedParams)
        );

        foreach ($commands as $command) {
            /* @var \Xibo\Entity\Command $command */

            if ($this->isApi($request)) {
                continue;
            }

            $command->includeProperty('buttons');

            if ($this->getUser()->featureEnabled('command.modify')) {
                // Command edit
                if ($this->getUser()->checkEditable($command)) {
                    $command->buttons[] = array(
                        'id' => 'command_button_edit',
                        'url' => $this->urlFor($request, 'command.edit.form', ['id' => $command->commandId]),
                        'text' => __('Edit')
                    );
                }

                // Command delete
                if ($this->getUser()->checkDeleteable($command)) {
                    $command->buttons[] = [
                        'id' => 'command_button_delete',
                        'url' => $this->urlFor($request, 'command.delete.form', ['id' => $command->commandId]),
                        'text' => __('Delete'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            [
                                'name' => 'commit-url',
                                'value' => $this->urlFor($request, 'command.delete', ['id' => $command->commandId])
                            ],
                            ['name' => 'commit-method', 'value' => 'delete'],
                            ['name' => 'id', 'value' => 'command_button_delete'],
                            ['name' => 'text', 'value' => __('Delete')],
                            ['name' => 'sort-group', 'value' => 1],
                            ['name' => 'rowtitle', 'value' => $command->command]
                        ]
                    ];
                }

                // Command Permissions
                if ($this->getUser()->checkPermissionsModifyable($command)) {
                    // Permissions button
                    $command->buttons[] = [
                        'id' => 'command_button_permissions',
                        'url' => $this->urlFor(
                            $request,
                            'user.permissions.form',
                            ['entity' => 'Command', 'id' => $command->commandId]
                        ),
                        'text' => __('Share'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            [
                                'name' => 'commit-url',
                                'value' => $this->urlFor(
                                    $request,
                                    'user.permissions.multi',
                                    ['entity' => 'Command', 'id' => $command->commandId]
                                )
                            ],
                            ['name' => 'commit-method', 'value' => 'post'],
                            ['name' => 'id', 'value' => 'command_button_permissions'],
                            ['name' => 'text', 'value' => __('Share')],
                            ['name' => 'rowtitle', 'value' => $command->command],
                            ['name' => 'sort-group', 'value' => 2],
                            ['name' => 'custom-handler', 'value' => 'XiboMultiSelectPermissionsFormOpen'],
                            [
                                'name' => 'custom-handler-url',
                                'value' => $this->urlFor(
                                    $request,
                                    'user.permissions.multi.form',
                                    ['entity' => 'Command']
                                )
                            ],
                            ['name' => 'content-id-name', 'value' => 'commandId']
                        ]
                    ];
                }
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->commandFactory->countLast();
        $this->getState()->setData($commands);

        return $this->render($request, $response);
    }

    /**
     * Add Command Form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     */
    public function addForm(Request $request, Response $response)
    {
        $this->getState()->template = 'command-form-add';

        return $this->render($request, $response);
    }

    /**
     * Edit Command
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
        $command = $this->commandFactory->getById($id);

        if (!$this->getUser()->checkEditable($command)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'command-form-edit';
        $this->getState()->setData([
            'command' => $command
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Command
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function deleteForm(Request $request, Response $response, $id)
    {
        $command = $this->commandFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($command)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'command-form-delete';
        $this->getState()->setData([
            'command' => $command
        ]);

        return $this->render($request, $response);
    }

    #[OA\Post(
        path: '/command',
        operationId: 'commandAdd',
        description: 'Add a Command',
        summary: 'Command Add',
        tags: ['command']
    )]
    #[OA\RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
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
                ],
                required: ['command', 'code']
            )
        ),
        required: true
    )]
    #[OA\Response(
        response: 201,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/Command'),
        headers: [
            new OA\Header(
                header: 'Location',
                description: 'Location of the new record',
                schema: new OA\Schema(type: 'string')
            )
        ]
    )]
    /**
     * Add Command
     *
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws ControllerNotImplemented
     * @throws GeneralException
     */
    public function add(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $command = $this->commandFactory->create();
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

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $command->command),
            'id' => $command->commandId,
            'data' => $command
        ]);

        return $this->render($request, $response);
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
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
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
                ],
                required: ['command']
            )
        ),
        required: true
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/Command')
    )]
    /**
     * Edit Command
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function edit(Request $request, Response $response, $id)
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

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'message' => sprintf(__('Edited %s'), $command->command),
            'id' => $command->commandId,
            'data' => $command
        ]);

        return $this->render($request, $response);
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
     * Delete Command
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function delete(Request $request, Response $response, $id)
    {
        $command = $this->commandFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($command)) {
            throw new AccessDeniedException();
        }

        $this->getDispatcher()->dispatch(new CommandDeleteEvent($command), CommandDeleteEvent::$NAME);

        $command->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $command->command)
        ]);

        return $this->render($request, $response);
    }
}
