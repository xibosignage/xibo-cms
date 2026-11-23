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

use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

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
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct($store, $log, $dispatcher)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    public function __serialize(): array
    {
        return $this->jsonSerialize();
    }

    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
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
     * @param string $method
     * @param string $requestedRoute
     * @return bool
     */
    public function checkRoute(string $method, string $requestedRoute): bool
    {
        $routes = $this->getStore()->select('
            SELECT `route`
              FROM `oauth_scope_routes`
             WHERE `scopeId` = :scope
              AND `method` LIKE :method
        ', [
            'scope' => $this->getId(),
            'method' => '%' . $method . '%',
        ]);

        $this->getLog()->debug('checkRoute: there are ' . count($routes) . ' potential routes for the scope '
            . $this->getId() . ' with ' . $method);

        // We need to look through each route and run the regex against our requested route.
        $grantAccess = false;
        foreach ($routes as $route) {
            $regexResult = preg_match($route['route'], $requestedRoute);
            if ($regexResult === 1) {
                $grantAccess = true;
                break;
            }
        }

        return $grantAccess;
    }
}
