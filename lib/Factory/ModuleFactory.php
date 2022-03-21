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


namespace Xibo\Factory;

use Slim\Views\Twig;
use Xibo\Entity\Media;
use Xibo\Entity\Module;
use Xibo\Entity\Region;
use Xibo\Entity\User;
use Xibo\Entity\Widget;
use Xibo\Helper\HttpCacheProvider;
use Xibo\Service\ModuleServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Widget\Definition\PlayerCompatibility;
use Xibo\Widget\Definition\Property;
use Xibo\Widget\Definition\Stencil;
use Xibo\Widget\ModuleWidget;

/**
 * Class ModuleFactory
 * @package Xibo\Factory
 */
class ModuleFactory extends BaseFactory
{
    /** @var Module[] all modules */
    private $modules = null;

    /**
     * @var WidgetFactory
     */
    private $widgetFactory;

    /**
     * @var RegionFactory
     */
    private $regionFactory;

    /**
     * @var PlaylistFactory
     */
    private $playlistFactory;

    /**
     * @var MediaFactory
     */
    protected $mediaFactory;

    /**
     * @var DataSetFactory
     */
    protected $dataSetFactory;

    /**
     * @var DataSetColumnFactory
     */
    protected $dataSetColumnFactory;

    /**
     * @var TransitionFactory
     */
    protected $transitionFactory;

    /**
     * @var DisplayFactory
     */
    protected $displayFactory;

    /**
     * @var CommandFactory
     */
    protected $commandFactory;

    /** @var  ScheduleFactory */
    protected $scheduleFactory;

    /** @var  PermissionFactory */
    protected $permissionFactory;

    /** @var  UserGroupFactory */
    protected $userGroupFactory;

    /** @var MenuBoardFactory */
    protected $menuBoardFactory;

    /** @var MenuBoardCategoryFactory */
    protected $menuBoardCategoryFactory;

    /** @var NotificationFactory */
    protected $notificationFactory;

    /** @var Twig */
    protected $view;

    /** @var HttpCacheProvider */
    private $cacheProvider;

    /**
     * Construct a factory
     * @param User $user
     * @param UserFactory $userFactory
     * @param WidgetFactory $widgetFactory
     * @param RegionFactory $regionFactory
     * @param PlaylistFactory $playlistFactory
     * @param MediaFactory $mediaFactory
     * @param DataSetFactory $dataSetFactory
     * @param DataSetColumnFactory $dataSetColumnFactory
     * @param TransitionFactory $transitionFactory
     * @param DisplayFactory $displayFactory
     * @param CommandFactory $commandFactory
     * @param ScheduleFactory $scheduleFactory
     * @param PermissionFactory $permissionFactory
     * @param UserGroupFactory $userGroupFactory
     * @param MenuBoardFactory $menuBoardFactory
     * @param MenuBoardCategoryFactory $menuBoardCategoryFactory
     * @param NotificationFactory $notificationFactory
     * @param Twig $view
     * @param HttpCacheProvider $cacheProvider
     */
    public function __construct(
        $user,
        $userFactory,
        $widgetFactory,
        $regionFactory,
        $playlistFactory,
        $mediaFactory,
        $dataSetFactory,
        $dataSetColumnFactory,
        $transitionFactory,
        $displayFactory,
        $commandFactory,
        $scheduleFactory,
        $permissionFactory,
        $userGroupFactory,
        $menuBoardFactory,
        $menuBoardCategoryFactory,
        $notificationFactory,
        $view,
        HttpCacheProvider $cacheProvider
    ) {
        $this->setAclDependencies($user, $userFactory);

        $this->widgetFactory = $widgetFactory;
        $this->regionFactory = $regionFactory;
        $this->playlistFactory = $playlistFactory;
        $this->mediaFactory = $mediaFactory;
        $this->dataSetFactory = $dataSetFactory;
        $this->dataSetColumnFactory = $dataSetColumnFactory;
        $this->transitionFactory = $transitionFactory;
        $this->displayFactory = $displayFactory;
        $this->commandFactory = $commandFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->permissionFactory = $permissionFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->menuBoardFactory = $menuBoardFactory;
        $this->menuBoardCategoryFactory = $menuBoardCategoryFactory;
        $this->notificationFactory = $notificationFactory;
        $this->view = $view;
        $this->cacheProvider = $cacheProvider;
    }

