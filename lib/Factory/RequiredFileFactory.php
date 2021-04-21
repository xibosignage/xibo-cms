<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
     * @param $released
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