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

use Carbon\Carbon;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Helper\DateFormatHelper;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class representing widget data
 * @SWG\Definition()
 */
class WidgetData implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(
     *     property="id",
     *     description="The ID"
     * )
     * @var int|null
     */
    public ?int $id = null;

    /**
     * @SWG\Property(
     *     property="widgetId",
     *     description="The Widget ID"
     * )
     * @var int
     */
    public int $widgetId;

    /**
     * @SWG\Property(
     *     property="data",
     *     description="Array of data properties depending on the widget data type this data is for",
     *     @SWG\Items(type="string")
     * )
     * @var array
     */
    public array $data;

    /**
     * @SWG\Property(
     *     property="displayOrder",
     *     description="The Display Order"
     * )
     * @var int
     */
    public int $displayOrder;

    /**
     * @SWG\Property(
     *     property="createdDt",
     *     description="The datetime this entity was created"
     * )
     * @var ?string
     */
    public ?string $createdDt;

    /**
     * @SWG\Property(
     *     property="createdDt",
     *     description="The datetime this entity was last modified"
     * )
     * @var ?string
     */
    public ?string $modifiedDt;

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
            $this->modifiedDt = Carbon::now()->format(DateFormatHelper::getSystemFormat());
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
        $this->getStore()->update('DELETE FROM `widgetdata` WHERE `id` = :id', ['id' => $this->id]);
    }

    /**
     * Add and capture the ID
     * @return void
     */
    private function add(): void
    {
        $this->id = $this->getStore()->insert('
            INSERT INTO `widgetdata` (`widgetId`, `data`, `displayOrder`, `createdDt`, `modifiedDt`) 
                VALUES (:widgetId, :data, :displayOrder, :createdDt, :modifiedDt)
        ', [
            'widgetId' => $this->widgetId,
            'data' => $this->data == null ? null : json_encode($this->data),
            'displayOrder' => $this->displayOrder,
            'createdDt' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            'modifiedDt' => null,
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
                `displayOrder` = :displayOrder,
                `modifiedDt` = :modifiedDt
             WHERE `id` = :id
        ', [
            'id' => $this->id,
            'data' => $this->data == null ? null : json_encode($this->data),
            'displayOrder' => $this->displayOrder,
            'modifiedDt' => $this->modifiedDt,
        ]);
    }
}
