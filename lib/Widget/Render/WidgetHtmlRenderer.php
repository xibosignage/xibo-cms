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

namespace Xibo\Widget\Render;

use Carbon\Carbon;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\Views\Twig;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Module;
use Xibo\Entity\ModuleTemplate;
use Xibo\Entity\Region;
use Xibo\Entity\Widget;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Class responsible for rendering out a widgets HTML, caching it if necessary
 */
class WidgetHtmlRenderer
{
    /** @var string Cache Path */
    private $cachePath;

    /** @var LoggerInterface */
    private $logger;

    /** @var \Stash\Interfaces\PoolInterface */
    private $pool;

    /** @var \Slim\Views\Twig */
    private $twig;

    /**
     * @param string $cachePath
     * @param \Stash\Interfaces\PoolInterface $pool
     * @param \Slim\Views\Twig $twig
     */
    public function __construct(string $cachePath, PoolInterface $pool, Twig $twig)
    {
        $this->cachePath = $cachePath;
        $this->pool = $pool;
        $this->twig = $twig;
    }

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @return $this
     */
    public function useLogger(LoggerInterface $logger): WidgetHtmlRenderer
    {
        $this->logger = $logger;
        return $this;
    }

    private function getLog(): LoggerInterface
    {
        if ($this->logger === null) {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }

    /**
     * @param \Xibo\Entity\Module $module
     * @param \Xibo\Entity\Region $region
     * @param \Xibo\Entity\Widget $widget
     * @param \Xibo\Support\Sanitizer\SanitizerInterface $params
     * @param string $downloadUrl
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function preview(
        Module $module,
        Region $region,
        Widget $widget,
        SanitizerInterface $params,
        string $downloadUrl
    ): string {
        if ($module->previewEnabled == 1) {
            $width = $params->getDouble('width', ['default' => 0]);
            $height = $params->getDouble('height', ['default' => 0]);

            if ($module->preview !== null) {
                // Parse out our preview (which is always a stencil)
                $module->decorateProperties($widget, true);
                return $this->twig->fetchFromString($module->preview->twig, [
                    'width' => $width,
                    'height' => $height,
                    'params' => $params,
                    'options' => $module->getPropertyValues(),
                    'downloadUrl' => $downloadUrl
                ]);
            } else {
                // Modules without a preview should render out as HTML
                return $this->twig->fetch('module-html-preview.twig', [
                    'width' => $width,
                    'height' => $height,
                    'regionId' => $region->regionId,
                    'widgetId' => $widget->widgetId
                ]);
            }
        } else {
            // Render an icon.
            return $this->twig->fetch('module-icon-preview.twig', [
                'moduleName' => $module->name,
                'moduleType' => $module->type
            ]);
        }
    }

    /**
     * @param ModuleTemplate[] $moduleTemplates
     * @param \Xibo\Entity\Widget[] $widgets
     * @throws \Twig\Error\SyntaxError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\LoaderError
     */
    public function renderOrCache(
        Module $module,
        Region $region,
        array $widgets,
        array $moduleTemplates,
        int $displayId
    ): string {
        // For caching purposes we always take only the first widget
        $widget = $widgets[0];

        // Have we changed since we last cached this widget
        $modifiedDt = Carbon::createFromTimestamp($widget->modifiedDt);
        $cachedDt = $this->getCacheDate($displayId);
        $cachePath = $this->cachePath . DIRECTORY_SEPARATOR . $widget->widgetId . DIRECTORY_SEPARATOR;

        // Cache File
        // ----------
        // displayId_width_height
        // Widgets may or may not appear in the same Region each time they are previewed due to them potentially
        // being contained in a Playlist.
        // Equally, a Region might be resized, which would also affect the way the Widget looks. Just moving a Region
        // location wouldn't though, which is why we base this on the width/height.
        $cacheFile = $widget->widgetId
            . (($displayId === 0) ? '_0' : '')
            . '_'
            . $region->width
            . '_'
            . $region->height;

        $this->getLog()->debug('Cache details - modifiedDt: '
            . $modifiedDt->format(DateFormatHelper::getSystemFormat())
            . ', cacheDt: ' . $cachedDt->format(DateFormatHelper::getSystemFormat())
            . ', cacheFile: ' . $cacheFile);

        if (!file_exists($cachePath)) {
            mkdir($cachePath, 0777, true);
        }

        if ($modifiedDt->greaterThan($cachedDt)
            || !file_exists($cachePath . $cacheFile)
            || !file_get_contents($cachePath . $cacheFile)
        ) {
            $this->getLog()->debug('We will need to regenerate');

            // Are we worried about concurrent requests here?
            // these aren't providing any data anymore, so in theory it shouldn't be possible to
            // get locked up here
            // We don't clear cached media here, as that comes along with data.
            if (file_exists($cachePath . $cacheFile)) {
                // Store a hash of the existing cache file to determine whether its worth
                // notifying displays the change
                $hash = md5_file($cachePath . $cacheFile);
                unlink($cachePath . $cacheFile);

                $this->getLog()->debug('Cache file ' . $cachePath . $cacheFile
                    . ' already existed with hash ' . $hash);
            } else {
                $hash = '';
            }

            // Render
            // for rendering purposes we always take all widgets
            $output = $this->render($module, $region, $widgets, $moduleTemplates, $displayId);

            // Cache to the library
            file_put_contents($cachePath . $cacheFile, $output);

            // Should we notify this display of this widget changing?
            if ($hash !== md5_file($cachePath . $cacheFile)) {
                $this->getLog()->debug('Cache file was different, we will need to notify the display');

                // Notify
                $widget->save([
                    'saveWidgetOptions' => false,
                    'notify' => false,
                    'notifyDisplays' => true,
                    'audit' => false
                ]);
            } else {
                $this->getLog()->debug('Cache file identical no need to notify the display');
            }

            // Update the cache date
            $this->setCacheDate($displayId);

            $this->getLog()->debug('Generate complete');

            return $output;
        } else {
            $this->getLog()->debug('Serving from cache');
            return file_get_contents($cachePath . $cacheFile);
        }
    }

    /**
     * @param \Xibo\Entity\Widget[] $widgets
     * @param ModuleTemplate[] $moduleTemplates
     * @throws \Twig\Error\SyntaxError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\LoaderError
     */
    public function render(
        Module $module,
        Region $region,
        array $widgets,
        array $moduleTemplates,
        int $displayId
    ): string {
        $this->getLog()->debug('render: getResourceOrCache for displayId ' . $displayId
            . ' and regionId ' . $region->regionId);

        // Build up some data for twig
        $twig = [];
        $twig['viewPortWidth'] = $displayId === 0 ? $region->width : '[[ViewPortWidth]]';
        $twig['hbs'] = [];
        $twig['twig'] = [];
        $twig['elements'] = [];

        // Render each widget out into the html
        foreach ($widgets as $widget) {
            $this->getLog()->debug('render: widget to process is widgetId: ' . $widget->widgetId);

            // Decorate our module with the saved widget properties
            // we include the defaults.
            $module->decorateProperties($widget, true);

            // What does our module have
            if ($module->stencil !== null) {
                // Stencils have access to any module properties
                if ($module->stencil->twig !== null) {
                    $twig['twig'][] = $this->twig->fetchFromString(
                        $module->stencil->twig,
                        $module->getPropertyValues()
                    );
                }
                if ($module->stencil->hbs !== null) {
                    $twig['hbs'][] = $module->stencil->hbs;
                }
            }

            // TODO: canvas widget
            // TODO: list of widgets will need to be output to the HTML
            //  we will need the elements associated with those widgets.
            //  we also need to send some widget options (probably selectively)
            //  perhaps we need an option for that in the XML?
            // Include elements/element groups - they will already be JSON encoded.
            $twig['elements'][] = $widget->getOptionValue('elements', null);

            // If we have a static template, then render that out.
            foreach ($moduleTemplates as $moduleTemplate) {
                if ($moduleTemplate->type === 'static') {
                    $moduleTemplate->decorateProperties($widget, true);
                    if ($moduleTemplate->stencil !== null) {
                        if ($moduleTemplate->stencil->twig !== null) {
                            $twig['twig'] = $this->twig->fetchFromString(
                                $moduleTemplate->stencil->twig,
                                $moduleTemplate->getPropertyValues()
                            );
                        }
                    }
                }

                // Render out any hbs
                if ($moduleTemplate->stencil->hbs !== null) {
                    $twig['hbs'][] = $moduleTemplate->stencil->hbs;
                }
            }
        }

        // We use the default get resource template.
        return $this->twig->fetch('widget-html-render.twig', $twig);
    }

    /**
     * @param int $widgetId
     * @return \Carbon\Carbon
     */
    private function getCacheDate(int $widgetId): Carbon
    {
        $item = $this->pool->getItem('/widget/html/' . $widgetId);
        $date = $item->get();

        // If not cached set it to have cached a long time in the past
        if ($date === null) {
            return Carbon::now()->subYear();
        }

        // Parse the date
        return Carbon::createFromFormat(DateFormatHelper::getSystemFormat(), $date);
    }

    /**
     * @param int $widgetId
     * @return void
     */
    private function setCacheDate(int $widgetId): void
    {
        $now = Carbon::now();
        $item = $this->pool->getItem('/widget/html/' . $widgetId);

        $item->set($now->format(DateFormatHelper::getSystemFormat()));
        $item->expiresAt($now->addYear());

        $this->pool->save($item);
    }
}
