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
use Xibo\Exception\InvalidArgumentException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\TagFactory;
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

    public $tags = [];
    
    private $layouts = [];
    private $permissions = [];
    private $events = [];
    
    // Private
    private $unassignTags = [];

    /** @var bool Have the Layout assignments changed? */
    private $layoutAssignmentsChanged = false;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;
    
    /**
     * @var TagFactory
     */
    private $tagFactory;

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
     * @param TagFactory $tagFactory
     */
    public function __construct($store, $log, $permissionFactory, $scheduleFactory, $displayFactory, $tagFactory)
    {
        $this->setCommonDependencies($store, $log);
        $this->permissionFactory = $permissionFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->displayFactory = $displayFactory;
        $this->tagFactory = $tagFactory;
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

    public function load($options = [])
    {
        $options = array_merge([
            'loadPermissions' => true,
            'loadLayouts' => true,
            'loadTags' => true,
            'loadEvents' => true
        ], $options);
        
        // If we are already loaded, then don't do it again
        if ($this->campaignId == null || $this->loaded)
            return;

        if ($this->layoutFactory == null)
            throw new \RuntimeException('Cannot load campaign with all objects without first calling setChildObjectDependencies');

        // Permissions
        if ($options['loadPermissions'])
            $this->permissions = $this->permissionFactory->getByObjectId('Campaign', $this->campaignId);

        // Layouts
        if ($options['loadLayouts'])
            $this->layouts = $this->layoutFactory->getByCampaignId($this->campaignId);
            
        // Load all tags
        if ($options['loadTags'])
            $this->tags = $this->tagFactory->loadByCampaignId($this->campaignId);

        // Events
        if ($options['loadEvents'])
            $this->events = $this->scheduleFactory->getByCampaignId($this->campaignId);

        $this->loaded = true;
    }

    public function validate()
    {
        if (!v::string()->notEmpty()->validate($this->campaign))
            throw new InvalidArgumentException(__('Name cannot be empty'), 'name');
    }
    
    
    /**
     * Does the campaign have the provided tag?
     * @param $searchTag
     * @return bool
     */
    public function hasTag($searchTag)
    {
        $this->load();

        foreach ($this->tags as $tag) {
            /* @var Tag $tag */
            if ($tag->tag == $searchTag)
                return true;
        }

        return false;
    }

    /**
     * Assign Tag
     * @param Tag $tag
     * @return $this
     */
    public function assignTag($tag)
    {
        $this->load();

        if (!in_array($tag, $this->tags))
            $this->tags[] = $tag;

        return $this;
    }

    /**
     * Unassign tag
     * @param Tag $tag
     * @return $this
     */
    public function unassignTag($tag)
    {
        $this->tags = array_udiff($this->tags, [$tag], function($a, $b) {
            /* @var Tag $a */
            /* @var Tag $b */
            return $a->tagId - $b->tagId;
        });

        return $this;
    }

    /**
     * @param array[Tag] $tags
     */
    public function replaceTags($tags = [])
    {
        if (!is_array($this->tags) || count($this->tags) <= 0)
            $this->tags = $this->tagFactory->loadByCampaignId($this->campaignId);

        $this->unassignTags = array_udiff($this->tags, $tags, function($a, $b) {
            /* @var Tag $a */
            /* @var Tag $b */
            return $a->tagId - $b->tagId;
        });

        $this->getLog()->debug('Tags to be removed: %s', json_encode($this->unassignTags));

        // Replace the arrays
        $this->tags = $tags;

        $this->getLog()->debug('Tags remaining: %s', json_encode($this->tags));
    }

    /**
     * Save this Campaign
     * @param array $options
     */
    public function save($options = [])
    {
        
        $options = array_merge([
            'validate' => true,
            'notify' => true,
            'saveTags' => true
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
        
            
        // Save the tags
        if (is_array($this->tags)) {
            foreach ($this->tags as $tag) {
                /* @var Tag $tag */

                $this->getLog()->debug('Assigning tag %s', $tag->tag);

                $tag->assignCampaign($this->campaignId);
                $tag->save();
            }
        }

        // Remove unwanted ones
        if (is_array($this->unassignTags)) {
            foreach ($this->unassignTags as $tag) {
                /* @var Tag $tag */
                $this->getLog()->debug('Unassigning tag %s', $tag->tag);

                $tag->unassignCampaign($this->campaignId);
                $tag->save();
            }
        }

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
        
        // Unassign all Tags
        foreach ($this->tags as $tag) {
            /* @var Tag $tag */
            $tag->unassignCampaign($this->campaignId);
            $tag->save();
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

        $layout->displayOrder = ($layout->displayOrder == null || $layout->displayOrder == 0) ? count($this->layouts) + 1 : $layout->displayOrder;

        $found = false;
        foreach ($this->layouts as $existingLayout) {
            if ($existingLayout->getId() === $layout->getId() && $existingLayout->displayOrder === $layout->displayOrder) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            $this->layoutAssignmentsChanged = true;
            $this->layouts[] = $layout;
        }
    }

    /**
     * Unassign Layout
     * @param Layout $layout
     */
    public function unassignLayout($layout)
    {
        $this->load();

        $countBefore = count($this->layouts);
        $this->getLog()->debug('Unassigning Layout [%s] from Campaign [%s]. Display Order %d. Count before assign = %d', $layout, $this, $layout->displayOrder, $countBefore);

        $this->layouts = array_udiff($this->layouts, [$layout], function ($a, $b) {
            /**
             * @var Layout $a
             * @var Layout $b
             */
            // Are we a layout that has been configured with a display order, or are we a complete layout removal?
            if ($a->displayOrder == null || $b->displayOrder == null)
                $return = ($a->getId() - $b->getId());
            else
                $return = ($a->getId() - $b->getId()) + ($a->displayOrder - $b->displayOrder);

            //$this->getLog()->debug('Comparing a [%d, %d] with b [%d, %d]. Return = %d', $a->layoutId, $a->displayOrder, $b->layoutId, $b->displayOrder, $return);
            return $return;
        });

        $countAfter = count($this->layouts);
        $this->getLog()->debug('Count after unassign = %d', $countAfter);

        if ($countBefore !== $countAfter)
            $this->layoutAssignmentsChanged = true;
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
        if ($this->layoutAssignmentsChanged) {
            $this->getLog()->debug('Managing Assignments on ' . $this);
            $this->unlinkLayouts();
            $this->linkLayouts();
        } else {
            $this->getLog()->debug('Assignments have not changed on ' . $this);
        }
    }

    /**
     * Link Layout
     */
    private function linkLayouts()
    {
        // Don't do anything if we don't have any layouts
        if (count($this->layouts) <= 0)
            return;

        // Sort the layouts by their display order
        usort($this->layouts, function($a, $b) {
            /** @var Layout $a */
            /** @var Layout $b */
            if ($a->displayOrder === null)
                return 1;

            if ($a->displayOrder === $b->displayOrder)
                return 0;

            return ($a->displayOrder < $b->displayOrder) ? -1 : 1;
        });

        // Update the layouts, in order to have display order 1 to n
        $i = 0;
        $sql = 'INSERT INTO `lkcampaignlayout` (CampaignID, LayoutID, DisplayOrder) VALUES ';
        $params = [];

        foreach ($this->layouts as $layout) {
            $i++;
            $layout->displayOrder = $i;

            $sql .= '(:campaignId_' . $i . ', :layoutId_' . $i . ', :displayOrder_' . $i . '),';
            $params['campaignId_' . $i] = $this->campaignId;
            $params['layoutId_' . $i] = $layout->layoutId;
            $params['displayOrder_' . $i] = $layout->displayOrder;
        }

        $sql = rtrim($sql, ',');

        $this->getStore()->update($sql, $params);
    }

    /**
     * Unlink Layout
     */
    private function unlinkLayouts()
    {
        // Delete all the links
        $this->getStore()->update('DELETE FROM `lkcampaignlayout` WHERE campaignId = :campaignId', ['campaignId' => $this->campaignId]);
    }

    /**
     * Notify displays of this campaign change
     */
    private function notify()
    {
        $this->getLog()->debug('CampaignId ' . $this->campaignId . ' wants to notify.');

        $this->displayFactory->getDisplayNotifyService()->collectNow()->notifyByCampaignId($this->campaignId);
    }
}
