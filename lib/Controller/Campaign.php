<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2012-2014 Daniel Garner
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
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Theme;


class Campaign extends Base
{
    public function displayPage()
    {
        $this->getState()->template = 'campaign-page';
    }

    /**
     * Returns a Grid of Campaigns
     */
    public function grid()
    {
        $user = $this->getUser();

        $campaigns = $user->CampaignList();

        foreach ($campaigns as $campaign) {
            /* @var \Xibo\Entity\Campaign $campaign */

            if ($campaign->isLayoutSpecific)
                continue;

            $campaign->buttons = [];

            // Schedule Now
            $campaign->buttons[] = array(
                'id' => 'campaign_button_schedulenow',
                'url' => $this->urlFor('schedule.now.form', ['id' => $campaign->campaignId, 'from' => 'Campaign']),
                'text' => __('Schedule Now')
            );

            // Buttons based on permissions
            if ($this->getUser()->checkEditable($campaign)) {
                // Assign Layouts
                $campaign->buttons[] = array(
                    'id' => 'campaign_button_layouts',
                    'url' => $this->urlFor('campaign.layouts.form', ['id' => $campaign->campaignId]),
                    'text' => __('Layouts')
                );

                // Edit the Campaign
                $campaign->buttons[] = array(
                    'id' => 'campaign_button_edit',
                    'url' => $this->urlFor('campaign.edit.form', ['id' => $campaign->campaignId]),
                    'text' => __('Edit')
                );
            }

            if ($this->getUser()->checkDeleteable($campaign)) {
                // Delete Campaign
                $campaign->buttons[] = array(
                    'id' => 'campaign_button_delete',
                    'url' => $this->urlFor('campaign.delete.form', ['id' => $campaign->campaignId]),
                    'text' => __('Delete')
                );
            }

            if ($this->getUser()->checkPermissionsModifyable($campaign)) {
                // Permissions for Campaign
                $campaign->buttons[] = array(
                    'id' => 'campaign_button_delete',
                    'url' => $this->urlFor('user.permissions.form', ['entity' => 'Campaign', 'id' => $campaign->campaignId]),
                    'text' => __('Permissions')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($campaigns);
    }

    /**
     * Campaign Add Form
     */
    public function addForm()
    {
        $this->getState()->template = 'campaign-form-add';
        $this->getState()->setData([
            'help' => Help::Link('Campaign', 'Add')
        ]);
    }

    /**
     * Add a Campaign
     */
    public function add()
    {
        $campaign = new \Xibo\Entity\Campaign();
        $campaign->ownerId = $this->getUser()->userId;
        $campaign->campaign = Sanitize::getString('name');
        $campaign->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $campaign->campaign),
            'id' => $campaign->campaignId,
            'data' => [$campaign]
        ]);
    }

    /**
     * Campaign Edit Form
     * @param int $campaignId
     */
    public function editForm($campaignId)
    {
        $campaign = CampaignFactory::getById($campaignId);

        if (!$this->getUser()->checkEditable($campaign))
            throw new AccessDeniedException();

        $this->getState()->template = 'campaign-form-edit';
        $this->getState()->setData([
            'campaign' => $campaign,
            'help' => Help::Link('Campaign', 'Edit')
        ]);
    }

