<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
use Xibo\Entity\Module;
use Xibo\Entity\ModuleTemplate;
use Xibo\Entity\Region;
use Xibo\Entity\Widget;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Translate;
use Xibo\Service\ConfigServiceInterface;
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

    /** @var \Slim\Views\Twig */
    private $twig;

    /** @var \Xibo\Service\ConfigServiceInterface */
    private $config;

    /**
     * @param string $cachePath
     * @param \Slim\Views\Twig $twig
     * @param \Xibo\Service\ConfigServiceInterface $config
     */
    public function __construct(string $cachePath, Twig $twig, ConfigServiceInterface $config)
    {
        $this->cachePath = $cachePath;
        $this->twig = $twig;
        $this->config = $config;
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
                return $this->twig->fetchFromString(
                    $module->preview->twig,
                    array_merge(
                        [
                            'width' => $width,
                            'height' => $height,
                            'params' => $params,
                            'options' => $module->getPropertyValues(),
                            'downloadUrl' => $downloadUrl
                        ],
                        $module->getPropertyValues()
                    )
                );
            } else if ($module->renderAs === 'html') {
                // Modules without a preview should render out as HTML
                return $this->twig->fetch(
                    'module-html-preview.twig',
                    array_merge(
                        [
                            'width' => $width,
                            'height' => $height,
                            'regionId' => $region->regionId,
                            'widgetId' => $widget->widgetId
                        ],
                        $module->getPropertyValues()
                    )
                );
            }
        }

        // Render an icon.
        return $this->twig->fetch('module-icon-preview.twig', [
            'moduleName' => $module->name,
            'moduleType' => $module->type
        ]);
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
        // Widgets may or may not appear in the same Region each time they are previewed due to them potentially
        // being contained in a Playlist.
        // Region width/height only changes in Draft state, so the FE is responsible for asserting the correct
        // width/height relating scaling params when the preview first loads.
        $cachePath = $this->cachePath . DIRECTORY_SEPARATOR
            . $widget->widgetId
            . '_'
            . $region->regionId
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
     * @param callable $urlFor
     * @return string
     */
    public function decorateForPreview(Region $region, string $output, callable $urlFor): string
    {
        $matches = [];
        preg_match_all('/\[\[(.*?)\]\]/', $output, $matches);
        foreach ($matches[1] as $match) {
            if ($match === 'PlayerBundle') {
                $output = str_replace('[[PlayerBundle]]', $urlFor('layout.preview.bundle', []), $output);
            } else if ($match === 'FontBundle') {
                $output = str_replace('[[FontBundle]]', $urlFor('library.font.css', []), $output);
            } else if ($match === 'ViewPortWidth') {
                $output = str_replace('[[ViewPortWidth]]', $region->width, $output);
            } else if (Str::startsWith($match, 'dataUrl')) {
                $value = explode('=', $match);
                $output = str_replace(
                    '[[' . $match . ']]',
                    $urlFor('module.getData', ['regionId' => $region->regionId, 'id' => $value[1]]),
                    $output
                );
            } else if (Str::startsWith($match, 'data=')) {
                // Not needed as this CMS is always capable of providing separate data.
                $output = str_replace('"[[' . $match . ']]"', '[]', $output);
            } else if (Str::startsWith($match, 'mediaId') || Str::startsWith($match, 'libraryId')) {
                $value = explode('=', $match);
                $params = ['id' => $value[1]];
                if (Str::startsWith($match, 'mediaId')) {
                    $params['type'] = 'image';
                }
                $output = str_replace(
                    '[[' . $match . ']]',
                    $urlFor('library.download', $params) . '?preview=1',
                    $output
                );
            } else if (Str::startsWith($match, 'assetId')) {
                $value = explode('=', $match);
                $output = str_replace(
                    '[[' . $match . ']]',
                    $urlFor('module.asset.download', ['assetId' => $value[1]]) . '?preview=1',
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
     * @param bool $isSupportsDataUrl
     * @param array $data A keyed array of data this widget has access to
     * @param \Xibo\Widget\Definition\Asset[] $assets A keyed array of assets this widget has access to
     * @return string
     */
    public function decorateForPlayer(
        string $output,
        array $storedAs,
        bool $isSupportsDataUrl = true,
        array $data = [],
        array $assets = []
    ): string {
        $matches = [];
        preg_match_all('/\[\[(.*?)\]\]/', $output, $matches);
        foreach ($matches[1] as $match) {
            if ($match === 'PlayerBundle') {
                $output = str_replace('[[PlayerBundle]]', '-1.js', $output);
            } else if ($match === 'FontBundle') {
                $output = str_replace('[[FontBundle]]', 'fonts.css', $output);
            } else if ($match === 'ViewPortWidth') {
                // Player does this itself.
                continue;
            } else if ($match === 'Data') {
                $output = str_replace('[[Data]]', json_encode($data), $output);
            } else if (Str::startsWith($match, 'dataUrl')) {
                $value = explode('=', $match);
                $replace = $isSupportsDataUrl ? $value[1] . '.json' : $value[1] . '.html';
                $output = str_replace('[[' . $match . ']]', $replace, $output);
            } else if (Str::startsWith($match, 'data=')) {
                $value = explode('=', $match);
                $output = str_replace('[[' . $match . ']]', $data[$value[1]] ?? [], $output);
            } else if (Str::startsWith($match, 'mediaId') || Str::startsWith($match, 'libraryId')) {
                $value = explode('=', $match);
                if (array_key_exists($value[1], $storedAs)) {
                    $output = str_replace('[[' . $match . ']]', $storedAs[$value[1]]['storedAs'], $output);
                } else {
                    $output = str_replace('[[' . $match . ']]', '', $output);
                }
            } else if (Str::startsWith($match, 'assetId')) {
                $value = explode('=', $match);
                if (array_key_exists($value[1], $assets)) {
                    $output = str_replace('[[' . $match . ']]', $assets[$value[1]]->getFilename(), $output);
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
        $twig['onRender'] = [];
        $twig['onParseData'] = [];
        $twig['onInitialize'] = [];
        $twig['templateProperties'] = [];
        $twig['elements'] = [];
        $twig['width'] = $region->width;
        $twig['height'] = $region->height;
        $twig['cmsDateFormat'] = $this->config->getSetting('DATE_FORMAT');
        $twig['locale'] = Translate::GetJSLocale();

        // Output some data for each widget.
        $twig['data'] = [];

        // Max duration
        $duration = 0;
        $numItems = 0;

        // Render each widget out into the html
        foreach ($widgets as $widget) {
            $this->getLog()->debug('render: widget to process is widgetId: ' . $widget->widgetId);
            $this->getLog()->debug('render: ' . count($widgets) . ' widgets, '
                . count($moduleTemplates) . ' templates');

            // Decorate our module with the saved widget properties
            // we include the defaults.
            $module->decorateProperties($widget, true);

            // templateId or "elements"
            $templateId = $widget->getOptionValue('templateId', null);

            // Output some sample data and a data url.
            $widgetData = [
                'widgetId' => $widget->widgetId,
                'url' => '[[dataUrl=' . $widget->widgetId . ']]',
                'data' => '[[data=' . $widget->widgetId . ']]',
                'templateId' => $templateId,
                'sample' => $module->sampleData, //TODO decorate for player/preview [[sampleData=]]
                'properties' => $module->getPropertyValues(),
            ];

            // Do we have a library file with this module?
            if ($module->regionSpecific == 0) {
                $widgetData['libraryId'] = '[[libraryId=' . $widget->getPrimaryMediaId() . ']]';
            }

            // Output event functions for this widget
            if (!empty($module->onInitialize)) {
                $twig['onInitialize'][$widget->widgetId] = $module->onInitialize;
            }
            if (!empty($module->onParseData)) {
                $twig['onParseData'][$widget->widgetId] = $module->onParseData;
            }
            if (!empty($module->onRender)) {
                $twig['onRender'][$widget->widgetId] = $module->onRender;
            }
            if (!empty($module->onVisible)) {
                $twig['onVisible'][$widget->widgetId] = $module->onVisible;
            }

            // Find my template
            if ($templateId !== 'elements') {
                foreach ($moduleTemplates as $moduleTemplate) {
                    if ($moduleTemplate->templateId === $templateId) {
                        $moduleTemplate->decorateProperties($widget, true);
                        $widgetData['templateProperties'] = $moduleTemplate->getPropertyValues();

                        $this->getLog()->debug('Static template to include: ' . $moduleTemplate->templateId);
                        if ($moduleTemplate->stencil !== null) {
                            if ($moduleTemplate->stencil->twig !== null) {
                                $twig['twig'][] = $this->twig->fetchFromString(
                                    $this->decorateTranslations($moduleTemplate->stencil->twig),
                                    $moduleTemplate->getPropertyValues()
                                );
                            }
                        }
                        break;
                    }
                }
            }

            // Add to widgetData
            $twig['data'][] = $widgetData;

            // Watermark duration
            $duration = max($duration, $widget->calculatedDuration);
            // TODO: this won't always be right? can we make it right
            $numItems = max($numItems, $widgetData['properties']['numItems'] ?? 0);

            // What does our module have
            if ($module->stencil !== null) {
                // Stencils have access to any module properties
                if ($module->stencil->twig !== null) {
                    $twig['twig'][] = $this->twig->fetchFromString(
                        $this->decorateTranslations($module->stencil->twig),
                        $module->getPropertyValues()
                    );
                }
                if ($module->stencil->hbs !== null) {
                    $twig['hbs']['module'] = [
                        'content' => $this->decorateTranslations($module->stencil->hbs),
                        'width' => $module->stencil->width,
                        'height' => $module->stencil->height,
                        'gapBetweenHbs' => $module->stencil->gapBetweenHbs,
                    ];
                }
            }

            // Include elements/element groups - they will already be JSON encoded.
            $twig['elements'][] = $widget->getOptionValue('elements', null);
        }

        // Render out HBS from templates
        foreach ($moduleTemplates as $moduleTemplate) {
            // Render out any hbs
            if ($moduleTemplate->stencil !== null && $moduleTemplate->stencil->hbs !== null) {
                $twig['hbs'][$moduleTemplate->templateId] = [
                    'content' => $this->decorateTranslations($moduleTemplate->stencil->hbs),
                    'width' => $moduleTemplate->stencil->width,
                    'height' => $moduleTemplate->stencil->height,
                    'gapBetweenHbs' => $moduleTemplate->stencil->gapBetweenHbs,
                ];
            }

            if ($moduleTemplate->onTemplateRender !== null) {
                $twig['onTemplateRender'][$moduleTemplate->templateId] = $moduleTemplate->onTemplateRender;
            }
        }

        // Duration
        $twig['duration'] = $duration;
        $twig['numItems'] = $numItems;

        // We use the default get resource template.
        return $this->twig->fetch('widget-html-render.twig', $twig);
    }

    /**
     * Decorate translations in template files.
     * @param string $content
     * @return string
     */
    private function decorateTranslations(string $content): string
    {
        $matches = [];
        preg_match_all('/\|\|.*?\|\|/', $content, $matches);
        foreach ($matches[0] as $sub) {
            // Parse out the translateTag
            $translateTag = str_replace('||', '', $sub);

            // We have a valid translateTag to substitute
            $replace = __($translateTag);

            // Substitute the replacement we have found (it might be '')
            $content = str_replace($sub, $replace, $content);
        }

        return $content;
    }
}
