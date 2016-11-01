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
    private $directory = null;
    private $files = [];
    private $displayId = 0;

    /** @var  PoolInterface */
    private $pool;

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
        return new RequiredFile($this->getStore(), $this->getLog(), $this);
    }

    /**
     * @param string $nonce
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByNonce($nonce)
    {
        $this->getLog()->debug('Required file by Nonce: ' . $nonce);

        if ($this->directory === null) {
            $item = $this->pool->getItem('inventory/directory');

            if ($item->isHit()) {
                $this->directory = json_decode($item->get(), true);
            } else {
                throw new NotFoundException();
            }
        }

        if (count($this->directory) <= 0) {
            $this->getLog()->debug('Empty inventory directory');
            throw new NotFoundException();
        }

        if (array_key_exists($nonce, $this->directory)) {
            // Get the nonce out of the relevent display inventory
            $displayId = $this->directory[$nonce];

            $this->getLog()->debug('Nonce resolves to displayId ' . $displayId);

            return $this->getByDisplayAndNonce($displayId, $nonce);
        } else {
            $this->getLog()->debug('Nonce ' . $nonce . ' does not exist in directory.');
            throw new NotFoundException();
        }
    }

    /**
     * Get Required files for display
     * @param int $displayId
     */
    public function setDisplay($displayId)
    {
        if ($this->displayId === $displayId)
            return;

        // Persist what we currently have
        $this->persist();

        // Load fresh display
        $this->files = [
            '_total' => 0,
            '_totalSize' => 0,
            '_totalComplete' => 0,
            '_totalSizeComplete' => 0,
            'layout' => [],
            'media' => [],
            'widget' => [],
            'nonce' => []
        ];

        $item = $this->pool->getItem('inventory/' . $displayId);

        if ($item->isHit()) {
            $files = json_decode($item->get(), true);

            foreach ($files as $key => $element) {

                $file = $this->createEmpty()->hydrate($element);

                $this->files['_total'] = $this->files['_total'] + 1;
                $this->files['_totalSize'] = $this->files['_totalSize'] + $file->size;

                if ($file->complete == 1) {
                    $this->files['_totalComplete'] = $this->files['_totalComplete'] + 1;
                    $this->files['_totalSizeComplete'] = $this->files['_totalSizeComplete'] + $file->size;
                }

                // Add the required file to the appropriate array
                $this->addFileToLookupKey($file);
                $this->addFileToStore($file);
            }
        }

        //$this->getLog()->debug('Cache: ' . json_encode($this->files));

        // Store the current displayId
        $this->displayId = $displayId;
    }

    /**
     * @param RequiredFile $file
     */
    private function addFileToStore($file)
    {
        $this->files['nonce'][$file->nonce] = $file;
    }

    /**
     * @param RequiredFile $file
     */
    private function addFileToLookupKey($file)
    {
        // Add the required file to the appropriate array
        if ($file->layoutId != 0 && $file->regionId != 0 && $file->mediaId != 0) {
            $this->files['widget'][$file->mediaId] = $file->nonce;
        } else if ($file->mediaId != 0) {
            $this->files['media'][$file->mediaId] = $file->nonce;
        } else if ($file->layoutId != 0) {
            $this->files['layout'][$file->layoutId] = $file->nonce;
        }
    }

    /**
     * @param RequiredFile $file
     * @param string $nonce
     */
    public function addOrReplace($file, $nonce)
    {
        if ($this->displayId != $file->displayId)
            $this->setDisplay($file->displayId);

        // Given the required file we've been provided, find that in our current cache and replace the nonce and the pointer to it
        if ($nonce !== '') {
            // We are an existing required file, which needs removing and then adding.
            $this->remove($file, $nonce);
        }

        // pop it in the current array, according to its nature
        $this->addFileToStore($file);

        // Add the required file to the appropriate array
        $this->addFileToLookupKey($file);
    }

    /**
     * Removes a required file from the cache
     * @param RequiredFile $file
     * @param string $nonce
     */
    private function remove($file, $nonce)
    {
        // Remove from the cache
        if ($file->layoutId != 0 && $file->regionId != 0 && $file->mediaId != 0) {
            unset($this->files['widget'][$file->mediaId]);
        } else if ($file->mediaId != 0) {
            unset($this->files['media'][$file->mediaId]);
        } else if ($file->layoutId != 0) {
            unset($this->files['layout'][$file->layoutId]);
        }

        unset($this->files['nonce'][$nonce]);
    }

    /**
     * @return int
     */
    public function getTotalCount()
    {
        return $this->files['_total'];
    }

    /**
     * @return mixed
     */
    public function getCompleteCount()
    {
        return $this->files['_totalComplete'];
    }

    /**
     * @return mixed
     */
    public function getTotalSize()
    {
        return $this->files['_totalSize'];
    }

    /**
     * @return mixed
     */
    public function getCompleteSize()
    {
        return $this->files['_totalSizeComplete'];
    }

    /**
     * @return array
     */
    public function getLayoutIds()
    {
        return array_keys($this->files['layout']);
    }

    /**
     * @return array
     */
    public function getMediaIds()
    {
        return array_keys($this->files['media']);
    }

    /**
     * @return array
     */
    public function getWidgetIds()
    {
        return array_keys($this->files['widget']);
    }

    /**
     * @param int $displayId
     * @param string $nonce
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByDisplayAndNonce($displayId, $nonce)
    {
        if ($this->displayId != $displayId)
            $this->setDisplay($displayId);

        if (!isset($this->files['nonce'][$nonce]))
            throw new NotFoundException('Nonce not in directory required files.');

        $rf = $this->files['nonce'][$nonce];

        if ($rf == null)
            throw new NotFoundException();

        return $rf;
    }

    /**
     * @param int $displayId
     * @param int $layoutId
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByDisplayAndLayout($displayId, $layoutId)
    {
        if ($this->displayId != $displayId)
            $this->setDisplay($displayId);

        if (!isset($this->files['layout'][$layoutId]))
            throw new NotFoundException();

        $rf = $this->files['nonce'][$this->files['layout'][$layoutId]];

        if ($rf == null)
            throw new NotFoundException();

        return $rf;
    }

    /**
     * @param int $displayId
     * @param int $mediaId
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByDisplayAndMedia($displayId, $mediaId)
    {
        if ($this->displayId != $displayId)
            $this->setDisplay($displayId);

        if (!isset($this->files['media'][$mediaId]))
            throw new NotFoundException();

        $file = $this->files['nonce'][$this->files['media'][$mediaId]];

        if ($file == null)
            throw new NotFoundException();

        return $file;
    }

    /**
     * @param int $displayId
     * @param int $widgetId
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByDisplayAndWidget($displayId, $widgetId)
    {
        if ($this->displayId != $displayId)
            $this->setDisplay($displayId);

        if (!isset($this->files['widget'][$widgetId]))
            throw new NotFoundException();

        $file = $this->files['nonce'][$this->files['widget'][$widgetId]];

        if ($file == null)
            throw new NotFoundException();

        return $file;
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
        $this->setDisplay($displayId);

        // Go through each nonce and set it to a short expiry
        foreach ($this->files['nonce'] as $file) {
            /** @var RequiredFile $file */
            $file->expireSoon();
        }
    }

    /**
     * Persist the current pool to the cache
     */
    public function persist()
    {
        if ($this->displayId == null)
            return;

        $directoryItem = $this->pool->getItem('inventory/directory');

        if ($directoryItem->isHit()) {
            $directory = json_decode($directoryItem->get(), true);
        } else {
            $directory = [];
        }

        // Combine our nonce directory with the existing global directory
        foreach ($this->files['nonce'] as $key => $value) {
            /** @var RequiredFile $value */
            if ($value->isExpired()) {
                unset($directory[$key]);
                $this->remove($value, $value->nonce);
            } else {
                $directory[$key] = $this->displayId;
            }
        }

        // Overwrite the pool cache
        $item = $this->pool->getItem('inventory/' . $this->displayId);
        $item->set(json_encode($this->files['nonce']));
        $item->expiresAfter(new \DateInterval('P2M'));

        // Set the directory
        $directoryItem->set(json_encode($directory));
        $directoryItem->expiresAfter(new \DateInterval('P2M'));

        $this->pool->saveDeferred($item);
        $this->pool->saveDeferred($directoryItem);
    }
}