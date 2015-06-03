<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Helper\Date;
use Xibo\Helper\Help;
use Xibo\Helper\Sanitize;


class DisplayProfile extends Base
{
    /**
     * Include display page template page based on sub page selected
     */
    function displayPage()
    {
        $this->getState()->template = 'displayprofile-page';
    }

    function grid()
    {
        $profiles = $this->getUser()->DisplayProfileList();

        foreach ($profiles as $profile) {
            /* @var \Xibo\Entity\DisplayProfile $profile */

            // Default Layout
            $profile->buttons[] = array(
                'id' => 'displayprofile_button_edit',
                'url' => $this->urlFor('displayProfile.edit.form', ['id' => $profile->displayProfileId]),
                'text' => __('Edit')
            );

            if ($this->getUser()->checkDeleteable($profile)) {
                $profile->buttons[] = array(
                    'id' => 'displayprofile_button_delete',
                    'url' => $this->urlFor('displayProfile.delete.form', ['id' => $profile->displayProfileId]),
                    'text' => __('Delete')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($profiles);
    }

    /**
     * Display Profile Add Form
     */
    function addForm()
    {
        $this->getState()->template = 'displayprofile-form-add';
        $this->getState()->setData([
            'clientTypes' => array(
                array('key' => 'windows', 'value' => 'Windows'),
                array('key' => 'ubuntu', 'value' => 'Ubuntu'),
                array('key' => 'android', 'value' => 'Android')
            )
        ]);
    }

    /**
     * Display Profile Add
     */
    public function add()
    {
        $displayProfile = new \Xibo\Entity\DisplayProfile();
        $displayProfile->name = Sanitize::getString('name');
        $displayProfile->type = Sanitize::getString('type');
        $displayProfile->isDefault = Sanitize::getCheckbox('isDefault');
        $displayProfile->userId = $this->getUser()->userId;

        $displayProfile->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $displayProfile->name),
            'id' => $displayProfile->displayProfileId,
            'data' => [$displayProfile]
        ]);
    }

    /**
     * Edit Profile Form
     * @param int $displayProfileId
     */
    public function editForm($displayProfileId)
    {
        // Create a form out of the config object.
        $displayProfile = DisplayProfileFactory::getById($displayProfileId);

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId)
            throw new AccessDeniedException(__('You do not have permission to edit this profile'));

        $this->getState()->template = 'displayprofile-form-edit';
        $this->getState()->setData([
            'displayProfile' => $displayProfile,
            'tabs' => $displayProfile->configTabs,
            'config' => $displayProfile->configDefault
        ]);
    }

    /**
     * Edit Form
     * @param $displayProfileId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function edit($displayProfileId)
    {
        // Create a form out of the config object.
        $displayProfile = DisplayProfileFactory::getById($displayProfileId);

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId)
            throw new AccessDeniedException(__('You do not have permission to edit this profile'));

        $displayProfile->name = Sanitize::getString('name');
        $displayProfile->isDefault = Sanitize::getCheckbox('isDefault');

        // Capture and validate the posted form parameters in accordance with the display config object.
        $combined = array();

        foreach ($displayProfile->configDefault as $setting) {
            // Validate the parameter
            $value = null;

            switch ($setting['type']) {
                case 'string':
                    $value = Sanitize::getString($setting['name'], $setting['default']);
                    break;

                case 'int':
                    $value = Sanitize::getInt($setting['name'], $setting['default']);
                    break;

                case 'double':
                    $value = Sanitize::getDouble($setting['name'], $setting['default']);
                    break;

                case 'checkbox':
                    $value = Sanitize::getCheckbox($setting['name']);
                    break;

                default:
                    $value = Sanitize::getParam($setting['name'], $setting['default']);
            }

            // If we are a time picker, then process the received time
            if ($setting['fieldType'] == 'timePicker') {
                $value = ($value == '00:00') ? '0' : Date::getTimestampFromTimeString($value . ' GMT') * 1000;
            }

            // Add to the combined array
            $combined[] = array(
                'name' => $setting['name'],
                'value' => $value,
                'type' => $setting['type']
            );
        }

        // Recursively merge the arrays and update
        $displayProfile->config = $combined;

        $displayProfile->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $displayProfile->name),
            'id' => $displayProfile->displayProfileId,
            'data' => [$displayProfile]
        ]);
    }

    /**
     * Delete Form
     * @param int $displayProfileId
     * @throws \Xibo\Exception\NotFoundException
     */
    function deleteForm($displayProfileId)
    {
        // Create a form out of the config object.
        $displayProfile = DisplayProfileFactory::getById($displayProfileId);

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId)
            throw new AccessDeniedException(__('You do not have permission to edit this profile'));

        $this->getState()->template = 'displayprofile-form-delete';
        $this->getState()->setData([
            'displayProfile' => $displayProfile,
            'help' => Help::Link('DisplayProfile', 'Delete')
        ]);
    }

    /**
     * Delete Display Profile
     * @param int $displayProfileId
     */
    function delete($displayProfileId)
    {
        // Create a form out of the config object.
        $displayProfile = DisplayProfileFactory::getById($displayProfileId);

        if ($this->getUser()->userTypeId != 1 && $this->getUser()->userId != $displayProfile->userId)
            throw new AccessDeniedException(__('You do not have permission to edit this profile'));

        $displayProfile->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $displayProfile->name)
        ]);
    }
}