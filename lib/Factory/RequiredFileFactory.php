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
        if ($this->directory === null) {
            $item = $this->pool->getItem('inventory/directory');

            if ($item->isHit()) {
                $this->directory = json_decode($item->get(), true);
            } else {
                throw new NotFoundException();
            }
        }

        if (count($this->directory) <= 0)
            throw new NotFoundException();

        if (array_key_exists($nonce, $this->directory)) {
            // Get the nonce out of the relevent display inventory
            $this->setDisplay($this->directory[$nonce]);

            return $this->files['nonce'][$nonce];
        } else {
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

        $this->getLog()->debug('Cache: ' . json_encode($this->files));

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
            unset($this->files['nonce'][$nonce]);

            if ($file->layoutId != 0 && $file->regionId != 0 && $file->mediaId != 0) {
                unset($this->files['widget'][$file->mediaId]);
            } else if ($file->mediaId != 0) {
                unset($this->files['media'][$file->mediaId]);
            } else if ($file->layoutId != 0) {
                unset($this->files['layout'][$file->layoutId]);
            }
        }

        // pop it in the current array, according to its nature
        $this->addFileToStore($file);

        // Add the required file to the appropriate array
        $this->addFileToLookupKey($file);
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

        return $this->files['nonce'][$this->files['layout'][$layoutId]];
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

        return $this->files['nonce'][$this->files['media'][$mediaId]];
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

        return $this->files['nonce'][$this->files['widget'][$widgetId]];
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
     * Persist the current pool to the cache
     */
    public function persist()
    {
        if ($this->displayId == null)
            return;

        $directory = $this->pool->getItem('inventory/directory');

        $directoryAdditions = [];

        foreach ($this->files['nonce'] as $key => $value) {
            $directoryAdditions[$key] = $this->displayId;
        }

        if ($directory->isHit()) {
            // Combine our nonce directory with the existing global directory
            $directory->set(json_encode(array_merge(json_decode($directory->get(), true), $directoryAdditions)));
        } else {
            $directory->set(json_encode($directoryAdditions));
        }
        $directory->expiresAfter(new \DateInterval('P2M'));

        // Overwrite the pool cache
        $item = $this->pool->getItem('inventory/' . $this->displayId);

        $item->set(json_encode($this->files['nonce']));
        $item->expiresAfter(new \DateInterval('P2M'));

        $this->pool->saveDeferred($item);
        $this->pool->saveDeferred($directory);
    }
}