    /**
     * @return Module
     */
    public function createEmpty(): Module
    {
        return new Module($this->getStore(), $this->getLog());
    }

    /**
     * Create a Module
     * @param string $type
     * @return ModuleWidget
     * @throws NotFoundException
     */
    public function create($type)
    {
        $modules = $this->query(['enabled DESC'], array('type' => $type));

        $this->getLog()->debug(sprintf(
            'Creating %s out of possible %s',
            $type,
            json_encode(
                array_map(
                    function ($element) {
                        return $element->class;
                    },
                    $modules
                )
            )
        ));

        if (count($modules) <= 0) {
            throw new NotFoundException(sprintf(__('Unknown type %s'), $type));
        }

        // Create a module
        return $this->moduleService->get(
            $modules[0],
            $this,
            $this->mediaFactory,
            $this->dataSetFactory,
            $this->dataSetColumnFactory,
            $this->transitionFactory,
            $this->displayFactory,
            $this->commandFactory,
            $this->scheduleFactory,
            $this->permissionFactory,
            $this->userGroupFactory,
            $this->playlistFactory,
            $this->menuBoardFactory,
            $this->menuBoardCategoryFactory,
            $this->notificationFactory,
            $this->view,
            $this->cacheProvider
        );
    }

    /**
     * Create a Module
     * @param string $class
     * @return ModuleWidget
     * @throws NotFoundException
     */
    public function createByClass($class)
    {
        $modules = $this->query(['enabled DESC'], array('class' => $class));

        $this->getLog()->debug(sprintf(
            'Creating %s out of possible %s',
            $class,
            json_encode(
                array_map(
                    function ($element) {
                        return $element->class;
                    },
                    $modules
                )
            )
        ));

        if (count($modules) <= 0) {
            throw new NotFoundException(sprintf(__('Unknown class %s'), $class));
        }

        // Create a module
        return $this->moduleService->get(
            $modules[0],
            $this,
            $this->mediaFactory,
            $this->dataSetFactory,
            $this->dataSetColumnFactory,
            $this->transitionFactory,
            $this->displayFactory,
            $this->commandFactory,
            $this->scheduleFactory,
            $this->permissionFactory,
            $this->userGroupFactory,
            $this->playlistFactory,
            $this->menuBoardFactory,
            $this->menuBoardCategoryFactory,
            $this->notificationFactory,
            $this->view,
            $this->cacheProvider
        );
    }

    /**
     * Create a Module
     * @param string $className
     * @return ModuleWidget
     */
    public function createForInstall($className)
    {
        // Create a module
        return $this->moduleService->getByClass(
            $className,
            $this,
            $this->mediaFactory,
            $this->dataSetFactory,
            $this->dataSetColumnFactory,
            $this->transitionFactory,
            $this->displayFactory,
            $this->commandFactory,
            $this->scheduleFactory,
            $this->permissionFactory,
            $this->userGroupFactory,
            $this->playlistFactory,
            $this->menuBoardFactory,
            $this->menuBoardCategoryFactory,
            $this->notificationFactory,
            $this->view,
            $this->cacheProvider
        );
    }

    /**
     * Create a Module
     * @param int $moduleId
     * @return ModuleWidget
     * @throws NotFoundException
     */
    public function createById(int $moduleId)
    {
        return $this->moduleService->get(
            $this->getById($moduleId),
            $this,
            $this->mediaFactory,
            $this->dataSetFactory,
            $this->dataSetColumnFactory,
            $this->transitionFactory,
            $this->displayFactory,
            $this->commandFactory,
            $this->scheduleFactory,
            $this->permissionFactory,
            $this->userGroupFactory,
            $this->playlistFactory,
            $this->menuBoardFactory,
            $this->menuBoardCategoryFactory,
            $this->notificationFactory,
            $this->view,
            $this->cacheProvider
        );
    }

