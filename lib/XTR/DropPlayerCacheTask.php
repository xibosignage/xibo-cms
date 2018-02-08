<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2018 Spring Signage Ltd
 * (DropPlayerCacheTask.php)
 */


namespace Xibo\XTR;

/**
 * Class DropPlayerCacheTask
 * @package Xibo\XTR
 */
class DropPlayerCacheTask implements TaskInterface
{
    use TaskTrait;

    /** @inheritdoc */
    public function setFactories($container)
    {
        // Nothing needed here
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $this->pool->deleteItem('display');
    }
}