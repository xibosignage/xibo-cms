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
use Respect\Validation\Validator as v;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class MenuBoardProductOption
 * @package Xibo\Entity
 */
#[OA\Schema]
class MenuBoardProductOption implements \JsonSerializable
{
    use EntityTrait;

    #[OA\Property(description: 'The Menu Product ID that this Option belongs to')]
    public $menuProductId;

    #[OA\Property(description: 'The option name')]
    public $option;

    #[OA\Property(description: 'The option value')]
    public $value;

    public function __construct(
        StorageServiceInterface $store,
        LogServiceInterface $log,
        EventDispatcherInterface $dispatcher,
    ) {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    public function __clone(): void
    {
        $this->menuProductId = null;
    }

    public function __toString(): string
    {
        return sprintf('ProductOption %s with value %s', $this->option, $this->value);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function validate(): void
    {
        if (!v::stringType()->notEmpty()->validate($this->option)
            && v::floatType()->notEmpty()->validate($this->value)
        ) {
            throw new InvalidArgumentException(__('Each value needs a corresponding option'), 'option');
        }

        if (!v::floatType()->notEmpty()->validate($this->value)
            && v::stringType()->notEmpty()->validate($this->option)
        ) {
            throw new InvalidArgumentException(__('Each option needs a corresponding value'), 'value');
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function save(array $options = []): void
    {
        $options = array_merge([
            'validate' => true,
        ], $options);

        $this->getLog()->debug('Saving ' . $this);

        if ($options['validate']) {
            $this->validate();
        }

        $this->getStore()->insert(
            'INSERT INTO `menu_product_options` (`menuProductId`, `option`, `value`) VALUES (:menuProductId, :option, :value) ON DUPLICATE KEY UPDATE `value` = :value2',
            [
                'menuProductId' => $this->menuProductId,
                'option' => $this->option,
                'value' => $this->value,
                'value2' => $this->value
            ]
        );
    }

    public function delete(): void
    {
        $this->getStore()->update(
            'DELETE FROM `menu_product_options` WHERE `menuProductId` = :menuProductId AND `option` = :option',
            [
                'menuProductId' => $this->menuProductId,
                'option' => $this->option
            ]
        );
    }
}
