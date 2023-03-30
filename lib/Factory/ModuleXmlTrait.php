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
use Xibo\Entity\Module;
use Xibo\Widget\Definition\Asset;
use Xibo\Widget\Definition\Element;
use Xibo\Widget\Definition\Extend;
use Xibo\Widget\Definition\LegacyType;
use Xibo\Widget\Definition\PlayerCompatibility;
use Xibo\Widget\Definition\Property;
use Xibo\Widget\Definition\PropertyGroup;
use Xibo\Widget\Definition\Stencil;

/**
 * A trait to help with parsing modules from XML
 */
trait ModuleXmlTrait
{
    /**
     * Get stencils from a DOM node list
     * @param \DOMNodeList $nodes
     * @return Stencil[]
     */
    private function getStencils(\DOMNodeList $nodes): array
    {
        $stencils = [];

        foreach ($nodes as $node) {
            $stencil = new Stencil();

            /** @var \DOMNode $node */
            foreach ($node->childNodes as $childNode) {
                /** @var \DOMElement $childNode */
                if ($childNode->nodeName === 'twig') {
                    $stencil->twig = $childNode->textContent;
                } else if ($childNode->nodeName === 'hbs') {
                    $stencil->hbsId = $childNode->getAttribute('id');
                    $stencil->hbs = $childNode->textContent;
                } else if ($childNode->nodeName === 'elements') {
                    $stencil->elements = $this->parseElements($childNode->childNodes);
                } else if ($childNode->nodeName === 'width') {
                    $stencil->width = doubleval($childNode->textContent);
                } else if ($childNode->nodeName === 'height') {
                    $stencil->height = doubleval($childNode->textContent);
                } else if ($childNode->nodeName === 'gapBetweenHbs') {
                    $stencil->gapBetweenHbs = doubleval($childNode->textContent);
                }
            }

            if ($stencil->twig !== null || $stencil->hbs !== null) {
                $stencils[] = $stencil;
            }
        }

        return $stencils;
    }

    /**
     * @param \DOMNode[]|\DOMNodeList $propertyNodes
     * @return \Xibo\Widget\Definition\Property[]
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    private function parseProperties($propertyNodes, ?Module $module = null): array
    {
        if ($propertyNodes instanceof \DOMNodeList) {
            // Property nodes are the parent node
            if (count($propertyNodes) <= 0) {
                return [];
            }
            $propertyNodes = $propertyNodes->item(0)->childNodes;
        }

        $defaultValues = [];
        $properties = [];
        foreach ($propertyNodes as $node) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                /** @var \DOMElement $node */
                $property = new Property();
                $property->id = $node->getAttribute('id');
                $property->type = $node->getAttribute('type');
                $property->variant = $node->getAttribute('variant');
                $property->format = $node->getAttribute('format');
                $property->mode = $node->getAttribute('mode');
                $property->target = $node->getAttribute('target');
                $property->propertyGroupId = $node->getAttribute('propertyGroupId');
                $property->allowLibraryRefs = $node->getAttribute('allowLibraryRefs') === 'true';
                $property->allowAssetRefs = $node->getAttribute('allowAssetRefs') === 'true';
                $property->title = __($this->getFirstValueOrDefaultFromXmlNode($node, 'title'));
                $property->helpText = __($this->getFirstValueOrDefaultFromXmlNode($node, 'helpText'));
                $property->value = $this->getFirstValueOrDefaultFromXmlNode($node, 'value');
                $property->dependsOn = $this->getFirstValueOrDefaultFromXmlNode($node, 'dependsOn');

                // Default value
                $defaultValue = $this->getFirstValueOrDefaultFromXmlNode($node, 'default');

                // Is this a variable?
                if ($module !== null && Str::startsWith($defaultValue, '%') && Str::endsWith($defaultValue, '%')) {
                    $defaultValue = $module->getSetting(str_replace('%', '', $defaultValue));
                } else if (Str::startsWith($defaultValue, '#') && Str::endsWith($defaultValue, '#')) {
                    $defaultValue = $this->getConfig()->getSetting(str_replace('#', '', $defaultValue));
                }
                $defaultValues[$property->id] = $defaultValue;

