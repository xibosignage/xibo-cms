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

namespace Xibo\Controller;

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Views\Twig;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;

/**
 * Class MediaManager
 * @package Xibo\Controller
 */
class MediaManager extends Base
{
    /** @var ModuleFactory */
    private $moduleFactory;

    /** @var LayoutFactory */
    private $layoutFactory;

    /** @var RegionFactory */
    private $regionFactory;

    /** @var PlaylistFactory */
    private $playlistFactory;

    /** @var WidgetFactory */
    private $widgetFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param ModuleFactory $moduleFactory
     * @param LayoutFactory $layoutFactory
     * @param RegionFactory $regionFactory
     * @param PlaylistFactory $playlistFactory
     * @param WidgetFactory $widgetFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $moduleFactory, $layoutFactory, $regionFactory, $playlistFactory, $widgetFactory, Twig $view)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config, $view);
        $this->moduleFactory = $moduleFactory;
        $this->layoutFactory = $layoutFactory;
        $this->regionFactory = $regionFactory;
        $this->playlistFactory = $playlistFactory;
        $this->widgetFactory = $widgetFactory;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    public function displayPage(Request $request, Response $response)
    {
        $moduleFactory = $this->moduleFactory;
        
        $this->getState()->template .= 'media-manager-page';
        $this->getState()->setData([
            // Users we have permission to see
            'modules' => $this->moduleFactory->query(null, ['assignable' => 1, 'enabled' => 1]),
            'assignableModules' => array_map(function($element) use ($moduleFactory) { 
                    $module = $moduleFactory->createForInstall($element->class);
                    $module->setModule($element);
                    return $module;
                }, $moduleFactory->getAssignableModules())
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     * @throws \Xibo\Exception\NotFoundException
     */
    public function grid(Request $request, Response $response)
    {
        $this->getState()->template = 'grid';
        $sanitizedQueryParams = $this->getSanitizer($request->getQueryParams());

        $rows = [];

        $widgets = $this->widgetFactory->query($this->gridRenderSort($request), $this->gridRenderFilter([
            'layout' => $sanitizedQueryParams->getString('layout'),
            'region' => $sanitizedQueryParams->getString('region'),
            'media' => $sanitizedQueryParams->getString('media'),
            'type' => $sanitizedQueryParams->getString('type'),
            'playlist' => $sanitizedQueryParams->getString('playlist'),
            'showWidgetsFrom' => $sanitizedQueryParams->getInt('showWidgetsFrom')
        ], $request));
        $widgetsCount = $this->widgetFactory->countLast();

        foreach ($widgets as $widget) {

            // Load the widget
            $widget->load();

            // Create a module
            $module = $this->moduleFactory->createWithWidget($widget);

            // Get a list of Layouts that this playlist uses
            $layouts = $this->layoutFactory->query(null, ['playlistId' => $widget->playlistId, 'showDrafts' => 1], $request);

            $layoutNames = array_map(function($layout) {
                return $layout->layout;
            }, $layouts);

            // Get a list of Regions that this playlists uses
            $regions = $this->regionFactory->getByPlaylistId($widget->playlistId);

            $regionNames = array_map(function($region) {
                return $region->name;
            }, $regions);

            // We are good to go
            $row = [
                'layout' => implode(',', $layoutNames),
                'region' => implode(',', $regionNames),
                'playlist' => $widget->playlist,
                'widget' => $module->getName(),
                'widgetId' => $widget->widgetId,
                'type' => $module->getModuleName(),
                'displayOrder' => $widget->displayOrder,
                'thumbnail' => '',
                'thumbnailUrl' => ''
            ];

            $row['buttons'] = [];

            // Check editable
            if (!$this->getUser()->checkEditable($widget)) {
                $rows[] = $row;
                continue;
            }

            // for widgets on Playlist not inside of a region
            $regionWidth = null;
            $regionHeight = null;

            // Get region dimensions
            foreach ($regions as $region) {
                $regionWidth = $region->width;
                $regionHeight = $region->height;
            }

            $row['buttons'][] = [
                'id' => 'WidgetEditForm',
                'class' => 'WidgetEditForm',
                'dataAttributes' => [
                    ['name' => 'region-width', 'value' => $regionWidth],
                    ['name' => 'region-height', 'value' => $regionHeight]
                ],
                'url' => $this->urlFor($request,'module.widget.edit.form', ['id' => $widget->widgetId]),
                'text' => __('Edit')
            ];

            // Thumbnail URL
            $row['thumbnail'] = '';
            $row['thumbnailUrl'] = '';

            if ($module->getModule()->regionSpecific == 0) {

                if ($widget->type == 'image') {
                    $download = $this->urlFor($request,'library.download', ['id' => $widget->getPrimaryMediaId()]) . '?preview=1';
                    $row['thumbnail'] = '<a class="img-replace" data-toggle="lightbox" data-type="image" href="' . $download . '"><img src="' . $download . '&width=100&height=56&cache=1" /></i></a>';
                    $row['thumbnailUrl'] = $download . '&width=100&height=56&cache=1';
                }

                // Add a replace button directly on the drop down menu
                $row['buttons'][] = [
                    'id' => 'MediaReplaceForm',
                    'url' => '#',
                    'text' => __('Replace'),
                    'dataAttributes' => [
                        ['name' => 'media-id', 'value' => $widget->getPrimaryMediaId()],
                        ['name' => 'widget-id', 'value' => $widget->widgetId],
                        ['name' => 'valid-extensions', 'value' => implode('|', $this->moduleFactory->getValidExtensions(['type' => $widget->type]))]
                    ],
                    'class' => 'MediaManagerReplaceButton'
                ];
            }


            $rows[] = $row;
        }

        $this->getState()->recordsTotal = $widgetsCount;
        $this->getState()->setData($rows);

        return $this->render($request, $response);
    }
}
