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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Factory\ModuleTemplateFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Widget\Definition\Asset;

/**
 * Represents a module template
 * @SWG\Definition()
 */
class ModuleTemplate implements \JsonSerializable
{
    use EntityTrait;
    use ModulePropertyTrait;

    /** @var int The database ID */
    public $id;

    /**
     * @SWG\Property()
     * @var string The templateId
     */
    public $templateId;

    /**
     * @SWG\Property()
     * @var string Type of template (static|element|stencil)
     */
    public $type;

    /**
     * @SWG\Property()
     * @var \Xibo\Widget\Definition\Extend|null If this template extends another
     */
    public $extends;

    /**
     * @SWG\Property()
     * @var string The datatype of this template
     */
    public $dataType;

    /**
     * @SWG\Property()
     * @var string The title
     */
    public $title;

    /**
     * @SWG\Property()
     * @var string Icon
     */
    public $icon;

    /**
     * @SWG\Property()
     * Thumbnail
     * this is the location to a module template's thumbnail, which should be added to the installation
     * relative to the module class file.
     * @var string
     */
    public $thumbnail;

    /** @var int The width of the zone */
    public $startWidth;

    /** @var int The height of the zone */
    public $startHeight;

    /** @var bool Does this template have dimensions? */
    public $hasDimensions;

    /** @var bool Can this template be rotated? */
    public $canRotate;

    /**
     * @SWG\Property(description="A flag indicating whether the template should be excluded from the Layout Editor")
     * @var string
     */
    public $showIn = 'both';

    /**
     * @SWG\Property()
     * @var \Xibo\Widget\Definition\Property[]|null Properties
     */
    public $properties;

    /**
     * @SWG\Property()
     * @var bool Is Visible?
     */
    public $isVisible = true;

    /**
     * @SWG\Property(description="An array of additional module specific group properties")
     * @var \Xibo\Widget\Definition\PropertyGroup[]
     */
    public $propertyGroups = [];

    /**
     * @SWG\Property()
     * @var \Xibo\Widget\Definition\Stencil|null A stencil, if needed
     */
    public $stencil;

    /**
     * @SWG\Property()
     * @var Asset[]
     */
    public $assets;

    /** @var string A Renderer to run if custom rendering is required. */
    public $onTemplateRender;

    /** @var string A data parser for elements */
    public $onElementParseData;

    /** @var bool $isError Does this module have any errors? */
    public $isError;

    /** @var string[] $errors An array of errors this module has. */
    public $errors;

    /** @var string $ownership Who owns this file? system|custom|user */
    public $ownership;

    /** @var string $xml The XML used to build this template */
    private $xml;

    /** @var \Xibo\Factory\ModuleTemplateFactory */
    private $moduleTemplateFactory;

    /**
     * Entity constructor.
     * @param \Xibo\Storage\StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param \Xibo\Factory\ModuleTemplateFactory $moduleTemplateFactory
     * @param string $file The file this template resides in
     */
    public function __construct(
        StorageServiceInterface $store,
        LogServiceInterface $log,
        EventDispatcherInterface $dispatcher,
        ModuleTemplateFactory $moduleTemplateFactory,
        private readonly string $file
    ) {
        $this->setCommonDependencies($store, $log, $dispatcher);
        $this->moduleTemplateFactory = $moduleTemplateFactory;
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
     * Set XML for this Module Template
     * @param string $xml
     * @return void
     */
    public function setXml(string $xml): void
    {
        $this->xml = $xml;
    }

    /**
     * Get XML for this Module Template
     * @return string
     */
    public function getXml(): string
    {
        if ($this->file === 'database') {
            return $this->xml;
        } else {
            return file_get_contents($this->file);
        }
    }

    /**
     * Save
     * @return void
     */
    public function save(): void
    {
        if ($this->file === 'database') {
            if ($this->id === null) {
                $this->add();
            } else {
                $this->edit();
            }
        }
    }

    /**
     * Delete
     * @return void
     */
    public function delete(): void
    {
        if ($this->file === 'database') {
            $this->getStore()->update('DELETE FROM module_templates WHERE id = :id', [
                'id' => $this->id
            ]);
        }
    }

    /**
     * Invalidate this module template for any widgets that use it
     * @return void
     */
    public function invalidate(): void
    {
        // TODO: can we improve this via the event mechanism instead?
        $this->getStore()->update('
            UPDATE `widget` SET modifiedDt = :now
             WHERE widgetId IN (
                SELECT widgetId
                  FROM widgetoption 
                 WHERE `option` = \'templateId\'
                    AND `value` = :templateId
             )
        ', [
            'now' => time(),
            'templateId' => $this->templateId,
        ]);
    }

    /**
     * Add
     * @return void
     */
    private function add(): void
    {
        $this->id = $this->getStore()->insert('
            INSERT INTO `module_templates` (`templateId`, `dataType`, `xml`)
                VALUES (:templateId, :dataType, :xml)
        ', [
            'templateId' => $this->templateId,
            'dataType' => $this->dataType,
            'xml' => $this->xml,
        ]);
    }

    /**
     * Edit
     * @return void
     */
    private function edit(): void
    {
        $this->getStore()->update('
            UPDATE `module_templates` SET
                `templateId` = :templateId,
                `dataType`= :dataType,
                `xml` = :xml
             WHERE `id` = :id
        ', [
            'templateId' => $this->templateId,
            'dataType' => $this->dataType,
            'xml' => $this->xml,
            'id' => $this->id,
        ]);
    }
}
