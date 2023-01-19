<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

namespace Xibo\Factory;

use Slim\Views\Twig;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\ModuleTemplate;
use Xibo\Support\Exception\NotFoundException;

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
        $template->title = $this->getFirstValueOrDefaultFromXmlNode($xml, 'title');
        $template->thumbnail = $this->getFirstValueOrDefaultFromXmlNode($xml, 'thumbnail');
        $template->onTemplateRender = $this->getFirstValueOrDefaultFromXmlNode($xml, 'onTemplateRender');
        if (!empty($template->onTemplateRender)) {
            $template->onTemplateRender = trim($template->onTemplateRender);
        }

        $template->isError = false;
        $template->errors = [];

        // Parse property definitions.
        try {
            $template->properties = $this->parseProperties($xml->getElementsByTagName('properties'));
        } catch (\Exception $e) {
            $template->errors[] = __('Invalid properties');
            $this->getLog()->error('Module ' . $template->templateId
                . ' has invalid properties. e: ' .  $e->getMessage());
        }

        // Parse stencil
        try {
            $template->stencil = $this->getStencils($xml->getElementsByTagName('stencil'))[0] ?? null;
        } catch (\Exception $e) {
            $template->errors[] = __('Invalid stencils');
            $this->getLog()->error('Module ' . $template->templateId
                . ' has invalid stencils. e: ' .  $e->getMessage());
        }

        // Parse assets
        try {
            $template->assets = $this->parseAssets($xml->getElementsByTagName('assets'));
        } catch (\Exception $e) {
            $template->errors[] = __('Invalid assets');
            $this->getLog()->error('Module ' . $template->templateId
                . ' has invalid assets. e: ' .  $e->getMessage());
        }

        return $template;
    }
}
