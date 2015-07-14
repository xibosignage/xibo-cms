<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (BandwidthFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Bandwidth;

class BandwidthFactory extends BaseFactory
{
    /**
     * Create and Save Bandwidth record
     * @param int $type
     * @param int $displayId
     * @param int $size
     * @return Bandwidth
     */
    public static function createAndSave($type, $displayId, $size)
    {
        $bandwidth = new Bandwidth();
        $bandwidth->type = $type;
        $bandwidth->displayId = $displayId;
        $bandwidth->size = $size;
        $bandwidth->save();

        return $bandwidth;
    }
}