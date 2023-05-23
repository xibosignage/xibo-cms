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

namespace Xibo\Entity;

use Respect\Validation\Validator as v;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Factory\ModuleFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Widget\Definition\LegacyType;
use Xibo\Widget\Provider\DataProvider;
use Xibo\Widget\Provider\WidgetCompatibilityInterface;
use Xibo\Widget\Provider\WidgetProviderInterface;
use Xibo\Widget\Provider\WidgetValidatorInterface;

/**
 * Class Module
 * @package Xibo\Entity
 * @SWG\Definition()
 */
class Module implements \JsonSerializable
{
    use EntityTrait;
    use ModulePropertyTrait;

    /**
     * @SWG\Property(description="The ID of this Module")
     * @var int
     */
    public $moduleId;

    /**
     * @SWG\Property(description="Module Name")
     * @var string
     */
    public $name;

    /**
     * @SWG\Property(description="Module Author")
     * @var string
     */
    public $author;

    /**
     * @SWG\Property(description="Description of the Module")
     * @var string
     */
    public $description;

    /**
     * @SWG\Property(description="The type code for this module")
     * @var string
     */
    public $type;

    /**
     * @SWG\Property(description="Legacy type codes for this module")
     * @var LegacyType[]
     */
    public $legacyTypes;

    /**
     * @SWG\Property(description="The data type of the data expected to be returned by this modules data provider")
     * @var string
     */
    public $dataType;

    /**
     * @SWG\Property(description="The group details for this module")
     * @var string[]
     */
    public $group;

    /**
     * @SWG\Property(description="The cache key used when requesting data")
     * @var string
     */
    public $dataCacheKey;

    /**
     * @SWG\Property(description="Is specific to a Layout or can be uploaded to the Library?")
     * @var int
     */
    public $regionSpecific;

    /**
     * @SWG\Property(description="The schema version of the module")
     * @var int
     */
    public $schemaVersion;

    /**
     * @SWG\Property(description="The compatibility class of the module")
     * @var string
     */
    public $compatibilityClass = null;

    /**
     * @SWG\Property(description="A flag indicating whether the module should be excluded from the Layout Editor")
     * @var string
     */
    public $showIn = 'both';

    /**
     * @SWG\Property(description="A flag indicating whether the module is assignable to a Layout")
     * @var int
     */
    public $assignable;

    /**
     * @SWG\Property(description="Does this module have a thumbnail to render?")
     * @var int
     */
    public $hasThumbnail;

    /**
     * @SWG\Property(description="This is the location to a module's thumbnail")
     * @var string
     */
    public $thumbnail;

    /** @var int The width of the zone */
    public $startWidth;

    /** @var int The height of the zone */
    public $startHeight;

    /**
     * @SWG\Property(description="Should be rendered natively by the Player or via the CMS (native|html)")
     * @var string
     */
    public $renderAs;

    /**
     * @SWG\Property(description="Class Name including namespace")
     * @var string
     */
    public $class;

    /**
     * @SWG\Property(description="Validator class name including namespace")
     * @var string[]
     */
    public $validatorClass = [];

    /** @var \Xibo\Widget\Definition\Stencil|null Stencil for this modules preview */
    public $preview;

    /** @var \Xibo\Widget\Definition\Stencil|null Stencil for this modules HTML cache */
    public $stencil;

    /** @var \Xibo\Widget\Definition\Property[]|null */
    public $properties;

    /** @var \Xibo\Widget\Definition\Asset[]|null */
    public $assets;

    /**
     * @SWG\Property(description="JavaScript function run when a module is initialised, before data is returned")
     * @var string
     */
    public $onInitialize;

    /**
     * @SWG\Property(description="Data Parser run against each data item applicable when a dataType is present")
     * @var string
     */
    public $onParseData;

    /**
     * @SWG\Property(description="JavaScript function run when a module is rendered, after data has been returned")
     * @var string
     */
    public $onRender;

    /**
     * @SWG\Property(description="JavaScript function run when a module becomes visible")
     * @var string
     */
    public $onVisible;

    /**
     * @SWG\Property(description="Optional sample data item, only applicable when a dataType is present")
     * @var string
     */
    public $sampleData;

