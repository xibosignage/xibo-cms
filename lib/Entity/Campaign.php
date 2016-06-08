<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Campaign.php) is part of Xibo.
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


namespace Xibo\Entity;

use Respect\Validation\Validator as v;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Campaign
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Campaign implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The Campaign Id")
     * @var int
     */
    public $campaignId;

    /**
     * @SWG\Property(description="The userId of the User that owns this Campaign")
     * @var int
     */
    public $ownerId;

    /**
     * @SWG\Property(description="The name of the Campaign")
     * @var string
     */
    public $campaign;

    /**
     * @SWG\Property(description="A 0|1 flag to indicate whether this is a Layout specific Campaign or not.")
     * @var int
     */
    public $isLayoutSpecific = 0;

    /**
     * @SWG\Property(description="The number of Layouts associated with this Campaign")
     * @var int
     */
    public $numberLayouts;

    /**
     * @SWG\Property(description="The total duration of the campaign (sum of layout's durations)")
     * @var int
     */
    public $totalDuration;

    private $layouts = [];
    private $permissions = [];
    private $events = [];

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var ScheduleFactory
     */
    private $scheduleFactory;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param PermissionFactory $permissionFactory
     * @param ScheduleFactory $scheduleFactory
     * @param DisplayFactory $displayFactory
     */
    public function __construct($store, $log, $permissionFactory, $scheduleFactory, $displayFactory)
    {
        $this->setCommonDependencies($store, $log);
        $this->permissionFactory = $permissionFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->displayFactory = $displayFactory;
    }

    /**
     * Set Child Object Depencendies
     *  must be set before calling Load with all objects
     * @param LayoutFactory $layoutFactory
     * @return $this
     */
    public function setChildObjectDependencies($layoutFactory)
    {
        $this->layoutFactory = $layoutFactory;
        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf('CampaignId %d, Campaign %s, LayoutSpecific %d', $this->campaignId, $this->campaign, $this->isLayoutSpecific);
    }

    /**
     * Get the Id
     * @return int
     */
    public function getId()
    {
        return $this->campaignId;
    }

    /**
     * Get the OwnerId
     * @return int
     */
    public function getOwnerId()
    {
        return $this->ownerId;
    }

    /**
     * Sets the Owner
     * @param int $ownerId
     */
    public function setOwner($ownerId)
    {
        $this->ownerId = $ownerId;
    }

    public function load()
    {
        // If we are already loaded, then don't do it again
        if ($this->campaignId == null || $this->loaded)
            return;

        if ($this->layoutFactory == null)
            throw new \RuntimeException('Cannot load campaign with all objects without first calling setChildObjectDependencies');

        // Permissions
        $this->permissions = $this->permissionFactory->getByObjectId('Campaign', $this->campaignId);

        // Layouts
        $this->layouts = $this->layoutFactory->getByCampaignId($this->campaignId);

        // Events
        $this->events = $this->scheduleFactory->getByCampaignId($this->campaignId);

        $this->loaded = true;
    }

    public function validate()
    {
        if (!v::string()->notEmpty()->validate($this->campaign))
            throw new \InvalidArgumentException(__('Name cannot be empty'));
    }

    /**
     * Save this Campaign
     * @param array $options
     */
    public function save($options = [])
    {
        $options = array_merge([
            'validate' => true,
            'notify' => true
        ], $options);

        $this->getLog()->debug('Saving %s', $this);

        if ($options['validate'])
            $this->validate();

        if ($this->campaignId == null || $this->campaignId == 0) {
            $this->add();
            $this->loaded = true;
        }
        else
            $this->update();

        if ($this->loaded) {
            // Manage assignments
            $this->manageAssignments();
        }

        // Notify anyone interested of the changes
        if ($options['notify'])
            $this->notify();
    }

    public function delete()
    {
        $this->load();

        // Unassign all Layouts
        $this->layouts = [];
        $this->unlinkLayouts();

        // Delete all permissions
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->delete();
        }

        // Delete all events
        foreach ($this->events as $event) {
            /* @var Schedule $event */
            $event->delete();
        }

        // Delete the Actual Campaign
        $this->getStore()->update('DELETE FROM `campaign` WHERE CampaignID = :campaignId', ['campaignId' => $this->campaignId]);
    }

    /**
     * Assign Layout
     * @param Layout $layout
     */
    public function assignLayout($layout)
    {
        $this->load();

        if (!in_array($layout, $this->layouts))
            $this->layouts[] = $layout;
    }

    /**
     * Unassign Layout
     * @param Layout $layout
     */
    public function unassignLayout($layout)
    {
        $this->load();

        $this->getLog()->debug('Unassigning Layout [%s] from Campaign [%s]. Display Order %d. Count before assign = %d', $layout, $this, $layout->displayOrder, count($this->layouts));

        $this->layouts = array_udiff($this->layouts, [$layout], function ($a, $b) {
            /**
             * @var Layout $a
             * @var Layout $b
             */
            $return = ($a->getId() - $b->getId()) + ($a->displayOrder - $b->displayOrder);
            $this->getLog()->debug('Comparing a [%d, %d] with b [%d, %d]. Return = %d', $a->layoutId, $a->displayOrder, $b->layoutId, $b->displayOrder, $return);
            return $return;
        });

        $this->getLog()->debug('Count after unassign = %d', count($this->layouts));
    }

    private function add()
    {
        $this->campaignId = $this->getStore()->insert('INSERT INTO `campaign` (Campaign, IsLayoutSpecific, UserId) VALUES (:campaign, :isLayoutSpecific, :userId)', array(
            'campaign' => $this->campaign,
            'isLayoutSpecific' => $this->isLayoutSpecific,
            'userId' => $this->ownerId
        ));
    }

    private function update()
    {
        $this->getStore()->update('UPDATE `campaign` SET campaign = :campaign, userId = :userId WHERE CampaignID = :campaignId', [
            'campaignId' => $this->campaignId,
            'campaign' => $this->campaign,
            'userId' => $this->ownerId
        ]);
    }

    /**
     * Manage the assignments
     */
    private function manageAssignments()
    {
        $this->getLog()->debug('Managing Assignments on %s', $this);
        $this->unlinkLayouts();
        $this->linkLayouts();
    }

    /**
     * Link Layout
     */
    private function linkLayouts()
    {
        // Don't do anything if we don't have any layouts
        if (count($this->layouts) <= 0)
            return;

        $sql = 'INSERT INTO `lkcampaignlayout` (CampaignID, LayoutID, DisplayOrder) VALUES (:campaignId, :layoutId, :displayOrder) ON DUPLICATE KEY UPDATE layoutId = :layoutId2';

        // Get the Max display order
        $displayOrders = array_map(function ($element) {
            return $element->displayOrder;
        }, $this->layouts);

        $maxDisplayOrder = max($displayOrders);

        // Fill in empty display orders
        foreach ($this->layouts as $layout) {
            if ($layout->displayOrder == null) {
                $maxDisplayOrder++;
                $layout->displayOrder = $maxDisplayOrder;
            }
        }

        foreach ($this->layouts as $layout) {

            $this->getStore()->insert($sql, array(
                'campaignId' => $this->campaignId,
                'displayOrder' => $layout->displayOrder,
                'layoutId' => $layout->layoutId,
                'layoutId2' => $layout->layoutId
            ));
        }
    }

    /**
     * Unlink Layout
     */
    private function unlinkLayouts()
    {
        // Unlink any layouts that are NOT in the collection
        $params = ['campaignId' => $this->campaignId];

        if (count($this->layouts) <= 0) {
            $sql = ' DELETE FROM `lkcampaignlayout` WHERE campaignId = :campaignId ';
        }
        else {

            $sql = '
                  SELECT lkCampaignLayoutId
                    FROM `lkcampaignlayout`
                   WHERE campaignId = :campaignId AND (
            ';

            $i = 0;
            foreach ($this->layouts as $layout) {
                $i++;

                if ($i > 1)
                    $sql .= ' OR ';

                $sql .= ' (layoutId = :layoutId' . $i . ' AND displayOrder = :displayOrder' . $i . ') ';
                $params['layoutId' . $i] = $layout->layoutId;
                $params['displayOrder' . $i] = $layout->displayOrder;
            }

            $sql .= ')';

            // Get the lkid's for the delete

            $ids = $this->getStore()->select($sql, $params);

            $ids = array_map(function ($element) {
                return $element['lkCampaignLayoutId'];
            }, $ids);

            if (count($ids) <= 0)
                $ids[] = 0;

            $sql = '
              DELETE FROM `lkcampaignlayout`
               WHERE campaignId = :campaignId
                AND lkCampaignLayoutId NOT IN (' . implode(',', $ids) . ') ';

            // Reset params to just be the campaign id
            $params = ['campaignId' => $this->campaignId];
        }



        $this->getStore()->update($sql, $params);
    }

    /**
     * Notify displays of this campaign change
     */
    private function notify()
    {
        $this->getLog()->debug('Checking for Displays to refresh on Campaign %d', $this->campaignId);

        $displays = array_merge($this->displayFactory->getByActiveCampaignId($this->campaignId), $this->displayFactory->getByAssignedCampaignId($this->campaignId));

        foreach ($displays as $display) {
            /* @var \Xibo\Entity\Display $display */
            $display->setMediaIncomplete();
            $display->setCollectRequired(true);
            $display->save(['validate' => false, 'audit' => false]);
        }
    }
}
