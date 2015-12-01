<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (VideoDurationStep.php)
 */


namespace Xibo\Upgrade;


use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Helper\Config;

class VideoDurationStep implements Step
{
    public static function doStep()
    {
        // Force higher DB version for this call
        DEFINE('DBVERSION', 120);

        $libraryLocation = Config::GetSetting('LIBRARY_LOCATION');
        $videos = MediaFactory::getByMediaType('video');

        foreach ($videos as $video) {
            /* @var \Xibo\Entity\Media $video */
            if ($video->duration == 0) {
                // Update
                $module = ModuleFactory::createWithMedia($video);
                $video->duration = $module->determineDuration($libraryLocation . $video->storedAs);
                $video->save(['validate' => false]);
            }
        }
    }
}