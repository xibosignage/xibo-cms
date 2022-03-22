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
use Xibo\Entity\Region;
use Xibo\Entity\Widget;
use Xibo\Helper\DateFormatHelper;

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
     * @throws \Twig\Error\SyntaxError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\LoaderError
     */
    public function render(
        Module $module,
        Region $region,
        Widget $widget,
        int $displayId
    ): string {
        $this->getLog()->debug('getResourceOrCache for displayId ' . $displayId
            . ' and widgetId ' . $widget->widgetId);

        // Have we changed since we last cached this widget
        $modifiedDt = Carbon::createFromTimestamp($widget->modifiedDt);
        $cachedDt = $this->getCacheDate($displayId);
        $cachePath = $this->cachePath . DIRECTORY_SEPARATOR . $widget->widgetId . DIRECTORY_SEPARATOR;

        // Prefix whatever cacheKey the Module generates with the Region dimensions.
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

            // Build up some data for twig
            $twig = [];
            $twig['viewPortWidth'] = $displayId === 0 ? $region->width : '[[ViewPortWidth]]';

            // What does our module have
            if ($module->stencil !== null) {
                if ($module->stencil->twig !== null) {
                    $twig['stencil'] = $this->twig->fetchFromString($module->stencil->twig);
                }
                if ($module->stencil->hbs !== null) {
                    $twig['hbs'] = $module->stencil->hbs;
                }
            }

            // What does our template have?


            // We use the default get resource template.
            $output = $this->twig->fetch('widget-html-render.twig', $twig);

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
     * @param int $widgetId
     * @return \Carbon\Carbon
     */
    private function getCacheDate(int $widgetId): Carbon
    {
        $item = $this->pool->getItem('/widget/html' . $widgetId);
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
        $item = $this->pool->getItem('/widget/html' . $widgetId);

        $item->set($now->format(DateFormatHelper::getSystemFormat()));
        $item->expiresAt($now->addYear());

        $this->pool->save($item);
    }
}
