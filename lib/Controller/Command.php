<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Command.php)
 */


namespace Xibo\Controller;


use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\XiboException;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

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
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param CommandFactory $commandFactory
     * @param DisplayProfileFactory $displayProfileFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $commandFactory, $displayProfileFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->commandFactory = $commandFactory;
        $this->displayProfileFactory = $displayProfileFactory;
    }

    public function displayPage()
    {
        $this->getState()->template = 'command-page';
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
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/Command")
     *      )
     *  )
     * )
     */
    function grid()
    {
        $filter = [
            'commandId' => $this->getSanitizer()->getInt('commandId'),
            'command' => $this->getSanitizer()->getString('command'),
            'code' => $this->getSanitizer()->getString('code')
        ];

        $commands = $this->commandFactory->query($this->gridRenderSort(), $this->gridRenderFilter($filter));

        foreach ($commands as $command) {
            /* @var \Xibo\Entity\Command $command */

            if ($this->isApi())
                break;

            $command->includeProperty('buttons');

            // Command edit
            if ($this->getUser()->checkEditable($command)) {
                $command->buttons[] = array(
                    'id' => 'command_button_edit',
                    'url' => $this->urlFor('command.edit.form', ['id' => $command->commandId]),
                    'text' => __('Edit')
                );
            }

            // Command delete
            if ($this->getUser()->checkDeleteable($command)) {
                $command->buttons[] = array(
                    'id' => 'command_button_delete',
                    'url' => $this->urlFor('command.delete.form', ['id' => $command->commandId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => array(
                        array('name' => 'commit-url', 'value' => $this->urlFor('command.delete', ['id' => $command->commandId])),
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
                    'url' => $this->urlFor('user.permissions.form', ['entity' => 'Command', 'id' => $command->commandId]),
                    'text' => __('Permissions')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->commandFactory->countLast();
        $this->getState()->setData($commands);
    }

    /**
     * Add Command Form
     */
    public function addForm()
    {
        $this->getState()->template = 'command-form-add';
    }

    /**
     * Edit Command
     * @param int $commandId
     * @throws XiboException
     */
    public function editForm($commandId)
    {
        $command = $this->commandFactory->getById($commandId);

        if (!$this->getUser()->checkEditable($command)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'command-form-edit';
        $this->getState()->setData([
            'command' => $command
        ]);
    }

    /**
     * Delete Command
     * @param int $commandId
     * @throws XiboException
     */
    public function deleteForm($commandId)
    {
        $command = $this->commandFactory->getById($commandId);

        if (!$this->getUser()->checkDeleteable($command)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'command-form-delete';
        $this->getState()->setData([
            'command' => $command
        ]);
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
     */
    public function add()
    {
        $command = $this->commandFactory->create();
        $command->command = $this->getSanitizer()->getString('command');
        $command->description = $this->getSanitizer()->getString('description');
        $command->code = $this->getSanitizer()->getString('code');
        $command->userId = $this->getUser()->userId;
        $command->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $command->command),
            'id' => $command->commandId,
            'data' => $command
        ]);
    }

    /**
     * Edit Command
     * @param int $commandId
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
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Command")
     *  )
     * )
     *
     * @throws XiboException
     */
    public function edit($commandId)
    {
        $command = $this->commandFactory->getById($commandId);

        if (!$this->getUser()->checkEditable($command)) {
            throw new AccessDeniedException();
        }

        $command->command = $this->getSanitizer()->getString('command');
        $command->description = $this->getSanitizer()->getString('description');
        $command->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 200,
            'message' => sprintf(__('Edited %s'), $command->command),
            'id' => $command->commandId,
            'data' => $command
        ]);
    }

    /**
     * Delete Command
     * @param int $commandId
     *
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
    public function delete($commandId)
    {
        $command = $this->commandFactory->getById($commandId);

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
    }
}