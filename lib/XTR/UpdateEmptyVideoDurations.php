<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2018 Spring Signage Ltd
 * (UpdateEmptyVideoDurations.php)
 */

namespace Xibo\XTR;

use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;

/**
 * Class UpdateEmptyVideoDurations
 * @package Xibo\XTR
 *
 *  update video durations
 */
class UpdateEmptyVideoDurations implements TaskInterface
{
    use TaskTrait;

    /** @var MediaFactory */
    private $mediaFactory;

    /** @var ModuleFactory */
    private $moduleFactory;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->mediaFactory = $container->get('mediaFactory');
        $this->moduleFactory = $container->get('moduleFactory');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $libraryLocation = $this->config->getSetting('LIBRARY_LOCATION');
        $videos = $this->mediaFactory->getByMediaType('video');

        foreach ($videos as $video) {
            /* @var \Xibo\Entity\Media $video */
            if ($video->duration == 0) {
                // Update
                $module = $this->moduleFactory->createWithMedia($video);
                $video->duration = $module->determineDuration($libraryLocation . $video->storedAs);
                $video->save(['validate' => false]);
            }
        }
    }
}