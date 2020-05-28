<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2013 Daniel Garner
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

use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\HelpFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class Help
 * @package Xibo\Controller
 */
class Help extends Base
{
    /**
     * @var HelpFactory
     */
    private $helpFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param HelpFactory $helpFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $helpFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->helpFactory = $helpFactory;
    }

    /**
     * Help Page
     */
    function displayPage()
    {
        $this->getState()->template = 'help-page';
    }

    public function grid()
    {
        $helpLinks = $this->helpFactory->query($this->gridRenderSort(), $this->gridRenderFilter());

        foreach ($helpLinks as $row) {
            /* @var \Xibo\Entity\Help $row */

            // we only want to show certain buttons, depending on the user logged in
            if ($this->getUser()->userTypeId == 1) {

                // Edit
                $row->buttons[] = array(
                    'id' => 'help_button_edit',
                    'url' => $this->urlFor('help.edit.form', ['id' => $row->helpId]),
                    'text' => __('Edit')
                );

                // Delete
                $row->buttons[] = array(
                    'id' => 'help_button_delete',
                    'url' => $this->urlFor('help.delete.form', ['id' => $row->helpId]),
                    'text' => __('Delete')
                );

                // Test
                $row->buttons[] = array(
                    'id' => 'help_button_test',
                    'linkType' => '_self', 'external' => true,
                    'url' => $this->getHelp()->link($row->topic, $row->category),
                    'text' => __('Test')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->helpFactory->countLast();
        $this->getState()->setData($helpLinks);
    }

    /**
     * Add Form
     */
    public function addForm()
    {
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $this->getState()->template = 'help-form-add';
    }

    /**
     * Help Edit form
     * @param int $helpId
     */
    public function editForm($helpId)
    {
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $help = $this->helpFactory->getById($helpId);

        $this->getState()->template = 'help-form-edit';
        $this->getState()->setData([
            'help' => $help
        ]);
    }

    /**
     * Delete Help Link Form
     * @param int $helpId
     */
    public function deleteForm($helpId)
    {
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $help = $this->helpFactory->getById($helpId);

        $this->getState()->template = 'help-form-delete';
        $this->getState()->setData([
            'help' => $help
        ]);
    }

    /**
     * Adds a help link
     */
    public function add()
    {
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $help = $this->helpFactory->createEmpty();
        $help->topic = $this->getSanitizer()->getString('topic');
        $help->category = $this->getSanitizer()->getString('category');
        $help->link = $this->getSanitizer()->getString('link');

        $help->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $help->topic),
            'id' => $help->helpId,
            'data' => $help
        ]);
    }

    /**
     * Edits a help link
     * @param int $helpId
     */
    public function edit($helpId)
    {
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $help = $this->helpFactory->getById($helpId);
        $help->topic = $this->getSanitizer()->getString('topic');
        $help->category = $this->getSanitizer()->getString('category');
        $help->link = $this->getSanitizer()->getString('link');

        $help->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $help->topic),
            'id' => $help->helpId,
            'data' => $help
        ]);
    }

    /**
     * Delete
     * @param int $helpId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function delete($helpId)
    {
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $help = $this->helpFactory->getById($helpId);
        $help->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $help->topic)
        ]);
    }
}
