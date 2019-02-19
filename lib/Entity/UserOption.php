<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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
namespace Xibo\Entity;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;


/**
 * Class UserOption
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class UserOption implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The userId that this Option applies to")
     * @var int
     */
    public $userId;

    /**
     * @SWG\Property(description="The option name")
     * @var string
     */
    public $option;

    /**
     * @SWG\Property(description="The option value")
     * @var string
     */
    public $value;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
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
        if (!$this->hasPropertyChanged('value')) {
            return;
        }

        $this->getStore()->insert('INSERT INTO `useroption` (`userId`, `option`, `value`) VALUES (:userId, :option, :value) ON DUPLICATE KEY UPDATE `value` = :value2', [
            'userId' => $this->userId,
            'option' => $this->option,
            'value' => $this->value,
            'value2' => $this->value,
        ]);
    }

    public function delete()
    {
        $this->getStore()->update('DELETE FROM `useroption` WHERE `userId` = :userId AND `option` = :option', [
            'userId' => $this->userId,
            'option' => $this->option
        ]);
    }
}