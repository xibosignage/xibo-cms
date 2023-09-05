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

namespace Xibo\Factory;

use DI\ContainerBuilder;
use Exception;
use Psr\Container\ContainerInterface;
use Slim\Views\Twig;
use Stash\Driver\Composite;
use Stash\Pool;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Xibo\Dependencies\Controllers;
use Xibo\Dependencies\Factories;
use Xibo\Entity\User;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Environment;
use Xibo\Helper\SanitizerService;
use Xibo\Service\BaseDependenciesService;
use Xibo\Service\ConfigService;
use Xibo\Service\HelpService;
use Xibo\Service\ImageProcessingService;
use Xibo\Service\JwtService;
use Xibo\Service\MediaService;
use Xibo\Service\ModuleService;
use Xibo\Storage\MySqlTimeSeriesStore;
use Xibo\Storage\PdoStorageService;
use Xibo\Twig\ByteFormatterTwigExtension;
use Xibo\Twig\DateFormatTwigExtension;
use Xibo\Twig\TransExtension;

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
}

/**
 * Class ContainerFactory
 * @package Xibo\Factory
 */
class ContainerFactory
{
    /**
     * Create DI Container with definitions
     *
     * @return ContainerInterface
     * @throws Exception
     */
    public static function create()
    {
        $containerBuilder = new ContainerBuilder();

        $containerBuilder->addDefinitions([
            'basePath' => function (ContainerInterface $c) {
                // Server params
                $scriptName = $_SERVER['SCRIPT_NAME'] ?? ''; // <-- "/foo/index.php"
                $requestUri = $_SERVER['REQUEST_URI'] ?? ''; // <-- "/foo/bar?test=abc" or "/foo/index.php/bar?test=abc"

                // Physical path
                if (empty($scriptName) || empty($requestUri)) {
                    return '';
                } else if (strpos($requestUri, $scriptName) !== false) {
                    $physicalPath = $scriptName; // <-- Without rewriting
                } else {
                    $physicalPath = str_replace('\\', '', dirname($scriptName)); // <-- With rewriting
                }
                return rtrim($physicalPath, '/'); // <-- Remove trailing slashes
            },
            'rootUri' => function (ContainerInterface $c) {
                // Work out whether we're in a folder, and what our base path is relative to that folder
                // Static source, so remove index.php from the path
                // this should only happen if rewrite is disabled
                $basePath = str_replace('/index.php', '', $c->get('basePath') . '/');

                // Replace out all of the entrypoints to get back to the root
                $basePath = str_replace('/api/authorize', '', $basePath);
                $basePath = str_replace('/api', '', $basePath);
                $basePath = str_replace('/maintenance', '', $basePath);
                $basePath = str_replace('/install', '', $basePath);

                // Handle an empty (we always have our root with reference to `/`
                if ($basePath == null) {
                    $basePath = '/';
                }

                return $basePath;
            },
            'logService' => function (ContainerInterface $c) {
                return new \Xibo\Service\LogService($c->get('logger'));
            },
            'view' => function (ContainerInterface $c) {
                $view =  Twig::create([
                    PROJECT_ROOT . '/views',
                    PROJECT_ROOT . '/modules',
                    PROJECT_ROOT . '/reports',
                    PROJECT_ROOT . '/custom'
                ], [
                    'cache' => Environment::isDevMode() ? false : PROJECT_ROOT . '/cache'
                ]);
                $view->addExtension(new TransExtension());
                $view->addExtension(new ByteFormatterTwigExtension());
                $view->addExtension(new DateFormatTwigExtension());

                return $view;
            },
            'sanitizerService' => function (ContainerInterface $c) {
                return new SanitizerService();
            },
            'store' => function (ContainerInterface $c) {
                return (new PdoStorageService($c->get('logService')))->setConnection();
            },
            'timeSeriesStore' => function (ContainerInterface $c) {
                if ($c->get('configService')->timeSeriesStore == null) {
                    $timeSeriesStore = new MySqlTimeSeriesStore();
                } else {
                    $timeSeriesStore = $c->get('configService')->timeSeriesStore;
                    $timeSeriesStore = $timeSeriesStore();
                }

                return $timeSeriesStore
                    ->setDependencies(
                        $c->get('logService'),
                        $c->get('layoutFactory'),
                        $c->get('campaignFactory'),
                        $c->get('mediaFactory'),
                        $c->get('widgetFactory'),
                        $c->get('displayFactory'),
                        $c->get('displayGroupFactory')
                    )
                    ->setStore($c->get('store'));
            },
            'state' => function () {
                return new ApplicationState();
            },
            'configService' => function (ContainerInterface $c) {
                return ConfigService::Load($c, PROJECT_ROOT . '/web/settings.php');
            },
            'user' => function (ContainerInterface $c) {
                return new User(
                    $c->get('store'),
                    $c->get('logService'),
                    $c->get('dispatcher'),
                    $c->get('configService'),
                    $c->get('userFactory'),
                    $c->get('permissionFactory'),
                    $c->get('userOptionFactory'),
                    $c->get('applicationScopeFactory')
                );
            },
            'helpService' => function (ContainerInterface $c) {
                // Pass in the help base configuration.
                $helpBase = $c->get('configService')->getSetting('HELP_BASE');
                if (stripos($helpBase, 'http://') === false && stripos($helpBase, 'https://') === false) {
                    // We need to convert the URL to a full URL
                    $helpBase = $c->get('configService')->rootUri() . $helpBase;
                }
                return new HelpService($helpBase);
            },
            'pool' => function (ContainerInterface $c) {
                $drivers = [];

                if ($c->get('configService')->getCacheDrivers() != null && is_array($c->get('configService')->getCacheDrivers())) {
                    $drivers = $c->get('configService')->getCacheDrivers();
                } else {
                    // File System Driver
                    $realPath = realpath($c->get('configService')->getSetting('LIBRARY_LOCATION'));
                    $cachePath = ($realPath)
                        ? $realPath . '/cache/'
                        : $c->get('configService')->getSetting('LIBRARY_LOCATION') . 'cache/';

                    $drivers[] = new \Stash\Driver\FileSystem(['path' => $cachePath]);
                }

                // Create a composite driver
                $composite = new Composite(['drivers' => $drivers]);

                $pool = new Pool($composite);
                $pool->setLogger($c->get('logService')->getLoggerInterface());
                $pool->setNamespace($c->get('configService')->getCacheNamespace());
                $c->get('configService')->setPool($pool);
                return $pool;
            },
            'imageProcessingService' => function (ContainerInterface $c) {
                $imageProcessingService = new ImageProcessingService();
                $imageProcessingService->setDependencies(
                    $c->get('logService')
                );
                return $imageProcessingService;
            },
            'httpCache' => function () {
                return new \Xibo\Helper\HttpCacheProvider();
            },
            'mediaService' => function (ContainerInterface $c) {
                $mediaSevice = new MediaService(
                    $c->get('configService'),
                    $c->get('logService'),
                    $c->get('store'),
                    $c->get('sanitizerService'),
                    $c->get('pool'),
                    $c->get('mediaFactory'),
                    $c->get('fontFactory')
                );
                $mediaSevice->setDispatcher($c->get('dispatcher'));
                return $mediaSevice;
            },
            'ControllerBaseDependenciesService' => function (ContainerInterface $c) {
                $controller = new BaseDependenciesService();
                $controller->setLogger($c->get('logService'));
                $controller->setSanitizer($c->get('sanitizerService'));
                $controller->setState($c->get('state'));
                $controller->setUser($c->get('user'));
                $controller->setConfig($c->get('configService'));
                $controller->setView($c->get('view'));
                $controller->setDispatcher($c->get('dispatcher'));
                return $controller;
            },
            'RepositoryBaseDependenciesService' => function (ContainerInterface $c) {
                $repository = new BaseDependenciesService();
                $repository->setLogger($c->get('logService'));
                $repository->setSanitizer($c->get('sanitizerService'));
                $repository->setStore($c->get('store'));
                $repository->setDispatcher($c->get('dispatcher'));
                $repository->setConfig($c->get('configService'));
                return $repository;
            },
            'dispatcher' => function (ContainerInterface $c) {
                return new EventDispatcher();
            },
            'jwtService' => function (ContainerInterface $c) {
                return (new JwtService())
                    ->useLogger($c->get('logService')->getLoggerInterface())
                    ->useKeys($c->get('configService')->getApiKeyDetails());
            }
        ]);

        $containerBuilder->addDefinitions(Controllers::registerControllersWithDi());
        $containerBuilder->addDefinitions(Factories::registerFactoriesWithDi());

        // Should we compile the container?
        /*if (!Environment::isDevMode()) {
            $containerBuilder->enableCompilation(PROJECT_ROOT . '/cache');
        }*/

        return $containerBuilder->build();
    }
}
