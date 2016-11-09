<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (RequiredFileFactory.php)
 */


namespace Xibo\Factory;


use Stash\Interfaces\PoolInterface;
use Xibo\Entity\RequiredFile;
use Xibo\Exception\NotFoundException;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class RequiredFileFactory
 * @package Xibo\Factory
 */
class RequiredFileFactory extends BaseFactory
{
    /** @var  PoolInterface */
    private $pool;

    private $directoryKey = '/directory/nonce';
    private $displayKey = '/display/nonce';

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param PoolInterface $pool
     */
    public function __construct($store, $log, $sanitizerService, $pool)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->pool = $pool;
    }

    /**
     * @return RequiredFile
     */
    public function createEmpty()
    {
        return (new RequiredFile())->setDependencies($this->getLog(), $this);
    }

    /**
     * @param RequiredFile $file
     * @param string $nonce
     */
    public function addOrReplace($file, $nonce)
    {
        $cacheKey = '';
        if ($file->layoutId != 0 && $file->regionId != 0 && $file->mediaId != 0) {
            $cacheKey = 'widget/' . $file->mediaId;
        } else if ($file->mediaId != 0) {
            $cacheKey = 'media/' . $file->mediaId;
        } else if ($file->layoutId != 0) {
            $cacheKey = 'layout/' . $file->layoutId;
        }

        $displayCache = $this->displayKey . '/' . $file->displayId . '/inventory/' . $cacheKey;

        $this->getLog()->debug('Add or replace for file: ' . $displayCache);

        // Update this file in the cache.
        $item = $this->pool->getItem($displayCache);
        $item->set($file);
        $item->expiresAfter(86400);

        // Update this nonce in the directory
        $directory = $this->pool->getItem($this->directoryKey . '/' . $file->nonce);
        $directory->set($displayCache);
        $directory->expiresAfter(86400);

        // Save both items
        $this->pool->saveDeferred($item);
        $this->pool->saveDeferred($directory);

        if ($nonce !== $file->nonce) {
            // Nonce provided is not equal to the current nonce, which means the nonce for this required file
            // has changed.
            // We should delete the cache keys for the old nonce in the directory.
            $this->pool->deleteItem($this->directoryKey . '/' . $nonce);
        }
    }

    /**
     * @param string $nonce
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByNonce($nonce)
    {
        // Try and get the required file diplay id from the main directory
        $this->getLog()->debug('Required file by Nonce: ' . $nonce);

        $item = $this->pool->getItem($this->directoryKey . '/' . $nonce);

        if ($item->isMiss()) {
            $this->getLog()->debug('Nonce ' . $nonce . ' does not exist in directory.');
            throw new NotFoundException();
        }

        // We have the nonce in the cache.
        $file = $this->pool->getItem($item->get());

        if ($file->isMiss()) {
            $this->getLog()->debug('Nonce ' . $nonce . ' in directory but has the wrong key.');
            throw new NotFoundException();
        }

        return $file->get()->setDependencies($this->getLog(), $this);
    }

    /**
     * @param int $displayId
     * @param int $layoutId
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByDisplayAndLayout($displayId, $layoutId)
    {
        $item = $this->pool->getItem($this->displayKey . '/' . $displayId . '/inventory/layout/' . $layoutId);

        if ($item->isMiss())
            throw new NotFoundException(__('Required file not found for Display and Layout Combination'));

        return $item->get()->setDependencies($this->getLog(), $this);
    }

    /**
     * @param int $displayId
     * @param int $mediaId
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByDisplayAndMedia($displayId, $mediaId)
    {
        $item = $this->pool->getItem($this->displayKey . '/' . $displayId . '/inventory/media/' . $mediaId);

        if ($item->isMiss())
            throw new NotFoundException(__('Required file not found for Display and Media Combination'));

        return $item->get()->setDependencies($this->getLog(), $this);
    }

    /**
     * @param int $displayId
     * @param int $widgetId
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByDisplayAndWidget($displayId, $widgetId)
    {
        $item = $this->pool->getItem($this->displayKey . '/' . $displayId . '/inventory/widget/' . $widgetId);

        if ($item->isMiss())
            throw new NotFoundException(__('Required file not found for Display and Layout Widget'));

        return $item->get()->setDependencies($this->getLog(), $this);
    }

    /**
     * Create for layout
     * @param $displayId
     * @param $layoutId
     * @param $size
     * @param $path
     * @return RequiredFile
     */
    public function createForLayout($displayId, $layoutId, $size, $path)
    {
        try {
            $nonce = $this->getByDisplayAndLayout($displayId, $layoutId);
        }
        catch (NotFoundException $e) {
            $nonce = $this->createEmpty();
        }

        $nonce->displayId = $displayId;
        $nonce->layoutId = $layoutId;
        $nonce->size = $size;
        $nonce->storedAs = $path;
        return $nonce;
    }

    /**
     * Create for Get Resource
     * @param $displayId
     * @param $layoutId
     * @param $regionId
     * @param $mediaId
     * @return RequiredFile
     */
    public function createForGetResource($displayId, $layoutId, $regionId, $mediaId)
    {
        try {
            $nonce = $this->getByDisplayAndWidget($displayId, $mediaId);
        }
        catch (NotFoundException $e) {
            $nonce = $this->createEmpty();
        }

        $nonce->displayId = $displayId;
        $nonce->layoutId = $layoutId;
        $nonce->regionId = $regionId;
        $nonce->mediaId = $mediaId;
        return $nonce;
    }

    /**
     * Create for Media
     * @param $displayId
     * @param $mediaId
     * @param $size
     * @param $path
     * @return RequiredFile
     */
    public function createForMedia($displayId, $mediaId, $size, $path)
    {
        try {
            $nonce = $this->getByDisplayAndMedia($displayId, $mediaId);
        }
        catch (NotFoundException $e) {
            $nonce = $this->createEmpty();
        }

        $nonce->displayId = $displayId;
        $nonce->mediaId = $mediaId;
        $nonce->size = $size;
        $nonce->storedAs = $path;
        return $nonce;
    }

    /**
     * Expire all nonces
     * @param $displayId
     */
    public function expireAll($displayId)
    {
        $this->pool->deleteItem($this->displayKey . '/' . $displayId . '/inventory');
    }
}