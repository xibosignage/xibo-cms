<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\Widget\Render;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\Views\Twig;
use Stash\Interfaces\PoolInterface;
use Twig\Extension\SandboxExtension;
use Twig\Sandbox\SecurityPolicy;
use Twig\TwigFilter;
use Xibo\Entity\Display;
use Xibo\Entity\Module;
use Xibo\Entity\ModuleTemplate;
use Xibo\Entity\Region;
use Xibo\Entity\Widget;
use Xibo\Factory\ModuleFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\StashLock;
use Xibo\Helper\StoredFileMetadata;
use Xibo\Helper\Translate;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Storage\LibraryFileJournal;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;
use Xibo\Twig\TransExtension;
use Xibo\Xmds\PlayerFileLink;

/**
 * Class responsible for rendering out a widgets HTML, caching it if necessary
 */
class WidgetHtmlRenderer
{
    /**
     * Seconds a render may hold its lock, and how long others wait for it in total.
     */
    private const LOCK_TTL = 30;
    private const LOCK_WAIT_MS = 250;
    private const LOCK_TRIES = 9;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        private readonly LibraryStorageRegistryInterface $storageRegistry,
        private readonly StorageServiceInterface $store,
        private readonly PoolInterface $pool,
        private readonly Twig $twig,
        private readonly ConfigServiceInterface $config,
        private readonly ModuleFactory $moduleFactory
    ) {
    }

    /**
     * @param  \Psr\Log\LoggerInterface $logger
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
     * @param string $previewUrl
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
        string $previewUrl,
        string $downloadUrl,
        array $additionalContexts = []
    ): string {
        if ($module->previewEnabled == 1) {
            $twigSandbox = $this->getTwigSandbox();
            $width = $params->getDouble('width', ['default' => 0]);
            $height = $params->getDouble('height', ['default' => 0]);

            if ($module->preview !== null) {
                // Parse out our preview (which is always a stencil)
                $module->decorateProperties($widget, true);
                return $twigSandbox->fetchFromString(
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
                            'previewIframeSrc' => $previewUrl,
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
     *
     * @param  ModuleTemplate[]      $moduleTemplates
     * @param  \Xibo\Entity\Widget[] $widgets
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
        if ($region->type === 'canvas') {
            foreach ($widgets as $item) {
                if ($item->type === 'global') {
                    $widget = $item;
                }
            }

            // If we don't have a global widget, just grab the first one.
            $widget = $widget ?? $widgets[0];
        } else {
            $widget = $widgets[0];
            $widgets = [$widget];
        }

        // Widgets may or may not appear in the same Region each time they are previewed due to them potentially
        // being contained in a Playlist.
        // Region width/height only changes in Draft state, so the FE is responsible for asserting the correct
        // width/height relating scaling params when the preview first loads.
        $version = self::getRenderVersion($widgets, $this->getPlaylistModifiedDt($region));
        $key = self::getRenderKey($widget->widgetId, $region->regionId, $version);

        $render = fn () => $this->render($widget->widgetId, $region, $widgets, $moduleTemplates);

        try {
            $storage = $this->storageRegistry->get(LibraryAsset::WIDGET_HTML);
            $output = $this->readRender($storage, $key);
        } catch (GeneralException $exception) {
            $this->getLog()->error('renderOrCache: cannot read ' . $key . ', rendering without storing. '
                . $exception->getMessage());
            return $render();
        }

        if ($output !== null) {
            return $output;
        }

        $lock = new StashLock($this->pool, self::getLockKey($key), $this->getLog());
        if (!$lock->acquire(self::LOCK_TTL, self::LOCK_WAIT_MS, self::LOCK_TRIES)) {
            $this->getLog()->debug('renderOrCache: ' . $key . ' is still being rendered elsewhere');
            return $this->readRenderOrNull($storage, $key) ?? $render();
        }

        try {
            // Rendered by another request while this one waited for the lock.
            $output = $this->readRenderOrNull($storage, $key);
            if ($output !== null) {
                return $output;
            }

            $output = $render();
            $this->storeRender($storage, $key, $output);
            $this->deleteRenders($widget->widgetId, $region->regionId, $version);

            return $output;
        } finally {
            $lock->release();
        }
    }

    /**
     * The version of a render: the latest change to the widgets rendered or the region's playlist.
     * @param Widget[] $widgets
     */
    public static function getRenderVersion(array $widgets, ?string $playlistModifiedDt): int
    {
        $version = 0;
        foreach ($widgets as $widget) {
            $version = max($version, (int)$widget->modifiedDt);
        }

        if (!empty($playlistModifiedDt)) {
            $version = max(
                $version,
                Carbon::createFromFormat(DateFormatHelper::getSystemFormat(), $playlistModifiedDt)->getTimestamp()
            );
        }

        return $version;
    }

    public static function getRenderKey(int $widgetId, int $regionId, int $version): string
    {
        return $widgetId . '/' . $regionId . '_' . $version . '.html';
    }

    /**
     * The parts of a getRenderKey() key, or null for any other key.
     * @return array{widgetId: int, regionId: int, version: int}|null
     */
    public static function parseRenderKey(string $key): ?array
    {
        if (!preg_match('/^(\d+)\/(\d+)_(\d+)\.html$/', $key, $matches)) {
            return null;
        }

        return ['widgetId' => (int)$matches[1], 'regionId' => (int)$matches[2], 'version' => (int)$matches[3]];
    }

    private static function getLockKey(string $renderKey): string
    {
        return 'locks/widget-render/' . str_replace('/', '_', $renderKey);
    }

    private function getPlaylistModifiedDt(Region $region): ?string
    {
        try {
            return $region->getPlaylist([
                'loadPermissions' => false,
                'loadWidgets' => false,
                'loadTags' => false,
                'loadActions' => false,
            ])->modifiedDt;
        } catch (\Exception) {
            $this->getLog()->error('renderOrCache: cannot find playlist modifiedDt for regionId '
                . $region->regionId);
            return null;
        }
    }

    /**
     * The stored render, or null if there isn't one.
     * @throws GeneralException when storage can't be read
     */
    private function readRender(LibraryStorageServiceInterface $storage, string $key): ?string
    {
        try {
            $stream = $storage->readStream($key);
        } catch (NotFoundException) {
            return null;
        }

        try {
            return $stream->getContents();
        } finally {
            $stream->close();
        }
    }

    /**
     * The stored render, or null if there isn't one or it can't be read.
     */
    private function readRenderOrNull(LibraryStorageServiceInterface $storage, string $key): ?string
    {
        try {
            return $this->readRender($storage, $key);
        } catch (GeneralException $exception) {
            $this->getLog()->error('renderOrCache: cannot read ' . $key . '. ' . $exception->getMessage());
            return null;
        }
    }

    /**
     * Stores a render, logging rather than failing the request when storage can't take it.
     */
    private function storeRender(LibraryStorageServiceInterface $storage, string $key, string $output): void
    {
        try {
            StoredFileMetadata::storeContents(
                $storage,
                $key,
                $output,
                $this->config->getSetting('LIBRARY_LOCATION') . 'temp/'
            );
        } catch (GeneralException $exception) {
            $this->getLog()->error('renderOrCache: cannot store ' . $key . '. ' . $exception->getMessage());
        }
    }

    /**
     * Deletes a widget's renders, or only those of $regionId older than $version.
     */
    private function deleteRenders(int $widgetId, ?int $regionId = null, ?int $version = null): void
    {
        try {
            $keys = $this->storageRegistry->get(LibraryAsset::WIDGET_HTML)->listKeys((string)$widgetId);
            foreach ($keys as $item) {
                $render = self::parseRenderKey($item['key']);
                if ($render === null
                    || ($regionId !== null && ($render['regionId'] !== $regionId || $render['version'] >= $version))
                ) {
                    continue;
                }

                LibraryFileJournal::deleteFileAfterCommit(
                    $this->store,
                    $this->storageRegistry,
                    LibraryAsset::WIDGET_HTML,
                    $item['key'],
                    $this->getLog()
                );
            }
        } catch (GeneralException $exception) {
            $this->getLog()->error('Cannot list the renders of widgetId ' . $widgetId . '. '
                . $exception->getMessage());
        }
    }

    /**
     * Decorate the HTML output for a preview
     *
     * @param  \Xibo\Entity\Region                      $region
     * @param  string                                   $output
     * @param  callable                                 $urlFor
     * @param  \Psr\Http\Message\ServerRequestInterface $request
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
                $url = $urlFor('library.download', $params);
                $output = str_replace(
                    '[[' . $match . ']]',
                    $url . (Str::contains($url, '?') ? '&' : '?') . 'preview=1',
                    $output
                );
            } else if (Str::startsWith($match, 'assetId')) {
                $value = explode('=', $match);
                $url = $urlFor('module.asset.download', ['assetId' => $value[1]]);
                $output = str_replace(
                    '[[' . $match . ']]',
                    $url . (Str::contains($url, '?') ? '&' : '?') . 'preview=1',
                    $output
                );
            } else if (Str::startsWith($match, 'assetAlias')) {
                $value = explode('=', $match);
                $url = $urlFor('module.asset.download', ['assetId' => $value[1]]);
                $output = str_replace(
                    '[[' . $match . ']]',
                    $url . (Str::contains($url, '?') ? '&' : '?') . 'preview=1&isAlias=1',
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
     *
     * @param  \Xibo\Xmds\PlayerFileLink       $playerFileLink
     * @param  \Xibo\Entity\Display            $display
     * @param  string                          $output
     * @param  array                           $storedAs          A keyed array of library media this widget has access to
     * @param  bool                            $isSupportsDataUrl
     * @param  array                           $data              A keyed array of data this widget has access to
     * @param  \Xibo\Widget\Definition\Asset[] $assets            A keyed array of assets this widget has access to
     * @return string
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function decorateForPlayer(
        PlayerFileLink $playerFileLink,
        Display $display,
        string $output,
        array $storedAs,
        bool $isSupportsDataUrl = true,
        array $data = [],
        array $assets = []
    ): string {
        // Do we need to add a URL prefix to the requests?
        $auth = $display->isPwa()
            ? '&v=7&serverKey=' . $this->config->getSetting('SERVER_KEY') . '&hardwareKey=' . $display->license
            : null;
        $matches = [];
        preg_match_all('/\[\[(.*?)\]\]/', $output, $matches);
        foreach ($matches[1] as $match) {
            if ($match === 'PlayerBundle') {
                if ($display->isPwa()) {
                    $url = $playerFileLink->get(
                        $display,
                        'P',
                        1,
                        'bundle.min.js',
                        'bundle',
                        true,
                    )[0];
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
                    $url = $playerFileLink->get(
                        $display,
                        'P',
                        1,
                        'fonts.css',
                        'fontCss',
                        true,
                    )[0];
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
                            : $value[1] . '.json')
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
                        $url = $playerFileLink->get(
                            $display,
                            'M',
                            $value[1],
                            $storedAs[$value[1]],
                            null,
                            true,
                        )[0];
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
                        $url = $playerFileLink->get(
                            $display,
                            'P',
                            $asset->id,
                            $asset->getFilename(),
                            'asset',
                            true,
                        )[0];
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
                            $url = $playerFileLink->get(
                                $display,
                                'P',
                                $asset->id,
                                $asset->getFilename(),
                                'asset',
                                true,
                            )[0];
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
     *
     * @param  \Xibo\Entity\Widget[] $widgets
     * @param  ModuleTemplate[]      $moduleTemplates
     * @throws \Twig\Error\SyntaxError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\LoaderError
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    protected function render(
        int $widgetId,
        Region $region,
        array $widgets,
        array $moduleTemplates
    ): string {
        // Build a Twig Sandbox
        $twigSandbox = $this->getTwigSandbox();

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
            $this->getLog()->debug(
                'render: ' . count($widgets) . ' widgets, '
                . count($moduleTemplates) . ' templates'
            );

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
                                $twig['twig'][] = $twigSandbox->fetchFromString(
                                    $this->decorateTranslations($moduleTemplate->stencil->twig, $translator),
                                    $widgetData['templateProperties'],
                                );
                            }
                            if ($moduleTemplate->stencil->style !== null) {
                                $twig['style'][] = [
                                    'content' => $twigSandbox->fetchFromString(
                                        $moduleTemplate->stencil->style,
                                        $widgetData['templateProperties'],
                                    ),
                                    'type' => $moduleTemplate->type,
                                    'dataType' => $moduleTemplate->dataType,
                                    'templateId' => $moduleTemplate->templateId,
                                ];
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
                    $twig['twig'][] = $twigSandbox->fetchFromString(
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
                    $twig['head'][] = $twigSandbox->fetchFromString(
                        $this->decorateTranslations($module->stencil->head, null),
                        $modulePropertyValues,
                    );
                }
                if ($module->stencil->style !== null) {
                    $twig['style'][] = [
                        'content' => $twigSandbox->fetchFromString(
                            $module->stencil->style,
                            $modulePropertyValues,
                        ),
                        'type' => $module->type,
                        'dataType' => $module->dataType,
                    ];
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
                        $this->getLog()->debug(
                            'render: elements: processing widget index ' . $widgetIndex
                            . ', element index ' . $elementIndex . ' with id ' . $element['id']
                        );

                        foreach ($moduleTemplates as $moduleTemplate) {
                            if ($moduleTemplate->templateId === $element['id']) {
                                $this->getLog()->debug(
                                    'render: elements: found template for element '
                                    . $element['id']
                                );

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
                        'escapeHtml' => $moduleTemplate->extends?->escapeHtml ?? true,
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
                        'escapeHtml' => $moduleTemplate->extends?->escapeHtml ?? true,
                    ],
                ];

                if ($extension->stencil->head !== null) {
                    $twig['head'][] = $extension->stencil->head;
                    $isExtensionHasHead = true;
                }

                if ($extension->stencil->style !== null) {
                    $twig['style'][] = [
                        'content' => $extension->stencil->style,
                        'type' => $moduleTemplate->type,
                        'dataType' => $moduleTemplate->dataType,
                        'templateId' => $moduleTemplate->templateId,
                    ];
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
                // Add more info to the element style
                // so we can use it to create CSS scope
                $twig['style'][] = [
                    'content' => $moduleTemplate->stencil->style,
                    'type' => $moduleTemplate->type,
                    'dataType' => $moduleTemplate->dataType,
                    'templateId' => $moduleTemplate->templateId,
                ];
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
     *
     * @param  string              $content
     * @param  ?\GetText\Translator $translator
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
     * Deletes every render of a widget, once the caller's transaction commits.
     */
    public function clearWidgetCache(Widget $widget): void
    {
        $this->deleteRenders((int)$widget->widgetId);
    }

    /**
     * Get a Twig Sandbox
     *
     * @return \Slim\Views\Twig
     * @throws \Twig\Error\LoaderError
     */
    private function getTwigSandbox(): Twig
    {
        // Create a Twig Environment with a Sandbox
        $sandbox = Twig::create([
            PROJECT_ROOT . '/modules',
        ], [
            'cache' => false,
        ]);

        // Add missing filter
        $sandbox->getEnvironment()->addFilter(new TwigFilter('url_decode', 'urldecode'));

        // Add the trans extention (some previews have translations)
        $sandbox->addExtension(new TransExtension());

        // Configure a security policy
        // Create a new security policy
        $policy = new SecurityPolicy();

        // Allowed tags
        // import is allowed for weather static templates which import a macro
        $policy->setAllowedTags(['if', 'for', 'set', 'macro', 'import', 'trans']);

        // Allowed filters
        $policy->setAllowedFilters(['escape', 'raw', 'url_decode']);

        // Create a Sandbox
        $sandbox->addExtension(new SandboxExtension($policy, true));

        return $sandbox;
    }
}
