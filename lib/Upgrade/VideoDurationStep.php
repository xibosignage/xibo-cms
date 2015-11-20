<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (VideoDurationStep.php)
 */


namespace Xibo\Upgrade;


use Xibo\Factory\MediaFactory;

class VideoDurationStep implements Step
{
    public static function doStep()
    {
        $videos = MediaFactory::getByMediaType('video');

        foreach ($videos as $video) {
            /* @var \Xibo\Entity\Media $video */
            if ($video->duration == 0) {
                // Update
                $video->duration = $video->determineDuration();
                $video->save(['validate' => false]);
            }
        }
    }
}