    /**
     * Create a Module with a Media Record
     * @param Media $media
     * @return ModuleWidget
     * @throws NotFoundException
     */
    public function createWithMedia($media)
    {
        $modules = $this->query(null, array('type' => $media->mediaType));

        if (count($modules) <= 0) {
            throw new NotFoundException(sprintf(__('Unknown type %s'), $media->mediaType));
        }

        // Create a widget
        $widget = $this->widgetFactory->createEmpty();
        $widget->assignMedia($media->mediaId);

        // Create a module
        /* @var ModuleWidget $object */
        $module = $modules[0];
        $object = $this->moduleService->get(
            $module,
            $this,
            $this->mediaFactory,
            $this->dataSetFactory,
            $this->dataSetColumnFactory,
            $this->transitionFactory,
            $this->displayFactory,
            $this->commandFactory,
            $this->scheduleFactory,
            $this->permissionFactory,
            $this->userGroupFactory,
            $this->playlistFactory,
            $this->menuBoardFactory,
            $this->menuBoardCategoryFactory,
            $this->notificationFactory,
            $this->view,
            $this->cacheProvider
        );
        $object->setWidget($widget);

        return $object;
    }

    /**
     * Create a Module for a Widget and optionally a playlist/region
     * @param string $type
     * @param int $widgetId
     * @param int $ownerId
     * @param int $playlistId
     * @param int $regionId
     * @return ModuleWidget
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function createForWidget($type, $widgetId = 0, $ownerId = 0, $playlistId = null, $regionId = 0)
    {
        $module = $this->create($type);

        // Do we have a regionId
        if ($regionId != 0) {
            // Load the region and set
            $region = $this->regionFactory->getById($regionId);
            $module->setRegion($region);
        }

        // Do we have a widgetId
        if ($widgetId == 0) {
            // If we don't have a widget we must have a playlist
            if ($playlistId == null) {
                throw new InvalidArgumentException(__('Neither Playlist or Widget provided'), 'playlistId');
            }

            // Create a new widget to use
            $widget = $this->widgetFactory->create($ownerId, $playlistId, $module->getModuleType(), null);
            $module->setWidget($widget);
        } else {
            // Load the widget
            $module->setWidget($this->widgetFactory->loadByWidgetId($widgetId));
        }

        return $module;
    }

    /**
     * Create a Module using a Widget
     * @param Widget $widget
     * @param Region|null $region
     * @return ModuleWidget
     * @throws NotFoundException
     */
    public function createWithWidget($widget, $region = null)
    {
        $module = $this->create($widget->type);
        $module->setWidget($widget);

        if ($region != null) {
            // Are we loading a widget from the drawer?
            $targetRegionId = $widget->getOptionValue('targetRegionId', 0);
            if ($targetRegionId !== 0) {
                // Lookup the targetRegionId and use that
                $module->setRegion($this->regionFactory->getById($targetRegionId));
            } else {
                // Use the source region
                $module->setRegion($region);
            }
        }

        return $module;
    }

    /**
     * @return array
     */
    public function getKeyedArrayOfModules(): array
    {
        $modules = [];
        foreach ($this->load() as $module) {
            $modules[$module->type] = $module;
        }
        return $modules;
    }

    /**
     * @return Module[]
     */
    public function getAssignableModules(): array
    {
        $modules = [];
        foreach ($this->load() as $module) {
            if ($module->enabled === 1 && $module->assignable === 1) {
                $modules[] = $module;
            }
        }
        return $modules;
    }

    /**
     * @return Module[]
     */
    public function getLibraryModules(): array
    {
        $modules = [];
        foreach ($this->load() as $module) {
            if ($module->enabled == 1 && $module->regionSpecific === 0) {
                $modules[] = $module;
            }
        }
        return $modules;
    }

    /**
     * Get module by Id
     * @param string $moduleId
     * @return Module
     * @throws NotFoundException
     */
    public function getById($moduleId): Module
    {
        foreach ($this->load() as $module) {
            if ($module->moduleId === $moduleId) {
                return $module;
            }
        }

        throw new NotFoundException();
    }

    /**
     * Get an array of all modules
     * @return Module[]
     */
    public function getAll(): array
    {
        return $this->load();
    }

