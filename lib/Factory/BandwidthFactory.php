<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (BandwidthFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\Bandwidth;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class BandwidthFactory
 * @package Xibo\Factory
 */
class BandwidthFactory extends BaseFactory
{
    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     */
    public function __construct($store, $log, $sanitizerService)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
    }

    /**
     * @return Bandwidth
     */
    public function createEmpty()
    {
        return new Bandwidth($this->getStore(), $this->getLog());
    }

    /**
     * Create and Save Bandwidth record
     * @param int $type
     * @param int $displayId
     * @param int $size
     * @return Bandwidth
     */
    public function createAndSave($type, $displayId, $size)
    {
        $bandwidth = $this->createEmpty();
        $bandwidth->type = $type;
        $bandwidth->displayId = $displayId;
        $bandwidth->size = $size;
        $bandwidth->save();

        return $bandwidth;
    }
}