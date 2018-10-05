<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (WidgetOptionFactory.php) is part of Xibo.
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


use Xibo\Entity\WidgetOption;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

class WidgetOptionFactory extends BaseFactory
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
     * @return WidgetOption
     */
    public function createEmpty()
    {
        return new WidgetOption($this->getStore(), $this->getLog());
    }

    /**
     * Create a Widget Option
     * @param int $widgetId
     * @param string $type
     * @param string $option
     * @param mixed $value
     * @return WidgetOption
     */
    public function create($widgetId, $type, $option, $value)
    {
        $widgetOption = $this->createEmpty();
        $widgetOption->widgetId = $widgetId;
        $widgetOption->type = $type;
        $widgetOption->option = $option;
        $widgetOption->value = $value;

        return $widgetOption;
    }

    /**
     * Load by Widget Id
     * @param int $widgetId
     * @return array[WidgetOption]
     */
    public function getByWidgetId($widgetId)
    {
        return $this->query(null, array('widgetId' => $widgetId));
    }

    /**
     * Query Widget options
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[WidgetOption]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = array();

        $sql = 'SELECT * FROM `widgetoption` WHERE widgetId = :widgetId';

        foreach ($this->getStore()->select($sql, [
            'widgetId' => $this->getSanitizer()->getInt('widgetId', $filterBy)
        ]) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }
}