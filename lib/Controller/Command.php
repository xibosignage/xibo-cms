<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Command.php)
 */


namespace Xibo\Controller;


use Xibo\Factory\CommandFactory;

class Command extends Base
{
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
        $commands = CommandFactory::query($this->gridRenderSort(), $this->gridRenderFilter());

        foreach ($commands as $command) {
            /* @var \Xibo\Entity\Command $command */

            // Default Layout
            $command->buttons[] = array(
                'id' => 'command_button_edit',
                'url' => $this->urlFor('command.edit.form', ['id' => $command->commandId]),
                'text' => __('Edit')
            );

            if ($this->getUser()->checkDeleteable($command)) {
                $command->buttons[] = array(
                    'id' => 'command_button_delete',
                    'url' => $this->urlFor('command.delete.form', ['id' => $command->commandId]),
                    'text' => __('Delete')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = CommandFactory::countLast();
        $this->getState()->setData($commands);
    }

    public function addForm()
    {
        $this->getState()->template = 'command-form-add';
    }
}