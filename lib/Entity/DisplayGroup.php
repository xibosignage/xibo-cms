<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayGroup.php)
 */


namespace Xibo\Entity;


use Respect\Validation\Validator as v;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Helper\Log;
use Xibo\Storage\PDOConnect;

/**
 * Class DisplayGroup
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class DisplayGroup implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(
     *  description="The displayGroup Id"
     * )
     * @var int
     */
    public $displayGroupId;

    /**
     * @SWG\Property(
     *  description="The displayGroup Name"
     * )
     * @var string
     */
    public $displayGroup;

    /**
     * @SWG\Property(
     *  description="The displayGroup Description"
     * )
     * @var string
     */
    public $description;

    /**
     * @SWG\Property(
     *  description="A flag indicating whether this displayGroup is a single display displayGroup",
     * )
     * @var int
     */
    public $isDisplaySpecific = 0;

    /**
     * @SWG\Property(
     *  description="A flag indicating whether this displayGroup is dynamic",
     * )
     * @var int
     */
    public $isDynamic = 0;

    /**
     * @SWG\Property(
     *  description="A flag indicating whether this displayGroup is dynamic",
     * )
     * @var int
     */
    public $dynamicCriteria;

    /**
     * @SWG\Property(
     *  description="The UserId who owns this display group",
     * )
     * @var int
     */
    public $userId = 0;

    // Child Items the Display Group is linked to
    private $displays = [];
    private $displayGroups = [];
    private $layouts = [];
    private $media = [];
    private $permissions = [];
    private $events = [];

    // Track original assignments
    private $originalDisplayGroups = [];

    /**
     * Is notify required during save?
     * @var bool
     */
    private $notifyRequired = false;

    /**
     * Is collect required?
     * @var bool
     */
    private $collectRequired = false;

    public function getId()
    {
        return $this->displayGroupId;
    }

    public function getOwnerId()
    {
        return $this->userId;
    }

    /**
     * Set Notify Required
     * @param bool|true $collectRequired
     */
    public function setCollectRequired($collectRequired = true)
    {
        $this->collectRequired = $collectRequired;
    }

    /**
     * Set the Owner of this Group
     * @param Display $display
     */
    public function setOwner($display)
    {
        $this->load();

        $this->isDisplaySpecific = 1;
        $this->assignDisplay($display);
    }

    /**
     * Set the Media Status to Incomplete
     */
    public function setMediaIncomplete()
    {
        foreach ((new DisplayFactory($this->getApp()))->getByDisplayGroupId($this->displayGroupId) as $display) {
            /* @var Display $display */
            $display->setMediaIncomplete();
            $display->setCollectRequired($this->collectRequired);
            $display->save(['validate' => false, 'audit' => false, 'triggerDynamicDisplayGroupAssessment' => false]);
        }
    }

    /**
     * Assign Display
     * @param Display $display
     */
    public function assignDisplay($display)
    {
        $this->load();

        if (!in_array($display, $this->displays))
            $this->displays[] = $display;
    }

    /**
     * Unassign Display
     * @param Display $display
     */
    public function unassignDisplay($display)
    {
        $this->load();

        $this->displays = array_udiff($this->displays, [$display], function($a, $b) {
            /**
             * @var Display $a
             * @var Display $b
             */
            return $a->getId() - $b->getId();
        });
    }

    /**
     * Assign DisplayGroup
     * @param DisplayGroup $displayGroup
     */
    public function assignDisplayGroup($displayGroup)
    {
        $this->load();

        if (!in_array($displayGroup, $this->displayGroups))
            $this->displayGroups[] = $displayGroup;
    }

    /**
     * Unassign DisplayGroup
     * @param DisplayGroup $displayGroup
     */
    public function unassignDisplayGroup($displayGroup)
    {
        $this->load();

        $this->displayGroups = array_udiff($this->displayGroups, [$displayGroup], function($a, $b) {
            /**
             * @var DisplayGroup $a
             * @var DisplayGroup $b
             */
            return $a->getId() - $b->getId();
        });
    }

    /**
     * Assign Media
     * @param Media $media
     */
    public function assignMedia($media)
    {
        $this->load();

        if (!in_array($media, $this->media)) {
            $this->media[] = $media;

            // We should notify
            $this->notifyRequired = true;
        }
    }

    /**
     * Unassign Media
     * @param Media $media
     */
    public function unassignMedia($media)
    {
        $this->load();

        $this->media = array_udiff($this->media, [$media], function($a, $b) {
            /**
             * @var Media $a
             * @var Media $b
             */
            return $a->getId() - $b->getId();
        });
    }

    /**
     * Assign Layout
     * @param Layout $layout
     */
    public function assignLayout($layout)
    {
        $this->load();

        if (!in_array($layout, $this->layouts)) {
            $this->layouts[] = $layout;

            // We should notify
            $this->notifyRequired = true;
        }
    }

    /**
     * Unassign Layout
     * @param Layout $layout
     */
    public function unassignLayout($layout)
    {
        $this->load();

        $this->layouts = array_udiff($this->layouts, [$layout], function($a, $b) {
            /**
             * @var Layout $a
             * @var Layout $b
             */
            return $a->getId() - $b->getId();
        });
    }

    /**
     * Load the contents for this display group
     */
    public function load()
    {
        if ($this->loaded || $this->displayGroupId == null || $this->displayGroupId == 0)
            return;

        $this->permissions = (new PermissionFactory($this->getApp()))->getByObjectId(get_class($this), $this->displayGroupId);

        $this->displays = (new DisplayFactory($this->getApp()))->getByDisplayGroupId($this->displayGroupId);

        $this->displayGroups = (new DisplayGroupFactory($this->getApp()))->getByParentId($this->displayGroupId);

        $this->layouts = (new LayoutFactory($this->getApp()))->getByDisplayGroupId($this->displayGroupId);

        $this->media = (new MediaFactory($this->getApp()))->getByDisplayGroupId($this->displayGroupId);

        $this->events = (new ScheduleFactory($this->getApp()))->getByDisplayGroupId($this->displayGroupId);

        // Set the originals
        $this->originalDisplayGroups = $this->displayGroups;

        // We are loaded
        $this->loaded = true;
    }

    /**
     * Validate this display
     */
    public function validate()
    {
        if (!v::string()->notEmpty()->validate($this->displayGroup))
            throw new \InvalidArgumentException(__('Please enter a display group name'));

        if (!empty($this->description) && !v::string()->length(null, 254)->validate($this->description))
            throw new \InvalidArgumentException(__('Description can not be longer than 254 characters'));

        if ($this->isDisplaySpecific == 0) {
            // Check the name
            $result = PDOConnect::select('SELECT DisplayGroup FROM displaygroup WHERE DisplayGroup = :displayGroup AND IsDisplaySpecific = 0 AND displayGroupId <> :displayGroupId', [
                'displayGroup' => $this->displayGroup,
                'displayGroupId' => (($this->displayGroupId == null) ? 0 : $this->displayGroupId)
            ]);

            if (count($result) > 0)
                throw new \InvalidArgumentException(sprintf(__('You already own a display group called "%s". Please choose another name.'), $this->displayGroup));

            // If we are dynamic, then make sure we have some criteria
            if ($this->isDynamic == 1 && $this->dynamicCriteria == '')
                throw new \InvalidArgumentException(__('Dynamic Display Groups must have at least one Criteria specified.'));
        }
    }

    /**
     * Save
     * @param array $options
     */
    public function save($options = [])
    {
        $options = array_merge([
            'validate' => true,
            'saveGroup' => true,
            'manageDisplayLinks' => true
        ], $options);

        if ($options['validate'])
            $this->validate();

        if ($this->displayGroupId == null || $this->displayGroupId == 0) {
            $this->add();
            $this->loaded = true;
        }
        else if ($options['saveGroup'])
            $this->edit();

        if ($this->loaded && $options['manageDisplayLinks']) {
            Log::debug('Manage links to Display Group');

            // Handle any changes in the displays linked
            $this->manageDisplayLinks();

            // Handle any changes in the media linked
            $this->linkMedia();
            $this->unlinkMedia();

            // Handle any changes in the layouts linked
            $this->linkLayouts();
            $this->unlinkLayouts();

            // Handle any group links
            $this->manageDisplayGroupLinks();

        } else if ($this->isDynamic && $options['manageDisplayLinks']) {
            $this->manageDisplayLinks();
        }

        // Set media incomplete if necessary
        if ($this->notifyRequired)
            $this->setMediaIncomplete();
    }

    /**
     * Delete
     */
    public function delete()
    {
        // Load everything for the delete
        $this->load();

        // Delete things this group can own
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->delete();
        }

        foreach ($this->events as $event) {
            /* @var Schedule $event */
            $event->delete();
        }

        // Delete assignments
        $this->removeAssignments();

        // Delete the Group itself
        PDOConnect::update('DELETE FROM `displaygroup` WHERE DisplayGroupID = :displayGroupId', ['displayGroupId' => $this->displayGroupId]);
    }

    /**
     * Remove any assignments
     */
    public function removeAssignments()
    {
        $this->displays = [];
        $this->displayGroups = [];
        $this->layouts = [];
        $this->media = [];

        $this->unlinkDisplays();
        $this->unlinkAllDisplayGroups();
        $this->unlinkLayouts();
        $this->unlinkMedia();
    }

    private function add()
    {
        $this->displayGroupId = PDOConnect::insert('
          INSERT INTO displaygroup (DisplayGroup, IsDisplaySpecific, Description, `isDynamic`, `dynamicCriteria`, `userId`)
            VALUES (:displayGroup, :isDisplaySpecific, :description, :isDynamic, :dynamicCriteria, :userId)
        ', [
            'displayGroup' => $this->displayGroup,
            'isDisplaySpecific' => $this->isDisplaySpecific,
            'description' => $this->description,
            'isDynamic' => $this->isDynamic,
            'dynamicCriteria' => $this->dynamicCriteria,
            'userId' => $this->userId
        ]);

        // Insert my self link
        PDOConnect::insert('INSERT INTO `lkdgdg` (`parentId`, `childId`, `depth`) VALUES (:parentId, :childId, 0)', [
            'parentId' => $this->displayGroupId,
            'childId' => $this->displayGroupId
        ]);
    }

    private function edit()
    {
        Log::debug('Updating Display Group. %s, %d', $this->displayGroup, $this->displayGroupId);

        PDOConnect::update('
          UPDATE displaygroup
            SET DisplayGroup = :displayGroup,
              Description = :description,
              `isDynamic` = :isDynamic,
              `dynamicCriteria` = :dynamicCriteria,
              `userId` = :userId
           WHERE DisplayGroupID = :displayGroupId
          ', [
            'displayGroup' => $this->displayGroup,
            'description' => $this->description,
            'displayGroupId' => $this->displayGroupId,
            'isDynamic' => $this->isDynamic,
            'dynamicCriteria' => $this->dynamicCriteria,
            'userId' => $this->userId
        ]);
    }

    /**
     * Manage the links to this display, dynamic or otherwise
     */
    private function manageDisplayLinks()
    {
        if ($this->isDynamic) {

            Log::info('Managing Display Links for Dynamic Display Group %s', $this->displayGroup);

            $originalDisplays = ($this->loaded) ? $this->displays : (new DisplayFactory($this->getApp()))->getByDisplayGroupId($this->displayGroupId);

            // Update the linked displays based on the filter criteria
            // these displays must be permission checked based on the owner of the group NOT the logged in user
            $this->displays = (new DisplayFactory($this->getApp()))->query(null, ['display' => $this->dynamicCriteria, 'userCheckUserId' => $this->getOwnerId()]);

            Log::debug('There are %d original displays and %d displays that match the filter criteria now.', count($originalDisplays), count($this->displays));

            $difference = array_udiff($originalDisplays, $this->displays, function ($a, $b) {
                /**
                 * @var Display $a
                 * @var Display $b
                 */
                return $a->getId() - $b->getId();
            });

            $this->notifyRequired = (count($difference) >= 0);
        }

        $this->linkDisplays();
        $this->unlinkDisplays();
    }

    /**
     * Manage display group links
     */
    private function manageDisplayGroupLinks()
    {
        $this->linkDisplayGroups();
        $this->unlinkDisplayGroups();

        // Check for circular references
        // this is a lazy last minute check as we can't really tell if there is a circular reference unless
        // we've inserted the records already.
        if (PDOConnect::exists('SELECT depth FROM `lkdgdg` WHERE parentId = :parentId AND childId = parentId AND depth > 0', ['parentId' => $this->displayGroupId]))
            throw new \InvalidArgumentException(__('This assignment creates a circular reference'));
    }

    private function linkDisplays()
    {
        foreach ($this->displays as $display) {
            /* @var Display $display */
            PDOConnect::update('INSERT INTO lkdisplaydg (DisplayGroupID, DisplayID) VALUES (:displayGroupId, :displayId) ON DUPLICATE KEY UPDATE DisplayID = DisplayID', [
                'displayGroupId' => $this->displayGroupId,
                'displayId' => $display->displayId
            ]);
        }
    }

    private function unlinkDisplays()
    {
        // Unlink any displays that are NOT in the collection
        $params = ['displayGroupId' => $this->displayGroupId];

        $sql = 'DELETE FROM lkdisplaydg WHERE DisplayGroupID = :displayGroupId AND DisplayID NOT IN (0';

        $i = 0;
        foreach ($this->displays as $display) {
            /* @var Display $display */
            $i++;
            $sql .= ',:displayId' . $i;
            $params['displayId' . $i] = $display->displayId;
        }

        $sql .= ')';

        PDOConnect::update($sql, $params);
    }

    /**
     * Links the display groups that have been added to the OM
     * adding them to the closure table `lkdgdg`
     */
    private function linkDisplayGroups()
    {
        $links = array_udiff($this->displayGroups, $this->originalDisplayGroups, function($a, $b) {
            /**
             * @var DisplayGroup $a
             * @var DisplayGroup $b
             */
            return $a->getId() - $b->getId();
        });

        Log::debug('Linking %d display groups to Display Group %s', count($links), $this->displayGroup);

        foreach ($links as $displayGroup) {
            /* @var DisplayGroup $displayGroup */
            PDOConnect::insert('
                INSERT INTO lkdgdg (parentId, childId, depth)
                SELECT p.parentId, c.childId, p.depth + c.depth + 1
                  FROM lkdgdg p, lkdgdg c
                 WHERE p.childId = :parentId AND c.parentId = :childId
            ', [
                'parentId' => $this->displayGroupId,
                'childId' => $displayGroup->displayGroupId
            ]);
        }
    }

    /**
     * Unlinks the display groups that have been removed from the OM
     * removing them from the closure table `lkdgdg`
     */
    private function unlinkDisplayGroups()
    {
        $links = array_udiff($this->originalDisplayGroups, $this->displayGroups, function($a, $b) {
            /**
             * @var DisplayGroup $a
             * @var DisplayGroup $b
             */
            return $a->getId() - $b->getId();
        });

        Log::debug('Unlinking %d display groups to Display Group %s', count($links), $this->displayGroup);

        foreach ($links as $displayGroup) {
            /* @var DisplayGroup $displayGroup */
            PDOConnect::update('
                DELETE link
                  FROM `lkdgdg` p, `lkdgdg` link, `lkdgdg` c
                 WHERE p.parentId = link.parentId AND c.childId = link.childId
                   AND p.childId = :parentId AND c.parentId = :childId
            ', [
                'parentId' => $this->displayGroupId,
                'childId' => $displayGroup->displayGroupId
            ]);
        }
    }

    /**
     * Unlinks all display groups
     * usually in preparation for a delete
     */
    private function unlinkAllDisplayGroups()
    {
        PDOConnect::update('
            DELETE link
              FROM `lkdgdg` p, `lkdgdg` link, `lkdgdg` c, `lkdgdg` to_delete
             WHERE p.parentId = link.parentId AND c.childId = link.childId
               AND p.childId  = to_delete.parentId AND c.parentId = to_delete.childId
               AND (to_delete.parentId = :parentId OR to_delete.childId = :childId)
               AND to_delete.depth < 2
        ', [
            'parentId' => $this->displayGroupId,
            'childId' => $this->displayGroupId
        ]);
    }

    private function linkMedia()
    {
        foreach ($this->media as $media) {
            /* @var Media $media */
            PDOConnect::update('INSERT INTO `lkmediadisplaygroup` (mediaid, displaygroupid) VALUES (:mediaId, :displayGroupId) ON DUPLICATE KEY UPDATE mediaid = mediaid', [
                'displayGroupId' => $this->displayGroupId,
                'mediaId' => $media->mediaId
            ]);
        }
    }

    private function unlinkMedia()
    {
        // Unlink any media that is NOT in the collection
        $params = ['displayGroupId' => $this->displayGroupId];

        $sql = 'DELETE FROM `lkmediadisplaygroup` WHERE DisplayGroupID = :displayGroupId AND mediaId NOT IN (0';

        $i = 0;
        foreach ($this->media as $media) {
            /* @var Media $media */
            $i++;
            $sql .= ',:mediaId' . $i;
            $params['mediaId' . $i] = $media->mediaId;
        }

        $sql .= ')';

        PDOConnect::update($sql, $params);
    }

    private function linkLayouts()
    {
        foreach ($this->layouts as $layout) {
            /* @var Layout $media */
            PDOConnect::update('INSERT INTO `lklayoutdisplaygroup` (layoutid, displaygroupid) VALUES (:layoutId, :displayGroupId) ON DUPLICATE KEY UPDATE layoutid = layoutid', [
                'displayGroupId' => $this->displayGroupId,
                'layoutId' => $layout->layoutId
            ]);
        }
    }

    private function unlinkLayouts()
    {
        // Unlink any layout that is NOT in the collection
        $params = ['displayGroupId' => $this->displayGroupId];

        $sql = 'DELETE FROM `lklayoutdisplaygroup` WHERE DisplayGroupID = :displayGroupId AND layoutId NOT IN (0';

        $i = 0;
        foreach ($this->layouts as $layout) {
            /* @var Layout $layout */
            $i++;
            $sql .= ',:layoutId' . $i;
            $params['layoutId' . $i] = $layout->layoutId;
        }

        $sql .= ')';

        PDOConnect::update($sql, $params);
    }
}