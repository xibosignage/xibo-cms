<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSet.php)
 */


namespace Xibo\Entity;


class DataSet
{
    use EntityTrait;

    public $dataSetId;
    public $dataSet;
    public $description;
    public $userId;
    public $lastDataEdit;

    // Read only properties
    public $owner;

    public function getId()
    {
        return $this->dataSetId;
    }

    public function getOwnerId()
    {
        return $this->userId;
    }
}