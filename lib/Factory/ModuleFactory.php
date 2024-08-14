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

namespace Xibo\Factory;

use Illuminate\Support\Str;
use Slim\Views\Twig;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Module;
use Xibo\Entity\ModuleTemplate;
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

    public static $systemDataTypes = [
        'Article',
        'Event',
        'Forecast',
        'Product',
        'ProductCategory',
        'SocialMedia',
        'dataset'
    ];

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
        return new DataProvider(
            $module,
            $widget,
            $this->config->getGuzzleProxy(),
            $this->getSanitizerService(),
            $this->pool,
        );
    }

    /**
     * @param Module $module
     * @param Widget $widget
     * @return DurationProviderInterface
     */
    public function createDurationProvider(Module $module, Widget $widget): DurationProviderInterface
    {
        return new DurationProvider($module, $widget);
    }

    /**
     * Create a widget renderer
     * @return \Xibo\Widget\Render\WidgetHtmlRenderer
     */
    public function createWidgetHtmlRenderer(): WidgetHtmlRenderer
    {
        return (new WidgetHtmlRenderer($this->cachePath, $this->twig, $this->config, $this))
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
        $cacheKey = $widgetInterface?->getDataCacheKey($dataProvider);

        if ($cacheKey === null) {
            // Determinthe cache key from the setting in XML.
            if (empty($module->dataCacheKey)) {
                // Best we can do here is a cache per widget, but we should log this as an error.
                $this->getLog()->debug('determineCacheKey: module without dataCacheKey: ' . $module->moduleId);
                $cacheKey = $widget->widgetId;
            } else {
                // Start with the one provided
                $this->getLog()->debug('determineCacheKey: module dataCacheKey: ' . $module->dataCacheKey);

                $cacheKey = $module->dataCacheKey;

                // Properties
                $module->decorateProperties($widget, true);
                $properties = $module->getPropertyValues(false);

                // Is display location in use?
                // We should see if the display location property is set (this is a special property), and if it is
                // update the lat/lng with the details stored on the display
                $latitude = $properties['latitude'] ?? '';
                $longitude = $properties['longitude'] ?? '';
                if ($dataProvider->getProperty('useDisplayLocation') == 1) {
                    $latitude = $dataProvider->getDisplayLatitude() ?: $latitude;
                    $longitude = $dataProvider->getDisplayLongitude() ?: $longitude;
                }

                // Parse the cache key for variables.
                $matches = [];
                preg_match_all('/%(.*?)%/', $cacheKey, $matches);
                foreach ($matches[1] as $match) {
                    if ($match === 'displayId') {
                        $cacheKey = str_replace('%displayId%', $displayId, $cacheKey);
                    } else if ($match === 'widgetId') {
                        $cacheKey = str_replace('%widgetId%', $widget->widgetId, $cacheKey);
                    } else if ($match === 'latitude') {
                        $cacheKey = str_replace('%latitude%', $latitude, $cacheKey);
                    } else if ($match === 'longitude') {
                        $cacheKey = str_replace('%longitude%', $longitude, $cacheKey);
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

            // Include a separate cache per fallback data?
            if ($module->fallbackData == 1) {
                $cacheKey .= '_fb ' . $widget->getOptionValue('showFallback', 'never');
            }
        }

        $this->getLog()->debug('determineCacheKey: cache key is : ' . $cacheKey);

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
        $this->getLog()->debug('ModuleFactory: getKeyedArrayOfModules');
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
        $this->getLog()->debug('ModuleFactory: getAssignableModules');
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
        $this->getLog()->debug('ModuleFactory: getLibraryModules');
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
        $this->getLog()->debug('ModuleFactory: getById');
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
        $this->getLog()->debug('ModuleFactory: getAll');
        return $this->load();
    }

    /**
     * Get an array of all modules except canvas
     * @param array $filter
     * @return Module[]
     */
    public function getAllExceptCanvas(array $filter = []): array
    {
        $sanitizedFilter = $this->getSanitizer($filter);
        $this->getLog()->debug('ModuleFactory: getAllButCanvas');
        $modules = [];
        foreach ($this->load() as $module) {
            // Hide the canvas module from the module list
            if ($module->moduleId != 'core-canvas') {
                // do we have a name filter?
                if (!empty($sanitizedFilter->getString('name'))) {
                    if (str_contains(strtolower($module->name), strtolower($sanitizedFilter->getString('name')))) {
                        $modules[] = $module;
                    }
                } else {
                    $modules[] = $module;
                }
            }
        }
        return $modules;
    }

    /**
     * Get an array of all enabled modules
     * @return Module[]
     */
    public function getEnabled(): array
    {
        $this->getLog()->debug('ModuleFactory: getEnabled');
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
     * @param array $conditions Conditions that are created based on the widget's option and value, e.g, templateId==worldclock1
     * @return Module
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getByType(string $type, array $conditions = []): Module
    {
        $this->getLog()->debug('ModuleFactory: getByType ' . $type);
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
            $legacyConditions = [];
            if (count($module->legacyTypes) > 0) {
                $legacyTypes = array_column($module->legacyTypes, 'name');
                $legacyConditions = array_column($module->legacyTypes, 'condition');
            }

            if (in_array($type, $legacyTypes)) {
                foreach ($conditions as $value) {
                    if (in_array($value, $legacyConditions)) {
                        return $module;
                    }
                }

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
        $this->getLog()->debug('ModuleFactory: getByExtension');
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
        $this->getLog()->debug('ModuleFactory: getValidExtensions');
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
        $className = ucfirst(str_replace('-', '', ucwords($dataTypeId, '-')));
        $className = '\\Xibo\\Widget\\DataType\\' . $className;
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
     * @return DataType[]
     */
    public function getAllDataTypes()
    {
        $dataTypes = [];

        // get system data types
        foreach (self::$systemDataTypes as $dataTypeId) {
            $className = '\\Xibo\\Widget\\DataType\\' . ucfirst($dataTypeId);
            if (class_exists($className)) {
                $class = new $className();
                if ($class instanceof DataTypeInterface) {
                    $dataTypes[] = $class->getDefinition();
                }
            }

            // special handling for dataset
            if ($dataTypeId === 'dataset') {
                $dataType = new DataType();
                $dataType->id  = $dataTypeId;
                $dataType->name = 'DataSet';
                $dataTypes[] = $dataType;
            }
        }

        // get data types from xml
        $files = array_merge(
            glob(PROJECT_ROOT . '/modules/datatypes/*.xml'),
            glob(PROJECT_ROOT . '/custom/modules/datatypes/*.xml')
        );

        foreach ($files as $file) {
            $xml = new \DOMDocument();
            $xml->load($file);
            $dataType = new DataType();
            $dataType->id = $this->getFirstValueOrDefaultFromXmlNode($xml, 'id');
            $dataType->name = $this->getFirstValueOrDefaultFromXmlNode($xml, 'name');
            $dataTypes[] = $dataType;
        }

        sort($dataTypes);
        return $dataTypes;
    }

    /**
     * @param string $assetId
     * @return \Xibo\Widget\Definition\Asset
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getAssetById(string $assetId): Asset
    {
        $this->getLog()->debug('getAssetById: ' . $assetId);
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
     * @param string $alias
     * @return \Xibo\Widget\Definition\Asset
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getAssetByAlias(string $alias): Asset
    {
        $this->getLog()->debug('getAssetByAlias: ' . $alias);
        foreach ($this->getEnabled() as $module) {
            foreach ($module->getAssets() as $asset) {
                if ($asset->alias === $alias) {
                    return $asset;
                }
            }
        }

        throw new NotFoundException(__('Asset not found'));
    }

    /**
     * @param ModuleTemplate[] $templates
     * @return Asset[]
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
     * Get all assets
     * @return Asset[]
     */
    public function getAllAssets(): array
    {
        $assets = [];
        foreach ($this->getEnabled() as $module) {
            foreach ($module->getAssets() as $asset) {
                $assets[$asset->id] = $asset;
            }
        }
        return $assets;
    }

    /**
     * Get an asset from anywhere by its ID
     * @param string $assetId
     * @param ModuleTemplateFactory $moduleTemplateFactory
     * @param bool $isAlias
     * @return Asset
     * @throws NotFoundException
     */
    public function getAssetsFromAnywhereById(
        string $assetId,
        ModuleTemplateFactory $moduleTemplateFactory,
        bool $isAlias = false,
    ): Asset {
        $asset = null;
        try {
            $asset = $isAlias
                ? $this->getAssetByAlias($assetId)
                : $this->getAssetById($assetId);
        } catch (NotFoundException) {
            // Not a module asset.
        }

        // Try a template instead
        try {
            $asset = $isAlias
                ? $moduleTemplateFactory->getAssetByAlias($assetId)
                : $moduleTemplateFactory->getAssetById($assetId);
        } catch (NotFoundException) {
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
                        } else {
                            $class = $module->class;
                            $module->setWidgetProvider(new $class());
                        }
                    }

                    // Create a widget compatibility if necessary
                    if (!empty($module->compatibilityClass)) {
                        // We create a module specific provider
                        if (!class_exists($module->compatibilityClass)) {
                            $module->errors[] = 'Module compatibilityClass not found: ' . $module->compatibilityClass;
                        } else {
                            $compatibilityClass = $module->compatibilityClass;
                            $module->setWidgetCompatibility(new $compatibilityClass());
                        }
                    }

                    // Create a widget validator if necessary
                    foreach ($module->validatorClass as $validatorClass) {
                        // We create a module specific provider
                        if (!class_exists($validatorClass)) {
                            $module->errors[] = 'Module validatorClass not found: ' . $validatorClass;
                        } else {
                            $module->addWidgetValidator(
                                (new $validatorClass())
                                    ->setLog($this->getLog()->getLoggerInterface())
                            );
                        }
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
        $module->name = __($this->getFirstValueOrDefaultFromXmlNode($xml, 'name'));
        $module->author = $this->getFirstValueOrDefaultFromXmlNode($xml, 'author');
        $module->description = __($this->getFirstValueOrDefaultFromXmlNode($xml, 'description'));
        $module->icon = $this->getFirstValueOrDefaultFromXmlNode($xml, 'icon');
        $module->class = $this->getFirstValueOrDefaultFromXmlNode($xml, 'class');
        $module->type = $this->getFirstValueOrDefaultFromXmlNode($xml, 'type');
        $module->thumbnail = $this->getFirstValueOrDefaultFromXmlNode($xml, 'thumbnail');
        $module->startWidth = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'startWidth'));
        $module->startHeight = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'startHeight'));
        $module->dataType = $this->getFirstValueOrDefaultFromXmlNode($xml, 'dataType');
        $module->dataCacheKey = $this->getFirstValueOrDefaultFromXmlNode($xml, 'dataCacheKey');
        $module->fallbackData = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'fallbackData', 0));
        $module->schemaVersion = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'schemaVersion'));
        $module->compatibilityClass = $this->getFirstValueOrDefaultFromXmlNode($xml, 'compatibilityClass');
        $module->showIn = $this->getFirstValueOrDefaultFromXmlNode($xml, 'showIn') ?? 'both';
        $module->assignable = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'assignable'));
        $module->regionSpecific = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'regionSpecific'));
        $module->renderAs = $this->getFirstValueOrDefaultFromXmlNode($xml, 'renderAs');
        $module->defaultDuration = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'defaultDuration'));
        $module->hasThumbnail = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'hasThumbnail', 0));
        $module->allowPreview = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'allowPreview', 1));

        // Validator classes
        foreach ($xml->getElementsByTagName('validatorClass') as $node) {
            /** @var \DOMNode $node */
            if ($node instanceof \DOMElement) {
                $module->validatorClass[] = trim($node->textContent);
            }
        }

        // Event listeners
        $module->onInitialize = $this->getFirstValueOrDefaultFromXmlNode($xml, 'onInitialize');
        if (!empty($module->onInitialize)) {
            $module->onInitialize = trim($module->onInitialize);
        }

        $module->onParseData = $this->getFirstValueOrDefaultFromXmlNode($xml, 'onParseData');
        if (!empty($module->onParseData)) {
            $module->onParseData = trim($module->onParseData);
        }

        $module->onDataLoad = $this->getFirstValueOrDefaultFromXmlNode($xml, 'onDataLoad');
        if (!empty($module->onDataLoad)) {
            $module->onDataLoad = trim($module->onDataLoad);
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

            // make sure canvas is always enabled
            if ($module->moduleId === 'core-canvas') {
                $module->enabled = 1;
                // update the table
                if ($moduleSettings->getInt('enabled', ['default' => 0]) === 0) {
                    $this->getStore()->update(
                        'UPDATE `module` SET enabled = 1 WHERE `module`.moduleId = \'core-canvas\' ',
                        []
                    );
                }
            } else {
                $module->enabled = $moduleSettings->getInt('enabled', ['default' => 0]);
            }

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
            $this->getLog()->error('Module ' . $module->moduleId . ' has invalid property groups. e: '
                .  $e->getMessage());
        }

        // Parse required elements.
        $requiredElements = $this->getFirstValueOrDefaultFromXmlNode($xml, 'requiredElements');
        if (!empty($requiredElements)) {
            $module->requiredElements = explode(',', $requiredElements);
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
                    $field->getAttribute('type'),
                    $field->getAttribute('isRequired') === 'true',
                );
            }
        }

        return $dataType;
    }
}