    /**
     * Edit a Campaign
     * @param int $campaignId
     */
    public function edit($campaignId)
    {
        $campaign = CampaignFactory::getById($campaignId);

        if (!$this->getUser()->checkEditable($campaign))
            throw new AccessDeniedException();

        $campaign->campaign = Sanitize::getString('name');
        $campaign->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $campaign->campaign),
            'id' => $campaign->campaignId,
            'data' => [$campaign]
        ]);
    }

    /**
     * Shows the Delete Group Form
     * @param int $campaignId
     */
    function deleteForm($campaignId)
    {
        $campaign = CampaignFactory::getById($campaignId);

        if (!$this->getUser()->checkDeleteable($campaign))
            throw new AccessDeniedException();

        $this->getState()->template = 'campaign-form-delete';
        $this->getState()->setData([
            'campaign' => $campaign,
            'help' => Help::Link('Campaign', 'Delete')
        ]);
    }

    /**
     * Delete Campaign
     * @param int $campaignId
     */
    public function delete($campaignId)
    {
        $campaign = CampaignFactory::getById($campaignId);

        if (!$this->getUser()->checkDeleteable($campaign))
            throw new AccessDeniedException();

        $campaign->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $campaign->campaign)
        ]);
    }

    /**
     * Layouts form
     * @param int $campaignId
     */
    public function layoutsForm($campaignId)
    {
        $campaign = CampaignFactory::getById($campaignId);

        if (!$this->getUser()->checkEditable($campaign))
            throw new AccessDeniedException();

        $this->getState()->template = 'campaign-form-layouts';
        $this->getState()->setData([
            'campaign' => $campaign,
            'layouts' => LayoutFactory::getByCampaignId($campaignId),
            'help' => Help::Link('Campaign', 'Layouts')
        ]);
    }

    /**
     * Sets the Members of a group
     */
    public function SetMembers()
    {
        // Check the token
        if (!Kit::CheckToken('assign_token'))
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);

        $response = $this->getState();

        $campaignObject = new Campaign();

        $campaign = \Xibo\Factory\CampaignFactory::getById(Kit::GetParam('CampaignID', _REQUEST, _INT));
        $layouts = \Kit::GetParam('LayoutID', _POST, _ARRAY, array());

        // Authenticate this user
        if (!$this->getUser()->checkEditable($campaign))
            trigger_error(__('You do not have permission to edit this campaign'), E_USER_ERROR);

        // Get all current members
        $currentMembers = \Xibo\Factory\LayoutFactory::query(null, array('campaignId' => $campaign->campaignId));

        // Flatten
        $currentLayouts = array_map(function ($element) {
            return $element->layoutId;
        }, $currentMembers);

        // Work out which ones are NEW
        $newLayouts = array_diff($currentLayouts, $layouts);

        // Check permissions to all new layouts that have been selected
        foreach ($newLayouts as $layoutId) {
            // Authenticate
            if (!$this->getUser()->checkViewable(\Xibo\Factory\LayoutFactory::getById($layoutId)))
                trigger_error(__('Your permissions to view a layout you are adding have been revoked. Please reload the Layouts form.'), E_USER_ERROR);
        }

        // Remove all current members
        $campaignObject->UnlinkAll($campaign->campaignId);

        // Add all new members
        $displayOrder = 1;

        foreach ($layouts as $layoutId) {
            // By this point everything should be authenticated
            $campaignObject->Link($campaign->campaignId, $layoutId, $displayOrder);
            $displayOrder++;
        }

        $response->SetFormSubmitResponse(__('Layouts Added to Campaign'), false);

    }

    /**
     * Displays the Library Assign form
     */
    function LayoutAssignForm()
    {
        $response = $this->getState();

        $campaign = \Xibo\Factory\CampaignFactory::getById(Kit::GetParam('CampaignID', _GET, _INT));

        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="campaign"><input type="hidden" name="q" value="LayoutAssignView">');
        Theme::Set('pager', ApplicationState::Pager($id, 'grid_pager'));

        // Get the currently assigned layouts and put them in the "well"
        $layoutsAssigned = \Xibo\Factory\LayoutFactory::query(array('lkcl.DisplayOrder'), array('campaignId' => $campaign->campaignId));

        Log::notice(count($layoutsAssigned) . ' layouts assigned already');

        $formFields = array();
        $formFields[] = Form::AddText('filter_name', __('Name'), NULL, NULL, 'l');
        $formFields[] = Form::AddText('filter_tags', __('Tags'), NULL, NULL, 't');
        Theme::Set('form_fields', $formFields);

        // Set the layouts assigned
        Theme::Set('layouts_assigned', $layoutsAssigned);
        Theme::Set('append', Theme::RenderReturn('campaign_form_layout_assign'));

        // Call to render the template
        Theme::Set('header_text', __('Choose Layouts'));
        $output = Theme::RenderReturn('grid_render');

        // Construct the Response
        $response->html = $output;
        $response->success = true;
        $response->dialogSize = true;
        $response->dialogWidth = '780px';
        $response->dialogHeight = '580px';
        $response->dialogTitle = __('Layouts on Campaign');

        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Campaign', 'Layouts') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), 'LayoutsSubmit("' . $campaign->campaignId . '")');


    }

    /**
     * Show the library
     */
    function LayoutAssignView()
    {
        $response = $this->getState();

        // Input vars
        $name = \Xibo\Helper\Sanitize::getString('filter_name');
        $tags = \Xibo\Helper\Sanitize::getString('filter_tags');

        // Get a list of media
        $layoutList = $this->getUser()->LayoutList(NULL, array('layout' => $name, 'tags' => $tags));

        $cols = array(
            array('name' => 'layout', 'title' => __('Name'))
        );
        Theme::Set('table_cols', $cols);

        $rows = array();

        // Add some extra information
        foreach ($layoutList as $layout) {
            /* @var \Xibo\Entity\Layout $layout */

            $row = array();
            $row['layoutid'] = $layout->layoutId;
            $row['layout'] = $layout->layout;

            $row['list_id'] = 'LayoutID_' . $row['layoutid'];
            $row['assign_icons'][] = array(
                'assign_icons_class' => 'layout_assign_list_select'
            );
            $row['dataAttributes'] = array(
                array('name' => 'rowid', 'value' => $row['list_id']),
                array('name' => 'litext', 'value' => $row['layout'])
            );

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);

        // Render the Theme
        $response->SetGridResponse(Theme::RenderReturn('table_render'));
        $response->callBack = 'LayoutAssignCallback';
        $response->pageSize = 5;

    }
}
