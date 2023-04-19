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

use Illuminate\Support\Str;
use Slim\Views\Twig;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Module;
use Xibo\Entity\Widget;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Widget\DataType\DataTypeInterface;
use Xibo\Widget\Definition\Asset;
use Xibo\Widget\Definition\DataType;
use Xibo\Widget\Provider\DataProvider;
use Xibo\Widget\Provider\DataProviderInterface;
use Xibo\Widget\Provider\DurationProvider;
use Xibo\Widget\Provider\DurationProviderInterface;
use Xibo\Widget\Provider\WidgetProviderInterface;
use Xibo\Widget\Render\WidgetDataProviderCache;
use Xibo\Widget\Render\WidgetHtmlRenderer;

/**
 * Class ModuleFactory
 * @package Xibo\Factory
 */
class ModuleFactory extends BaseFactory
{
    use ModuleXmlTrait;

    /** @var Module[] all modules */
    private $modules = null;

    /** @var \Xibo\Widget\Definition\DataType[] */
    private $dataTypes = null;

    /** @var \Stash\Interfaces\PoolInterface */
    private $pool;

    /** @var string */
    private $cachePath;

    /** @var \Slim\Views\Twig */
    private $twig;

    /** @var \Xibo\Service\ConfigServiceInterface */
    private $config;

    /**
     * Construct a factory
     * @param string $cachePath
     * @param PoolInterface $pool
     * @param \Slim\Views\Twig $twig
     * @param \Xibo\Service\ConfigServiceInterface $config
     */
    public function __construct(string $cachePath, PoolInterface $pool, Twig $twig, ConfigServiceInterface $config)
    {
        $this->cachePath = $cachePath;
        $this->pool = $pool;
        $this->twig = $twig;
        $this->config = $config;
    }

    /**
     * @param \Xibo\Entity\Module $module
     * @param \Xibo\Entity\Widget $widget
     * @return \Xibo\Widget\Provider\DataProviderInterface
     */
    public function createDataProvider(Module $module, Widget $widget): DataProviderInterface
    {
        return new DataProvider($module, $widget, $this->config->getGuzzleProxy());
    }

    /**
     * @param string $path
     * @param int|null $duration
     * @return DurationProviderInterface
     */
    public function createDurationProvider(string $path, ?int $duration): DurationProviderInterface
    {
        return new DurationProvider($path, $duration);
    }

    /**
     * Create a widget renderer
     * @return \Xibo\Widget\Render\WidgetHtmlRenderer
     */
    public function createWidgetHtmlRenderer(): WidgetHtmlRenderer
    {
        return (new WidgetHtmlRenderer($this->cachePath, $this->twig, $this->config))
            ->useLogger($this->getLog()->getLoggerInterface());
    }

    /**
     * Create a widget data provider cache
     */
    public function createWidgetDataProviderCache(): WidgetDataProviderCache
    {
        return (new WidgetDataProviderCache($this->pool))
            ->useLogger($this->getLog()->getLoggerInterface());
    }

    /**
     * Determine the cache key
     * @param \Xibo\Entity\Module $module
     * @param \Xibo\Entity\Widget $widget
     * @param int $displayId the displayId (0 for preview)
     * @param \Xibo\Widget\Provider\DataProviderInterface $dataProvider
     * @param \Xibo\Widget\Provider\WidgetProviderInterface|null $widgetInterface
     * @return string
     */
    public function determineCacheKey(
        Module $module,
        Widget $widget,
        int $displayId,
        DataProviderInterface $dataProvider,
        ?WidgetProviderInterface $widgetInterface
    ): string {
        // Determine the cache key
        $cacheKey = null;
        if ($widgetInterface !== null) {
            $cacheKey = $widgetInterface->getDataCacheKey($dataProvider);
        }

        if ($cacheKey === null) {
            // Determinthe cache key from the setting in XML.
            if (empty($module->dataCacheKey)) {
                // Best we can do here is a cache per widget, but we should log this as an error.
                $this->getLog()->debug('getData: module without dataCacheKey: ' . $module->moduleId);
                $cacheKey = $widget->widgetId;
            } else {
                // Start with the one provided
                $cacheKey = $module->dataCacheKey;

                // Properties
                $module->decorateProperties($widget, true);
                $properties = $module->getPropertyValues(false);

                // Parse the cache key for variables.
                $matches = [];
                preg_match_all('/%(.*?)%/', $cacheKey, $matches);
                foreach ($matches[1] as $match) {
                    if ($match === 'displayId') {
                        $cacheKey = str_replace('%displayId%', $displayId, $cacheKey);
                    } else if ($match === 'widgetId') {
                        $cacheKey = str_replace('%widgetId%', $widget->widgetId, $cacheKey);
                    } else {
                        $this->getLog()->debug($match);
                        $cacheKey = str_replace(
                            '%' . $match . '%',
                            $properties[$match] ?? '',
                            $cacheKey
                        );
                    }
                }
            }
        }

        return $cacheKey;
    }

