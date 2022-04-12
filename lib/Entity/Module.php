<?php
/**
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

namespace Xibo\Entity;

use Respect\Validation\Validator as v;
use Xibo\Factory\ModuleFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Widget\Provider\DataProviderInterface;
use Xibo\Widget\Provider\DurationProviderInterface;
use Xibo\Widget\Provider\WidgetProviderInterface;

/**
 * Class Module
 * @package Xibo\Entity
 * @property bool $isInstalled Is this module installed?
 * @property bool $isError Does this module have any errors?
 * @property string[] $errors An array of errors this module has.
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
     * @SWG\Property(description="The data type of the data expected to be returned by this modules data provider")
     * @var string
     */
    public $dataType;

    /**
     * @SWG\Property(description="A flag indicating whether this module is specific to a Layout or can be uploaded to the Library")
     * @var int
     */
    public $regionSpecific;

    /**
     * @SWG\Property(description="The schema version of the module")
     * @var int
     */
    public $schemaVersion;

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
     * @SWG\Property(description="A flag indicating whether the module should be rendered natively by the Player or via the CMS (native|html)")
     * @var string
     */
    public $renderAs;

    /**
     * @SWG\Property(description="Class Name including namespace")
     * @var string
     */
    public $class;

    /** @var \Xibo\Widget\Definition\Stencil|null Stencil for this modules preview */
    public $preview;

    /** @var \Xibo\Widget\Definition\Stencil|null Stencil for this modules HTML cache */
    public $stencil;

    /** @var \Xibo\Widget\Definition\Property[]|null */
    public $properties;

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

    // </editor-fold>

    /** @var ModuleFactory */
    private $moduleFactory;

    /** @var WidgetProviderInterface */
    private $widgetProvider;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Xibo\Factory\ModuleFactory $moduleFactory
     */
    public function __construct(
        StorageServiceInterface $store,
        LogServiceInterface $log,
        ModuleFactory $moduleFactory
    ) {
        $this->setCommonDependencies($store, $log);
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
     * Get this module's widget provider, or null if there isn't one
     * @return \Xibo\Widget\Provider\WidgetProviderInterface|null
     */
    public function getWidgetProviderOrNull(): ?WidgetProviderInterface
    {
        return $this->widgetProvider;
    }

    /**
     * @param \Xibo\Entity\Widget $widget
     * @return \Xibo\Widget\Provider\DataProviderInterface
     */
    public function createDataProvider(Widget $widget): DataProviderInterface
    {
        return $this->moduleFactory->createDataProvider($this, $widget);
    }

    /**
     * @param string $file a fully qualified path to this file
     * @return \Xibo\Widget\Provider\DurationProviderInterface
     */
    public function createDurationProvider(string $file): DurationProviderInterface
    {
        return $this->moduleFactory->createDurationProvider($file);
    }

    /**
     * Fetch duration of a file.
     * @param string $file
     * @return int
     */
    public function fetchDurationOrDefault(string $file): int
    {
        if ($this->widgetProvider === null) {
            return $this->defaultDuration;
        }
        $durationProvider = $this->createDurationProvider($file);
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
        return $this;
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
