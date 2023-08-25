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

use Slim\Views\Twig;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\ModuleTemplate;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Widget\Definition\Asset;

/**
 * Factory for working with Module Templates
 */
class ModuleTemplateFactory extends BaseFactory
{
    use ModuleXmlTrait;

    /** @var ModuleTemplate[]|null */
    private $templates = null;

    /** @var \Stash\Interfaces\PoolInterface */
    private $pool;

    /** @var \Slim\Views\Twig */
    private $twig;

    /**
     * Construct a factory
     * @param PoolInterface $pool
     * @param \Slim\Views\Twig $twig
     */
    public function __construct(PoolInterface $pool, Twig $twig)
    {
        $this->pool = $pool;
        $this->twig = $twig;
    }

    /**
     * @param string $type The type of template (element|elementGroup|static)
     * @param string $id
     * @return \Xibo\Entity\ModuleTemplate
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getByTypeAndId(string $type, string $id): ModuleTemplate
    {
        foreach ($this->load() as $template) {
            if ($template->type === $type && $template->templateId === $id) {
                return $template;
            }
        }
        throw new NotFoundException(sprintf(__('%s not found for %s'), $type, $id));
    }

    /**
     * @param string $dataType
     * @param string $id
     * @return \Xibo\Entity\ModuleTemplate
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getByDataTypeAndId(string $dataType, string $id): ModuleTemplate
    {
        foreach ($this->load() as $template) {
            if ($template->dataType === $dataType && $template->templateId === $id) {
                return $template;
            }
        }
        throw new NotFoundException(sprintf(__('Template not found for %s and %s'), $dataType, $id));
    }

    /**
     * @param string $dataType
     * @return ModuleTemplate[]
     */
    public function getByDataType(string $dataType): array
    {
        $templates = [];
        foreach ($this->load() as $template) {
            if ($template->dataType === $dataType) {
                $templates[] = $template;
            }
        }
        return $templates;
    }

    /**
     * @param string $assetId
     * @return \Xibo\Widget\Definition\Asset
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getAssetById(string $assetId): Asset
    {
        foreach ($this->load() as $template) {
            foreach ($template->getAssets() as $asset) {
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
        foreach ($this->load() as $template) {
            foreach ($template->getAssets() as $asset) {
                if ($asset->alias === $alias) {
                    return $asset;
                }
            }
        }

        throw new NotFoundException(__('Asset not found'));
    }

    /**
     * Get an array of all modules
     * @return \Xibo\Entity\ModuleTemplate[]
     */
    public function getAll(): array
    {
        return $this->load();
    }

    /**
     * Get an array of all modules
     * @return Asset[]
     */
    public function getAllAssets(): array
    {
        $assets = [];
        foreach ($this->load() as $template) {
            foreach ($template->getAssets() as $asset) {
                $assets[$asset->id] = $asset;
            }
        }
        return $assets;
    }

    /**
     * Load templates
     * @return \Xibo\Entity\ModuleTemplate[]
     */
    private function load(): array
    {
        if ($this->templates === null) {
            $this->getLog()->debug('Loading templates');

            $files = array_merge(
                glob(PROJECT_ROOT . '/modules/templates/*.xml'),
                glob(PROJECT_ROOT . '/custom/modules/templates/*.xml')
            );

            foreach ($files as $file) {
                // Create our module entity from this file
                try {
                    $this->createMultiFromXml($file);
                } catch (\Exception $exception) {
                    $this->getLog()->error('Unable to create template from '
                        . basename($file) . ', skipping. e = ' . $exception->getMessage());
                }
            }
        }

        return $this->templates;
    }

    /**
     * Create multiple templates from XML
     * @param string $file
     * @return void
     */
    private function createMultiFromXml(string $file): void
    {
        $xml = new \DOMDocument();
        $xml->load($file);

        foreach ($xml->getElementsByTagName('templates') as $node) {
            if ($node instanceof \DOMElement) {
                $this->getLog()->debug('createMultiFromXml: there are ' . count($node->childNodes)
                    . ' templates in ' . $file);
                foreach ($node->childNodes as $childNode) {
                    if ($childNode instanceof \DOMElement) {
                        $this->templates[] = $this->createFromXml($childNode);
                    }
                }
            }
        }
    }

