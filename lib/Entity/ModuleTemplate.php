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

/**
 * Represents a module template
 * @SWG\Definition()
 */
class ModuleTemplate implements \JsonSerializable
{
    use EntityTrait;
    use ModulePropertyTrait;

    /** @var string The templateId */
    public $templateId;

    /** @var string Type of template (static|element|stencil) */
    public $type;

    /** @var \Xibo\Widget\Definition\Extend|null If this template extends another */
    public $extends;

    /** @var string The datatype of this template */
    public $dataType;

    /** @var string The title */
    public $title;

    /**
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

    /** @var \Xibo\Widget\Definition\Property[]|null */
    public $properties;

    /** @var bool Is Visible? */
    public $isVisible = true;

    /**
     * @SWG\Property(
     *     description="An array of additional module specific group properties",
     *     type="array",
     *     @SWG\Items(type="string")
     * )
     * @var \Xibo\Widget\Definition\PropertyGroup[]
     */
    public $propertyGroups = [];

    /** @var \Xibo\Widget\Definition\Stencil|null */
    public $stencil;

    /** @var  */
    public $assets;

    /** @var string A Renderer to run if custom rendering is required. */
    public $onTemplateRender;

    /** @var string A data parser for elements */
    public $onElementParseData;
    
    /** @var bool $isError Does this module have any errors? */
    public $isError;

    /** @var string[] $errors An array of errors this module has. */
    public $errors;

    /** @var \Xibo\Factory\ModuleTemplateFactory */
    private $moduleTemplateFactory;

    /**
     * Entity constructor.
     * @param \Xibo\Storage\StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param \Xibo\Factory\ModuleTemplateFactory $moduleTemplateFactory
     */
    public function __construct(
        StorageServiceInterface $store,
        LogServiceInterface $log,
        EventDispatcherInterface $dispatcher,
        ModuleTemplateFactory $moduleTemplateFactory
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
}
