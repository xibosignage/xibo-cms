<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayGroup.php)
 */


namespace Xibo\Entity;


use Respect\Validation\Validator as v;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Helper\Log;
use Xibo\Storage\PDOConnect;

class DisplayGroup
{
    use EntityTrait;
    public $displayGroupId;
    public $displayGroup;
    public $description;
    public $isDisplaySpecific = 0;

    // Child Items the Display Group is linked to
    private $displayIds = [];
    private $mediaIds = [];

    // Child Items the Display Group is linked from
    private $permissions = [];
    private $events = [];

    public function getId()
    {
        return $this->displayGroupId;
    }

    public function getOwnerId()
    {
        return 1;
    }

    /**
     * Set the Owner of this Group
     * @param int $displayId
     */
    public function setOwner($displayId)
    {
        $this->isDisplaySpecific = 1;
        $this->assignDisplay($displayId);
    }

    /**
     * Set the Media Status to Incomplete
     */
    public function setMediaIncomplete()
    {
        foreach (DisplayFactory::getByDisplayGroupId($this->displayGroupId) as $display) {
            /* @var Display $display */
            $display->setMediaIncomplete();
            $display->save(false);
        }
    }

    /**
     * Assign Display
     * @param int $displayId
     */
    public function assignDisplay($displayId)
    {
        if (!in_array($displayId, $this->displayIds))
            $this->displayIds[] = $displayId;
    }

    /**
     * Unassign Display
     * @param int $displayId
     */
    public function unassignDisplay($displayId)
    {
        $this->displayIds = array_diff($this->displayIds, [$displayId]);
    }

    /**
     * Assign Media
     * @param int $mediaId
     */
    public function assignMedia($mediaId)
    {
        if (!in_array($mediaId, $this->mediaIds))
            $this->mediaIds[] = $mediaId;
    }

    /**
     * Unassign Media
     * @param int $mediaId
     */
    public function unassignMedia($mediaId)
    {
        $this->mediaIds = array_diff($this->mediaIds, [$mediaId]);
    }

    /**
     * Load the contents for this display group
     */
    public function load()
    {
        $this->permissions = PermissionFactory::getByObjectId('displaygroup', $this->displayGroupId);

        foreach (DisplayFactory::getByDisplayGroupId($this->displayGroupId) as $display) {
            /* @var Display $display */
            $this->displayIds[] = $display->displayId;
        }

        foreach (MediaFactory::getByDisplayGroupId($this->displayGroupId) as $media) {
            /* @var Media $media */
            $this->mediaIds[] = $media->mediaId;
        }

        $this->events = ScheduleFactory::getByDisplayGroupId($this->displayGroupId);
    }

    /**
     * Validate this display
     */
    public function validate()
    {
        if (!v::string()->notEmpty()->validate($this->displayGroup))
            throw new \InvalidArgumentException(__('Please enter a display group name'));

        if (!v::string()->length(0, 254)->validate($this->description))
            throw new \InvalidArgumentException(__('Description can not be longer than 254 characters'));

        if ($this->isDisplaySpecific == 0) {
            // Check the name
            $result = PDOConnect::select('SELECT DisplayGroup FROM displaygroup WHERE DisplayGroup = :displayGroup AND IsDisplaySpecific = 0 AND displayGroupId <> :displayGroupId', [
                'displayGroup' => $this->displayGroup,
                'displayGroupId' => (($this->displayGroupId == null) ? 0 : $this->displayGroupId)
            ]);

            if (count($result) > 0)
                throw new \InvalidArgumentException(sprintf(__('You already own a display group called "%s". Please choose another name.'), $this->displayGroup));
        }
    }

    /**
     * Save
     * @param bool $validate
     */
    public function save($validate = true)
    {
        if ($validate)
            $this->validate();

        if ($this->displayGroupId == null || $this->displayGroupId == 0)
            $this->add();
        else
            $this->edit();

        Log::debug('Manage links to Display Group');

        // Handle any changes in the displays linked
        $this->linkDisplays();
        $this->unlinkDisplays();

        // Handle any changes in the media linked
        $this->linkMedia();
        $this->unlinkMedia();
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
        $this->unlinkDisplays();
        $this->unlinkMedia();
    }

    private function add()
    {
        $this->displayGroupId = PDOConnect::insert('
          INSERT INTO displaygroup (DisplayGroup, IsDisplaySpecific, Description)
            VALUES (:displayGroup, :isDisplaySpecific, :description)
        ', [
            'displayGroup' => $this->displayGroup,
            'isDisplaySpecific' => $this->isDisplaySpecific,
            'description' => $this->description
        ]);
    }

    private function edit()
    {
        PDOConnect::update('UPDATE displaygroup SET DisplayGroup = :displayGroup, Description = :description WHERE DisplayGroupID = :displayGroupId', [
            'displayGroup' => $this->displayGroup,
            'description' => $this->description,
            'displayGroupId' => $this->displayGroupId
        ]);
    }

    private function linkDisplays()
    {
        foreach ($this->displayIds as $displayId) {
            PDOConnect::update('INSERT INTO lkdisplaydg (DisplayGroupID, DisplayID) VALUES (:displayGroupId, :displayId) ON DUPLICATE KEY UPDATE DisplayID = DisplayID', [
                'displayGroupId' => $this->displayGroupId,
                'displayId' => $displayId
            ]);
        }
    }

    private function unlinkDisplays()
    {
        // Unlink any displays that are NOT in the collection
        if (count($this->displayIds) <= 0)
            $this->displayIds = [0];

        $params = ['displayGroupId' => $this->displayGroupId];

        $sql = 'DELETE FROM lkdisplaydg WHERE DisplayGroupID = :displayGroupId AND DisplayID NOT IN (';

        $i = 0;
        foreach ($this->displayIds as $displayId) {
            $i++;
            $sql .= ':displayId' . $i;
            $params['displayId' . $i] = $displayId;
        }

        $sql .= ')';

        Log::sql($sql, $params);
        PDOConnect::update($sql, $params);
    }

    private function linkMedia()
    {
        foreach ($this->mediaIds as $mediaId) {
            PDOConnect::update('INSERT INTO `lkmediadisplaygroup` (mediaid, displaygroupid) VALUES (:mediaId, :displayGroupId) ON DUPLICATE KEY UPDATE mediaid = mediaid', [
                'displayGroupId' => $this->displayGroupId,
                'mediaId' => $mediaId
            ]);
        }
    }

    private function unlinkMedia()
    {
        foreach ($this->mediaIds as $mediaId) {
            PDOConnect::update('DELETE FROM `lkmediadisplaygroup` WHERE displaygroupid = :displayGroupId AND mediaId = :mediaId', [
                'displayGroupId' => $this->displayGroupId,
                'mediaId' => $mediaId
            ]);
        }
    }
}