<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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
use Xibo\Support\Exception\AccessDeniedException;

/**
 * Class ApplicationScope
 * @package Xibo\Entity
 */
class ApplicationScope implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $description;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
    }

    /**
     * Get Id
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Check whether this scope has permission for this route
     * @param $method
     * @param $route
     * @throws AccessDeniedException
     */
    public function checkRoute($method, $route)
    {
        $route = $this->getStore()->select('
            SELECT *
              FROM `oauth_scope_routes`
             WHERE scopeId = :scope
              AND method = :method
              AND route = :route
        ', [
            'scope' => $this->getId(),
            'method' => $method,
            'route' => $route
        ]);

        if (count($route) <= 0)
            throw new AccessDeniedException(__('Access to this route is denied for this scope'));
    }
}
