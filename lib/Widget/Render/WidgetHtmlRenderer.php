<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

namespace Xibo\Widget\Render;

use Carbon\Carbon;
use FilesystemIterator;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\Views\Twig;
use Xibo\Entity\Display;
use Xibo\Entity\Module;
use Xibo\Entity\ModuleTemplate;
use Xibo\Entity\Region;
use Xibo\Entity\Widget;
use Xibo\Factory\ModuleFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\LinkSigner;
use Xibo\Helper\Translate;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
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

    /** @var ModuleFactory */
    private $moduleFactory;

    /**
     * @param string $cachePath
     * @param Twig $twig
     * @param ConfigServiceInterface $config
     * @param ModuleFactory $moduleFactory
     */
    public function __construct(
        string $cachePath,
        Twig $twig,
        ConfigServiceInterface $config,
        ModuleFactory $moduleFactory
    ) {
        $this->cachePath = $cachePath;
        $this->twig = $twig;
        $this->config = $config;
        $this->moduleFactory = $moduleFactory;
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
     * @param array $additionalContexts An array of additional key/value contexts for the templates
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
        string $downloadUrl,
        array $additionalContexts = []
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
                            'downloadUrl' => $downloadUrl,
                            'calculatedDuration' => $widget->calculatedDuration,
                        ],
                        $module->getPropertyValues(),
                        $additionalContexts
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
                            'widgetId' => $widget->widgetId,
                            'calculatedDuration' => $widget->calculatedDuration,
                        ],
                        $module->getPropertyValues(),
                        $additionalContexts
                    )
                );
            }
        }

        // Render an icon.
        return $this->twig->fetch('module-icon-preview.twig', [
            'moduleName' => $module->name,
            'moduleType' => $module->type,
            'moduleIcon' => $module->icon,
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
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function renderOrCache(
        Region $region,
        array $widgets,
        array $moduleTemplates
    ): string {
        // HTML is cached per widget for regions of type zone/frame and playlist.
        // HTML is cached per region for regions of type canvas.
        $widgetModifiedDt = 0;

        if ($region->type === 'canvas') {
            foreach ($widgets as $item) {
                $widgetModifiedDt = max($widgetModifiedDt, $item->modifiedDt);
                if ($item->type === 'global') {
                    $widget = $item;
                }
            }

            // If we don't have a global widget, just grab the first one.
            $widget = $widget ?? $widgets[0];
        } else {
            $widget = $widgets[0];
            $widgetModifiedDt = $widget->modifiedDt;
        }

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

        // Changes to the Playlist should also invalidate Widget HTML caches
        try {
            $playlistModifiedDt = Carbon::createFromFormat(DateFormatHelper::getSystemFormat(), $region->getPlaylist([
                'loadPermissions' => false,
                'loadWidgets' => false,
                'loadTags' => false,
                'loadActions' => false,
            ])->modifiedDt);
        } catch (\Exception) {
            $this->getLog()->error('renderOrCache: cannot find playlist modifiedDt, using now');
            $playlistModifiedDt = Carbon::now();
        }

        // Have we changed since we last cached this widget
        $modifiedDt = max(Carbon::createFromTimestamp($widgetModifiedDt), $playlistModifiedDt);
        $cachedDt = Carbon::createFromTimestamp(file_exists($cachePath) ? filemtime($cachePath) : 0);

        $this->getLog()->debug('renderOrCache: Cache details - modifiedDt: '
            . $modifiedDt->format(DateFormatHelper::getSystemFormat())
            . ', cachedDt: ' . $cachedDt->format(DateFormatHelper::getSystemFormat())
            . ', cachePath: ' . $cachePath);

        if ($modifiedDt->greaterThan($cachedDt) || !file_get_contents($cachePath)) {
            $this->getLog()->debug('renderOrCache: We will need to regenerate');

            // Are we worried about concurrent requests here?
            // these aren't providing any data anymore, so in theory it shouldn't be possible to
            // get locked up here
            // We don't clear cached media here, as that comes along with data.
            if (file_exists($cachePath)) {
                $this->getLog()->debug('renderOrCache: Deleting cache file ' . $cachePath . ' which already existed');
                unlink($cachePath);
            }

            // Render
            $output = $this->render($widget->widgetId, $region, $widgets, $moduleTemplates);

            // Cache to the library
            file_put_contents($cachePath, $output);

            $this->getLog()->debug('renderOrCache: Generate complete');

            return $output;
        } else {
            $this->getLog()->debug('renderOrCache: Serving from cache');
            return file_get_contents($cachePath);
        }
    }

    /**
     * Decorate the HTML output for a preview
     * @param \Xibo\Entity\Region $region
     * @param string $output
     * @param callable $urlFor
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return string
     */
    public function decorateForPreview(
        Region $region,
        string $output,
        callable $urlFor,
        RequestInterface $request
    ): string {
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
            } else if (Str::startsWith($match, 'assetAlias')) {
                $value = explode('=', $match);
                $output = str_replace(
                    '[[' . $match . ']]',
                    $urlFor('module.asset.download', ['assetId' => $value[1]]) . '?preview=1&isAlias=1',
                    $output
                );
            }
        }

        // Handle CSP in preview
        $html = new \DOMDocument();
        $html->loadHTML($output, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_SCHEMA_CREATE);
        foreach ($html->getElementsByTagName('script') as $node) {
            // We add this requests cspNonce to every script tag
            if ($node instanceof \DOMElement) {
                $node->setAttribute('nonce', $request->getAttribute('cspNonce'));
            }
        }

        return $html->saveHTML();
    }

    /**
     * Decorate the HTML output for a player
     * @param \Xibo\Entity\Display $display
     * @param string $output
     * @param array $storedAs A keyed array of library media this widget has access to
     * @param bool $isSupportsDataUrl
     * @param array $data A keyed array of data this widget has access to
     * @param \Xibo\Widget\Definition\Asset[] $assets A keyed array of assets this widget has access to
     * @return string
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function decorateForPlayer(
        Display $display,
        string $output,
        array $storedAs,
        bool $isSupportsDataUrl = true,
        array $data = [],
        array $assets = []
    ): string {
        // Do we need to add a URL prefix to the requests?
        $auth = $display->isPwa()
            ? '&v=7&serverKey=' . $this->config->getSetting('serverKey') . '&hardwareKey=' . $display->license
            : null;
        $encryptionKey = $this->config->getApiKeyDetails()['encryptionKey'];

        $matches = [];
        preg_match_all('/\[\[(.*?)\]\]/', $output, $matches);
        foreach ($matches[1] as $match) {
            if ($match === 'PlayerBundle') {
                if ($display->isPwa()) {
                    $url = LinkSigner::generateSignedLink(
                        $display,
                        $encryptionKey,
                        null,
                        'P',
                        1,
                        'bundle.min.js',
                        'bundle',
                    );
                } else {
                    $url = 'bundle.min.js';
                }
                $output = str_replace(
                    '[[PlayerBundle]]',
                    $url,
                    $output,
                );
            } else if ($match === 'FontBundle') {
                if ($display->isPwa()) {
                    $url = LinkSigner::generateSignedLink(
                        $display,
                        $encryptionKey,
                        null,
                        'P',
                        1,
                        'fonts.css',
                        'fontCss',
                    );
                } else {
                    $url = 'fonts.css';
                }
                $output = str_replace(
                    '[[FontBundle]]',
                    $url,
                    $output,
                );
            } else if ($match === 'ViewPortWidth') {
                if ($display->isPwa()) {
                    $output = str_replace(
                        '[[ViewPortWidth]]',
                        explode('x', ($display->resolution ?: 'x'))[0],
                        $output,
                    );
                }
            } else if (Str::startsWith($match, 'dataUrl')) {
                $value = explode('=', $match);
                $output = str_replace(
                    '[[' . $match . ']]',
                    $isSupportsDataUrl
                        ? ($display->isPwa()
                            ? '/pwa/getData?widgetId=' . $value[1] . $auth
                            : '/' . $value[1] . '.json')
                        : 'null',
                    $output,
                );
            } else if (Str::startsWith($match, 'data=')) {
                $value = explode('=', $match);
                $output = str_replace(
                    '"[[' . $match . ']]"',
                    isset($data[$value[1]])
                        ? json_encode($data[$value[1]])
                        : 'null',
                    $output,
                );
            } else if (Str::startsWith($match, 'mediaId') || Str::startsWith($match, 'libraryId')) {
                $value = explode('=', $match);
                if (array_key_exists($value[1], $storedAs)) {
                    if ($display->isPwa()) {
                        $url = LinkSigner::generateSignedLink(
                            $display,
                            $encryptionKey,
                            null,
                            'M',
                            $value[1],
                            $storedAs[$value[1]]
                        );
                    } else {
                        $url = $storedAs[$value[1]];
                    }
                    $output = str_replace(
                        '[[' . $match . ']]',
                        $url,
                        $output,
                    );
                } else {
                    $output = str_replace(
                        '[[' . $match . ']]',
                        '',
                        $output,
                    );
                }
            } else if (Str::startsWith($match, 'assetId')) {
                $value = explode('=', $match);
                if (array_key_exists($value[1], $assets)) {
                    $asset = $assets[$value[1]];
                    if ($display->isPwa()) {
                        $url = LinkSigner::generateSignedLink(
                            $display,
                            $encryptionKey,
                            null,
                            'P',
                            $asset->id,
                            $asset->getFilename(),
                            'asset',
                        );
                    } else {
                        $url = $asset->getFilename();
                    }
                    $output = str_replace(
                        '[[' . $match . ']]',
                        $url,
                        $output,
                    );
                } else {
                    $output = str_replace(
                        '[[' . $match . ']]',
                        '',
                        $output,
                    );
                }
            } else if (Str::startsWith($match, 'assetAlias')) {
                $value = explode('=', $match);
                foreach ($assets as $asset) {
                    if ($asset->alias === $value[1]) {
                        if ($display->isPwa()) {
                            $url = LinkSigner::generateSignedLink(
                                $display,
                                $encryptionKey,
                                null,
                                'P',
                                $asset->id,
                                $asset->getFilename(),
                                'asset',
                            );
                        } else {
                            $url = $asset->getFilename();
                        }
                        $output = str_replace(
                            '[[' . $match . ']]',
                            $url,
                            $output,
                        );
                        continue 2;
                    }
                }
                $output = str_replace('[[' . $match . ']]', '', $output);
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
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    private function render(
        int $widgetId,
        Region $region,
        array $widgets,
        array $moduleTemplates
    ): string {
        // Build up some data for twig
        $twig = [];
        $twig['widgetId'] = $widgetId;
        $twig['hbs'] = [];
        $twig['twig'] = [];
        $twig['style'] = [];
        $twig['assets'] = [];
        $twig['onRender'] = [];
        $twig['onParseData'] = [];
        $twig['onDataLoad'] = [];
        $twig['onElementParseData'] = [];
        $twig['onTemplateRender'] = [];
        $twig['onTemplateVisible'] = [];
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

        // Grab any global elements in our templates
        $globalElements = [];
        foreach ($moduleTemplates as $moduleTemplate) {
            if ($moduleTemplate->type === 'element' && $moduleTemplate->dataType === 'global') {
                // Add global elements to an array of extendable elements
                $globalElements[$moduleTemplate->templateId] = $moduleTemplate;
            }
        }

        $this->getLog()->debug('render: there are ' . count($globalElements) . ' global elements');

        // Extend any elements which need to be extended.
        foreach ($moduleTemplates as $moduleTemplate) {
            if ($moduleTemplate->type === 'element'
                && !empty($moduleTemplate->extends)
                && array_key_exists($moduleTemplate->extends->template, $globalElements)
            ) {
                $extends = $globalElements[$moduleTemplate->extends->template];

                $this->getLog()->debug('render: extending template ' . $moduleTemplate->templateId);

                // Merge properties
                $moduleTemplate->properties = array_merge($extends->properties, $moduleTemplate->properties);

                // Store on the object to use when we output the stencil
                $moduleTemplate->setUnmatchedProperty('extends', $extends);
            }
        }

        // Render each widget out into the html
        foreach ($widgets as $widget) {
            $this->getLog()->debug('render: widget to process is widgetId: ' . $widget->widgetId);
            $this->getLog()->debug('render: ' . count($widgets) . ' widgets, '
                . count($moduleTemplates) . ' templates');

            // Get the module.
            $module = $this->moduleFactory->getByType($widget->type);

            // Decorate our module with the saved widget properties
            // we include the defaults.
            $module->decorateProperties($widget, true);

            // templateId or "elements"
            $templateId = $widget->getOptionValue('templateId', null);

            // Validate this modules properties.
            try {
                $module->validateProperties('status');
                $widget->isValid = 1;
            } catch (InvalidArgumentException $invalidArgumentException) {
                $widget->isValid = 0;
            }

            // Parse out some common properties.
            $moduleLanguage = null;

            foreach ($module->properties as $property) {
                if ($property->type === 'languageSelector' && !empty($property->value)) {
                    $moduleLanguage = $property->value;
                    break;
                }
            }

            // Get an array of the modules property values.
            $modulePropertyValues = $module->getPropertyValues();

            // Configure a translator for the module
            // Note: We are using the language defined against the module and not from the module template
            $translator = null;
            if ($moduleLanguage !== null) {
                $translator = Translate::getTranslationsFromLocale($moduleLanguage);
            }

            // Output some sample data and a data url.
            $widgetData = [
                'widgetId' => $widget->widgetId,
                'templateId' => $templateId,
                'sample' => $module->sampleData,
                'properties' => $modulePropertyValues,
                'isValid' => $widget->isValid === 1,
                'isRepeatData' => $widget->getOptionValue('isRepeatData', 1) === 1,
                'duration' => $widget->useDuration ? $widget->duration : $module->defaultDuration,
                'calculatedDuration' => $widget->calculatedDuration,
                'isDataExpected' => $module->isDataProviderExpected(),
            ];

            // Should we expect data?
            if ($module->isDataProviderExpected()) {
                $widgetData['url'] = '[[dataUrl=' . $widget->widgetId . ']]';
                $widgetData['data'] = '[[data=' . $widget->widgetId . ']]';
            } else {
                $widgetData['url'] = null;
                $widgetData['data'] = null;
            }

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
            if (!empty($module->onDataLoad)) {
                $twig['onDataLoad'][$widget->widgetId] = $module->onDataLoad;
            }
            if (!empty($module->onRender)) {
                $twig['onRender'][$widget->widgetId] = $module->onRender;
            }
            if (!empty($module->onVisible)) {
                $twig['onVisible'][$widget->widgetId] = $module->onVisible;
            }

            // Include any module assets.
            foreach ($module->assets as $asset) {
                if ($asset->isSendToPlayer()
                    && $asset->mimeType === 'text/css' || $asset->mimeType === 'text/javascript'
                ) {
                    $twig['assets'][] = $asset;
                }
            }

            // Find my template
            if ($templateId !== 'elements') {
                // Render out the `twig` from our specific static template
                foreach ($moduleTemplates as $moduleTemplate) {
                    if ($moduleTemplate->templateId === $templateId) {
                        $moduleTemplate->decorateProperties($widget, true);
                        $widgetData['templateProperties'] = $moduleTemplate->getPropertyValues();

                        $this->getLog()->debug('render: Static template to include: ' . $moduleTemplate->templateId);
                        if ($moduleTemplate->stencil !== null) {
                            if ($moduleTemplate->stencil->twig !== null) {
                                $twig['twig'][] = $this->twig->fetchFromString(
                                    $this->decorateTranslations($moduleTemplate->stencil->twig, $translator),
                                    $widgetData['templateProperties'],
                                );
                            }
                            if ($moduleTemplate->stencil->style !== null) {
                                $twig['style'][] = $this->twig->fetchFromString(
                                    $moduleTemplate->stencil->style,
                                    $widgetData['templateProperties'],
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
                        $this->decorateTranslations($module->stencil->twig, null),
                        array_merge($modulePropertyValues, ['settings' => $module->getSettingsForOutput()]),
                    );
                }
                if ($module->stencil->hbs !== null) {
                    $twig['hbs']['module'] = [
                        'content' => $this->decorateTranslations($module->stencil->hbs, null),
                        'width' => $module->stencil->width,
                        'height' => $module->stencil->height,
                        'gapBetweenHbs' => $module->stencil->gapBetweenHbs,
                    ];
                }
                if ($module->stencil->head !== null) {
                    $twig['head'][] = $this->twig->fetchFromString(
                        $this->decorateTranslations($module->stencil->head, null),
                        $modulePropertyValues,
                    );
                }
                if ($module->stencil->style !== null) {
                    $twig['style'][] = $this->twig->fetchFromString(
                        $module->stencil->style,
                        $modulePropertyValues,
                    );
                }
            }

            // Include elements/element groups - they will already be JSON encoded.
            $widgetElements = $widget->getOptionValue('elements', null);
            if (!empty($widgetElements)) {
                $this->getLog()->debug('render: there are elements to include');

                // Elements will be JSON
                $widgetElements = json_decode($widgetElements, true);

                // Are any of the module properties marked for sending to elements?
                $modulePropertiesToSend = [];
                if (count($widgetElements) > 0) {
                    foreach ($module->properties as $property) {
                        if ($property->sendToElements) {
                            $modulePropertiesToSend[$property->id] = $modulePropertyValues[$property->id] ?? null;
                        }
                    }
                }

                // Join together the template properties for this element, and the element properties
                foreach ($widgetElements as $widgetIndex => $widgetElement) {
                    // Assert the widgetId
                    $widgetElements[$widgetIndex]['widgetId'] = $widget->widgetId;

                    foreach (($widgetElement['elements'] ?? []) as $elementIndex => $element) {
                        $this->getLog()->debug('render: elements: processing widget index ' . $widgetIndex
                            . ', element index ' . $elementIndex . ' with id ' . $element['id']);

                        foreach ($moduleTemplates as $moduleTemplate) {
                            if ($moduleTemplate->templateId === $element['id']) {
                                $this->getLog()->debug('render: elements: found template for element '
                                    . $element['id']);

                                // Merge the properties on the element with the properties on the template.
                                $widgetElements[$widgetIndex]['elements'][$elementIndex]['properties'] =
                                    $moduleTemplate->getPropertyValues(
                                        true,
                                        $moduleTemplate->decoratePropertiesByArray(
                                            $element['properties'] ?? [],
                                            true
                                        )
                                    );

                                // Update any properties which match on the element
                                foreach ($modulePropertiesToSend as $propertyToSend => $valueToSend) {
                                    $widgetElements[$widgetIndex]['elements']
                                        [$elementIndex]['properties'][$propertyToSend] = $valueToSend;
                                }
                            }
                        }

                        // Check the element for a mediaId property and set it to
                        // [[mediaId=the_id_from_the_mediaId_property]]
                        if (!empty($element['mediaId'])) {
                            // Update the element so we output the mediaId replacement
                            $widgetElements[$widgetIndex]['elements'][$elementIndex]['properties']['mediaId']
                                = '[[mediaId=' . $element['mediaId'] . ']]';
                        }
                    }
                }

                $twig['elements'][] = json_encode($widgetElements);
            }
        }

        // Render out HBS/style from templates
        // we do not render Twig here
        foreach ($moduleTemplates as $moduleTemplate) {
            $this->getLog()->debug('render: outputting module template ' . $moduleTemplate->templateId);

            // Handle extends.
            $extension = $moduleTemplate->getUnmatchedProperty('extends');
            $isExtensionHasHead = false;
            $isExtensionHasStyle = false;

            // Render out any hbs
            if ($moduleTemplate->stencil !== null && $moduleTemplate->stencil->hbs !== null) {
                // If we have an extension then look for %parent% and insert it.
                if ($extension !== null && Str::contains('%parent%', $moduleTemplate->stencil->hbs)) {
                    $moduleTemplate->stencil->hbs = str_replace(
                        '%parent%',
                        $extension->stencil->hbs,
                        $moduleTemplate->stencil->hbs
                    );
                }

                // Output the hbs
                $twig['hbs'][$moduleTemplate->templateId] = [
                    'content' => $this->decorateTranslations($moduleTemplate->stencil->hbs, null),
                    'width' => $moduleTemplate->stencil->width,
                    'height' => $moduleTemplate->stencil->height,
                    'gapBetweenHbs' => $moduleTemplate->stencil->gapBetweenHbs,
                    'extends' => [
                        'override' => $moduleTemplate->extends?->override,
                        'with' => $moduleTemplate->extends?->with,
                        'escapeHtml' => $moduleTemplate->extends?->escapeHtml,
                    ],
                ];
            } else if ($extension !== null) {
                // Output the extension HBS instead
                $twig['hbs'][$moduleTemplate->templateId] = [
                    'content' => $this->decorateTranslations($extension->stencil->hbs, null),
                    'width' => $extension->stencil->width,
                    'height' => $extension->stencil->height,
                    'gapBetweenHbs' => $extension->stencil->gapBetweenHbs,
                    'extends' => [
                        'override' => $moduleTemplate->extends?->override,
                        'with' => $moduleTemplate->extends?->with,
                        'escapeHtml' => $moduleTemplate->extends?->escapeHtml,
                    ],
                ];

                if ($extension->stencil->head !== null) {
                    $twig['head'][] = $extension->stencil->head;
                    $isExtensionHasHead = true;
                }

                if ($extension->stencil->style !== null) {
                    $twig['style'][] = $extension->stencil->style;
                    $isExtensionHasStyle = true;
                }
            }

            // Render the module template's head, if present and not already output by the extension
            if ($moduleTemplate->stencil !== null
                && $moduleTemplate->stencil->head !== null
                && !$isExtensionHasHead
            ) {
                $twig['head'][] = $moduleTemplate->stencil->head;
            }

            // Render the module template's style, if present and not already output by the extension
            if ($moduleTemplate->stencil !== null
                && $moduleTemplate->stencil->style !== null
                && !$isExtensionHasStyle
                && $moduleTemplate->type === 'element'
            ) {
                $twig['style'][] = $moduleTemplate->stencil->style;
            }

            if ($moduleTemplate->onTemplateRender !== null) {
                $twig['onTemplateRender'][$moduleTemplate->templateId] = $moduleTemplate->onTemplateRender;
            }

            if ($moduleTemplate->onTemplateVisible !== null) {
                $twig['onTemplateVisible'][$moduleTemplate->templateId] = $moduleTemplate->onTemplateVisible;
            }

            if ($moduleTemplate->onElementParseData !== null) {
                $twig['onElementParseData'][$moduleTemplate->templateId] = $moduleTemplate->onElementParseData;
            }

            // Include any module template assets.
            foreach ($moduleTemplate->assets as $asset) {
                if ($asset->isSendToPlayer()
                    && $asset->mimeType === 'text/css' || $asset->mimeType === 'text/javascript'
                ) {
                    $twig['assets'][] = $asset;
                }
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
     * @param \GetText\Translator $translator
     * @return string
     */
    private function decorateTranslations(string $content, ?\Gettext\Translator $translator): string
    {
        $matches = [];
        preg_match_all('/\|\|.*?\|\|/', $content, $matches);
        foreach ($matches[0] as $sub) {
            // Parse out the translateTag
            $translateTag = str_replace('||', '', $sub);

            // We have a valid translateTag to substitute
            if ($translator !== null) {
                $replace = $translator->gettext($translateTag);
            } else {
                $replace = __($translateTag);
            }

            // Substitute the replacement we have found (it might be '')
            $content = str_replace($sub, $replace, $content);
        }

        return $content;
    }

    /**
     * @param \Xibo\Entity\Widget $widget
     * @return void
     */
    public function clearWidgetCache(Widget $widget)
    {
        $cachePath = $this->cachePath
            . DIRECTORY_SEPARATOR
            . $widget->widgetId
            . DIRECTORY_SEPARATOR;

        // Drop the cache
        // there is a chance this may not yet exist
        try {
            $it = new \RecursiveDirectoryIterator($cachePath, FilesystemIterator::SKIP_DOTS);
            $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
            rmdir($cachePath);
        } catch (\UnexpectedValueException $unexpectedValueException) {
            $this->logger->debug('HTML cache doesn\'t exist yet or cannot be deleted. '
                . $unexpectedValueException->getMessage());
        }
    }
}
