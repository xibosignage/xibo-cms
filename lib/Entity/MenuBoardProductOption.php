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

namespace Xibo\Entity;

use Respect\Validation\Validator as v;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * @SWG\Definition()
 */
class MenuBoardProductOption implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The Menu Product ID that this Option belongs to")
     * @var int
     */
    public $menuProductId;

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
    public function __construct($store, $log, $dispatcher)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    public function __clone()
    {
        $this->menuProductId = null;
    }

    public function __toString()
    {
        return sprintf('ProductOption %s with value %s', $this->option, $this->value);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function validate()
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
     * @param array $options
     * @throws InvalidArgumentException
     */
    public function save($options = [])
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

    public function delete()
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