                // Validation
                $validationNodes = $node->getElementsByTagName('rule');
                foreach ($validationNodes as $validationNode) {
                    if ($validationNode instanceof \DOMElement) {
                        $property->validation[] = $validationNode->textContent;
                    }
                }

                // Options
                $options = $node->getElementsByTagName('options');
                if (count($options) > 0) {
                    foreach ($options->item(0)->childNodes as $optionNode) {
                        if ($optionNode->nodeType === XML_ELEMENT_NODE) {
                            $set = [];
                            if (!empty($optionNode->getAttribute('set'))) {
                                $set = explode(',', $optionNode->getAttribute('set'));
                            }

                            /** @var \DOMElement $optionNode */
                            $property->addOption(
                                $optionNode->getAttribute('name'),
                                $optionNode->getAttribute('image'),
                                $set,
                                $optionNode->textContent
                            );
                        }
                    }
                }

                // Visibility conditions
                $visibility = $node->getElementsByTagName('visibility');
                if (count($visibility) > 0) {
                    foreach ($visibility->item(0)->childNodes as $testNode) {
                        if ($testNode->nodeType === XML_ELEMENT_NODE) {
                            /** @var \DOMElement $testNode */
                            $conditions = [];
                            foreach ($testNode->getElementsByTagName('condition') as $condNode) {
                                if ($condNode instanceof \DOMElement) {
                                    $conditions[] = [
                                        'field' => $condNode->getAttribute('field'),
                                        'type' => $condNode->getAttribute('type'),
                                        'value' => $condNode->textContent
                                    ];
                                }
                            }

                            $property->addVisibilityTest(
                                $testNode->getAttribute('type'),
                                $conditions
                            );
                        }
                    }
                }

                // Player compat
                $playerCompat = $node->getElementsByTagName('playerCompatibility');
                if (count($playerCompat) > 0) {
                    $playerCompat = $playerCompat->item(0);
                    if ($playerCompat->nodeType === XML_ELEMENT_NODE) {
                        /** @var \DOMElement $playerCompat */
                        $playerCompatibility = new PlayerCompatibility();
                        $playerCompatibility->message = $playerCompat->textContent;
                        $playerCompatibility->windows = $playerCompat->getAttribute('windows');
                        $playerCompatibility->android = $playerCompat->getAttribute('android');
                        $playerCompatibility->linux = $playerCompat->getAttribute('linux');
                        $playerCompatibility->webos = $playerCompat->getAttribute('webos');
                        $playerCompatibility->tizen = $playerCompat->getAttribute('tizen');
                        $property->playerCompatibility = $playerCompatibility;
                    }
                }

                // Custom popover
                $property->customPopOver = __($this->getFirstValueOrDefaultFromXmlNode($node, 'customPopOver'));

