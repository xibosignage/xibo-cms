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
namespace Xibo\Middleware;

use Slim\App;

/**
 * Trait CustomMiddlewareTrait
 * Add this trait to all custom middleware
 * @package Xibo\Middleware
 */
trait CustomMiddlewareTrait
{
    /** @var \Slim\App */
    private $app;

    /**
     * @param \Slim\App $app
     * @return $this
     */
    public function setApp(App $app)
    {
        $this->app = $app;
        return $this;
    }

    /**
     * @return \Slim\App
     */
    protected function getApp()
    {
        return $this->app;
    }

    /**
     * @return \DI\Container|\Psr\Container\ContainerInterface
     */
    protected function getContainer()
    {
        return $this->app->getContainer();
    }

    /***
     * @param $key
     * @return mixed
     */
    protected function getFromContainer($key)
    {
        return $this->getContainer()->get($key);
    }
}
