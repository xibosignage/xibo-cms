<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (VideoDurationStep.php)
 */


namespace Xibo\Upgrade;


use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;

class VideoDurationStep implements Step
{
    public static function doStep()
    {
        $libraryLocation = $this->getConfig()->GetSetting('LIBRARY_LOCATION');
        $videos = (new MediaFactory($this->getApp()))->getByMediaType('video');

        foreach ($videos as $video) {
            /* @var \Xibo\Entity\Media $video */
            if ($video->duration == 0) {
                // Update
                $module = (new ModuleFactory($this->getApp()))->createWithMedia($video);
                $video->duration = $module->determineDuration($libraryLocation . $video->storedAs);
                $video->save(['validate' => false]);
            }
        }
    }
}