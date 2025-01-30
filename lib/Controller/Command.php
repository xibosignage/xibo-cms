<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
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

    /**
     * @SWG\Get(
     *  path="/command",
     *  operationId="commandSearch",
     *  tags={"command"},
     *  summary="Command Search",
     *  description="Search this users Commands",
     *  @SWG\Parameter(
     *      name="commandId",
     *      in="query",
     *      description="Filter by Command Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="command",
     *      in="query",
     *      description="Filter by Command Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="code",
     *      in="query",
     *      description="Filter by Command Code",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="useRegexForName",
     *      in="query",
     *      description="Flag (0,1). When filtering by multiple commands in command filter, should we use regex?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *    name="useRegexForCode",
     *     in="query",
     *     description="Flag (0,1). When filtering by multiple codes in code filter, should we use regex?",
     *     type="integer",
     *     required=false
     *   ),
     *  @SWG\Parameter(
     *      name="logicalOperatorName",
     *      in="query",
     *      description="When filtering by multiple commands in command filter,
     * which logical operator should be used? AND|OR",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="logicalOperatorCode",
     *      in="query",
     *      description="When filtering by multiple codes in code filter,
     * which logical operator should be used? AND|OR",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Command")
     *      )
     *  )
     * )
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

    /**
     * Add Command
     *
     * @SWG\Post(
     *  path="/command",
     *  operationId="commandAdd",
     *  tags={"command"},
     *  summary="Command Add",
     *  description="Add a Command",
     *  @SWG\Parameter(
     *      name="command",
     *      in="formData",
     *      description="The Command Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="A description for the command",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="code",
     *      in="formData",
     *      description="A unique code for this command",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="commandString",
     *      in="formData",
     *      description="The Command String for this Command. Can be overridden on Display Settings.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="validationString",
     *      in="formData",
     *      description="The Validation String for this Command. Can be overridden on Display Settings.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="availableOn",
     *      in="formData",
     *      description="An array of Player types this Command is available on, empty for all.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="createAlertOn",
     *      in="formData",
     *      description="On command execution, when should a Display alert be created?
     * success, failure, always or never",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Command"),
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
     *
     * @SWG\Put(
     *  path="/command/{commandId}",
     *  operationId="commandEdit",
     *  tags={"command"},
     *  summary="Edit Command",
     *  description="Edit the provided command",
     *  @SWG\Parameter(
     *      name="commandId",
     *      in="path",
     *      description="The Command Id to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="command",
     *      in="formData",
     *      description="The Command Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="A description for the command",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="commandString",
     *      in="formData",
     *      description="The Command String for this Command. Can be overridden on Display Settings.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="validationString",
     *      in="formData",
     *      description="The Validation String for this Command. Can be overridden on Display Settings.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="availableOn",
     *      in="formData",
     *      description="An array of Player types this Command is available on, empty for all.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="createAlertOn",
     *      in="formData",
     *      description="On command execution, when should a Display alert be created?
     * success, failure, always or never",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Command")
     *  )
     * )
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
     * @SWG\Delete(
     *  path="/command/{commandId}",
     *  operationId="commandDelete",
     *  tags={"command"},
     *  summary="Delete Command",
     *  description="Delete the provided command",
     *  @SWG\Parameter(
     *      name="commandId",
     *      in="path",
     *      description="The Command Id to Delete",
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