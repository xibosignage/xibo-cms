<?php
/*
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

namespace Xibo\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Xibo\Support\Exception\AccessDeniedException;

/**
 * Class FeatureAuth
 * This is route middleware to checks user access against a set of features
 * @package Xibo\Middleware
 */
class FeatureAuth implements MiddlewareInterface
{
    /** @var \Psr\Container\ContainerInterface */
    private $container;

    /** @var array */
    private $features;

    /**
     * FeatureAuth constructor.
     * @param ContainerInterface $container
     * @param array $features an array of one or more features which would authorize access
     */
    public function __construct(ContainerInterface $container, array $features)
    {
        $this->container = $container;
        $this->features = $features;
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Xibo\Support\Exception\AccessDeniedException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // If no features are provided, then this must be public
        if (count($this->features) <= 0) {
            // We handle the rest of the request
            return $handler->handle($request);
        }

        // Compare the features requested with the features this user has access to.
        // if none match, throw 403
        foreach ($this->features as $feature) {
            if ($this->getUser()->featureEnabled($feature)) {
                // We handle the rest of the request
                return $handler->handle($request);
            }
        }

        throw new AccessDeniedException(__('Feature not enabled'), __('This feature has not been enabled for your user.'));
    }

    /**
     * @return \Xibo\Entity\User
     */
    private function getUser()
    {
        return $this->container->get('user');
    }
}