<?php
/*
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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Factory\ModuleTemplateFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Represents a module template
 * @property bool $isError Does this module have any errors?
 * @property string[] $errors An array of errors this module has.
 */
class ModuleTemplate implements \JsonSerializable
{
    use EntityTrait;
    use ModulePropertyTrait;

    /** @var string The templateId */
    public $templateId;

    /** @var string Type of template (static|element|stencil) */
    public $type;

    /** @var string The datatype of this template */
    public $dataType;

    /** @var string The title */
    public $title;

    /** @var \Xibo\Widget\Definition\Property[]|null */
    public $properties;

    /** @var \Xibo\Widget\Definition\Stencil|null */
    public $stencil;

    /** @var string A Renderer to run if custom rendering is required. */
    public $renderer;

    /** @var \Xibo\Factory\ModuleTemplateFactory */
    private $moduleTemplateFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
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
     * @param \Xibo\Entity\Widget $widget
     * @param bool $includeDefaults
     * @return $this
     */
    public function decorateProperties(Widget $widget, bool $includeDefaults = false): ModuleTemplate
    {
        foreach ($this->properties as $property) {
            $property->value = $widget->getOptionValue($property->id, null);

            // Should we include defaults?
            if ($includeDefaults && $property->value === null) {
                $property->value = $property->default;
            }

            if ($property->variant === 'uri') {
                $property->value = urldecode($property->value);
            }
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getPropertyValues(): array
    {
        $properties = [];
        foreach ($this->properties as $property) {
            $properties[$property->id] = $property->value;
        }
        return $properties;
    }

    /**
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function validateProperties(): void
    {
        // Go through all of our required properties, and validate that they are as they should be.
        foreach ($this->properties as $property) {
            $property->validate();
        }
    }
}
