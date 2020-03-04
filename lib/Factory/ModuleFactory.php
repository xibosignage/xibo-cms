<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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


use Illuminate\Support\Str;
use Psr\Container\ContainerInterface;
use Slim\Views\Twig;
use Xibo\Entity\Media;
use Xibo\Entity\Module;
use Xibo\Entity\Region;
use Xibo\Entity\User;
use Xibo\Entity\Widget;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
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

    /** @var Twig */
    protected $view;

    /** @var ContainerInterface */
    protected $container;

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
     * @param Twig $view
     * @param ContainerInterface $container
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $moduleService, $widgetFactory, $regionFactory, $playlistFactory, $mediaFactory, $dataSetFactory, $dataSetColumnFactory, $transitionFactory, $displayFactory, $commandFactory, $scheduleFactory, $permissionFactory, $userGroupFactory, $view, ContainerInterface $container)
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
        $this->view = $view;
        $this->container = $container;
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
            $this->playlistFactory,
            $this->view,
            $this->container
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
            $this->playlistFactory,
            $this->view,
            $this->container
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
            $this->playlistFactory,
            $this->view,
            $this->container
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
            $this->playlistFactory,
            $this->view,
            $this->container
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
            $this->playlistFactory,
            $this->view,
            $this->container
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

    /**
     * @return Module[]
     */
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
     * @return Module
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
            return Str::replaceFirst('..', PROJECT_ROOT, $module->viewPath);
        }, $modules);

        $paths = array_unique($paths);

        return $paths;
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return Module[]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $parsedBody = $this->getSanitizer($filterBy);
        
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

        if ($parsedBody->getInt('moduleId') !== null) {
            $params['moduleId'] = $parsedBody->getInt('moduleId');
            $body .= ' AND `ModuleID` = :moduleId ';
        }

        if ($parsedBody->getString('name') != '') {
            $params['name'] = $parsedBody->getString('name');
            $body .= ' AND `name` = :name ';
        }

        if ($parsedBody->getString('installName') != null) {
            $params['installName'] = $parsedBody->getString('installName');
            $body .= ' AND `installName` = :installName ';
        }

        if ($parsedBody->getString('type') != '') {
            $params['type'] = $parsedBody->getString('type');
            $body .= ' AND `module` = :type ';
        }

        if ($parsedBody->getString('class') != '') {
            $params['class'] = $parsedBody->getString('class');
            $body .= ' AND `class` = :class ';
        }

        if ($parsedBody->getString('extension') != '') {
            $params['extension'] = '%' . $parsedBody->getString('extension') . '%';
            $body .= ' AND `ValidExtensions` LIKE :extension ';
        }

        if ($parsedBody->getInt('assignable', ['default' => -1]) != -1) {
            $body .= " AND `assignable` = :assignable ";
            $params['assignable'] = $parsedBody->getInt('assignable');
        }

        if ($parsedBody->getInt('enabled', ['default' => -1]) != -1) {
            $body .= " AND `enabled` = :enabled ";
            $params['enabled'] = $parsedBody->getInt('enabled');
        }

        if ($parsedBody->getInt('regionSpecific', ['default' => -1]) != -1) {
            $body .= " AND `regionSpecific` = :regionSpecific ";
            $params['regionSpecific'] = $parsedBody->getInt('regionSpecific');
        }

        if ($parsedBody->getInt('notPlayerSoftware') == 1) {
            $body .= ' AND `module` <> \'playersoftware\' ';
        }

        if ($parsedBody->getInt('notSavedReport') == 1) {
            $body .= ' AND `module` <> \'savedreport\' ';
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $parsedBody->getInt('start') !== null && $parsedBody->getInt('length') !== null) {
            $limit = ' LIMIT ' . intval($parsedBody->getInt('start'), 0) . ', ' . $parsedBody->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        //

        $sth = $dbh->prepare($sql);
        $sth->execute($params);

        foreach ($sth->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $module = $this->createEmpty();
            $parsedRow = $this->getSanitizer($row);

            $module->moduleId = $parsedRow->getInt('ModuleID');
            $module->name = $parsedRow->getString('Name');
            $module->description = $parsedRow->getString('Description');
            $module->validExtensions = $parsedRow->getString('ValidExtensions');
            $module->renderAs = $parsedRow->getString('render_as');
            $module->enabled = $parsedRow->getInt('Enabled');
            $module->regionSpecific = $parsedRow->getInt('RegionSpecific');
            $module->previewEnabled = $parsedRow->getInt('PreviewEnabled');
            $module->assignable = $parsedRow->getInt('assignable');
            $module->schemaVersion = $parsedRow->getInt('SchemaVersion');

            // Identification
            $module->type = strtolower($row['Module']);

            $module->class = $parsedRow->getString('class');
            $module->viewPath = $parsedRow->getString('viewPath');
            $module->defaultDuration = $parsedRow->getInt('defaultDuration');
            $module->installName = $parsedRow->getString('installName');

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