    /**
     * @param string $dataType
     * @return void
     */
    public function clearCacheForDataType(string $dataType): void
    {
        $this->getLog()->debug('clearCacheForDataType: /widget/' . $dataType);

        $this->pool->deleteItem('/widget/' . $dataType);
    }

    /**
     * @return \Xibo\Entity\Module[]
     */
    public function getKeyedArrayOfModules(): array
    {
        $modules = [];
        foreach ($this->load() as $module) {
            $modules[$module->type] = $module;
        }
        return $modules;
    }

    /**
     * @return Module[]
     */
    public function getAssignableModules(): array
    {
        $modules = [];
        foreach ($this->load() as $module) {
            if ($module->enabled === 1 && $module->assignable === 1) {
                $modules[] = $module;
            }
        }
        return $modules;
    }

    /**
     * @return Module[]
     */
    public function getLibraryModules(): array
    {
        $modules = [];
        foreach ($this->load() as $module) {
            if ($module->enabled == 1 && $module->regionSpecific === 0) {
                $modules[] = $module;
            }
        }
        return $modules;
    }

    /**
     * Get module by Id
     * @param string $moduleId
     * @return Module
     * @throws NotFoundException
     */
    public function getById($moduleId): Module
    {
        foreach ($this->load() as $module) {
            if ($module->moduleId === $moduleId) {
                return $module;
            }
        }

        throw new NotFoundException();
    }

    /**
     * Get an array of all modules
     * @return Module[]
     */
    public function getAll(): array
    {
        return $this->load();
    }

    /**
     * Get an array of all enabled modules
     * @return Module[]
     */
    public function getEnabled(): array
    {
        $modules = [];
        foreach ($this->load() as $module) {
            if ($module->enabled == 1) {
                $modules[] = $module;
            }
        }
        return $modules;
    }

