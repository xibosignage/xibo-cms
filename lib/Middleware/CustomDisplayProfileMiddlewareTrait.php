<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
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

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;
use Xibo\Support\Exception\InvalidArgumentException;

trait CustomDisplayProfileMiddlewareTrait
{
    /**
     * @return string
     */
    public static function getClass():string
    {
        return self::class;
    }

    public static function getEditTemplateFunctionName():string
    {
        return 'getCustomEditTemplate';
    }

    public static function getDefaultConfigFunctionName():string
    {
        return 'getDefaultConfig';
    }

    public static function getEditCustomFieldsFunctionName():string
    {
        return 'editCustomConfigFields';
    }

    /**
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     * @throws InvalidArgumentException
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $this->getFromContainer('logService')->debug('Loading additional Middleware for Custom Display Profile type:' . self::getType());

        $store = $this->getFromContainer('store');
        $results = $store->select('SELECT displayProfileId FROM displayprofile WHERE type = :type', ['type' => self::getType()]);

        if (count($results) <= 0) {
            $profile = $this->getFromContainer('displayProfileFactory')->createCustomProfile([
                'name' => self::getName(),
                'type' => self::getType(),
                'isDefault' => 1,
                'userId' => $this->getFromContainer('userFactory')->getSuperAdmins()[0]->userId
            ]);
            $profile->save();
        }

        $this->getFromContainer('displayProfileFactory')->registerCustomDisplayProfile(
            self::getType(),
            self::getClass(),
            self::getEditTemplateFunctionName(),
            self::getDefaultConfigFunctionName(),
            self::getEditCustomFieldsFunctionName()
        );
        // Next middleware
        return $handler->handle($request);
    }

    /**
     * @return string
     * @throws InvalidArgumentException
     */
    public static function getCustomEditTemplate() : string
    {
        return 'displayprofile-form-edit-'.self::getType().'.twig';
    }

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
     * @return \Psr\Container\ContainerInterface|null
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

    private static function handleChangedSettings($setting, $oldValue, $newValue, &$changedSettings)
    {
        if ($oldValue != $newValue) {
            $changedSettings[$setting] = $oldValue . ' > ' . $newValue;
        }
    }
}
