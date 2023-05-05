<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */


namespace Xibo\Factory;

use Xibo\Entity\RequiredFile;
use Xibo\Event\XmdsDependencyRequestEvent;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class RequiredFileFactory
 * @package Xibo\Factory
 */
class RequiredFileFactory extends BaseFactory
{
    private $statement = null;

    /**
     * @return RequiredFile
     */
    public function createEmpty()
    {
        return new RequiredFile($this->getStore(), $this->getLog(), $this->getDispatcher());
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
            $files[] = $this->createEmpty()->hydrate($item, ['stringProperties' => ['realId']]);
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
    public function getByDisplayAndMedia($displayId, $mediaId, $type = 'M')
    {
        $result = $this->query(['displayId' => $displayId, 'type' => $type, 'itemId' => $mediaId]);

        if (count($result) <= 0)
            throw new NotFoundException(__('Required file not found for Display and Media Combination'));

        return $result[0];
    }

    /**
     * @param int $displayId
     * @param int $widgetId
     * @param string $type The type of widget, either W (widget html) or D (data)
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByDisplayAndWidget($displayId, $widgetId, $type = 'W')
    {
        $result = $this->query(['displayId' => $displayId, 'type' => $type, 'itemId' => $widgetId]);

        if (count($result) <= 0) {
            throw new NotFoundException(__('Required file not found for Display and Layout Widget'));
        }

        return $result[0];
    }

    /**
     * @param int $displayId
     * @param string $fileType The file type of this dependency
     * @param int $id The ID of this dependency
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByDisplayAndDependency($displayId, $fileType, $id, bool $isUseRealId = true)
    {
        $result = $this->getStore()->select('
            SELECT * 
              FROM `requiredfile` 
             WHERE `displayId` = :displayId
                AND `type` = :type 
                AND `fileType` = :fileType
                AND `' . ($isUseRealId ? 'realId' : 'itemId') . '` = :itemId
        ', [
            'displayId' => $displayId,
            'type' => 'P',
            'fileType' => $fileType,
            'itemId' => $id,
        ]);

        if (count($result) <= 0) {
            throw new NotFoundException(__('Required file not found for Display and Dependency'));
        }

        return $this->createEmpty()->hydrate($result[0], ['stringProperties' => ['realId']]);
    }

    /**
     * @param int $displayId
     * @param string $path The path of this dependency
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByDisplayAndDependencyPath($displayId, $path)
    {
        $result = $this->getStore()->select('
            SELECT * 
              FROM `requiredfile` 
             WHERE `displayId` = :displayId
                AND `type` = :type 
                AND `path` = :path
        ', [
            'displayId' => $displayId,
            'type' => 'P',
            'path' => $path
        ]);

        if (count($result) <= 0) {
            throw new NotFoundException(__('Required file not found for Display and Path'));
        }

        return $this->createEmpty()->hydrate($result[0], ['stringProperties' => ['realId']]);
    }

    /**
     * @param int $displayId
     * @param string $id The itemId of this dependency
     * @return RequiredFile
     * @throws NotFoundException
     */
    public function getByDisplayAndDependencyId($displayId, $id)
    {
        $result = $this->getStore()->select('
            SELECT * 
              FROM `requiredfile` 
             WHERE `displayId` = :displayId
                AND `type` = :type 
                AND `itemId` = :itemId
        ', [
            'displayId' => $displayId,
            'type' => 'P',
            'itemId' => $id
        ]);

        if (count($result) <= 0) {
            throw new NotFoundException(__('Required file not found for Display and Dependency ID'));
        }

        return $this->createEmpty()->hydrate($result[0], ['stringProperties' => ['realId']]);
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
     * Create for Get Data
     * @param $displayId
     * @param $widgetId
     * @return RequiredFile
     */
    public function createForGetData($displayId, $widgetId): RequiredFile
    {
        try {
            $requiredFile = $this->getByDisplayAndWidget($displayId, $widgetId, 'D');
        } catch (NotFoundException $e) {
            $requiredFile = $this->createEmpty();
        }

        $requiredFile->displayId = $displayId;
        $requiredFile->type = 'D';
        $requiredFile->itemId = $widgetId;
        return $requiredFile;
    }

    /**
     * Create for Get Dependency
     * @param $displayId
     * @param $fileType
     * @param $id
     * @param string|int $realId
     * @param $path
     * @return RequiredFile
     */
    public function createForGetDependency($displayId, $fileType, $id, $realId, $path): RequiredFile
    {
        try {
            $requiredFile = $this->getByDisplayAndDependency($displayId, $fileType, $id);
        } catch (NotFoundException $e) {
            $requiredFile = $this->createEmpty();
        }

        $requiredFile->displayId = $displayId;
        $requiredFile->type = 'P';
        $requiredFile->itemId = $id;
        $requiredFile->fileType = $fileType;
        $requiredFile->realId = $realId;
        $requiredFile->path = $path;
        return $requiredFile;
    }

    /**
     * Create for Media
     * @param $displayId
     * @param $mediaId
     * @param $size
     * @param $path
     * @param $released
     * @return RequiredFile
     */
    public function createForMedia($displayId, $mediaId, $size, $path, $released)
    {
        try {
            $requiredFile = $this->getByDisplayAndMedia($displayId, $mediaId);
        } catch (NotFoundException $e) {
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

    /**
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function resolveRequiredFileFromRequest($request): RequiredFile
    {
        $params = $this->getSanitizer($request);
        $displayId = $params->getInt('displayId');
        $itemId = $params->getInt('itemId');

        switch ($params->getString('type')) {
            case 'L':
                $file = $this->getByDisplayAndLayout($displayId, $itemId);
                break;

            case 'M':
                $file = $this->getByDisplayAndMedia($displayId, $itemId);
                break;

            case 'P':
                $fileType = $params->getString('fileType');
                if (empty($fileType)) {
                    throw new NotFoundException(__('Missing fileType'));
                }
                $file = $this->getByDisplayAndDependency($displayId, $fileType, $itemId);

                // Update $file->path with the path on disk (likely /dependencies/$fileType/$itemId)
                $event = new XmdsDependencyRequestEvent($file);
                $this->getDispatcher()->dispatch($event, XmdsDependencyRequestEvent::$NAME);

                // Path should be set - we only want the relative path here.
                $file->path = $event->getRelativePath();
                if (empty($file->path)) {
                    throw new NotFoundException(__('File not found'));
                }
                break;

            default:
                throw new NotFoundException(__('Unknown type'));
        }

        return $file;
    }
}
