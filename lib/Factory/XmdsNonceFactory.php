<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (XmdsNonceFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\XmdsNonce;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class XmdsNonceFactory
{
    /**
     * @param string $nonce
     * @return XmdsNonce
     * @throws NotFoundException
     */
    public static function getByNonce($nonce)
    {
        $nonce = XmdsNonceFactory::query(null, ['nonce' => $nonce]);

        if (count($nonce) <= 0)
            throw new NotFoundException();

        return $nonce[0];
    }

    /**
     * @param int $displayId
     * @param int $layoutId
     * @return array[XmdsNonce]
     */
    public static function getByDisplayAndLayout($displayId, $layoutId)
    {
        return XmdsNonceFactory::query(null, ['displayId' => $displayId, 'layoutId' => $layoutId]);
    }

    /**
     * @param int $displayId
     * @param int $mediaId
     * @return array[XmdsNonce]
     */
    public static function getByDisplayAndMedia($displayId, $mediaId)
    {
        return XmdsNonceFactory::query(null, ['displayId' => $displayId, 'mediaId' => $mediaId]);
    }

    /**
     * @param int $displayId
     * @param int $layoutId
     * @param int $regionId
     * @param int $mediaId
     * @return array[XmdsNonce]
     */
    public static function getByDisplayAndResource($displayId, $layoutId, $regionId, $mediaId)
    {
        return XmdsNonceFactory::query(null, ['displayId' => $displayId, 'layoutId' => $layoutId, 'regionId' => $regionId, 'mediaId' => $mediaId]);
    }

    /**
     * Create for layout
     * @param $displayId
     * @param $layoutId
     * @param $size
     * @param $path
     * @return XmdsNonce
     */
    public static function createForLayout($displayId, $layoutId, $size, $path)
    {
        $nonce = new XmdsNonce();
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
     * @return XmdsNonce
     */
    public static function createForGetResource($displayId, $layoutId, $regionId, $mediaId)
    {
        $nonce = new XmdsNonce();
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
     * @return XmdsNonce
     */
    public static function createForMedia($displayId, $mediaId, $size, $path)
    {
        $nonce = new XmdsNonce();
        $nonce->displayId = $displayId;
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
            SELECT nonceId,
                nonce,
                expiry,
                lastUsed,
                displayId,
                fileId,
                size,
                storedAs,
                layoutId,
                regionId,
                mediaId
             FROM `xmdsnonce`
            WHERE 1 = 1
        ';

        if (Sanitize::getString('nonce', $filterBy) !== null) {
            $sql .= ' AND xmdsnonce.nonce = :nonce';
            $params['nonce'] = Sanitize::getString('nonce', $filterBy);
        }

        if (Sanitize::getInt('displayId', $filterBy) !== null) {
            $sql .= ' AND xmdsnonce.displayId = :displayId';
            $params['displayId'] = Sanitize::getInt('displayId', $filterBy);
        }

        if (Sanitize::getInt('layoutId', $filterBy) !== null) {
            $sql .= ' AND xmdsnonce.layoutId = :layoutId';
            $params['layoutId'] = Sanitize::getInt('layoutId', $filterBy);
        }

        if (Sanitize::getInt('regionId', $filterBy) !== null) {
            $sql .= ' AND xmdsnonce.regionId = :regionId';
            $params['regionId'] = Sanitize::getInt('regionId', $filterBy);
        }

        if (Sanitize::getInt('mediaId', $filterBy) !== null) {
            $sql .= ' AND xmdsnonce.mediaId = :mediaId';
            $params['mediaId'] = Sanitize::getInt('mediaId', $filterBy);
        }

        // Sorting?
        if (is_array($sortOrder))
            $sql .= 'ORDER BY ' . implode(',', $sortOrder);

        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new XmdsNonce())->hydrate($row, ['intProperties' => ['expires', 'lastUsed', 'size']]);
        }

        return $entries;
    }
}