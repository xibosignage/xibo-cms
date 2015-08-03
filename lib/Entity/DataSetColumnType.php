<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetColumnType.php)
 */


namespace Xibo\Entity;

/**
 * Class DataSetColumnType
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class DataSetColumnType implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The ID for this DataSetColumnType")
     * @var int
     */
    public $dataSetColumnTypeId;

    /**
     * @SWG\Property(description="The name for this DataSetColumnType")
     * @var string
     */
    public $dataSetColumnType;
}