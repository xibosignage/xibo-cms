<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (RequiredFileFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\RequiredFile;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class RequiredFileFactory extends BaseFactory
{
    /**
     * @param string $nonce
     * @return RequiredFile
     * @throws NotFoundException
     */
    public static function getByNonce($nonce)
    {
        $nonce = RequiredFileFactory::query(null, ['nonce' => $nonce]);

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
    public static function getByDisplayAndLayout($displayId, $layoutId)
    {
        $files = RequiredFileFactory::query(null, ['displayId' => $displayId, 'layoutId' => $layoutId]);

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
    public static function getByDisplayAndMedia($displayId, $mediaId)
    {
        $files = RequiredFileFactory::query(null, ['displayId' => $displayId, 'mediaId' => $mediaId]);

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
    public static function getByDisplayAndResource($displayId, $layoutId, $regionId, $mediaId)
    {
        $files = RequiredFileFactory::query(null, ['displayId' => $displayId, 'layoutId' => $layoutId, 'regionId' => $regionId, 'mediaId' => $mediaId]);

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
    public static function createForLayout($displayId, $requestKey, $layoutId, $size, $path)
    {
        try {
            $nonce = RequiredFileFactory::getByDisplayAndLayout($displayId, $layoutId);
        }
        catch (NotFoundException $e) {
            $nonce = new RequiredFile();
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
    public static function createForGetResource($displayId, $requestKey, $layoutId, $regionId, $mediaId)
    {
        try {
            $nonce = RequiredFileFactory::getByDisplayAndResource($displayId, $layoutId, $regionId, $mediaId);
        }
        catch (NotFoundException $e) {
            $nonce = new RequiredFile();
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
    public static function createForMedia($displayId, $requestKey, $mediaId, $size, $path)
    {
        try {
            $nonce = RequiredFileFactory::getByDisplayAndMedia($displayId, $mediaId);
        }
        catch (NotFoundException $e) {
            $nonce = new RequiredFile();
        }

        $nonce->displayId = $displayId;
        $nonce->requestKey = $requestKey;
        $nonce->mediaId = $mediaId;
        $nonce->size = $size;
        $nonce->storedAs = $path;
        return $nonce;
    }

    public static function query($sortOrder = null, $filterBy = null)
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

        if (Sanitize::getString('nonce', $filterBy) !== null) {
            $sql .= ' AND requiredfile.nonce = :nonce';
            $params['nonce'] = Sanitize::getString('nonce', $filterBy);
        }

        if (Sanitize::getInt('displayId', $filterBy) !== null) {
            $sql .= ' AND requiredfile.displayId = :displayId';
            $params['displayId'] = Sanitize::getInt('displayId', $filterBy);
        }

        if (Sanitize::getInt('layoutId', $filterBy) !== null) {
            $sql .= ' AND requiredfile.layoutId = :layoutId';
            $params['layoutId'] = Sanitize::getInt('layoutId', $filterBy);
        }

        if (Sanitize::getInt('regionId', $filterBy) !== null) {
            $sql .= ' AND requiredfile.regionId = :regionId';
            $params['regionId'] = Sanitize::getInt('regionId', $filterBy);
        }

        if (Sanitize::getInt('mediaId', $filterBy) !== null) {
            $sql .= ' AND requiredfile.mediaId = :mediaId';
            $params['mediaId'] = Sanitize::getInt('mediaId', $filterBy);
        }

        // Sorting?
        if (is_array($sortOrder))
            $sql .= 'ORDER BY ' . implode(',', $sortOrder);

        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new RequiredFile())->hydrate($row, ['intProperties' => ['expires', 'lastUsed', 'size']]);
        }

        return $entries;
    }
}