    // <editor-fold desc="Properties recorded in the database">

    /**
     * @SWG\Property(description="A flag indicating whether this module is enabled")
     * @var int
     */
    public $enabled;

    /**
     * @SWG\Property(description="A flag indicating whether the Layout designer should render a preview of this module")
     * @var int
     */
    public $previewEnabled;

    /**
     * @SWG\Property(
     *     description="The default duration for Widgets of this Module when the user has not set a duration."
     * )
     * @var int
     */
    public $defaultDuration;

    /**
     * @SWG\Property(
     *     description="An array of additional module specific settings",
     *     type="array",
     *     @SWG\Items(type="string")
     * )
     * @var \Xibo\Widget\Definition\Property[]
     */
    public $settings = [];

    /**
     * @SWG\Property(
     *     description="An array of additional module specific group properties",
     *     type="array",
     *     @SWG\Items(type="string")
     * )
     * @var \Xibo\Widget\Definition\PropertyGroup[]
     */
    public $propertyGroups = [];

    /** @var bool $isInstalled Is this module installed? */
    public $isInstalled;

    /** @var bool $isError Does this module have any errors? */
    public $isError;

    /** @var string[] $errors An array of errors this module has. */
    public $errors;

    // </editor-fold>

    /** @var ModuleFactory */
    private $moduleFactory;

    /** @var WidgetProviderInterface */
    private $widgetProvider;

    /**  @var WidgetCompatibilityInterface */
    private $widgetCompatibility;

    /**  @var WidgetValidatorInterface[] */
    private $widgetValidators = [];

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param \Xibo\Factory\ModuleFactory $moduleFactory
     */
    public function __construct(
        StorageServiceInterface $store,
        LogServiceInterface $log,
        EventDispatcherInterface $dispatcher,
        ModuleFactory $moduleFactory
    ) {
        $this->setCommonDependencies($store, $log, $dispatcher);
        $this->moduleFactory = $moduleFactory;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf('%s - %s', $this->type, $this->name);
    }

    /**
     * Is a template expected?
     * @return bool
     */
    public function isTemplateExpected(): bool
    {
        return (!empty($this->dataType));
    }

    /**
     * Is a template expected?
     * @return bool
     */
    public function isDataProviderExpected(): bool
    {
        return (!empty($this->dataType));
    }

    /**
     * Is a widget provider available
     * @return bool
     */
    public function isWidgetProviderAvailable(): bool
    {
        return $this->widgetProvider !== null;
    }

    /**
     * Get this module's widget provider, or null if there isn't one
     * @return \Xibo\Widget\Provider\WidgetProviderInterface|null
     */
    public function getWidgetProviderOrNull(): ?WidgetProviderInterface
    {
        return $this->widgetProvider;
    }

    /**
     * @param \Xibo\Entity\Widget $widget
     * @return \Xibo\Widget\Provider\DataProvider
     */
    public function createDataProvider(Widget $widget): DataProvider
    {
        return $this->moduleFactory->createDataProvider($this, $widget);
    }

    /**
     * Fetch duration of a file.
     * @param string $file
     * @return int
     */
    public function fetchDurationOrDefaultFromFile(string $file): int
    {
        if ($this->widgetProvider === null) {
            return $this->defaultDuration;
        }
        $durationProvider = $this->moduleFactory->createDurationProvider($file, null);
        $this->widgetProvider->fetchDuration($durationProvider);

        return $durationProvider->getDuration();
    }

    /**
     * Sets the widget provider for this module
     * @param \Xibo\Widget\Provider\WidgetProviderInterface $widgetProvider
     * @return $this
     */
    public function setWidgetProvider(WidgetProviderInterface $widgetProvider): Module
    {
        $this->widgetProvider = $widgetProvider;
        $this->widgetProvider
            ->setLog($this->getLog()->getLoggerInterface())
            ->setDispatcher($this->getDispatcher());
        return $this;
    }

    /**
     * Is a widget compatibility available
     * @return bool
     */
    public function isWidgetCompatibilityAvailable(): bool
    {
        return $this->widgetCompatibility !== null;
    }

