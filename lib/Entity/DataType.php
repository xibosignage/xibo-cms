<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataType.php)
 */


namespace Xibo\Entity;


/**
 * Class DataType
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class DataType implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The ID for this DataType")
     * @var int
     */
    public $dataTypeId;

    /**
     * @SWG\Property(description="The Name for this DataType")
     * @var string
     */
    public $dataType;
}