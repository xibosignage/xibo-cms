<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayGroup.php)
 */


namespace Xibo\Entity;


use Xibo\Storage\PDOConnect;

class DisplayGroup
{
    public $displayGroupId;
    public $displayGroup;
    public $description;
    public $isDisplaySpecific;

    private $displays;

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
     * Assign User
     * @param int $displayId
     */
    public function assignDisplay($displayId)
    {
        if (!in_array($displayId, $this->displays))
            $this->displays[] = $displayId;
    }

    /**
     * Unassign User
     * @param int $displayId
     */
    public function unassignDisplay($displayId)
    {
        unset($this->displays[$displayId]);
    }

    public function save()
    {
        if ($this->displayGroupId == null || $this->displayGroupId == 0)
            $this->add();
        else
            $this->edit();

        // Link displays assigned
        $this->linkDisplays();
    }

    public function delete()
    {

    }

    /**
     * Remove any assignments
     */
    public function removeAssignments()
    {
        $this->unlinkDisplays();
    }

    private function add()
    {

    }

    private function edit()
    {

    }

    private function linkDisplays()
    {
        foreach ($this->displays as $displayId) {
            PDOConnect::update('INSERT INTO lkdisplaydg (DisplayGroupID, DisplayID) VALUES (:displayGroupId, :displayId)', [
                'displayGroupId' => $this->displayGroupId,
                'displayId' => $displayId
            ]);
        }
    }

    private function unlinkDisplays()
    {
        foreach ($this->displays as $displayId) {
            PDOConnect::update('DELETE FROM lkdisplaydg WHERE DisplayGroupID = :displayGroupId AND DisplayID = :displayId', [
                'displayGroupId' => $this->displayGroupId,
                'displayId' => $displayId
            ]);
        }
    }
}