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
use Xibo\Entity\Module;
use Xibo\Widget\Definition\Asset;
use Xibo\Widget\Definition\Element;
use Xibo\Widget\Definition\ElementGroup;
use Xibo\Widget\Definition\Extend;
use Xibo\Widget\Definition\LegacyType;
use Xibo\Widget\Definition\PlayerCompatibility;
use Xibo\Widget\Definition\Property;
use Xibo\Widget\Definition\PropertyGroup;
use Xibo\Widget\Definition\Rule;
use Xibo\Widget\Definition\Stencil;

/**
 * A trait to help with parsing modules from XML
 */
trait ModuleXmlTrait
{
    /**
     * @var array cache of already loaded assets - id => asset
     */
    private $assetCache = [];

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
                    $stencil->hbs = trim($childNode->textContent);
                } else if ($childNode->nodeName === 'head') {
                    $stencil->head = trim($childNode->textContent);
                } else if ($childNode->nodeName === 'style') {
                    $stencil->style = trim($childNode->textContent);
                } else if ($childNode->nodeName === 'elements') {
                    $stencil->elements = $this->parseElements($childNode->childNodes);
                } else if ($childNode->nodeName === 'width') {
                    $stencil->width = doubleval($childNode->textContent);
                } else if ($childNode->nodeName === 'height') {
                    $stencil->height = doubleval($childNode->textContent);
                } else if ($childNode->nodeName === 'gapBetweenHbs') {
                    $stencil->gapBetweenHbs = doubleval($childNode->textContent);
                } else if ($childNode->nodeName === 'elementGroups') {
                    $stencil->elementGroups = $this->parseElementGroups($childNode->childNodes);
                }
            }

            if ($stencil->twig !== null
                || $stencil->hbs !== null
                || $stencil->head !== null
                || $stencil->style !== null
            ) {
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
                $property->parseTranslations = $node->getAttribute('parseTranslations') === 'true';
                $property->saveDefault = $node->getAttribute('saveDefault') === 'true';
                $property->sendToElements = $node->getAttribute('sendToElements') === 'true';
                $property->title = __($this->getFirstValueOrDefaultFromXmlNode($node, 'title'));
                $property->helpText = __($this->getFirstValueOrDefaultFromXmlNode($node, 'helpText'));
                $property->dependsOn = $this->getFirstValueOrDefaultFromXmlNode($node, 'dependsOn');

                // How should we default includeInXlf?
                if ($module?->renderAs === 'native') {
                    // Include by default
                    $property->includeInXlf = $node->getAttribute('includeInXlf') !== 'false';
                } else {
                    // Exclude by default
                    $property->includeInXlf = $node->getAttribute('includeInXlf') === 'true';
                }

                // Default value
                $defaultValue = $this->getFirstValueOrDefaultFromXmlNode($node, 'default');

                // Is this a variable?
                $defaultValues[$property->id] = $this->decorateWithSettings($module, $defaultValue);

                // Validation (rule) conditions
                $validationNodes = $node->getElementsByTagName('rule');
                if (count($validationNodes) > 0) {
                    // We have a rule
                    $ruleNode = $validationNodes->item(0);
                    if ($ruleNode->nodeType === XML_ELEMENT_NODE) {
                        /** @var \DOMElement $ruleNode */
                        $rule = new Rule();
                        $rule->onSave = ($ruleNode->getAttribute('onSave') ?: 'true') === 'true';
                        $rule->onStatus = ($ruleNode->getAttribute('onStatus') ?: 'true') === 'true';

                        // Get tests
                        foreach ($ruleNode->childNodes as $testNode) {
                            if ($testNode->nodeType === XML_ELEMENT_NODE) {
                                /** @var \DOMElement $testNode */
                                $conditions = [];
                                foreach ($testNode->getElementsByTagName('condition') as $condNode) {
                                    if ($condNode instanceof \DOMElement) {
                                        $conditions[] = [
                                            'field' => $condNode->getAttribute('field'),
                                            'type' => $condNode->getAttribute('type'),
                                            'value' => $this->decorateWithSettings($module, trim($condNode->textContent)),
                                        ];
                                    }
                                }

                                $rule->addRuleTest($property->parseTest(
                                    $testNode->getAttribute('type'),
                                    $testNode->getAttribute('message'),
                                    $conditions,
                                ));
                            }
                        }

                        $property->validation = $rule;
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
                                trim($optionNode->textContent),
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
                                        'value' => $this->decorateWithSettings($module, trim($condNode->textContent)),
                                    ];
                                }
                            }

                            $property->addVisibilityTest(
                                $testNode->getAttribute('type'),
                                $testNode->getAttribute('message'),
                                $conditions,
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
     * Take a value and decorate it with any module/global settings
     * @param Module|null $module
     * @param string|null $value
     * @return string|null
     */
    private function decorateWithSettings(?Module $module, ?string $value): ?string
    {
        // If we're not empty, then try and do any variable substitutions
        if (!empty($value)) {
            if ($module !== null
                && Str::startsWith($value, '%')
                && Str::endsWith($value, '%')
            ) {
                $value = $module->getSetting(str_replace('%', '', $value));
            } else if (Str::startsWith($value, '#')
                && Str::endsWith($value, '#')
            ) {
                $value = $this->getConfig()->getSetting(str_replace('#', '', $value));
            }
        }
        return $value;
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
                $element->elementGroupId = $elementNode->getAttribute('elementGroupId');
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
                            foreach ($childNode->childNodes as $defaultPropertyNode) {
                                if ($defaultPropertyNode instanceof \DOMElement) {
                                    $element->properties[] = [
                                        'id' => $defaultPropertyNode->getAttribute('id'),
                                        'value' => trim($defaultPropertyNode->textContent)
                                    ];
                                }
                            }
                        }
                    }
                }
                $elements[] = $element;
            }
        }

        return $elements;
    }

    /**
     * @param \DOMNodeList $elementGroupsNodes
     * @return \Xibo\Widget\Definition\Property[]
     */
    private function parseElementGroups (\DOMNodeList $elementGroupsNodes): array
    {
        $elementGroups = [];
        foreach ($elementGroupsNodes as $elementGroupsNode) {
            /** @var \DOMNode $elementNode */
            if ($elementGroupsNode instanceof \DOMElement) {
                $elementGroup = new ElementGroup();
                $elementGroup->id = $elementGroupsNode->getAttribute('id');
                foreach ($elementGroupsNode->childNodes as $childNode) {
                    if ($childNode instanceof \DOMElement) {
                        if ($childNode->nodeName === 'top') {
                            $elementGroup->top = doubleval($childNode->textContent);
                        } else if ($childNode->nodeName === 'left') {
                            $elementGroup->left = doubleval($childNode->textContent);
                        } else if ($childNode->nodeName === 'width') {
                            $elementGroup->width = doubleval($childNode->textContent);
                        } else if ($childNode->nodeName === 'height') {
                            $elementGroup->height = doubleval($childNode->textContent);
                        } else if ($childNode->nodeName === 'layer') {
                            $elementGroup->layer = intval($childNode->textContent);
                        } else if ($childNode->nodeName === 'title') {
                            $elementGroup->title = $childNode->textContent;
                        } else if ($childNode->nodeName === 'slot') {
                            $elementGroup->slot = intval($childNode->textContent);
                        } else if ($childNode->nodeName === 'pinSlot') {
                            $elementGroup->pinSlot = boolval($childNode->textContent);
                        }
                    }
                }
                $elementGroups[] = $elementGroup;
            }
        }

        return $elementGroups;
    }

    /**
     * @param \DOMNodeList $legacyTypeNodes
     * @return \Xibo\Widget\Definition\LegacyType[]
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
                $assetId = $node->getAttribute('id');

                if (!array_key_exists($assetId, $this->assetCache)) {
                    $asset = new Asset();
                    $asset->id = $assetId;
                    $asset->alias = $node->getAttribute('alias');
                    $asset->path = $node->getAttribute('path');
                    $asset->mimeType = $node->getAttribute('mimeType');
                    $asset->type = $node->getAttribute('type');
                    $asset->cmsOnly = $node->getAttribute('cmsOnly') === 'true';
                    $asset->autoInclude = $node->getAttribute('autoInclude') !== 'false';
                    $asset->assetNo = count($this->assetCache) + 1;
                    $this->assetCache[$assetId] = $asset;
                }

                $assets[] = $this->assetCache[$assetId];
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
                $extend->escapeHtml = $node->getAttribute('escapeHtml') !== 'false';
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
