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
use Illuminate\Support\Str;
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
     * Render or cache.
     * ----------------
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
        array $moduleTemplates
    ): string {
        // For caching purposes we always take only the first widget
        $widget = $widgets[0];

        if (!file_exists($this->cachePath)) {
            mkdir($this->cachePath, 0777, true);
        }

        // Cache File
        // ----------
        // width_height
        // Widgets may or may not appear in the same Region each time they are previewed due to them potentially
        // being contained in a Playlist.
        // Equally, a Region might be resized, which would also affect the way the Widget looks. Just moving a Region
        // location wouldn't though, which is why we base this on the width/height.
        $cachePath = $this->cachePath . DIRECTORY_SEPARATOR
            . $widget->widgetId
            . '_'
            . $region->width
            . '_'
            . $region->height
            . '.html';

        // Have we changed since we last cached this widget
        $modifiedDt = Carbon::createFromTimestamp($widget->modifiedDt);
        $cachedDt = Carbon::createFromTimestamp(file_exists($cachePath) ? filemtime($cachePath) : 0);

        $this->getLog()->debug('Cache details - modifiedDt: '
            . $modifiedDt->format(DateFormatHelper::getSystemFormat())
            . ', cachedDt: ' . $cachedDt->format(DateFormatHelper::getSystemFormat())
            . ', cachePath: ' . $cachePath);

        if ($modifiedDt->greaterThan($cachedDt) || !file_get_contents($cachePath)) {
            $this->getLog()->debug('We will need to regenerate');

            // Are we worried about concurrent requests here?
            // these aren't providing any data anymore, so in theory it shouldn't be possible to
            // get locked up here
            // We don't clear cached media here, as that comes along with data.
            if (file_exists($cachePath)) {
                $this->getLog()->debug('Deleting cache file ' . $cachePath . ' which already existed');
                unlink($cachePath);
            }

            // Render
            $output = $this->render($module, $region, $widgets, $moduleTemplates);

            // Cache to the library
            file_put_contents($cachePath, $output);

            $this->getLog()->debug('Generate complete');

            return $output;
        } else {
            $this->getLog()->debug('Serving from cache');
            return file_get_contents($cachePath);
        }
    }

    /**
     * Decorate the HTML output for a preview
     * @param \Xibo\Entity\Region $region
     * @param string $output
     * @param string $dataUrl
     * @param string $libraryUrl
     * @return string
     */
    public function decorateForPreview(Region $region, string $output, string $dataUrl, string $libraryUrl): string
    {
        $matches = [];
        preg_match_all('/\[\[(.*?)\]\]/', $output, $matches);
        foreach ($matches[1] as $match) {
            if ($match === 'ViewPortWidth') {
                $output = str_replace('[[ViewPortWidth]]', $region->width, $output);
            } else if (Str::startsWith($match, 'dataUrl')) {
                $value = explode('=', $match);
                $output = str_replace(
                    '[[' . $match . ']]',
                    str_replace(':id', $value[1], $dataUrl),
                    $output
                );
            } else if (Str::startsWith($match, 'mediaId')) {
                $value = explode('=', $match);
                $output = str_replace(
                    '[[' . $match . ']]',
                    str_replace(':id', $value[1], $libraryUrl),
                    $output
                );
            }
        }
        return $output;
    }

    /**
     * Decorate the HTML output for a player
     * @param string $output
     * @param array $storedAs A keyed array of library media this widget has access to
     * @return string
     */
    public function decorateForPlayer(string $output, array $storedAs): string
    {
        $matches = [];
        preg_match_all('/\[\[(.*?)\]\]/', $output, $matches);
        foreach ($matches[1] as $match) {
            if ($match === 'ViewPortWidth') {
                // Player does this itself.
                continue;
            } else if (Str::startsWith($match, 'dataUrl')) {
                $value = explode('=', $match);
                $output = str_replace('[[' . $match . ']]', $value[1] . '.json', $output);
            } else if (Str::startsWith($match, 'mediaId')) {
                $value = explode('=', $match);
                if (array_key_exists($value[1], $storedAs)) {
                    $output = str_replace('[[' . $match . ']]', $storedAs[$value[1]]['storedAs'], $output);
                } else {
                    $output = str_replace('[[' . $match . ']]', '', $output);
                }
            }
        }
        return $output;
    }

    /**
     * Render out the widgets HTML
     * @param \Xibo\Entity\Widget[] $widgets
     * @param ModuleTemplate[] $moduleTemplates
     * @throws \Twig\Error\SyntaxError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\LoaderError
     */
    private function render(
        Module $module,
        Region $region,
        array $widgets,
        array $moduleTemplates
    ): string {
        // Build up some data for twig
        $twig = [];
        $twig['hbs'] = [];
        $twig['twig'] = [];
        $twig['elements'] = [];
        $twig['width'] = $region->width;
        $twig['height'] = $region->height;

        // Output some data for each widget.
        $twig['data'] = [];

        // Max duration
        $duration = 0;

        // Render each widget out into the html
        foreach ($widgets as $widget) {
            $this->getLog()->debug('render: widget to process is widgetId: ' . $widget->widgetId);

            // Decorate our module with the saved widget properties
            // we include the defaults.
            $module->decorateProperties($widget, true);

            // Output some sample data and a data url.
            $twig['data'][] = [
                'widgetId' => $widget->widgetId,
                'url' => '[[dataUrl=' . $widget->widgetId . ']]'
            ];

            // Watermark duration
            $duration = max($duration, $widget->calculatedDuration);

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

        // Duration
        $twig['duration'] = $duration;

        // We use the default get resource template.
        return $this->twig->fetch('widget-html-render.twig', $twig);
    }
}
