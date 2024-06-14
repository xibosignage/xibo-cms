<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

namespace Xibo\Entity;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class representing widget data
 */
class WidgetData implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The ID")
     * @var int|null
     */
    public ?int $id;

    /**
     * @SWG\Property(description="The Widget ID")
     * @var int
     */
    public int $widgetId;

    /**
     * @SWG\Property(description="Array of data properties")
     * @var array
     */
    public array $data;

    /**
     * @SWG\Property(description="The Display Order")
     * @var int
     */
    public int $displayOrder;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct(
        StorageServiceInterface $store,
        LogServiceInterface $log,
        EventDispatcherInterface $dispatcher
    ) {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    /**
     * Save this widget data
     * @return $this
     */
    public function save(): WidgetData
    {
        if ($this->id === null) {
            $this->add();
        } else {
            $this->edit();
        }
        return $this;
    }

    /**
     * Delete this widget data
     * @return void
     */
    public function delete(): void
    {
        $this->getStore()->update('DELETE FROM `widgetdata` WHERE `id` = :`id`', ['id' => $this->id]);
    }

    /**
     * Add and capture the ID
     * @return void
     */
    private function add(): void
    {
        $this->id = $this->getStore()->insert('
            INSERT INTO `widgetdata` (widgetId, data, displayOrder) 
                VALUES (:widgetId, :data, :displayOrder)
        ', [
            'widgetId' => $this->widgetId,
            'data' => $this->data == null ? null : json_encode($this->data),
            'displayOrder' => $this->displayOrder,
        ]);
    }

    /**
     * Edit
     * @return void
     */
    private function edit(): void
    {
        $this->getStore()->update('
            UPDATE `widgetdata` SET
                `data` = :data,
                `displayOrder` = :displayOrder
             WHERE `id` = :`id`
        ', [
            'id' => $this->id,
            'data' => $this->data == null ? null : json_encode($this->data),
            'displayOrder' => $this->displayOrder,
        ]);
    }
}
