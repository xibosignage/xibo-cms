<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

use OpenApi\Attributes as OA;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class UserOption
 * @package Xibo\Entity
 */
#[OA\Schema]
class UserOption implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @var int
     */
    #[OA\Property(description: 'The userId that this Option applies to')]
    public $userId;

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
        $this->excludeProperty('userId');
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        return $this->userId . '-' . $this->option . '-' . md5($this->value);
    }

    public function save()
    {
        // when the option is not in the database and default is on, switching option value to off
        // would not insert the record, hence the additional condition here xibosignage/xibo#2975
        if ($this->hasPropertyChanged('value')
            || ($this->getOriginalValue('value') === null && $this->value !== null)
        ) {
            $this->getStore()->insert('INSERT INTO `useroption` (`userId`, `option`, `value`) VALUES (:userId, :option, :value) ON DUPLICATE KEY UPDATE `value` = :value2', [
                'userId' => $this->userId,
                'option' => $this->option,
                'value' => $this->value,
                'value2' => $this->value,
            ]);
        }
    }

    public function delete()
    {
        $this->getStore()->update('DELETE FROM `useroption` WHERE `userId` = :userId AND `option` = :option', [
            'userId' => $this->userId,
            'option' => $this->option
        ]);
    }
}
