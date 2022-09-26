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
        $module = $this->moduleFactory->getByType('video');
        $videos = $this->mediaFactory->getByMediaType($module->type);

        foreach ($videos as $video) {
            if ($video->duration == 0) {
                // Update
                $video->duration = $module->fetchDurationOrDefaultFromFile($libraryLocation . $video->storedAs);
                $video->save(['validate' => false]);
            }
        }
    }
}