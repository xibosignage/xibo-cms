<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (RequiredFileFactory.php)
 */


namespace Xibo\Factory;


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
     * @return RequiredFile
     */
    public function createEmpty()
    {
        return new RequiredFile($this->getStore(), $this->getLog());
    }

    /**
     * @param string $nonce
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByNonce($nonce)
    {
        $nonce = $this->query(null, ['nonce' => $nonce]);

        if (count($nonce) <= 0)
            throw new NotFoundException();

        return $nonce[0];
    }

    /**
     * @param int $displayId
     * @param int $layoutId
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByDisplayAndLayout($displayId, $layoutId)
    {
        $files = $this->query(null, ['displayId' => $displayId, 'layoutId' => $layoutId]);

        if (count($files) <= 0)
            throw new NotFoundException();

        return $files[0];
    }

    /**
     * @param int $displayId
     * @param int $mediaId
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByDisplayAndMedia($displayId, $mediaId)
    {
        $files = $this->query(null, ['displayId' => $displayId, 'mediaId' => $mediaId]);

        if (count($files) <= 0)
            throw new NotFoundException();

        return $files[0];
    }

    /**
     * @param int $displayId
     * @param int $layoutId
     * @param int $regionId
     * @param int $mediaId
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByDisplayAndResource($displayId, $layoutId, $regionId, $mediaId)
    {
        $files = $this->query(null, ['displayId' => $displayId, 'layoutId' => $layoutId, 'regionId' => $regionId, 'mediaId' => $mediaId]);

        if (count($files) <= 0)
            throw new NotFoundException();

        return $files[0];
    }

    /**
     * Create for layout
     * @param $displayId
     * @param $requestKey
     * @param $layoutId
     * @param $size
     * @param $path
     * @return RequiredFile
     */
    public function createForLayout($displayId, $requestKey, $layoutId, $size, $path)
    {
        try {
            $nonce = $this->getByDisplayAndLayout($displayId, $layoutId);
        }
        catch (NotFoundException $e) {
            $nonce = $this->createEmpty();
        }

        $nonce->displayId = $displayId;
        $nonce->requestKey = $requestKey;
        $nonce->layoutId = $layoutId;
        $nonce->size = $size;
        $nonce->storedAs = $path;
        return $nonce;
    }

    /**
     * Create for Get Resource
     * @param $displayId
     * @param $requestKey
     * @param $layoutId
     * @param $regionId
     * @param $mediaId
     * @return RequiredFile
     */
    public function createForGetResource($displayId, $requestKey, $layoutId, $regionId, $mediaId)
    {
        try {
            $nonce = $this->getByDisplayAndResource($displayId, $layoutId, $regionId, $mediaId);
        }
        catch (NotFoundException $e) {
            $nonce = $this->createEmpty();
        }

        $nonce->displayId = $displayId;
        $nonce->requestKey = $requestKey;
        $nonce->layoutId = $layoutId;
        $nonce->regionId = $regionId;
        $nonce->mediaId = $mediaId;
        return $nonce;
    }

    /**
     * Create for Media
     * @param $displayId
     * @param $requestKey
     * @param $mediaId
     * @param $size
     * @param $path
     * @return RequiredFile
     */
    public function createForMedia($displayId, $requestKey, $mediaId, $size, $path)
    {
        try {
            $nonce = $this->getByDisplayAndMedia($displayId, $mediaId);
        }
        catch (NotFoundException $e) {
            $nonce = $this->createEmpty();
        }

        $nonce->displayId = $displayId;
        $nonce->requestKey = $requestKey;
        $nonce->mediaId = $mediaId;
        $nonce->size = $size;
        $nonce->storedAs = $path;
        return $nonce;
    }

    public function query($sortOrder = null, $filterBy = null)
    {
        $entries = [];
        $params = [];
        $sql = '
            SELECT rfId,
                `requiredfile`.requestKey,
                `requiredfile`.nonce,
                `requiredfile`.expiry,
                `requiredfile`.lastUsed,
                `requiredfile`.displayId,
                `requiredfile`.size,
                `requiredfile`.storedAs,
                `requiredfile`.layoutId,
                `requiredfile`.regionId,
                `requiredfile`.mediaId,
                `requiredfile`.bytesRequested,
                `requiredfile`.complete
             FROM `requiredfile`
            WHERE 1 = 1
        ';

        if ($this->getSanitizer()->getString('nonce', $filterBy) !== null) {
            $sql .= ' AND requiredfile.nonce = :nonce';
            $params['nonce'] = $this->getSanitizer()->getString('nonce', $filterBy);
        }

        if ($this->getSanitizer()->getInt('displayId', $filterBy) !== null) {
            $sql .= ' AND requiredfile.displayId = :displayId';
            $params['displayId'] = $this->getSanitizer()->getInt('displayId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('layoutId', $filterBy) !== null) {
            $sql .= ' AND requiredfile.layoutId = :layoutId';
            $params['layoutId'] = $this->getSanitizer()->getInt('layoutId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('regionId', $filterBy) !== null) {
            $sql .= ' AND requiredfile.regionId = :regionId';
            $params['regionId'] = $this->getSanitizer()->getInt('regionId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('mediaId', $filterBy) !== null) {
            $sql .= ' AND requiredfile.mediaId = :mediaId';
            $params['mediaId'] = $this->getSanitizer()->getInt('mediaId', $filterBy);
        }

        // Sorting?
        if (is_array($sortOrder))
            $sql .= 'ORDER BY ' . implode(',', $sortOrder);


        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, ['intProperties' => ['expires', 'lastUsed', 'size']]);
        }

        return $entries;
    }
}