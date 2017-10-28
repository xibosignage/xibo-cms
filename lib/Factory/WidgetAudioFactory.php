<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (WidgetMediaFactory.php) is part of Xibo.
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


use Xibo\Entity\WidgetAudio;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class WidgetAudioFactory
 * @package Xibo\Factory
 */
class WidgetAudioFactory extends BaseFactory
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
     * Create Empty
     * @return WidgetAudio
     */
    public function createEmpty()
    {
        return new WidgetAudio($this->getStore(), $this->getLog());
    }

    /**
     * Media Linked to Widgets by WidgetId
     * @param int $widgetId
     * @return array[int]
     */
    public function getByWidgetId($widgetId)
    {
        return $this->query(null, array('widgetId' => $widgetId));
    }

    /**
     * Query Media Linked to Widgets
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[int]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = [];
        $sql = 'SELECT `mediaId`, `widgetId`, `volume`, `loop` FROM `lkwidgetaudio` WHERE widgetId = :widgetId AND mediaId <> 0 ';

        foreach ($this->getStore()->select($sql, ['widgetId' => $this->getSanitizer()->getInt('widgetId', $filterBy)]) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, ['intProperties' => ['duration']]);
        }

        return $entries;
    }
}