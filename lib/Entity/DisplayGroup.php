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

    // Child Items the Display Group is linked to
    private $displays = [];
    private $media = [];
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
        foreach (DisplayFactory::getByDisplayGroupId($this->displayGroupId) as $display) {
            /* @var Display $display */
            $display->setMediaIncomplete();
            $display->save(['validate' => false, 'audit' => false]);
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
     * Assign Media
     * @param Media $media
     */
    public function assignMedia($media)
    {
        $this->load();

        if (!in_array($media, $this->media))
            $this->media[] = $media;
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
     * Load the contents for this display group
     */
    public function load()
    {
        if ($this->loaded || $this->displayGroupId == null || $this->displayGroupId == 0)
            return;

        $this->permissions = PermissionFactory::getByObjectId(get_class($this), $this->displayGroupId);

        $this->displays = DisplayFactory::getByDisplayGroupId($this->displayGroupId);

        $this->media = MediaFactory::getByDisplayGroupId($this->displayGroupId);

        $this->events = ScheduleFactory::getByDisplayGroupId($this->displayGroupId);

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

        if ($this->displayGroupId == null || $this->displayGroupId == 0) {
            $this->add();
            $this->loaded = true;
        }
        else
            $this->edit();

        Log::debug('Manage links to Display Group');

        if ($this->loaded) {
            // Handle any changes in the displays linked
            $this->linkDisplays();
            $this->unlinkDisplays();

            // Handle any changes in the media linked
            $this->linkMedia();
            $this->unlinkMedia();
        }
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
        $this->media = [];

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
        Log::debug('Updating Display Group. %s, %d', $this->displayGroup, $this->displayGroupId);

        PDOConnect::update('UPDATE displaygroup SET DisplayGroup = :displayGroup, Description = :description WHERE DisplayGroupID = :displayGroupId', [
            'displayGroup' => $this->displayGroup,
            'description' => $this->description,
            'displayGroupId' => $this->displayGroupId
        ]);
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
}