    /**
     * Get module by Type
     * this should return the first module enabled by the type specified.
     * @param string $type
     * @return Module
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getByType(string $type): Module
    {
        $modules = $this->load();
        usort($modules, function ($a, $b) {
            /** @var Module $a */
            /** @var Module $b */
            return $a->enabled - $b->enabled;
        });

        foreach ($modules as $module) {
            if ($module->type === $type) {
                return $module;
            }
        }

        // Match on legacy type
        foreach ($modules as $module) {
            // get the name of the legacytypes
            $legacyTypes = [];
            if (count($module->legacyTypes) > 0) {
                $legacyTypes = array_column($module->legacyTypes, 'name');
            }
            if (in_array($type, $legacyTypes)) {
                return $module;
            }
        }

        throw new NotFoundException();
    }

    /**
     * Get module by extension
     * @param string $extension
     * @return Module
     * @throws NotFoundException
     */
    public function getByExtension(string $extension): Module
    {
        foreach ($this->load() as $module) {
            $validExtensions = $module->getSetting('validExtensions');
            if (!empty($validExtensions) && Str::contains($validExtensions, $extension)) {
                return $module;
            }
        }

        throw new NotFoundException(sprintf(__('Extension %s does not match any enabled Module'), $extension));
    }

    /**
     * Get Valid Extensions
     * @param array $filterBy
     * @return string[]
     */
    public function getValidExtensions($filterBy = []): array
    {
        $filterBy = $this->getSanitizer($filterBy);
        $typeFilter = $filterBy->getString('type');
        $extensions = [];
        foreach ($this->load() as $module) {
            if ($typeFilter !== null && $module->type !== $typeFilter) {
                continue;
            }

            if (!empty($module->getSetting('validExtensions'))) {
                foreach (explode(',', $module->getSetting('validExtensions')) as $extension) {
                    $extensions[] = $extension;
                }
            }
        }

        return $extensions;
    }

    /**
     * @param string $dataTypeId
     * @return \Xibo\Widget\Definition\DataType
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getDataTypeById(string $dataTypeId): DataType
    {
        // Rely on a class if we have one.
        $className = '\\Xibo\\Widget\\DataType\\' . ucfirst($dataTypeId);
        if (class_exists($className)) {
            $class = new $className();
            if ($class instanceof DataTypeInterface) {
                return ($class->getDefinition());
            }
        }

        // Otherwise look in our XML definitions
        foreach ($this->loadDataTypes() as $dataType) {
            if ($dataType->id === $dataTypeId) {
                return $dataType;
            }
        }

        throw new NotFoundException(__('DataType not found'));
    }

    /**
     * @param string $assetId
     * @return \Xibo\Widget\Definition\Asset
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getAssetById(string $assetId): Asset
    {
        foreach ($this->getEnabled() as $module) {
            foreach ($module->getAssets() as $asset) {
                if ($asset->id === $assetId) {
                    return $asset;
                }
            }
        }

        throw new NotFoundException(__('Asset not found'));
    }

    /**
     * @param \Xibo\Entity\ModuleTemplate[] $templates
     * @return void
     */
    public function getAssetsFromTemplates(array $templates): array
    {
        $assets = [];
        foreach ($this->getEnabled() as $module) {
            foreach ($module->getAssets() as $asset) {
                $assets[$asset->id] = $asset;
            }

            foreach ($templates as $template) {
                foreach ($template->getAssets() as $asset) {
                    $assets[$asset->id] = $asset;
                }
            }
        }

        return $assets;
    }

    /**
     * Get an asset from anywhere by its ID
     * @param string $assetId
     * @param \Xibo\Factory\ModuleTemplateFactory $moduleTemplateFactory
     * @return \Xibo\Widget\Definition\Asset
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getAssetsFromAnywhereById(string $assetId, ModuleTemplateFactory $moduleTemplateFactory): Asset
    {
        $asset = null;
        try {
            $asset = $this->getAssetById($assetId);
        } catch (NotFoundException $notFoundException) {
            // Not a module asset.
        }

        // Try a template instead
        try {
            $asset = $moduleTemplateFactory->getAssetById($assetId);
        } catch (NotFoundException $notFoundException) {
            // Not a module template asset.
        }

        if ($asset !== null) {
            return $asset;
        } else {
            throw new NotFoundException(__('Asset not found'));
        }
    }

    /**
     * Load all modules into an array for use throughout this request
     * @return \Xibo\Entity\Module[]
     */
    private function load(): array
    {
        if ($this->modules === null) {
            // TODO: these are the only fields we require in the settings table
            $sql = '
                SELECT `moduleId`, `enabled`, `previewEnabled`, `defaultDuration`, `settings`
                 FROM `module`
            ';

            $modulesWithSettings = [];
            foreach ($this->getStore()->select($sql, []) as $row) {
                // Make a keyed array of these settings
                $modulesWithSettings[$row['moduleId']] = $this->getSanitizer($row);
            }

            // Load in our file system modules.
            // we consider modules in the module folder, and also custom modules
            $files = array_merge(
                glob(PROJECT_ROOT . '/modules/*.xml'),
                glob(PROJECT_ROOT . '/custom/modules/*.xml')
            );

            foreach ($files as $file) {
                // Create our module entity from this file
                try {
                    $module = $this->createFromXml($file, $modulesWithSettings);

                    // Create a widget provider if necessary
                    // Take our module and see if it has a class associated with it
                    if (!empty($module->class)) {
                        // We create a module specific provider
                        if (!class_exists($module->class)) {
                            $module->errors[] = 'Module class not found: ' . $module->class;
                        }
                        $class = $module->class;
                        $module->setWidgetProvider(new $class());
                    }

                    // Set error state
                    $module->isError = $module->errors !== null && count($module->errors) > 0;

                    // Register
                    $this->modules[] = $module;
                } catch (\Exception $exception) {
                    $this->getLog()->error('Unable to create module from '
                        . basename($file) . ', skipping. e = ' . $exception->getMessage());
                }
            }
        }

        return $this->modules;
    }

    /**
     * Load all data types into an array for use throughout this request
     * @return \Xibo\Widget\Definition\DataType[]
     */
    private function loadDataTypes(): array
    {
        if ($this->dataTypes === null) {
            $files = array_merge(
                glob(PROJECT_ROOT . '/modules/datatypes/*.xml'),
                glob(PROJECT_ROOT . '/custom/modules/datatypes/*.xml')
            );

            foreach ($files as $file) {
                $this->dataTypes[] = $this->createDataTypeFromXml($file);
            }
        }

        return $this->dataTypes ?? [];
    }

    /**
     * Create a module from its XML definition
     * @param string $file the path to the module definition
     * @param array $modulesWithSettings
     * @return \Xibo\Entity\Module
     */
    private function createFromXml(string $file, array $modulesWithSettings): Module
    {
        // TODO: cache this into Stash
        $xml = new \DOMDocument();
        $xml->load($file);

        $module = new Module($this->getStore(), $this->getLog(), $this->getDispatcher(), $this);
        $module->moduleId = $this->getFirstValueOrDefaultFromXmlNode($xml, 'id');
        $module->name = $this->getFirstValueOrDefaultFromXmlNode($xml, 'name');
        $module->author = $this->getFirstValueOrDefaultFromXmlNode($xml, 'author');
        $module->description = $this->getFirstValueOrDefaultFromXmlNode($xml, 'description');
        $module->class = $this->getFirstValueOrDefaultFromXmlNode($xml, 'class');
        $module->type = $this->getFirstValueOrDefaultFromXmlNode($xml, 'type');
        $module->thumbnail = $this->getFirstValueOrDefaultFromXmlNode($xml, 'thumbnail');
        $module->startWidth = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'startWidth'));
        $module->startHeight = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'startHeight'));
        $module->dataType = $this->getFirstValueOrDefaultFromXmlNode($xml, 'dataType');
        $module->dataCacheKey = $this->getFirstValueOrDefaultFromXmlNode($xml, 'dataCacheKey');
        $module->schemaVersion = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'schemaVersion'));
        $module->compatibilityClass = $this->getFirstValueOrDefaultFromXmlNode($xml, 'compatibilityClass');
        $module->showIn = $this->getFirstValueOrDefaultFromXmlNode($xml, 'showIn') ?? 'both';
        $module->assignable = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'assignable'));
        $module->regionSpecific = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'regionSpecific'));
        $module->renderAs = $this->getFirstValueOrDefaultFromXmlNode($xml, 'renderAs');
        $module->defaultDuration = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'defaultDuration'));
        $module->hasThumbnail = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'hasThumbnail', 0));

        // Event listeners
        $module->onInitialize = $this->getFirstValueOrDefaultFromXmlNode($xml, 'onInitialize');
        if (!empty($module->onInitialize)) {
            $module->onInitialize = trim($module->onInitialize);
        }

        $module->onParseData = $this->getFirstValueOrDefaultFromXmlNode($xml, 'onParseData');
        if (!empty($module->onParseData)) {
            $module->onParseData = trim($module->onParseData);
        }

        $module->onRender = $this->getFirstValueOrDefaultFromXmlNode($xml, 'onRender');
        if (!empty($module->onRender)) {
            $module->onRender = trim($module->onRender);
        }

        $module->onVisible = $this->getFirstValueOrDefaultFromXmlNode($xml, 'onVisible');
        if (!empty($module->onVisible)) {
            $module->onVisible = trim($module->onVisible);
        }

        // We might have sample data (usually only if there is a dataType)
        $sampleData = $this->getFirstValueOrDefaultFromXmlNode($xml, 'sampleData');

        if (!empty($sampleData)) {
            $module->sampleData = json_decode(trim($sampleData), true);
        }

        // Legacy types.
        try {
            $module->legacyTypes = $this->parseLegacyTypes($xml->getElementsByTagName('legacyType'));
        } catch (\Exception $e) {
            $module->errors[] = __('Invalid legacyType');
            $this->getLog()->error('Module ' . $module->moduleId . ' has invalid legacyType. e: ' .  $e->getMessage());
        }

        // Group for non datatype modules
        $module->group = [];
        $groupNodes = $xml->getElementsByTagName('group');
        foreach ($groupNodes as $groupNode) {
            if ($groupNode instanceof \DOMElement) {
                $module->group['id'] = $groupNode->getAttribute('id');
                $module->group['icon'] = $groupNode->getAttribute('icon');
                $module->group['name'] = $groupNode->textContent;
            }
        }

        // Parse assets
        try {
            $module->assets = $this->parseAssets($xml->getElementsByTagName('assets'));
        } catch (\Exception $e) {
            $module->errors[] = __('Invalid assets');
            $this->getLog()->error('Module ' . $module->moduleId
                . ' has invalid assets. e: ' .  $e->getMessage());
        }

        // Default values for remaining expected properties
        $module->isInstalled = false;
        $module->isError = false;
        $module->errors = [];
        $module->enabled = 0;
        $module->previewEnabled = 0;

        // Parse settings/property definitions.
        try {
            $module->settings = $this->parseProperties($xml->getElementsByTagName('settings'));
        } catch (\Exception $e) {
            $module->errors[] = __('Invalid settings');
            $this->getLog()->error('Module ' . $module->moduleId . ' has invalid settings. e: ' .  $e->getMessage());
        }

        // Add in any settings we already have
        if (array_key_exists($module->moduleId, $modulesWithSettings)) {
            $moduleSettings = $modulesWithSettings[$module->moduleId];
            $module->isInstalled = true;
            $module->enabled = $moduleSettings->getInt('enabled', ['default' => 0]);
            $module->previewEnabled = $moduleSettings->getInt('previewEnabled', ['default' => 0]);
            $module->defaultDuration = $moduleSettings->getInt('defaultDuration', ['default' => 10]);

            $settings = $moduleSettings->getString('settings');
            if ($settings !== null) {
                $settings = json_decode($settings, true);

                foreach ($module->settings as $property) {
                    foreach ($settings as $settingId => $setting) {
                        if ($settingId === $property->id) {
                            $property->value = $setting;
                            break;
                        }
                    }
                }
            }
        }

        try {
            $module->properties = $this->parseProperties($xml->getElementsByTagName('properties'), $module);
        } catch (\Exception $e) {
            $module->errors[] = __('Invalid properties');
            $this->getLog()->error('Module ' . $module->moduleId . ' has invalid properties. e: ' .  $e->getMessage());
        }

        // Parse group property definitions.
        try {
            $module->propertyGroups = $this->parsePropertyGroups($xml->getElementsByTagName('propertyGroups'));
        } catch (\Exception $e) {
            $module->errors[] = __('Invalid property groups');
            $this->getLog()->error('Module ' . $module->moduleId . ' has invalid property groups. e: ' .  $e->getMessage());
        }

        // Parse stencils
        try {
            $module->preview = $this->getStencils($xml->getElementsByTagName('preview'))[0] ?? null;
            $module->stencil = $this->getStencils($xml->getElementsByTagName('stencil'))[0] ?? null;
        } catch (\Exception $e) {
            $module->errors[] = __('Invalid stencils');
            $this->getLog()->error('Module ' . $module->moduleId . ' has invalid stencils. e: ' .  $e->getMessage());
        }

        return $module;
    }

    /**
     * Create DataType from XML
     * @param string $file
     * @return \Xibo\Widget\Definition\DataType
     */
    private function createDataTypeFromXml(string $file): DataType
    {
        $xml = new \DOMDocument();
        $xml->load($file);

        $dataType = new DataType();
        $dataType->id = $this->getFirstValueOrDefaultFromXmlNode($xml, 'id');
        $dataType->name = $this->getFirstValueOrDefaultFromXmlNode($xml, 'name');

        // Fields.
        foreach ($xml->getElementsByTagName('field') as $field) {
            if ($field instanceof \DOMElement) {
                $dataType->addField(
                    $field->getAttribute('id'),
                    trim($field->textContent),
                    $field->getAttribute('type')
                );
            }
        }

        return $dataType;
    }
}
