<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */


namespace Xibo\Entity;
use OpenApi\Attributes as OA;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;


/**
 * Class WidgetOption
 * @package Xibo\Entity
 */
#[OA\Schema]
class WidgetOption implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Widget ID that this Option belongs to')]
    public $widgetId;

    /**
     * @var string
     */
    #[OA\Property(description: 'The option type, either attrib or raw')]
    public $type;

    /**
     * @var string
     */
    #[OA\Property(description: 'The option name')]
    public $option;

    /**
     * @var string
     */
    #[OA\Property(description: 'The option value')]
    public $value;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct($store, $log, $dispatcher)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    public function __clone()
    {
        $this->widgetId = null;
    }

    public function __toString()
    {
        if ($this->type == 'cdata') {
            return sprintf('%s WidgetOption %s', $this->type, $this->option);
        }
        else {
            return sprintf('%s WidgetOption %s with value %s', $this->type, $this->option, $this->value);
        }
    }

    public function save()
    {
        $this->getLog()->debug('Saving ' . $this);

        $this->getStore()->insert('
            INSERT INTO `widgetoption` (`widgetId`, `type`, `option`, `value`)
              VALUES (:widgetId, :type, :option, :value) ON DUPLICATE KEY UPDATE `value` = :value2
        ', array(
            'widgetId' => $this->widgetId,
            'type' => $this->type,
            'option' => $this->option,
            'value' => $this->value,
            'value2' => $this->value
        ));
    }

    public function delete()
    {
        $this->getStore()->update('DELETE FROM `widgetoption` WHERE `widgetId` = :widgetId AND `option` = :option', array(
            'widgetId' => $this->widgetId, 'option' => $this->option)
        );
    }
}