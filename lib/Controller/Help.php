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
use Xibo\Helper\Sanitize;


class Help extends Base
{
    /**
     * Help Page
     */
    function displayPage()
    {
        $this->getState()->template = 'help-page';
    }

    public function grid()
    {
        $helpLinks = HelpFactory::query($this->gridRenderSort(), $this->gridRenderFilter());

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
                    'url' => \Xibo\Helper\Help::Link($row->topic, $row->category),
                    'text' => __('Test')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = HelpFactory::countLast();
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

        $help = HelpFactory::getById($helpId);

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

        $help = HelpFactory::getById($helpId);

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

        $help = new \Xibo\Entity\Help();
        $help->topic = Sanitize::getString('topic');
        $help->category = Sanitize::getString('category');
        $help->link = Sanitize::getString('link');

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

        $help = HelpFactory::getById($helpId);
        $help->topic = Sanitize::getString('topic');
        $help->category = Sanitize::getString('category');
        $help->link = Sanitize::getString('link');

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

        $help = HelpFactory::getById($helpId);
        $help->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $help->topic)
        ]);
    }
}