                $properties[] = $property;
            }
        }

        // Set the default values
        $params = $this->getSanitizer($defaultValues);
        foreach ($properties as $property) {
            $property->setDefaultByType($params);
        }

        return $properties;
    }

    /**
     * @param \DOMNode[]|\DOMNodeList $propertyGroupNodes
     * @return array
     */
    private function parsePropertyGroups($propertyGroupNodes): array
    {
        if ($propertyGroupNodes instanceof \DOMNodeList) {
            // Property nodes are the parent node
            if (count($propertyGroupNodes) <= 0) {
                return [];
            }
            $propertyGroupNodes = $propertyGroupNodes->item(0)->childNodes;
        }

        $propertyGroups = [];
        foreach ($propertyGroupNodes as $propertyGroupNode) {
            /** @var \DOMNode $propertyGroupNode */
            if ($propertyGroupNode instanceof \DOMElement) {
                $propertyGroup = new PropertyGroup();
                $propertyGroup->id = $propertyGroupNode->getAttribute('id');
                $propertyGroup->expanded = $propertyGroupNode->getAttribute('expanded') === 'true';
                $propertyGroup->title = __($this->getFirstValueOrDefaultFromXmlNode($propertyGroupNode, 'title'));
                $propertyGroup->helpText = __($this->getFirstValueOrDefaultFromXmlNode($propertyGroupNode, 'helpText'));
                $propertyGroups[] = $propertyGroup;
            }
        }

        return $propertyGroups;
    }

    /**
     * @param \DOMNodeList $elementsNodes
     * @return \Xibo\Widget\Definition\Property[]
     */
    private function parseElements(\DOMNodeList $elementsNodes): array
    {
        $elements = [];
        foreach ($elementsNodes as $elementNode) {
            /** @var \DOMNode $elementNode */
            if ($elementNode instanceof \DOMElement) {
                $element = new Element();
                $element->id = $elementNode->getAttribute('id');
                foreach ($elementNode->childNodes as $childNode) {
                    if ($childNode instanceof \DOMElement) {
                        if ($childNode->nodeName === 'top') {
                            $element->top = doubleval($childNode->textContent);
                        } else if ($childNode->nodeName === 'left') {
                            $element->left = doubleval($childNode->textContent);
                        } else if ($childNode->nodeName === 'width') {
                            $element->width = doubleval($childNode->textContent);
                        } else if ($childNode->nodeName === 'height') {
                            $element->height = doubleval($childNode->textContent);
                        } else if ($childNode->nodeName === 'layer') {
                            $element->layer = intval($childNode->textContent);
                        } else if ($childNode->nodeName === 'rotation') {
                            $element->rotation = intval($childNode->textContent);
                        } else if ($childNode->nodeName === 'defaultProperties') {
                            $element->properties[] = [
                                'id' => $childNode->getAttribute('id'),
                                'value' => $childNode->textContent
                            ];
                        }
                    }
                }
                $elements[] = $element;
            }
        }

        return $elements;
    }

    /**
     * @param \DOMNodeList $legacyTypeNodes
     * @return \Xibo\Widget\Definition\Property[]
     */
    private function parseLegacyTypes(\DOMNodeList $legacyTypeNodes): array
    {
        $legacyTypes = [];
        foreach ($legacyTypeNodes as $node) {
            /** @var \DOMNode $node */
            if ($node instanceof \DOMElement) {
                $legacyType = new LegacyType();
                $legacyType->name = trim($node->textContent);
                $legacyType->condition = $node->getAttribute('condition');

                $legacyTypes[] = $legacyType;
            }
        }

        return $legacyTypes;
    }

    /**
     * Parse assets
     * @param \DOMNode[]|\DOMNodeList $assetNodes
     * @return \Xibo\Widget\Definition\Asset[]
     */
    private function parseAssets($assetNodes): array
    {
        if ($assetNodes instanceof \DOMNodeList) {
            // Asset nodes are the parent node
            if (count($assetNodes) <= 0) {
                return [];
            }
            $assetNodes = $assetNodes->item(0)->childNodes;
        }

        $assets = [];
        foreach ($assetNodes as $node) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                /** @var \DOMElement $node */
                $asset = new Asset();
                $asset->id = $node->getAttribute('id');
                $asset->path = $node->getAttribute('path');
                $asset->mimeType = $node->getAttribute('mimeType');
                $asset->type = $node->getAttribute('type');
                $asset->cmsOnly = $node->getAttribute('cmsOnly');
                $assets[] = $asset;
            }
        }

        return $assets;
    }

    /**
     * Parse extends
     * @param \DOMNodeList $nodes
     * @return \Xibo\Widget\Definition\Asset[]
     */
    private function getExtends(\DOMNodeList $nodes): array
    {
        $extends = [];
        foreach ($nodes as $node) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                /** @var \DOMElement $node */
                $extend = new Extend();
                $extend->template = trim($node->textContent);
                $extend->override = $node->getAttribute('override');
                $extend->with = $node->getAttribute('with');
                $extends[] = $extend;
            }
        }

        return $extends;
    }

    /**
     * Get the first node value
     * @param \DOMDocument|\DOMElement $xml The XML document
     * @param string $nodeName The no name
     * @param string|null $default A default value is none is present
     * @return string|null
     */
    private function getFirstValueOrDefaultFromXmlNode($xml, string $nodeName, $default = null): ?string
    {
        foreach ($xml->getElementsByTagName($nodeName) as $node) {
            /** @var \DOMNode $node */
            if ($node->nodeType === XML_ELEMENT_NODE) {
                return $node->textContent;
            }
        }

        return $default;
    }
}
