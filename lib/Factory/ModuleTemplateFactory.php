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
     * @param int $id
     * @return \Xibo\Entity\ModuleTemplate
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getUserTemplateById(int $id): ModuleTemplate
    {
        $templates = $this->loadUserTemplates(null, ['id' => $id]);
        if (count($templates) !== 1) {
            throw new NotFoundException(sprintf(__('Template not found for %s'), $id));
        }

        return $templates[0];
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
     * @param string $type
     * @param string $dataType
     * @return ModuleTemplate[]
     */
    public function getByTypeAndDataType(string $type, string $dataType, bool $includeUserTemplates = true): array
    {
        $templates = [];
        foreach ($this->load($includeUserTemplates) as $template) {
            if ($template->dataType === $dataType && $template->type === $type) {
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
     * @param string|null $ownership
     * @param bool $includeUserTemplates
     * @return ModuleTemplate[]
     */
    public function getAll(?string $ownership = null, bool $includeUserTemplates = true): array
    {
        $templates = $this->load($includeUserTemplates);

        if ($ownership === null) {
            return $templates;
        } else {
            $ownedBy = [];
            foreach ($templates as $template) {
                if ($ownership === $template->ownership) {
                    $ownedBy[] = $template;
                }
            }
            return $ownedBy;
        }
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
     * @param bool $includeUserTemplates
     * @return ModuleTemplate[]
     */
    private function load(bool $includeUserTemplates = true): array
    {
        if ($this->templates === null) {
            $this->getLog()->debug('load: Loading templates');

            $this->templates = array_merge(
                $this->loadFolder(
                    PROJECT_ROOT . '/modules/templates/*.xml',
                    'system',
                ),
                $this->loadFolder(
                    PROJECT_ROOT . '/custom/modules/templates/*.xml',
                    'custom'
                ),
            );

            if ($includeUserTemplates) {
                $this->templates = array_merge(
                    $this->templates,
                    $this->loadUserTemplates()
                );
            }
        }

        return $this->templates;
    }

    /**
     * Load templates
     * @return \Xibo\Entity\ModuleTemplate[]
     */
    private function loadFolder(string $folder, string $ownership): array
    {
        $this->getLog()->debug('loadFolder: Loading templates from ' . $folder);
        $templates = [];

        foreach (glob($folder) as $file) {
            // Create our module entity from this file
            try {
                $templates = array_merge($templates, $this->createMultiFromXml($file, $ownership));
            } catch (\Exception $exception) {
                $this->getLog()->error('Unable to create template from '
                    . basename($file) . ', skipping. e = ' . $exception->getMessage());
            }
        }

        return $templates;
    }

    /**
     * Load user templates from the database.
     * @return ModuleTemplate[]
     */
    public function loadUserTemplates($sortOrder = [], $filterBy = []): array
    {
        $this->getLog()->debug('load: Loading user templates');

        if (empty($sortOrder)) {
            $sortOrder = ['id'];
        }

        $templates = [];
        $params = [];

        $filter = $this->getSanitizer($filterBy);

        $select = 'SELECT *,
                (SELECT GROUP_CONCAT(DISTINCT `group`.group)
                          FROM `permission`
                            INNER JOIN `permissionentity`
                            ON `permissionentity`.entityId = permission.entityId
                            INNER JOIN `group`
                            ON `group`.groupId = `permission`.groupId
                         WHERE entity = :permissionEntityGroups
                            AND objectId = `module_templates`.id
                            AND view = 1
                ) AS groupsWithPermissions';

        $body = ' FROM `module_templates`
                WHERE 1 = 1 ';

        if ($filter->getInt('id') !== null) {
            $body .= ' AND `id` = :id ';
            $params['id'] = $filter->getInt('id');
        }

        if (!empty($filter->getString('templateId'))) {
            $body .= ' AND `templateId` LIKE :templateId ';
            $params['templateId'] = '%' . $filter->getString('templateId') . '%';
        }

        if (!empty($filter->getString('dataType'))) {
            $body .= ' AND `dataType` = :dataType ';
            $params['dataType'] = $filter->getString('dataType') ;
        }

        $params['permissionEntityGroups'] = 'Xibo\\Entity\\ModuleTemplate';

        $this->viewPermissionSql(
            'Xibo\Entity\ModuleTemplate',
            $body,
            $params,
            'module_templates.id',
            'module_templates.ownerId',
            $filterBy,
        );

        $order = '';
        if (is_array($sortOrder) && !empty($sortOrder)) {
            $order .= ' ORDER BY ' . implode(',', $sortOrder);
        }

        // Paging
        $limit = '';
        if ($filterBy !== null && $filter->getInt('start') !== null && $filter->getInt('length') !== null) {
            $limit .= ' LIMIT ' .
                $filter->getInt('start', ['default' => 0]) . ', ' .
                $filter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order. $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $template = $this->createUserTemplate($row['xml']);
            $template->id = intval($row['id']);
            $template->templateId = $row['templateId'];
            $template->dataType = $row['dataType'];
            $template->isEnabled = $row['enabled'] == 1;
            $template->ownerId = intval($row['ownerId']);
            $template->groupsWithPermissions = $row['groupsWithPermissions'];
            $templates[] = $template;
        }

        // Paging
        if (!empty($limit) && count($templates) > 0) {
            unset($params['permissionEntityGroups']);
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $templates;
    }

    /**
     * Create a user template from an XML string
     * @param string $xmlString
     * @return ModuleTemplate
     */
    public function createUserTemplate(string $xmlString): ModuleTemplate
    {
        $xml = new \DOMDocument();
        $xml->loadXML($xmlString);

        $template = $this->createFromXml($xml->documentElement, 'user', 'database');
        $template->setXml($xmlString);
        $template->setDocument($xml);
        return $template;
    }

    /**
     * Create multiple templates from XML
     * @param string $file
     * @param string $ownership
     * @return ModuleTemplate[]
     */
    private function createMultiFromXml(string $file, string $ownership): array
    {
        $templates = [];

        $xml = new \DOMDocument();
        $xml->load($file);

        foreach ($xml->getElementsByTagName('templates') as $node) {
            if ($node instanceof \DOMElement) {
                $this->getLog()->debug('createMultiFromXml: there are ' . count($node->childNodes)
                    . ' templates in ' . $file);
                foreach ($node->childNodes as $childNode) {
                    if ($childNode instanceof \DOMElement) {
                        $templates[] = $this->createFromXml($childNode, $ownership, $file);
                    }
                }
            }
        }

        return $templates;
    }

    /**
     * @param \DOMElement $xml
     * @param string $ownership
     * @param string $file
     * @return \Xibo\Entity\ModuleTemplate
     */
    private function createFromXml(\DOMElement $xml, string $ownership, string $file): ModuleTemplate
    {
        // TODO: cache this into Stash
        $template = new ModuleTemplate($this->getStore(), $this->getLog(), $this->getDispatcher(), $this, $file);
        $template->ownership = $ownership;
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
        $template->onTemplateVisible = $this->getFirstValueOrDefaultFromXmlNode($xml, 'onTemplateVisible');
        $template->onElementParseData = $this->getFirstValueOrDefaultFromXmlNode($xml, 'onElementParseData');
        $template->showIn = $this->getFirstValueOrDefaultFromXmlNode($xml, 'showIn') ?? 'both';

        if (!empty($template->onTemplateRender)) {
            $template->onTemplateRender = trim($template->onTemplateRender);
        }

        if (!empty($template->onTemplateVisible)) {
            $template->onTemplateVisible = trim($template->onTemplateVisible);
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

    /**
     * Parse properties json into xml node.
     *
     * @param string $properties
     * @return \DOMDocument
     * @throws \DOMException
     */
    public function parseJsonPropertiesToXml(string $properties): \DOMDocument
    {
        $newPropertiesXml = new \DOMDocument();
        $newPropertiesNode = $newPropertiesXml->createElement('properties');
        $attributes = [
            'id',
            'type',
            'variant',
            'format',
            'mode',
            'target',
            'propertyGroupId',
            'allowLibraryRefs',
            'allowAssetRefs',
            'parseTranslations',
            'includeInXlf'
        ];

        $commonNodes = [
            'title',
            'helpText',
            'default',
            'dependsOn',
            'customPopOver'
        ];

        $newProperties = json_decode($properties, true);
        foreach ($newProperties as $property) {
            // create property node
            $propertyNode = $newPropertiesXml->createElement('property');

            // go through possible attributes on the property node.
            foreach ($attributes as $attribute) {
                if (!empty($property[$attribute])) {
                    $propertyNode->setAttribute($attribute, $property[$attribute]);
                }
            }

            // go through common nodes on property add them if not empty
            foreach ($commonNodes as $commonNode) {
                if (!empty($property[$commonNode])) {
                    $propertyNode->appendChild($newPropertiesXml->createElement($commonNode, $property[$commonNode]));
                }
            }

            // do we have options?
            if (!empty($property['options'])) {
                $options = $property['options'];
                if (!is_array($options)) {
                    $options = json_decode($options, true);
                }

                $optionsNode = $newPropertiesXml->createElement('options');
                foreach ($options as $option) {
                    $optionNode = $newPropertiesXml->createElement('option', $option['title']);
                    $optionNode->setAttribute('name', $option['name']);
                    if (!empty($option['set'])) {
                        $optionNode->setAttribute('set', $option['set']);
                    }
                    if (!empty($option['image'])) {
                        $optionNode->setAttribute('image', $option['image']);
                    }
                    $optionsNode->appendChild($optionNode);
                }
                $propertyNode->appendChild($optionsNode);
            }

            // do we have visibility?
            if (!empty($property['visibility'])) {
                $visibility = $property['visibility'];
                if (!is_array($visibility)) {
                    $visibility = json_decode($visibility, true);
                }

                $visibilityNode = $newPropertiesXml->createElement('visibility');

                foreach ($visibility as $testElement) {
                    $testNode = $newPropertiesXml->createElement('test');
                    $testNode->setAttribute('type', $testElement['type']);
                    $testNode->setAttribute('message', $testElement['message']);
                    foreach ($testElement['conditions'] as $condition) {
                        $conditionNode = $newPropertiesXml->createElement('condition', $condition['value']);
                        $conditionNode->setAttribute('field', $condition['field']);
                        $conditionNode->setAttribute('type', $condition['type']);
                        $testNode->appendChild($conditionNode);
                    }
                    $visibilityNode->appendChild($testNode);
                }
                $propertyNode->appendChild($visibilityNode);
            }

            // do we have validation rules?
            if (!empty($property['validation'])) {
                $validation = $property['validation'];
                if (!is_array($validation)) {
                    $validation = json_decode($property['validation'], true);
                }

                // xml uses rule node for this.
                $ruleNode = $newPropertiesXml->createElement('rule');

                // attributes on rule node;
                $ruleNode->setAttribute('onSave', $validation['onSave'] ? 'true' : 'false');
                $ruleNode->setAttribute('onStatus', $validation['onStatus'] ? 'true' : 'false');

                // validation property has an array on tests in it
                foreach ($validation['tests'] as $validationTest) {
                    $ruleTestNode = $newPropertiesXml->createElement('test');
                    $ruleTestNode->setAttribute('type', $validationTest['type']);
                    $ruleTestNode->setAttribute('message', $validationTest['message']);

                    foreach ($validationTest['conditions'] as $condition) {
                        $conditionNode = $newPropertiesXml->createElement('condition', $condition['value']);
                        $conditionNode->setAttribute('field', $condition['field']);
                        $conditionNode->setAttribute('type', $condition['type']);
                        $ruleTestNode->appendChild($conditionNode);
                    }
                    $ruleNode->appendChild($ruleTestNode);
                }
                $propertyNode->appendChild($ruleNode);
            }

            // do we have player compatibility?
            if (!empty($property['playerCompatibility'])) {
                $playerCompat = $property['playerCompatibility'];
                if (!is_array($playerCompat)) {
                    $playerCompat = json_decode($property['playerCompatibility'], true);
                }

                $playerCompatibilityNode = $newPropertiesXml->createElement('playerCompatibility');
                foreach ($playerCompat as $player => $value) {
                    $playerCompatibilityNode->setAttribute($player, $value);
                }

                $propertyNode->appendChild($playerCompatibilityNode);
            }

            $newPropertiesNode->appendChild($propertyNode);
        }

        $newPropertiesXml->appendChild($newPropertiesNode);

        return $newPropertiesXml;
    }
}
