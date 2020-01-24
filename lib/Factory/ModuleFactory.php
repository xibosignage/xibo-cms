<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (ModuleFactory.php) is part of Xibo.
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


use Xibo\Entity\Media;
use Xibo\Entity\Module;
use Xibo\Entity\Region;
use Xibo\Entity\User;
use Xibo\Entity\Widget;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\ModuleServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Widget\ModuleWidget;

/**
 * Class ModuleFactory
 * @package Xibo\Factory
 */
class ModuleFactory extends BaseFactory
{
    /**
     * @var ModuleServiceInterface
     */
    private $moduleService;

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

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     * @param ModuleServiceInterface $moduleService
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
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $moduleService, $widgetFactory, $regionFactory, $playlistFactory, $mediaFactory, $dataSetFactory, $dataSetColumnFactory, $transitionFactory, $displayFactory, $commandFactory, $scheduleFactory, $permissionFactory, $userGroupFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);

        $this->moduleService = $moduleService;
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
    }

    /**
     * @return Module
     */
    public function createEmpty()
    {
        return new Module($this->getStore(), $this->getLog());
    }

    /**
     * Create a Module
     * @param string $type
     * @return \Xibo\Widget\ModuleWidget
     * @throws NotFoundException
     */
    public function create($type)
    {
        $modules = $this->query(['enabled DESC'], array('type' => $type));

        $this->getLog()->debug('Creating %s out of possible %s', $type, json_encode(array_map(function($element) { return $element->class; }, $modules)));

        if (count($modules) <= 0)
            throw new NotFoundException(sprintf(__('Unknown type %s'), $type));

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
            $this->playlistFactory
        );
    }

    /**
     * Create a Module
     * @param string $class
     * @return \Xibo\Widget\ModuleWidget
     * @throws NotFoundException
     */
    public function createByClass($class)
    {
        $modules = $this->query(['enabled DESC'], array('class' => $class));

        $this->getLog()->debug('Creating %s out of possible %s', $class, json_encode(array_map(function($element) { return $element->class; }, $modules)));

        if (count($modules) <= 0)
            throw new NotFoundException(sprintf(__('Unknown class %s'), $class));

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
            $this->playlistFactory
        );
    }

    /**
     * Create a Module
     * @param string $className
     * @return \Xibo\Widget\ModuleWidget
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
            $this->playlistFactory
        );
    }

    /**
     * Create a Module
     * @param string $moduleId
     * @return \Xibo\Widget\ModuleWidget
     * @throws NotFoundException
     */
    public function createById($moduleId)
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
            $this->playlistFactory
        );
    }

    /**
     * Create a Module with a Media Record
     * @param Media $media
     * @return \Xibo\Widget\ModuleWidget
     * @throws NotFoundException
     */
    public function createWithMedia($media)
    {
        $modules = $this->query(null, array('type' => $media->mediaType));

        if (count($modules) <= 0)
            throw new NotFoundException(sprintf(__('Unknown type %s'), $media->mediaType));

        // Create a widget
        $widget = $this->widgetFactory->createEmpty();
        $widget->assignMedia($media->mediaId);

        // Create a module
        /* @var \Xibo\Widget\ModuleWidget $object */
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
            $this->playlistFactory
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
     * @return \Xibo\Widget\ModuleWidget
     * @throws \Xibo\Exception\NotFoundException
     * @throws \Xibo\Exception\InvalidArgumentException
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
        }
        else {
            // Load the widget
            $module->setWidget($this->widgetFactory->loadByWidgetId($widgetId));
        }

        return $module;
    }

    /**
     * Create a Module using a Widget
     * @param Widget $widget
     * @param Region|null $region
     * @return \Xibo\Widget\ModuleWidget
     * @throws NotFoundException
     */
    public function createWithWidget($widget, $region = null)
    {
        $module = $this->create($widget->type);
        $module->setWidget($widget);

        if ($region != null)
            $module->setRegion($region);

        return $module;
    }

    /**
     * @param string $key
     * @return array
     */
    public function get($key = 'type')
    {
        $modules = $this->query();

        if ($key != null && $key != '') {

            $keyed = array();
            foreach ($modules as $module) {
                /* @var Module $module */
                $keyed[$module->type] = $module;
            }

            return $keyed;
        }

        return $modules;
    }

    public function getAssignableModules()
    {
        return $this->query(null, array('assignable' => 1, 'enabled' => 1));
    }

    /**
     * Get module by Id
     * @param int $moduleId
     * @return Module
     * @throws NotFoundException
     */
    public function getById($moduleId)
    {
        $modules = $this->query(null, array('moduleId' => $moduleId));

        if (count($modules) <= 0)
            throw new NotFoundException();

        return $modules[0];
    }

    /**
     * Get module by InstallName
     * @param string $installName
     * @return Module
     * @throws NotFoundException
     */
    public function getByInstallName($installName)
    {
        $modules = $this->query(null, ['installName' => $installName]);

        if (count($modules) <= 0)
            throw new NotFoundException();

        return $modules[0];
    }


    /**
     * Get module by name
     * @param string $name
     * @return ModuleWidget
     * @throws NotFoundException
     */
    public function getByType($name)
    {
        $modules = $this->query(['enabled DESC'], ['name' => $name]);

        if (count($modules) <= 0)
            throw new NotFoundException(sprintf(__('Module type %s does not match any enabled Module'), $name));

        return $modules[0];
    }

    /**
     * Get Enabled
     * @return Module[]
     */
    public function getEnabled()
    {
        return $this->query(null, ['enabled' => 1]);
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

        if (count($modules) <= 0)
            throw new NotFoundException(sprintf(__('Extension %s does not match any enabled Module'), $extension));

        return $modules[0];
    }

    /**
     * Get Valid Extensions
     * @param array[Optional] $filterBy
     * @return array[string]
     */
    public function getValidExtensions($filterBy = [])
    {
        $modules = $this->query(null, $filterBy);
        $extensions = array();

        foreach($modules as $module) {
            /* @var Module $module */
            if ($module->validExtensions != '') {
                foreach (explode(',', $module->validExtensions) as $extension) {
                    $extensions[] = $extension;
                }
            }
        }

        return $extensions;
    }

    /**
     * Get View Paths
     * @return array[string]
     */
    public function getViewPaths()
    {
        $modules = $this->query();
        $paths = array_map(function ($module) {
            /* @var Module $module */
            return str_replace_first('..', PROJECT_ROOT, $module->viewPath);
        }, $modules);

        $paths = array_unique($paths);

        return $paths;
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return ModuleWidget[]
     */
    public function query($sortOrder = null, $filterBy = array())
    {
        if ($sortOrder == null)
            $sortOrder = array('Module');

        $entries = array();

        $dbh = $this->getStore()->getConnection();

        $params = array();

        $select = '
            SELECT ModuleID,
               Module,
               Name,
               Enabled,
               Description,
               render_as,
               settings,
               RegionSpecific,
               ValidExtensions,
               PreviewEnabled,
               assignable,
               SchemaVersion,
                viewPath,
               `class`,
                `defaultDuration`,
                IFNULL(`installName`, `module`) AS installName
            ';

        $body = '
                  FROM `module`
                 WHERE 1 = 1
            ';

        if ($this->getSanitizer()->getInt('moduleId', $filterBy) !== null) {
            $params['moduleId'] = $this->getSanitizer()->getInt('moduleId', $filterBy);
            $body .= ' AND `ModuleID` = :moduleId ';
        }

        if ($this->getSanitizer()->getString('name', $filterBy) != '') {
            $params['name'] = $this->getSanitizer()->getString('name', $filterBy);
            $body .= ' AND `name` = :name ';
        }

        if ($this->getSanitizer()->getString('installName', $filterBy) != null) {
            $params['installName'] = $this->getSanitizer()->getString('installName', $filterBy);
            $body .= ' AND `installName` = :installName ';
        }

        if ($this->getSanitizer()->getString('type', $filterBy) != '') {
            $params['type'] = $this->getSanitizer()->getString('type', $filterBy);
            $body .= ' AND `module` = :type ';
        }

        if ($this->getSanitizer()->getString('class', $filterBy) != '') {
            $params['class'] = $this->getSanitizer()->getString('class', $filterBy);
            $body .= ' AND `class` = :class ';
        }

        if ($this->getSanitizer()->getString('extension', $filterBy) != '') {
            $params['extension'] = '%' . $this->getSanitizer()->getString('extension', $filterBy) . '%';
            $body .= ' AND `ValidExtensions` LIKE :extension ';
        }

        if ($this->getSanitizer()->getInt('assignable', -1, $filterBy) != -1) {
            $body .= " AND `assignable` = :assignable ";
            $params['assignable'] = $this->getSanitizer()->getInt('assignable', $filterBy);
        }

        if ($this->getSanitizer()->getInt('enabled', -1, $filterBy) != -1) {
            $body .= " AND `enabled` = :enabled ";
            $params['enabled'] = $this->getSanitizer()->getInt('enabled', $filterBy);
        }

        if ($this->getSanitizer()->getInt('regionSpecific', -1, $filterBy) != -1) {
            $body .= " AND `regionSpecific` = :regionSpecific ";
            $params['regionSpecific'] = $this->getSanitizer()->getInt('regionSpecific', $filterBy);
        }

        if ($this->getSanitizer()->getInt('notPlayerSoftware', $filterBy) == 1) {
            $body .= ' AND `module` <> \'playersoftware\' ';
        }

        if ($this->getSanitizer()->getInt('notSavedReport', $filterBy) == 1) {
            $body .= ' AND `module` <> \'savedreport\' ';
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $order . $limit;

        //

        $sth = $dbh->prepare($sql);
        $sth->execute($params);

        foreach ($sth->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $module = $this->createEmpty();
            $module->moduleId = $this->getSanitizer()->int($row['ModuleID']);
            $module->name = __($this->getSanitizer()->string($row['Name']));
            $module->description = $this->getSanitizer()->string($row['Description']);
            $module->validExtensions = $this->getSanitizer()->string($row['ValidExtensions']);
            $module->renderAs = $this->getSanitizer()->string($row['render_as']);
            $module->enabled = $this->getSanitizer()->int($row['Enabled']);
            $module->regionSpecific = $this->getSanitizer()->int($row['RegionSpecific']);
            $module->previewEnabled = $this->getSanitizer()->int($row['PreviewEnabled']);
            $module->assignable = $this->getSanitizer()->int($row['assignable']);
            $module->schemaVersion = $this->getSanitizer()->int($row['SchemaVersion']);

            // Identification
            $module->type = strtolower($this->getSanitizer()->string($row['Module']));

            $module->class = $this->getSanitizer()->string($row['class']);
            $module->viewPath = $this->getSanitizer()->string($row['viewPath']);
            $module->defaultDuration = $this->getSanitizer()->int($row['defaultDuration']);
            $module->installName = $this->getSanitizer()->string($row['installName']);

            $settings = $row['settings'];
            $module->settings = ($settings == '') ? array() : json_decode($settings, true);

            $entries[] = $module;
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}