    /**
     * Get this module's widget compatibility, or null if there isn't one
     * @return \Xibo\Widget\Provider\WidgetCompatibilityInterface|null
     */
    public function getWidgetCompatibilityOrNull(): ?WidgetCompatibilityInterface
    {
        return $this->widgetCompatibility;
    }

    /**
     * Sets the widget compatibility for this module
     * @param WidgetCompatibilityInterface $widgetCompatibility
     * @return $this
     */
    public function setWidgetCompatibility(WidgetCompatibilityInterface $widgetCompatibility): Module
    {
        $this->widgetCompatibility = $widgetCompatibility;
        $this->widgetCompatibility->setLog($this->getLog()->getLoggerInterface());
        return $this;
    }

    public function addWidgetValidator(WidgetValidatorInterface $widgetValidator): Module
    {
        $this->widgetValidators[] = $widgetValidator;
        return $this;
    }

    /**
     * Get this module's widget validators
     * @return \Xibo\Widget\Provider\WidgetValidatorInterface[]
     */
    public function getWidgetValidators(): array
    {
        return $this->widgetValidators;
    }

    /**
     * Get all properties which allow library references.
     * @return \Xibo\Widget\Definition\Property[]
     */
    public function getPropertiesAllowingLibraryRefs(): array
    {
        $props = [];
        foreach ($this->properties as $property) {
            if ($property->allowLibraryRefs) {
                $props[] = $property;
            }
        }

        return $props;
    }

    /**
     * Get assets
     * @return \Xibo\Widget\Definition\Asset[]
     */
    public function getAssets(): array
    {
        return $this->assets;
    }

    /**
     * Get a module setting
     * If the setting does not exist, $default will be returned.
     * If the setting exists, but is not set, the default value from the setting will be returned
     * @param string $setting The setting
     * @param mixed|null $default A default value if the setting does not exist
     * @return mixed
     */
    public function getSetting(string $setting, $default = null)
    {
        foreach ($this->settings as $property) {
            if ($property->id === $setting) {
                return $property->value ?? $property->default;
            }
        }

        return $default;
    }

    /**
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function validate()
    {
        if (!v::intType()->validate($this->defaultDuration)) {
            throw new InvalidArgumentException(__('Default Duration is a required field.'), 'defaultDuration');
        }
    }

    /**
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function save()
    {
        $this->validate();

        if (!$this->isInstalled) {
            $this->add();
        } else {
            $this->edit();
        }
    }

    private function add()
    {
        $this->moduleId = $this->getStore()->insert('
          INSERT INTO `module` (
            `moduleId`,
            `enabled`,
            `previewEnabled`,
            `defaultDuration`,
            `settings`
            )
            VALUES (
            :moduleId,
            :enabled,
            :previewEnabled,
            :defaultDuration,
            :settings
            )
        ', [
            'moduleId' => $this->moduleId,
            'enabled' => $this->enabled,
            'previewEnabled' => $this->previewEnabled,
            'defaultDuration' => $this->defaultDuration,
            'settings' => $this->getSettingsForSaving()
        ]);
    }

    private function edit()
    {
        $this->getStore()->update('
          UPDATE `module` SET
              enabled = :enabled,
              previewEnabled = :previewEnabled,
              defaultDuration = :defaultDuration,
              settings = :settings
           WHERE moduleid = :moduleId
        ', [
            'moduleId' => $this->moduleId,
            'enabled' => $this->enabled,
            'previewEnabled' => $this->previewEnabled,
            'defaultDuration' => $this->defaultDuration,
            'settings' => $this->getSettingsForSaving()
        ]);
    }

    /**
     * @return string
     */
    private function getSettingsForSaving(): string
    {
        $settings = [];
        foreach ($this->settings as $setting) {
            if ($setting->value !== null) {
                $settings[$setting->id] = $setting->value;
            }
        }
        return count($settings) > 0 ? json_encode($settings) : '[]';
    }

    /**
     * Delete this module
     * @return void
     */
    public function delete()
    {
        $this->getStore()->update('DELETE FROM `module` WHERE moduleId = :id', [
            'id' => $this->moduleId
        ]);
    }
}
