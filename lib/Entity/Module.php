<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Module.php) is part of Xibo.
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
use Xibo\Exception\InvalidArgumentException;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Module
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Module implements \JsonSerializable
{
    use EntityTrait;

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
     * @SWG\Property(description="Description of the Module")
     * @var string
     */
    public $description;

    /**
     * @SWG\Property(description="A comma separated list of Valid Extensions")
     * @var string
     */
    public $validExtensions;

    /**
     * @SWG\Property(description="The type code for this module")
     * @var string
     */
    public $type;

    /**
     * @SWG\Property(description="A flag indicating whether this module is enabled")
     * @var int
     */
    public $enabled;

    /**
     * @SWG\Property(description="A flag indicating whether this module is specific to a Layout or can be uploaded to the Library")
     * @var int
     */
    public $regionSpecific;

    /**
     * @SWG\Property(description="A flag indicating whether the Layout designer should render a preview of this module")
     * @var int
     */
    public $previewEnabled;

    /**
     * @SWG\Property(description="A flag indicating whether the module is assignable to a Layout")
     * @var int
     */
    public $assignable;

    /**
     * @SWG\Property(description="A flag indicating whether the module should be rendered natively by the Player or via the CMS (native|html)")
     * @var string
     */
    public $renderAs;

    /**
     * @SWG\Property(description="The default duration for Widgets of this Module when the user has elected to not set a specific duration.")
     * @var int
     */
    public $defaultDuration;

    /**
     * @SWG\Property(description="An array of additional module specific settings")
     * @var string[]
     */
    public $settings = [];

    /**
     * @SWG\Property(description="The schema version of the module")
     * @var int
     */
    public $schemaVersion;

    /**
     * @SWG\Property(description="Class Name including namespace")
     * @var string
     */
    public $class;

    /**
     * @SWG\Property(description="The Twig View path for module specific templates")
     * @var string
     */
    public $viewPath = '../modules';

    /**
     * @SWG\Property(description="The original installation name of this module.")
     * @var string
     */
    public $installName;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf('%s - %s', $this->type, $this->name);
    }

    public function validate()
    {
        if (!v::intType()->validate($this->defaultDuration))
            throw new InvalidArgumentException(__('Default Duration is a required field.'), 'defaultDuration');

        if (!empty($this->validExtensions) && !v::alnum(',')->validate($this->validExtensions)) {
            throw new InvalidArgumentException(__('Comma separated file extensions only please, without the .'), 'validExtensions');
        }
    }

    public function save()
    {
        $this->validate();

        if ($this->moduleId == null || $this->moduleId == 0)
            $this->add();
        else
            $this->edit();

    }

    private function add()
    {
        $this->moduleId = $this->getStore()->insert('
          INSERT INTO `module` (`Module`, `Name`, `Enabled`, `RegionSpecific`, `Description`, `SchemaVersion`, `ValidExtensions`, `PreviewEnabled`, `assignable`, `render_as`, `settings`, `viewPath`, `class`, `defaultDuration`, `installName`)
            VALUES (:module, :name, :enabled, :region_specific, :description,
                :schema_version, :valid_extensions, :preview_enabled, :assignable, :render_as, :settings, :viewPath, :class, :defaultDuration, :installName)
        ', [
            'module' => $this->type,
            'name' => $this->name,
            'enabled' => $this->enabled,
            'region_specific' => $this->regionSpecific,
            'description' => $this->description,
            'schema_version' => $this->schemaVersion,
            'valid_extensions' => $this->validExtensions,
            'preview_enabled' => $this->previewEnabled,
            'assignable' => $this->assignable,
            'render_as' => $this->renderAs,
            'settings' => json_encode($this->settings),
            'viewPath' => $this->viewPath,
            'class' => $this->class,
            'defaultDuration' => $this->defaultDuration,
            'installName' => $this->installName
        ]);
    }

    private function edit()
    {
        $this->getStore()->update('
          UPDATE `module` SET
              enabled = :enabled,
              previewEnabled = :previewEnabled,
              validExtensions = :validExtensions,
              defaultDuration = :defaultDuration,
              settings = :settings
           WHERE moduleid = :moduleId
        ', [
            'moduleId' => $this->moduleId,
            'enabled' => $this->enabled,
            'previewEnabled' => $this->previewEnabled,
            'validExtensions' => $this->validExtensions,
            'defaultDuration' => $this->defaultDuration,
            'settings' => json_encode($this->settings)
        ]);
    }
}