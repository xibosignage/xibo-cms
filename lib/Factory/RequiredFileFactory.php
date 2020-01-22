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
    private $statement = null;

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
     * @param array $params
     * @return RequiredFile[]
     */
    private function query($params)
    {
        $files = [];

        if ($this->statement === null) {
            $this->statement = $this->getStore()->getConnection()->prepare('
              SELECT * 
                FROM `requiredfile` 
               WHERE `displayId` = :displayId
                  AND `type` = :type 
                  AND `itemId` = :itemId
              ');
        }

        $this->statement->execute($params);

        foreach ($this->statement->fetchAll(\PDO::FETCH_ASSOC) as $item) {
            $files[] = $this->createEmpty()->hydrate($item);
        }

        return $files;
    }

    /**
     * @param int $displayId
     * @param int $layoutId
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByDisplayAndLayout($displayId, $layoutId)
    {
        $result = $this->query(['displayId' => $displayId, 'type' => 'L', 'itemId' => $layoutId]);

        if (count($result) <= 0)
            throw new NotFoundException(__('Required file not found for Display and Layout Combination'));

        return $result[0];
    }

    /**
     * @param int $displayId
     * @param int $mediaId
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByDisplayAndMedia($displayId, $mediaId)
    {
        $result = $this->query(['displayId' => $displayId, 'type' => 'M', 'itemId' => $mediaId]);

        if (count($result) <= 0)
            throw new NotFoundException(__('Required file not found for Display and Media Combination'));

        return $result[0];
    }

    /**
     * @param int $displayId
     * @param int $widgetId
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByDisplayAndWidget($displayId, $widgetId)
    {
        $result = $this->query(['displayId' => $displayId, 'type' => 'W', 'itemId' => $widgetId]);

        if (count($result) <= 0)
            throw new NotFoundException(__('Required file not found for Display and Layout Widget'));

        return $result[0];
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
            $requiredFile = $this->getByDisplayAndLayout($displayId, $layoutId);
        }
        catch (NotFoundException $e) {
            $requiredFile = $this->createEmpty();
        }

        $requiredFile->displayId = $displayId;
        $requiredFile->type = 'L';
        $requiredFile->itemId = $layoutId;
        $requiredFile->size = $size;
        $requiredFile->path = $path;
        return $requiredFile;
    }

    /**
     * Create for Get Resource
     * @param $displayId
     * @param $widgetId
     * @return RequiredFile
     */
    public function createForGetResource($displayId, $widgetId)
    {
        try {
            $requiredFile = $this->getByDisplayAndWidget($displayId, $widgetId);
        }
        catch (NotFoundException $e) {
            $requiredFile = $this->createEmpty();
        }

        $requiredFile->displayId = $displayId;
        $requiredFile->type = 'W';
        $requiredFile->itemId = $widgetId;
        return $requiredFile;
    }

    /**
     * Create for Media
     * @param $displayId
     * @param $mediaId
     * @param $size
     * @param $path
     * @return RequiredFile
     */
    public function createForMedia($displayId, $mediaId, $size, $path, $released)
    {
        try {
            $requiredFile = $this->getByDisplayAndMedia($displayId, $mediaId);
        }
        catch (NotFoundException $e) {
            $requiredFile = $this->createEmpty();
        }

        $requiredFile->displayId = $displayId;
        $requiredFile->type = 'M';
        $requiredFile->itemId = $mediaId;
        $requiredFile->size = $size;
        $requiredFile->path = $path;
        $requiredFile->released = $released;
        return $requiredFile;
    }
}