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
use Xibo\Helper\Help;
use Xibo\Helper\Sanitize;


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
        $filter = [
            'campaignId' => Sanitize::getInt('campaignId'),
            'name' => Sanitize::getString('name'),
        ];

        $campaigns = CampaignFactory::query($this->gridRenderSort(), $this->gridRenderFilter($filter));

        foreach ($campaigns as $campaign) {
            /* @var \Xibo\Entity\Campaign $campaign */

            if ($this->isApi())
                break;

            $campaign->includeProperty('buttons');
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
     * Assigns a layout to a Campaign
     * @param int $campaignId
     */
    public function assignLayout($campaignId)
    {
        $campaign = CampaignFactory::getById($campaignId);

        if (!$this->getUser()->checkEditable($campaign))
            throw new AccessDeniedException();

        $layouts = Sanitize::getIntArray('layoutIds');

        if (count($layouts) <= 0)
            throw new \InvalidArgumentException(__('Layouts not provided'));

        // Check our permissions to see each one
        foreach ($layouts as $layoutId) {

            $layout = LayoutFactory::getById($layoutId);

            if (!$this->getUser()->checkViewable($layout))
                throw new AccessDeniedException(__('You do not have permission to assign the provided Layout'));

            // Assign it
            $campaign->assignLayout($layout);
        }

        $campaign->save(false);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Assigned Layouts to %s'), $campaign->campaign)
        ]);
    }

    /**
     * Unassign a layout to a Campaign
     * @param int $campaignId
     */
    public function unassignLayout($campaignId)
    {
        $campaign = CampaignFactory::getById($campaignId);

        if (!$this->getUser()->checkEditable($campaign))
            throw new AccessDeniedException();

        $layouts = Sanitize::getIntArray('layoutIds');

        if (count($layouts) <= 0)
            throw new \InvalidArgumentException(__('Layouts not provided'));

        // Check our permissions to see each one
        foreach ($layouts as $layoutId) {
            // Assign it
            $campaign->unassignLayouts(LayoutFactory::getById($layoutId));
        }

        $campaign->save(false);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Unassigned Layouts from %s'), $campaign->campaign)
        ]);
    }
}