    /**
     * @param \DOMElement $xml
     * @return \Xibo\Entity\ModuleTemplate
     */
    private function createFromXml(\DOMElement $xml): ModuleTemplate
    {
        // TODO: cache this into Stash
        $template = new ModuleTemplate($this->getStore(), $this->getLog(), $this->getDispatcher(), $this);
        $template->templateId = $this->getFirstValueOrDefaultFromXmlNode($xml, 'id');
        $template->type = $this->getFirstValueOrDefaultFromXmlNode($xml, 'type');
        $template->dataType = $this->getFirstValueOrDefaultFromXmlNode($xml, 'dataType');
        $template->title = __($this->getFirstValueOrDefaultFromXmlNode($xml, 'title'));
        $template->thumbnail = $this->getFirstValueOrDefaultFromXmlNode($xml, 'thumbnail');
        $template->icon = $this->getFirstValueOrDefaultFromXmlNode($xml, 'icon');
        $template->isVisible = $this->getFirstValueOrDefaultFromXmlNode($xml, 'isVisible') !== 'false';
        $template->startWidth = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'startWidth'));
        $template->startHeight = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'startHeight'));
        $template->hasDimensions = $this->getFirstValueOrDefaultFromXmlNode($xml, 'hasDimensions', 'true') === 'true';
        $template->canRotate = $this->getFirstValueOrDefaultFromXmlNode($xml, 'canRotate', 'false') === 'true';
        $template->onTemplateRender = $this->getFirstValueOrDefaultFromXmlNode($xml, 'onTemplateRender');
        $template->onElementParseData = $this->getFirstValueOrDefaultFromXmlNode($xml, 'onElementParseData');
        $template->showIn = $this->getFirstValueOrDefaultFromXmlNode($xml, 'showIn') ?? 'both';
        if (!empty($template->onTemplateRender)) {
            $template->onTemplateRender = trim($template->onTemplateRender);
        }

        if (!empty($template->onElementParseData)) {
            $template->onElementParseData = trim($template->onElementParseData);
        }

        $template->isError = false;
        $template->errors = [];

        // Parse extends definition
        try {
            $template->extends = $this->getExtends($xml->getElementsByTagName('extends'))[0] ?? null;
        } catch (\Exception $e) {
            $template->errors[] = __('Invalid Extends');
            $this->getLog()->error('Module Template ' . $template->templateId
                . ' has invalid extends definition. e: ' .  $e->getMessage());
        }

        // Parse property definitions.
        try {
            $template->properties = $this->parseProperties($xml->getElementsByTagName('properties'));
        } catch (\Exception $e) {
            $template->errors[] = __('Invalid properties');
            $this->getLog()->error('Module Template ' . $template->templateId
                . ' has invalid properties. e: ' .  $e->getMessage());
        }

        // Parse group property definitions.
        try {
            $template->propertyGroups = $this->parsePropertyGroups($xml->getElementsByTagName('propertyGroups'));
        } catch (\Exception $e) {
            $template->errors[] = __('Invalid property groups');
            $this->getLog()->error('Module Template ' . $template->templateId . ' has invalid property groups. e: '
                .  $e->getMessage());
        }

        // Parse stencil
        try {
            $template->stencil = $this->getStencils($xml->getElementsByTagName('stencil'))[0] ?? null;
        } catch (\Exception $e) {
            $template->errors[] = __('Invalid stencils');
            $this->getLog()->error('Module Template ' . $template->templateId
                . ' has invalid stencils. e: ' .  $e->getMessage());
        }

        // Parse assets
        try {
            $template->assets = $this->parseAssets($xml->getElementsByTagName('assets'));
        } catch (\Exception $e) {
            $template->errors[] = __('Invalid assets');
            $this->getLog()->error('Module Template ' . $template->templateId
                . ' has invalid assets. e: ' .  $e->getMessage());
        }

        return $template;
    }
}
