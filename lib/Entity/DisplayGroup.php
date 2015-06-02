<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayGroup.php)
 */


namespace Xibo\Entity;


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

    }

    public function delete()
    {

    }

    private function add()
    {

    }

    private function edit()
    {

    }
}