    /**
     * Get an array of all enabled modules
     * @return Module[]
     */
    public function getEnabled(): array
    {
        $modules = [];
        foreach ($this->load() as $module) {
            if ($module->enabled == 1) {
                $modules[] = $module;
            }
        }
        return $modules;
    }

    /**
     * Get module by Type
     * this should return the first module enabled by the type specified.
     * @param string $type
     * @return Module
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getByType(string $type): Module
    {
        $modules = $this->load();
        usort($modules, function ($a, $b) {
            /** @var Module $a */
            /** @var Module $b */
            return $a->enabled - $b->enabled;
        });

        foreach ($modules as $module) {
            if ($module->type === $type) {
                return $module;
            }
        }

        throw new NotFoundException();
    }

    /**
     * Get module by extension
     * @param string $extension
     * @return Module
     * @throws NotFoundException
     */
    public function getByExtension($extension)
    {
        $modules = $this->query(['enabled DESC'], array('extension' => $extension));

        if (count($modules) <= 0) {
            throw new NotFoundException(sprintf(__('Extension %s does not match any enabled Module'), $extension));
        }

        return $modules[0];
    }

    /**
     * Get Valid Extensions
     * @param array $filterBy
     * @return string[]
     */
    public function getValidExtensions($filterBy = []): array
    {
        $filterBy = $this->getSanitizer($filterBy);
        $typeFilter = $filterBy->getString('type');
        $extensions = [];
        foreach ($this->load() as $module) {
            if ($typeFilter !== null && $module->type !== $typeFilter) {
                continue;
            }

            if (!empty($module->getSetting('validExtensions'))) {
                foreach (explode(',', $module->getSetting('validExtensions')) as $extension) {
                    $extensions[] = $extension;
                }
            }
        }

        return $extensions;
    }

    /**
     * Load all modules into an array for use throughout this quest
     * @return \Xibo\Entity\Module[]
     */
    private function load(): array
    {
        if ($this->modules === null) {
            // TODO: these are the only fields we require in the settings table
            $sql = '
                SELECT `moduleId`, `enabled`, `previewEnabled`, `defaultDuration`, `settings`
                 FROM `module`
            ';

            $modulesWithSettings = [];
            foreach ($this->getStore()->select($sql, []) as $row) {
                // Make a keyed array of these settings
                $modulesWithSettings[$row['moduleId']] = $this->getSanitizer($row);
            }

            // Load in our file system modules.
            // we consider modules in the module folder, and also custom modules
            $files = array_merge(
                glob(PROJECT_ROOT . '/modules/*.xml'),
                glob(PROJECT_ROOT . '/custom/modules/*.xml')
            );

            foreach ($files as $file) {
                // Create our module entity from this file
                try {
                    $module = $this->createFromXml($file);

                    // Add in any settings we already have
                    if (array_key_exists($module->moduleId, $modulesWithSettings)) {
                        $moduleSettings = $modulesWithSettings[$module->moduleId];
                        $module->isInstalled = true;
                        $module->enabled = $moduleSettings->getInt('enabled', ['default' => 0]);
                        $module->previewEnabled = $moduleSettings->getInt('previewEnabled', ['default' => 0]);
                        $module->defaultDuration = $moduleSettings->getInt('defaultDuration', ['default' => 10]);

                        $settings = $moduleSettings->getString('settings');
                        if ($settings !== null) {
                            $settings = json_decode($settings, true);

                            foreach ($module->settings as $property) {
                                foreach ($settings as $setting) {
                                    if ($setting['id'] === $property->id) {
                                        $property->value = $setting['value'];
                                    }
                                }
                            }
                        }
                    }

                    // Set error state
                    $module->isError = $module->errors !== null && count($module->errors) > 0;

                    // Register
                    $this->modules[] = $module;
                } catch (\Exception $exception) {
                    $this->getLog()->error('Unable to create module from '
                        . basename($file) . ', skipping. e = ' . $exception->getMessage());
                }
            }
        }

        return $this->modules;
    }

    /**
     * Create a module from its XML definition
     * @param string $file the path to the module definition
     * @return \Xibo\Entity\Module
     */
    private function createFromXml(string $file): Module
    {
        // TODO: cache this into Stash
        $xml = new \DOMDocument();
        $xml->load($file);

        $module = new Module($this->getStore(), $this->getLog());
        $module->moduleId = $this->getFirstValueOrDefaultFromXmlNode($xml, 'id');
        $module->name = $this->getFirstValueOrDefaultFromXmlNode($xml, 'name');
        $module->author = $this->getFirstValueOrDefaultFromXmlNode($xml, 'author');
        $module->description = $this->getFirstValueOrDefaultFromXmlNode($xml, 'description');
        $module->class = $this->getFirstValueOrDefaultFromXmlNode($xml, 'class');
        $module->type = $this->getFirstValueOrDefaultFromXmlNode($xml, 'type');
        $module->dataType = $this->getFirstValueOrDefaultFromXmlNode($xml, 'dataType');
        $module->schemaVersion = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'schemaVersion'));
        $module->assignable = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'assignable'));
        $module->regionSpecific = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'regionSpecific'));
        $module->renderAs = $this->getFirstValueOrDefaultFromXmlNode($xml, 'renderAs');
        $module->defaultDuration = intval($this->getFirstValueOrDefaultFromXmlNode($xml, 'defaultDuration'));

        // Default values for remaining expected properties
        $module->isInstalled = false;
        $module->isError = false;
        $module->errors = [];
        $module->enabled = 0;
        $module->previewEnabled = 0;

        // Parse settings/property definitions.
        try {
            $module->settings = $this->parseProperties($xml->getElementsByTagName('settings'));
        } catch (\Exception $e) {
            $module->errors[] = __('Invalid settings');
            $this->getLog()->error('Module ' . $module->moduleId . ' has invalid settings. e: ' .  $e->getMessage());
        }

        try {
            $module->properties = $this->parseProperties($xml->getElementsByTagName('properties'));
        } catch (\Exception $e) {
            $module->errors[] = __('Invalid properties');
            $this->getLog()->error('Module ' . $module->moduleId . ' has invalid properties. e: ' .  $e->getMessage());
        }

        // Parse stencils
        $module->preview = $this->getStencils($xml->getElementsByTagName('preview'))[0] ?? null;
        $module->stencil = $this->getStencils($xml->getElementsByTagName('stencil'))[0] ?? null;

        return $module;
    }

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
                /** @var \DOMNode $childNode */
                if ($childNode->nodeName === 'twig') {
                    $stencil->twig = $childNode->textContent;
                } else if ($childNode->nodeName === 'hbs') {
                    $stencil->hbs = $childNode->textContent;
                } else if ($childNode->nodeName === 'title') {
                    $stencil->title = $childNode->textContent;
                } else if ($childNode->nodeName === 'properties') {
                    $stencil->properties = $this->parseProperties([$childNode]);
                } else if ($childNode->nodeName === 'elements') {
                    $stencil->elements = $this->parseElements([$childNode]);
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
    private function parseProperties($propertyNodes): array
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
                $property->title = $this->getFirstValueOrDefaultFromXmlNode($node, 'title');
                $property->helpText = $this->getFirstValueOrDefaultFromXmlNode($node, 'helpText');
                $defaultValues[$property->id] = $this->getFirstValueOrDefaultFromXmlNode($node, 'default');

                // Options
                $options = $node->getElementsByTagName('options');
                if (count($options) > 0) {
                    foreach ($options->item(0)->childNodes as $optionNode) {
                        if ($optionNode->nodeType === XML_ELEMENT_NODE) {
                            /** @var \DOMElement $optionNode */
                            $property->addOption(
                                $optionNode->getAttribute('name'),
                                $optionNode->textContent
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
                        $property->playerCompatability = $playerCompatibility;
                    }
                }

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
     * @param \DOMNode[]|\DOMNodeList $elementNodes
     * @return \Xibo\Widget\Definition\Property[]
     */
    private function parseElements($elementNodes): array
    {
        $elements = [];
        foreach ($elementNodes as $node) {

        }

        return $elements;
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
