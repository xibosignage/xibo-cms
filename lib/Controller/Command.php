<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\XiboException;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;

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
     * @var DisplayProfileFactory
     */
    private $displayProfileFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param CommandFactory $commandFactory
     * @param DisplayProfileFactory $displayProfileFactory
     * @param Twig $view
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $commandFactory, $displayProfileFactory, Twig $view)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config, $view);

        $this->commandFactory = $commandFactory;
        $this->displayProfileFactory = $displayProfileFactory;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
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
     *      in="formData",
     *      description="Filter by Command Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="command",
     *      in="formData",
     *      description="Filter by Command Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="code",
     *      in="formData",
     *      description="Filter by Command Code",
     *      type="string",
     *      required=false
     *   ),
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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    function grid(Request $request, Response $response)
    {
        $sanitzedParams = $this->getSanitizer($request->getParams());

        $filter = [
            'commandId' => $sanitzedParams->getInt('commandId'),
            'command' => $sanitzedParams->getString('command'),
            'code' => $sanitzedParams->getString('code')
        ];

        $commands = $this->commandFactory->query($this->gridRenderSort($request), $this->gridRenderFilter($filter, $request));

        foreach ($commands as $command) {
            /* @var \Xibo\Entity\Command $command */

            if ($this->isApi($request))
                continue;

            $command->includeProperty('buttons');

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
                $command->buttons[] = array(
                    'id' => 'command_button_delete',
                    'url' => $this->urlFor($request,'command.delete.form', ['id' => $command->commandId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor($request,'command.delete', ['id' => $command->commandId])),
                        array('name' => 'commit-method', 'value' => 'delete'),
                        array('name' => 'id', 'value' => 'command_button_delete'),
                        array('name' => 'text', 'value' => __('Delete')),
                        array('name' => 'rowtitle', 'value' => $command->command)
                    )
                );
            }

            // Command Permissions
            if ($this->getUser()->checkPermissionsModifyable($command)) {
                // Permissions button
                $command->buttons[] = array(
                    'id' => 'command_button_permissions',
                    'url' => $this->urlFor($request,'user.permissions.form', ['entity' => 'Command', 'id' => $command->commandId]),
                    'text' => __('Permissions')
                );
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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     * @throws \Xibo\Exception\NotFoundException
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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     * @throws \Xibo\Exception\NotFoundException
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
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     * @throws \Xibo\Exception\InvalidArgumentException
     */
    public function add(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $command = $this->commandFactory->create();
        $command->command = $sanitizedParams->getString('command');
        $command->description = $sanitizedParams->getString('description');
        $command->code = $sanitizedParams->getString('code');
        $command->userId = $this->getUser()->userId;
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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     * @throws \Xibo\Exception\InvalidArgumentException
     * @throws \Xibo\Exception\NotFoundException
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
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Command")
     *  )
     * )
     *
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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     * @throws \Xibo\Exception\NotFoundException
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

        $command->setChildObjectDependencies($this->displayProfileFactory);
        $command->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $command->command)
        ]);

        return $this->render($request, $response);
    }
}