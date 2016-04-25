<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (VideoDurationStep.php)
 */


namespace Xibo\Upgrade;


use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class VideoDurationStep
 * @package Xibo\Upgrade
 */
class VideoDurationStep implements Step
{
    /** @var  StorageServiceInterface */
    private $store;

    /** @var  LogServiceInterface */
    private $log;

    /** @var  ConfigServiceInterface */
    private $config;

    /**
     * DataSetConvertStep constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     */
    public function __construct($store, $log, $config)
    {
        $this->store = $store;
        $this->log = $log;
        $this->config = $config;
    }

    /**
     * @param \Slim\Helper\Set $container
     * @throws \Xibo\Exception\NotFoundException
     */
    public function doStep($container)
    {
        /** @var MediaFactory $mediaFactory */
        $mediaFactory = $container->get('mediaFactory');

        /** @var ModuleFactory $moduleFactory */
        $moduleFactory = $container->get('moduleFactory');

        $libraryLocation = $this->config->GetSetting('LIBRARY_LOCATION');
        $videos = $mediaFactory->getByMediaType('video');

        foreach ($videos as $video) {
            /* @var \Xibo\Entity\Media $video */
            if ($video->duration == 0) {
                // Update
                $module = $moduleFactory->createWithMedia($video);
                $video->duration = $module->determineDuration($libraryLocation . $video->storedAs);
                $video->save(['validate' => false]);
            }
        }
    }
}