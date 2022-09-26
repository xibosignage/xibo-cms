<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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
namespace Xibo\Widget;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Stream;
use Intervention\Image\ImageManagerStatic as Img;
use Mimey\MimeTypes;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Views\Twig;
use Stash\Interfaces\PoolInterface;
use Stash\Invalidation;
use Stash\Item;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Twig\Error\Error;
use Xibo\Entity\Media;
use Xibo\Entity\User;
use Xibo\Event\Event;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\MenuBoardCategoryFactory;
use Xibo\Factory\MenuBoardFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\TransitionFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\HttpCacheProvider;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Exception\ValueTooLargeException;

/**
 * Class ModuleWidget
 * @package Xibo\Widget
 *
 * @SWG\Definition()
 */
abstract class ModuleWidget implements ModuleInterface
{
    public static $STATUS_VALID = 1;
    public static $STATUS_PLAYER = 2;
    public static $STATUS_INVALID = 4;

    /**
     * @var \Xibo\Entity\Module $module
     */
    protected $module;

    /**
     * @SWG\Property(description="The Widget")
     * @var \Xibo\Entity\Widget $widget Widget
     */
    public $widget;

    /**
     * @var User $user
     */
    protected $user;

    /**
     * @var \Xibo\Entity\Region $region The region this module is in
     */
    protected $region;

    /**
     * @var int $codeSchemaVersion The Schema Version of this code
     */
    protected $codeSchemaVersion = -1;

    /**
     * @var string A module populated status message set during isValid.
     */
    protected $statusMessage;

    /** @var string|null Cache Key Prefix */
    private $cacheKeyPrefix = null;

    /** @var Event */
    private $saveEvent;

    /** @var bool Is this a preview */
    private $isPreview = false;

    /** @var \Slim\Interfaces\RouteParserInterface */
    private $routeParser;

    /** @var double The Preview Width */
    private $previewWidth;

    /** @var double The Preview Height */
    private $previewHeight;

    /** @var array Cache of module templates */
    private $moduleTemplates;

    //<editor-fold desc="Injected Factory Classes and Services ">

    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var PoolInterface
     */
    private $pool;

    /**
     * @var LogServiceInterface
     */
    private $logService;

    /**
     * @var ConfigServiceInterface
     */
    private $configService;

    /**
     * @var SanitizerService
     */
    private $sanitizerService;

    /** @var EventDispatcherInterface */
    private $dispatcher;

    /** @var ModuleFactory  */
    protected $moduleFactory;

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

    /** @var PlayerVersionFactory */
    protected $playerVersionFactory;

    /** @var PlaylistFactory */
    protected $playlistFactory;

    /** @var MenuBoardFactory */
    protected $menuBoardFactory;

    /** @var MenuBoardCategoryFactory */
    protected $menuBoardCategoryFactory;

    /** @var NotificationFactory */
    protected $notificationFactory;

    /** @var Twig */
    protected $view;

    /** @var HttpCacheProvider */
    protected $cacheProvider;

    // </editor-fold>

    /**
     * ModuleWidget constructor.
     * @param StorageServiceInterface $store
     * @param PoolInterface $pool
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param SanitizerService $sanitizer
     * @param ModuleFactory $moduleFactory
     * @param MediaFactory $mediaFactory
     * @param DataSetFactory $dataSetFactory
     * @param DataSetColumnFactory $dataSetColumnFactory
     * @param TransitionFactory $transitionFactory
     * @param DisplayFactory $displayFactory
     * @param CommandFactory $commandFactory
     * @param ScheduleFactory $scheduleFactory
     * @param PermissionFactory $permissionFactory
     * @param UserGroupFactory $userGroupFactory
     * @param PlaylistFactory $playlistFactory
     * @param MenuBoardFactory $menuBoardFactory
     * @param MenuBoardCategoryFactory $menuBoardCategoryFactory
     * @param NotificationFactory $notificationFactory
     * @param Twig $view
     * @param HttpCacheProvider $cacheProvider
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        $store,
        $pool,
        $log,
        $config,
        $sanitizer,
        $moduleFactory,
        $mediaFactory,
        $dataSetFactory,
        $dataSetColumnFactory,
        $transitionFactory,
        $displayFactory,
        $commandFactory,
        $scheduleFactory,
        $permissionFactory,
        $userGroupFactory,
        $playlistFactory,
        $menuBoardFactory,
        $menuBoardCategoryFactory,
        $notificationFactory,
        Twig $view,
        HttpCacheProvider $cacheProvider,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->store = $store;
        $this->pool = $pool;
        $this->logService = $log;
        $this->configService = $config;
        $this->sanitizerService = $sanitizer;
        $this->moduleFactory = $moduleFactory;
        $this->mediaFactory = $mediaFactory;
        $this->dataSetFactory = $dataSetFactory;
        $this->dataSetColumnFactory = $dataSetColumnFactory;
        $this->transitionFactory = $transitionFactory;
        $this->displayFactory = $displayFactory;
        $this->commandFactory = $commandFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->permissionFactory = $permissionFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->playlistFactory = $playlistFactory;
        $this->menuBoardFactory = $menuBoardFactory;
        $this->menuBoardCategoryFactory = $menuBoardCategoryFactory;
        $this->notificationFactory = $notificationFactory;
        $this->view = $view;
        $this->cacheProvider = $cacheProvider;
        $this->dispatcher = $eventDispatcher;

        $this->init();
    }

    /**
     * Set whether we are preview or not
     * @param $isPreview
     * @param \Slim\Interfaces\RouteParserInterface $routeParser
     * @param double $previewWidth
     * @param double $previewHeight
     * @return $this
     */
    public function setPreview($isPreview, $routeParser, $previewWidth, $previewHeight)
    {
        $this->isPreview = $isPreview;
        $this->routeParser = $routeParser;
        $this->previewWidth = $previewWidth;
        $this->previewHeight = $previewHeight;
        return $this;
    }

    /**
     * Get Cache Pool
     * @return \Stash\Interfaces\PoolInterface
     */
    protected function getPool()
    {
        return $this->pool;
    }

    /**
     * Get Store
     * @return StorageServiceInterface
     */
    protected function getStore()
    {
        return $this->store;
    }

    /**
     * Get Log
     * @return LogServiceInterface
     */
    protected function getLog()
    {
        return $this->logService;
    }

    /**
     * Get Config
     * @return ConfigServiceInterface
     */
    public function getConfig()
    {
        return $this->configService;
    }

    /**
     * @param $array
     * @return \Xibo\Support\Sanitizer\SanitizerInterface
     */
    protected function getSanitizer($array)
    {
        return $this->sanitizerService->getSanitizer($array);
    }

    /**
     * @return SanitizerService
     */
    protected function getSanitizerService()
    {
        return $this->sanitizerService;
    }

    /**
     * @return EventDispatcherInterface
     */
    public function getDispatcher(): EventDispatcherInterface
    {
        if ($this->dispatcher === null) {
            $this->getLog()->error('getDispatcher: [module] No dispatcher found, returning an empty one');
            $this->dispatcher = new EventDispatcher();
        }
        return $this->dispatcher;
    }

    //
    // End of Injected Factories and Services
    //

    /**
     * Any initialisation code
     */
    public function init()
    {

    }

    /**
     * Make a cache key
     * @param $id
     * @return string
     */
    public function makeCacheKey($id)
    {
        return $this->getCacheKeyPrefix() . '/' . $id;
    }

    /**
     * Get the cache prefix, including the leading /
     * @return null|string
     */
    private function getCacheKeyPrefix()
    {
        if ($this->cacheKeyPrefix == null) {
            $className = get_class($this);
            $this->cacheKeyPrefix = '/widget/' . substr($className, strrpos($className, '\\') + 1);
        }

        return $this->cacheKeyPrefix;
    }

    /**
     * Dump the cache for this module
     */
    public function dumpCacheForModule()
    {
        $this->getPool()->deleteItem($this->getCacheKeyPrefix());
    }

    /**
     * Set the Widget
     * @param \Xibo\Entity\Widget $widget
     */
    final public function setWidget($widget)
    {
        $this->widget = $widget;
    }

    /**
     * Set the Module
     * @param \Xibo\Entity\Module $module
     */
    final public function setModule($module)
    {
        $this->module = $module;
    }

    /**
     * Get the module
     * @return \Xibo\Entity\Module
     */
    final public function getModule()
    {
        return $this->module;
    }

    /**
     * Set the regionId
     * @param \Xibo\Entity\Region $region
     */
    final public function setRegion($region)
    {
        $this->region = $region;
    }

    /**
     * Set User
     * @param User $user
     * @return $this
     */
    final public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Set the duration
     * @param int $duration
     * @return $this
     * @throws InvalidArgumentException
     */
    final protected function setDuration($duration)
    {
        // Check if duration has a positive value
        if ($duration < 0) {
            throw new InvalidArgumentException(__('Duration needs to be a positive value'), 'duration');
        }

        // Set maximum duration
        if ($duration > 526000) {
            throw new InvalidArgumentException(__('Duration must be lower than 526000'), 'duration');
        }

        $this->widget->duration = $duration;
        return $this;
    }

    /**
     * Set the duration
     * @param int $useDuration
     * @return $this
     */
    final protected function setUseDuration($useDuration)
    {
        $this->widget->useDuration = $useDuration;
        return $this;
    }

    /**
     * @param $userId
     * @return \Xibo\Entity\Playlist[]
     * @throws NotFoundException
     */
    final public function getAssignablePlaylists($userId)
    {
        return $this->playlistFactory->query(null, ['regionSpecific' => 0, 'notPlaylistId' => $this->widget->playlistId, 'userCheckUserId' => $userId]);
    }

    /**
     * Save the Module
     */
    protected final function saveSettings()
    {
        // Save
        $this->module->save();
    }

    /**
     * Set Option
     * @param string $name
     * @param string $value
     * @return $this
     * @throws ValueTooLargeException
     */
    final protected function setOption($name, $value)
    {
        if (strlen($value) > 67108864) {
            throw new ValueTooLargeException(__('Value too large for %s', $name), $name);
        }

        $this->widget->setOptionValue($name, 'attrib', $value);

        return $this;
    }

    /**
     * Get Option or Default
     * @param string $name
     * @param mixed [Optional] $default
     * @return mixed
     */
    final public function getOption($name, $default = null)
    {
        return $this->widget->getOptionValue($name, $default);
    }

    /**
     * Get User
     * @return User
     */
    final protected function getUser()
    {
        return $this->user;
    }

    /**
     * Get Raw Node Value
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    final public function getRawNode($name, $default = null)
    {
        return $this->widget->getOptionValue($name, $default);
    }

    /**
     * Set Raw Node Value
     * @param $name
     * @param $value
     * @return $this
     * @throws ValueTooLargeException
     */
    final protected function setRawNode($name, $value)
    {
        if (strlen($value) > 67108864)
            throw new ValueTooLargeException(__('Value too large for %s', $name), $name);

        $this->widget->setOptionValue($name, 'cdata', $value);

        return $this;
    }

    /**
     * Assign Media
     * @param int $mediaId
     */
    final protected function assignMedia($mediaId)
    {
        $this->widget->assignMedia($mediaId);
    }

    /**
     * Unassign Media
     * @param int $mediaId
     */
    final protected function unassignMedia($mediaId)
    {
        $this->widget->unassignMedia($mediaId);
    }

    /**
     * Count Media
     * @return int count of media
     */
    final protected function countMedia()
    {
        return $this->widget->countMedia();
    }

    /**
     * Clear Media
     * should only be used on media items that do not automatically assign new media from the feed
     */
    private function clearMedia()
    {
        $this->widget->clearCachedMedia();
    }

    /**
     * Has the Media changes
     * @return bool
     */
    private function hasMediaChanged()
    {
        return $this->widget->hasMediaChanged();
    }

    /**
     * Get WidgetId
     * @return int
     */
    final protected function getWidgetId()
    {
        return $this->widget->widgetId;
    }

    /**
     * Get the PlaylistId
     * @return int
     */
    final protected function getPlaylistId()
    {
        return $this->widget->playlistId;
    }

    /**
     * Get the Module type
     * @return string
     */
    final public function getModuleType()
    {
        return $this->module->type;
    }

    /**
     * Get the Module Name
     * @return string
     */
    final public function getModuleName()
    {
        return $this->module->name;
    }

    /**
     * @return array
     * @throws \Xibo\Support\Exception\GeneralException
     */
    final public function getMediaTags()
    {
        if ($this->module->regionSpecific == 0) {
            $media = $this->mediaFactory->getById($this->widget->getPrimaryMediaId());
            $media->load();

            return $media->tags;
        } else {
            return [];
        }
    }

    /**
     * Get the duration
     * @param array $options
     * @return int
     */
    final public function getDuration($options = [])
    {
        $options = array_merge([
            'real' => false
        ], $options);

        $isRegionSpecific = ($this->module !== null && $this->module->regionSpecific === 1);

        if ($options['real'] && !$isRegionSpecific) {
            try {
                // Get the duration from the parent media record.
                return $this->getMedia()->duration;
            } catch (NotFoundException $e) {
                $this->getLog()->error('Tried to get real duration from a widget without media. widgetId: '
                    . $this->getWidgetId());
                // Do nothing - drop out
            }
        } else if ($this->widget->duration === null && $this->module !== null) {
            return $this->module->defaultDuration;
        }

        return $this->widget->duration;
    }

    /**
     * Gets the set duration option
     * @return int
     */
    final public function getUseDuration()
    {
        return $this->widget->useDuration;
    }

    /**
     * Gets the calculated duration of this widget
     * @return int
     */
    final public function getCalculatedDurationForGetResource()
    {
        return ($this->widget->useDuration == 0) ? $this->getModule()->defaultDuration : $this->widget->duration;
    }

    /**
     * Check if status message is not empty
     * @return bool
     */
    final public function hasStatusMessage()
    {
        return !empty($this->statusMessage);
    }

    /**
     * Gets the Module status message
     * @return string
     */
    final public function getStatusMessage()
    {
        return $this->statusMessage;
    }

    /**
     * @param Event $event
     * @return $this
     */
    final public function setSaveEvent($event)
    {
        $this->saveEvent = $event;
        return $this;
    }

    /**
     * Save the Widget
     */
    final protected function saveWidget()
    {
        if ($this->saveEvent !== null) {
            $this->getLog()->debug('Dispatching save event ' . $this->saveEvent->getName());

            // Dispatch the Edit Event
            $this->getDispatcher()->dispatch($this->saveEvent->getName(), $this->saveEvent);
        }

        $this->widget->calculateDuration($this)->save();
    }

    /** @inheritDoc */
    final public function add(Request $request, Response $response): Response
    {
        // Set the default widget options for this widget and save.
        $this->setDefaultWidgetOptions();
        $this->setOption('upperLimit', 0);
        $this->setOption('lowerLimit', 0);
        $this->saveWidget();

        return $response;
    }

    /** @inheritdoc */
    public function delete(Request $request, Response $response): Response
    {
        $cachePath = $this->getConfig()->getSetting('LIBRARY_LOCATION')
            . 'widget'
            . DIRECTORY_SEPARATOR
            . $this->getWidgetId()
            . DIRECTORY_SEPARATOR;

        // Drop the cache
        // there is a chance this may not yet exist
        try {
            $it = new \RecursiveDirectoryIterator($cachePath, \RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
            rmdir($cachePath);
        } catch (\UnexpectedValueException $unexpectedValueException) {
            $this->getLog()->debug('HTML cache doesn\'t exist yet or cannot be deleted. ' . $unexpectedValueException->getMessage());
        }

        return $response;
    }

    /**
     * Get Name
     * @return string
     * @throws NotFoundException
     */
    public function getName()
    {
        if ($this->getOption('name') != '')
            return $this->getOption('name');

        $this->getLog()->debug('Media assigned: ' . count($this->widget->mediaIds));

        if ($this->getModule()->regionSpecific == 0 && count($this->widget->mediaIds) > 0) {
            $media = $this->getMedia();
            $name = $media->name;
        } else {
            $name = $this->module->name;
        }

        return $name;
    }

    /**
     * Preview code for a module
     * @param double $width
     * @param double $height
     * @param int [Optional] $scaleOverride
     * @return string
     */
    public function preview($width, $height, $scaleOverride = 0)
    {
        if ($this->module->previewEnabled == 0)
            return $this->previewIcon();

        return $this->previewAsClient($width, $height, $scaleOverride);
    }

    /**
     * Preview Icon
     * @return string
     */
    public function previewIcon()
    {
        return '<div style="text-align:center;"><i alt="' . __($this->module->name) . ' thumbnail" class="fa module-preview-icon module-icon-' . __($this->module->type) . '"></i></div>';
    }

    /**
     * Preview as the Client
     * @param double $width
     * @param double $height
     * @param int [Optional] $scaleOverride
     * @return string
     */
    public function previewAsClient($width, $height, $scaleOverride = 0)
    {
        $widthPx = $width . 'px';
        $heightPx = $height . 'px';

        $url = $this->urlFor('module.getResource', ['regionId' => $this->region->regionId, 'id' => $this->getWidgetId()]);

        return '<iframe scrolling="no" src="' . $url . '?preview=1" width="' . $widthPx . '" height="' . $heightPx . '" style="border:0;"></iframe>';
    }

    /**
     * Gets a Tab
     * @param string $tab
     * @return mixed
     * @throws ControllerNotImplemented
     */
    public function getTab($tab)
    {
        throw new ControllerNotImplemented();
    }

    /**
     * Get File URL
     * @param Media $file
     * @param string|null $type
     * @return string
     */
    protected function getFileUrl($file, $type = null)
    {
        $params = ['id' => $file->mediaId];

        if ($type !== null) {
            $params['type'] = $type;
        }

        if ($this->isPreview()) {
            return $this->urlFor('library.download', $params) . '?preview=1"';
        } else {
            $url = $file->storedAs;
        }

        return $url;
    }

    /**
     * Get Resource Url
     * @param string $uri The file name
     * @param string|null $type
     * @return string
     */
    protected function getResourceUrl($uri, $type = null)
    {
        $isPreview = $this->isPreview();

        // Local clients store all files in the root of the library
        $uri = basename($uri);

        if ($isPreview) {
            // Use the URI to get this media record
            try {
                $media = $this->mediaFactory->getByName($uri);
                $params = ['id' => $media->mediaId];

                if ($type !== null) {
                    $params['type'] = $type;
                }

                return $this->urlFor('library.download', $params) . '?preview=1';

            } catch (NotFoundException $notFoundException) {
                $this->getLog()->info('Widget referencing a resource that doesnt exist: ' . $this->getModuleType() . ' for ' . $uri);

                // Return a URL which will 404
                return '/' . $uri;
            }
        }

        return $uri;
    }

    /**
     * Render a template and return the results
     * @param $data
     * @param string $template
     * @return string
     * @throws ConfigurationException
     */
    protected function renderTemplate($data, $template = 'get-resource')
    {
        // Get the Twig Engine
        try {
            return $this->view->fetch($template . '.twig', $data);
        } catch (Error $exception) {
            $this->getLog()->error($exception->getMessage());
            throw new ConfigurationException(__('Problem with template'));
        }
    }

    /**
     * Get the the Transition for this media
     * @param string $type Either "in" or "out"
     * @return string
     * @throws InvalidArgumentException
     */
    public function getTransition($type)
    {
        switch ($type) {
            case 'in':
                $code = $this->getOption('transIn');
                break;

            case 'out':
                $code = $this->getOption('transOut');
                break;

            default:
                throw new InvalidArgumentException(__('Unknown transition type'), 'type');
        }

        if ($code == '')
            return __('None');

        // Look up the real transition name
        try {
            $transition = $this->transitionFactory->getByCode($code);
            return __($transition->transition);
        }
        catch (NotFoundException $e) {
            $this->getLog()->error('Transition not found with code %s.', $code);
            return 'None';
        }
    }

    /**
     * Default behaviour for install / upgrade
     * this should be overridden for new modules
     * @param ModuleFactory $moduleFactory
     * @throws GeneralException
     */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module->renderAs != 'native')
            throw new ControllerNotImplemented(__('Module must implement InstallOrUpgrade'));
    }

    /**
     * Installs any files specific to this module
     * @throws GeneralException
     */
    public function installFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-layout-scaler.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-interactive-control.min.js')->save();
    }

    /**
     * Validates and Installs a Module
     * @throws InvalidArgumentException
     */
    public function installModule()
    {
        $this->getLog()->notice('Request to install module with name: ' . $this->module->name);

        // Validate some things.
        if ($this->module->type == '')
            throw new InvalidArgumentException(__('Module has not set the module type'));

        if ($this->module->name == '')
            throw new InvalidArgumentException(__('Module has not set the module name'));

        if ($this->module->description == '')
            throw new InvalidArgumentException(__('Module has not set the description'));

        if (!is_numeric($this->module->previewEnabled))
            throw new InvalidArgumentException(__('Preview Enabled variable must be a number'));

        if (!is_numeric($this->module->assignable))
            throw new InvalidArgumentException(__('Assignable variable must be a number'));

        // Save the module
        $this->module->save();
    }

    /**
     * Form for updating the module settings
     */
    public function settingsForm()
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function settings(Request $request, Response $response): Response
    {
        return $response;
    }

    /**
     * Module settings buttons to be displayed on the module admin page
     * @return array
     */
    public function settingsButtons()
    {
        return [];
    }

    /**
     * Configure any additional module routes
     *  these are available through the api and web portal
     */
    public function configureRoutes()
    {

    }

    /**
     * Default view for edit form
     * @param Request $request
     * @return string
     */
    public function editForm(Request $request)
    {
        return $this->getModuleType() . '-form-edit';
    }

    /**
     * Layout Designer JavaScript template
     * @return null
     */
    public function layoutDesignerJavaScript()
    {
        return null;
    }

    /**
     * Get Module Setting
     * @param string $setting
     * @param mixed $default
     * @return mixed
     */
    public function getSetting($setting, $default = NULL)
    {
        if (isset($this->module->settings[$setting]))
            return $this->module->settings[$setting];
        else
            return $default;
    }

    /**
     * Count Library Media
     * @return int
     */
    public function countLibraryMedia()
    {
        return 0;
    }

    /**
     * Get Media Id
     * @return int
     * @throws NotFoundException
     */
    public function getMediaId()
    {
        $this->getLog()->debug('Getting Primary MediaId for ' . $this->getWidgetId());
        return $this->widget->getPrimaryMediaId();
    }

    /**
     * Get Media
     * @return \Xibo\Entity\Media
     * @throws NotFoundException
     */
    public function getMedia(): Media
    {
        return $this->mediaFactory->getById($this->getMediaId());
    }

    /**
     * Return File
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws NotFoundException
     */
    public function download(Request $request, Response $response): Response
    {
        $media = $this->mediaFactory->getById($this->getMediaId());
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $attachment = $sanitizedParams->getString('attachment');

        $this->getLog()->debug('Download for mediaId ' . $media->mediaId);

        // Are we a preview or not?
        $isPreview = ($sanitizedParams->getCheckbox('preview') == 1);

        // The file path
        $libraryPath = $this->getConfig()->getSetting('LIBRARY_LOCATION') . $media->storedAs;

        // Set some headers
        $headers = [];
        $headers['Content-Length'] = filesize($libraryPath);

        // Different behaviour depending on whether we are a preview or not.
        if ($isPreview) {
            // correctly grab the MIME type of the file we want to serve
            $mimeTypes = new MimeTypes();
            $ext = explode('.', $media->storedAs);
            $headers['Content-Type'] = $mimeTypes->getMimeType($ext[count($ext) - 1]);
        } else {
            // This widget is expected to output a file - usually this is for file based media
            // Get the name with library
            $attachmentName = empty($attachment) ? $media->storedAs : $attachment;

            $httpCache = $this->cacheProvider;
            // Issue some headers
            $response = $httpCache->withEtag($response, $media->md5);
            $response = $httpCache->withExpires($response,'+1 week');

            $headers['Content-Type'] = 'application/octet-stream';
            $headers['Content-Transfer-Encoding'] = 'Binary';
            $headers['Content-disposition'] = 'attachment; filename="' . $attachmentName . '"';
        }

        // Output the file
        $sendFileMode = $this->getConfig()->getSetting('SENDFILE_MODE');

        if ($sendFileMode == 'Apache') {
            // Send via Apache X-Sendfile header?
            $headers['X-Sendfile'] = $libraryPath;
        } else if ($sendFileMode == 'Nginx') {
            // Send via Nginx X-Accel-Redirect?
            $headers['X-Accel-Redirect'] = '/download/' . $media->storedAs;
        }

        // Add the headers we've collected to our response
        foreach ($headers as $header => $value) {
            $response = $response->withHeader($header, $value);
        }

        // Should we output the file via the application stack, or directly by reading the file.
        if ($sendFileMode == 'Off') {
            // Return the file with PHP
            $response = $response->withBody(new Stream(fopen($libraryPath, 'r')));

            $this->getLog()->debug('Returning Stream with response body, sendfile off.');
        } else {
            $this->getLog()->debug('Using sendfile to return the file, only output headers.');
        }

        return $response;
    }

    /**
     * Parse for any library references
     * @param bool $isPreview
     * @param string $content containing media references in [].
     * @param string $tokenRegEx
     * @return string The Parsed Content
     */
    protected function parseLibraryReferences($isPreview, $content, $tokenRegEx = '/\[.*?\]/')
    {
        $parsedContent = $content;
        $matches = '';
        preg_match_all($tokenRegEx, $content, $matches);

        foreach ($matches[0] as $sub) {
            // Parse out the mediaId
            $mediaId = str_replace(']', '', str_replace('[', '', $sub));

            // Only proceed if the content is actually an ID
            if (!is_numeric($mediaId))
                continue;

            // Check that this mediaId exists and get some information about it
            try {
                $entry = $this->mediaFactory->getById($mediaId);

                // Assign it
                $this->assignMedia($entry->mediaId);

                // We have a valid mediaId to substitute
                $replace = ($isPreview) ? $this->urlFor('library.download', ['id' => $entry->mediaId]) . '?preview=1' : $entry->storedAs;

                // Substitute the replacement we have found (it might be '')
                $parsedContent = str_replace($sub, $replace, $parsedContent);
            }
            catch (NotFoundException $e) {
                $this->getLog()->info('Reference to Unknown mediaId ' . $mediaId);
            }
        }

        return $parsedContent;
    }

    /**
     * Has this Module got templates?
     * @return bool
     */
    public function hasTemplates()
    {
        return false;
    }

    /**
     * Get templatesAvailable
     * @param bool $loadImage Should the image URL be loaded?
     * @param null $folder Optional path to templates for custom Modules
     * @return array
     */
    public function templatesAvailable($loadImage = true, $folder = null)
    {
        if ($this->moduleTemplates === null) {
            $this->moduleTemplates = [];

            // Scan the folder for template files
            $this->scanFolderForTemplates(PROJECT_ROOT . '/modules/' . $this->module->type . '/*.template.json', $loadImage);

            // Scan the custom folder for template files.
            $this->scanFolderForTemplates(PROJECT_ROOT . '/custom/' . $this->module->type . '/*.template.json', $loadImage);

            // Scan the custom folder for template files.
            if ($folder != null) {
                $this->scanFolderForTemplates($folder, $loadImage);
            }
        }

        return $this->moduleTemplates;
    }

    /**
     * @param string $folder
     * @param bool $loadImage
     */
    private function scanFolderForTemplates($folder, $loadImage = true)
    {
        foreach (glob($folder) as $template) {
            // Read the contents, json_decode and add to the array
            $template = json_decode(file_get_contents($template), true);

            if (isset($template['image'])) {
                $template['fileName'] = $template['image'];

                if ($loadImage) {
                    // Find the URL to the module file representing this template image
                    $template['image'] = $this->urlFor('module.getTemplateImage', [
                        'type' => $this->module->type,
                        'templateId' => $template['id']
                    ]);
                }
            } else {
                $template['fileName'] = '';
                $template['image'] = '';
            }

            $this->moduleTemplates[] = $template;
        }
    }

    /**
     * Get by Template Id
     * @param int $templateId
     * @return array|null
     */
    public function getTemplateById($templateId)
    {
        $templates = $this->templatesAvailable(false);
        $template = null;

        if (count($templates) <= 0)
            return null;

        foreach ($templates as $item) {
            if ($item['id'] == $templateId) {
                $template = $item;
                break;
            }
        }

        return $template;
    }

    /**
     * Set template data
     * @param array $data
     * @return array
     */
    public function setTemplateData($data)
    {
        return $data;
    }

    /**
     * Download an image for this template
     * @param string $templateId
     * @return ResponseInterface
     * @throws NotFoundException
     */
    public function getTemplateImage(string $templateId): ResponseInterface
    {
        $template = $this->getTemplateById($templateId);

        if ($template === null || !isset($template['fileName']) || $template['fileName'] == '')
            throw new NotFoundException();

        // Output the image associated with this template
        $image =  Img::make(PROJECT_ROOT . '/' . $template['fileName']);

        return $image->psrResponse();
    }

    /**
     * Determine duration
     * @param string|null $fileName
     * @return int
     */
    public function determineDuration($fileName = null)
    {
        return $this->getModule()->defaultDuration;
    }

    /**
     * Pre-processing
     *  this is run before the media item is created.
     * @param string|null $fileName
     */
    public function preProcessFile($fileName = null)
    {
        $this->getLog()->debug('No pre-processing rules for this module type');
    }

    /**
     * Pre-process
     *  this is run before the media item is saved
     * @param Media $media
     * @param string $filePath
     */
    public function preProcess($media, $filePath)
    {

    }

    /**
     * Post-processing
     *  this is run after the media item has been created and before it is saved.
     * @param Media $media
     * @param \Xibo\Factory\PlayerVersionFactory|null $factory
     */
    public function postProcess($media, PlayerVersionFactory $factory = null)
    {

    }

    /**
     * Set Default Widget Options
     * @throws InvalidArgumentException
     * @throws ValueTooLargeException
     */
    public function setDefaultWidgetOptions()
    {
        $this->getLog()->debug('Default Widget Options: Setting use duration to 0');
        $this->setUseDuration(0);
        $this->setOption('enableStat', $this->getConfig()->getSetting('WIDGET_STATS_ENABLED_DEFAULT'));

        $this->setDuration($this->module->defaultDuration);
    }

    //<editor-fold desc="Get Resource and cache">

    /** @inheritdoc */
    public function getCacheDuration()
    {
        // Default update interval is 15 minutes
        return 15 * 60;
    }

    /** @inheritdoc */
    public function getCacheKey($displayId)
    {
        // Default is the widgetId
        return $this->getWidgetId() . (($displayId === 0) ? '_0' : '');
    }

    /** @inheritdoc */
    public function isCacheDisplaySpecific()
    {
        // the default cacheKey is the widgetId only, so the default answer here is false
        return false;
    }

    /** @inheritdoc */
    public function getLockKey()
    {
        // Default is the widgetId
        return $this->getWidgetId();
    }

    /** @inheritdoc */
    public function getModifiedDate($displayId)
    {
        // Default behaviour is to assume we use the widget modified date
        return Carbon::createFromTimestamp($this->widget->modifiedDt);
    }

    /** @inheritdoc */
    public final function getCacheDate($displayId)
    {
        $item = $this->getPool()->getItem($this->makeCacheKey('html/' . $this->getCacheKey($displayId)));
        $date = $item->get();

        // If not cached set it to have cached a long time in the past
        if ($date === null)
            return Carbon::now()->subYear();

        // Parse the date
        return Carbon::createFromFormat( DateFormatHelper::getSystemFormat(), $date);
    }

    /** @inheritdoc */
    public final function setCacheDate($displayId)
    {
        $now = Carbon::now();
        $item = $this->getPool()->getItem($this->makeCacheKey('html/' . $this->getCacheKey($displayId)));

        $item->set($now->format(DateFormatHelper::getSystemFormat()));
        $item->expiresAt($now->addYear());

        $this->getPool()->save($item);
    }

    /** @inheritdoc */
    public final function getResourceOrCache($displayId = 0)
    {
        $this->getLog()->debug('getResourceOrCache for displayId ' . $displayId . ' and widgetId ' . $this->getWidgetId());

        // End game - we will return this.
        $resource = null;

        // Have we changed since we last cached this widget
        $now = Carbon::now();
        $modifiedDt = $this->getModifiedDate($displayId);
        $cachedDt = $this->getCacheDate($displayId);
        $cacheDuration = $this->getCacheDuration();
        $cachePath = $this->getConfig()->getSetting('LIBRARY_LOCATION')
            . 'widget'
            . DIRECTORY_SEPARATOR
            . $this->getWidgetId()
            . DIRECTORY_SEPARATOR;

        $cacheKey = $this->getCacheKey($displayId);

        // Prefix whatever cacheKey the Module generates with the Region dimensions.
        // Widgets may or may not appear in the same Region each time they are previewed due to them potentially
        // being contained in a Playlist.
        // Equally a Region might be resized, which would also effect the way the Widget looks. Just moving a Region
        // location wouldn't though, which is why we base this on the width/height.
        $cacheFile = $cacheKey . '_' . $this->region->width . '_' . $this->region->height;

        $this->getLog()->debug('Cache details - modifiedDt: ' . $modifiedDt->format(DateFormatHelper::getSystemFormat())
            . ', cacheDt: ' . $cachedDt->format(DateFormatHelper::getSystemFormat())
            . ', cacheDuration: ' . $cacheDuration
            . ', cacheKey: ' . $cacheKey
            . ', cacheFile: ' . $cacheFile);

        if (!file_exists($cachePath)) {
            mkdir($cachePath, 0777, true);
        }

        $cacheFileExists = file_exists($cachePath . $cacheFile);

        if ( $modifiedDt->greaterThan($cachedDt)
                || $cachedDt->addSeconds($cacheDuration)->lessThan($now)
                || !$cacheFileExists
                || ($cacheFileExists && !file_get_contents($cachePath . $cacheFile)) ) {

            $this->getLog()->debug('We will need to regenerate');

            try {
                // Get and hold a lock
                $this->concurrentRequestLock();

                // We need to generate and cache this resource
                try {
                    // The cache has expired, so we remove all cache entries that match our cache key
                    // including the preview (which will also be out of date)
                    $this->getLog()->debug('Deleting old cache for this cache key: ' . $cacheKey);

                    foreach (glob($cachePath . $cacheKey . '*') as $fileName) {
                        unlink($fileName);
                    }

                    // Clear the resources widget content
                    $this->clearMedia();

                    // Generate the resource
                    $resource = $this->getResource($displayId);

                    // If the resource is false, then don't cache it for as long (most likely an error)
                    if ($resource === false)
                        throw new GeneralException('GetResource generated FALSE');

                    // Cache to the library
                    $hash = null;
                    if (file_exists($cachePath . $cacheFile)) {
                        // get a md5 of the file
                        $hash = md5_file($cachePath . $cacheFile);

                        $this->getLog()->debug('Cache file ' . $cachePath . $cacheFile . ' already existed with hash ' . $hash);
                    }

                    file_put_contents($cachePath . $cacheFile, $resource);

                    // Should we notify this display of this widget changing?
                    if ($hash !== md5_file($cachePath . $cacheFile) || $this->hasMediaChanged()) {
                        $this->getLog()->debug('Cache file was different, we will need to notify the display');

                        // Notify
                        $this->widget->save(['saveWidgetOptions' => false, 'notify' => false, 'notifyDisplays' => true, 'audit' => false]);
                    } else {
                        $this->getLog()->debug('Cache file identical no need to notify the display');
                    }

                    // Update the cache date
                    $this->setCacheDate($displayId);

                    $this->getLog()->debug('Generate complete');

                } catch (ConfigurationException $configurationException) {
                    // If we have something wrong with the module and we are in the preview, then we should present the error
                    // on screen
                    if ($displayId === 0) {
                        $this->getLog()->debug('Configuration error with Widget, in preview - rethrow');
                        throw $configurationException;
                    } else {
                        // Don't cache, just log
                        $this->getLog()->error('Configuration error with Widget ' . $this->getWidgetId() . ' for displayId ' . $displayId . '. E = ' . $configurationException->getMessage());
                    }

                } catch (\Exception $e) {
                    $this->getLog()->error('Problem with Widget ' . $this->getWidgetId() . ' for displayId ' . $displayId . '. E = ' . $e->getMessage());
                    $this->getLog()->debug($e->getTraceAsString());

                    // Update the cache date?
                    $this->setCacheDate($displayId);
                }

                // Unlock
                $this->concurrentRequestRelease();

            } catch (\Exception $exception) {
                // Unlock
                $this->concurrentRequestRelease();

                if ($exception instanceof ConfigurationException)
                    throw $exception;
                else
                    throw new GeneralException($exception->getMessage(), $exception->getCode(), $exception);
            }
        } else {
            $this->getLog()->debug('No need to regenerate, cached until ' . $cachedDt->addSeconds($cacheDuration)->format(DateFormatHelper::getSystemFormat()));

            $resource = file_get_contents($cachePath . $cacheFile);
        }

        // Return the resource
        return $resource;
    }

    //</editor-fold>

    // <editor-fold desc="Request locking">

    /** @var  Item */
    private $lock;

    /**
     * Hold a lock on concurrent requests
     *  blocks if the request is locked
     * @param int $ttl seconds
     * @param int $wait seconds
     * @param int $tries
     * @throws GeneralException
     */
    private function concurrentRequestLock($ttl = 300, $wait = 2, $tries = 5)
    {
        $key = $this->getLockKey();

        $this->lock = $this->getPool()->getItem('locks/widget/' . $key);

        // Set the invalidation method to simply return the value (not that we use it, but it gets us a miss on expiry)
        // isMiss() returns false if the item is missing or expired, no exceptions.
        $this->lock->setInvalidationMethod(Invalidation::NONE);

        // Get the lock
        // other requests will wait here until we're done, or we've timed out
        $locked = $this->lock->get();

        // Did we get a lock?
        // if we're a miss, then we're not already locked
        if ($this->lock->isMiss() || $locked === false) {
            $this->getLog()->debug('Lock miss or false. Locking for ' . $ttl . ' seconds. $locked is '. var_export($locked, true) . ', widgetId = ' . $this->widget->widgetId);

            // so lock now
            $this->lock->set(true);
            $this->lock->expiresAfter($ttl);
            $this->lock->save();

            //sleep(30);
        } else {
            // We are a hit - we must be locked
            $this->getLog()->debug('LOCK hit for ' . $key . ' expires ' . $this->lock->getExpiration()->format(DateFormatHelper::getSystemFormat()) . ', created ' . $this->lock->getCreation()->format(DateFormatHelper::getSystemFormat()));

            // Try again?
            $tries--;

            if ($tries <= 0) {
                // We've waited long enough
                $this->getLog()->error('concurrentRequestLock: Record locked, no tries remaining. widgetId: ' . $this->getWidgetId());
                throw new GeneralException('Concurrent record locked, time out.');
            } else {
                $this->getLog()->debug('Unable to get a lock, trying again. Remaining retries: ' . $tries);

                // Hang about waiting for the lock to be released.
                sleep($wait);

                // Recursive request (we've decremented the number of tries)
                $this->concurrentRequestLock($ttl, $wait, $tries);
            }
        }
    }

    /**
     * Release a lock on concurrent requests
     */
    private function concurrentRequestRelease()
    {
        if ($this->lock !== null) {
            $this->getLog()->debug('Releasing lock ' . $this->lock->getKey() . ' widgetId ' . $this->widget->widgetId);

            // Release lock
            $this->lock->set(false);
            $this->lock->expiresAfter(10); // Expire straight away (but give it time to save the thing)

            $this->getPool()->save($this->lock);
        }
    }

    // </editor-fold>

    //<editor-fold desc="GetResource Helpers">

    private $data;

    /**
     * Initialise getResource
     * @return $this
     */
    protected function initialiseGetResource()
    {
        $this->data['isPreview'] = $this->isPreview();
        $this->data['javaScript'] = '';
        $this->data['styleSheet'] = '';
        $this->data['head'] = '';
        $this->data['body'] = '';
        $this->data['controlMeta'] = [];
        $this->data['options'] = '{}';
        $this->data['items'] = '{}';
        return $this;
    }

    /**
     * @return bool Is Preview
     */
    protected function isPreview()
    {
        return $this->isPreview;
    }

    /**
     * Get preview width
     * @return float
     */
    protected function getPreviewWidth()
    {
        return $this->previewWidth;
    }

    /**
     * Get preview height
     * @return float
     */
    protected function getPreviewHeight()
    {
        return $this->previewHeight;
    }

    /**
     * Finalise getResource
     * @param string $templateName an optional template name
     * @return string the rendered template
     * @throws ConfigurationException
     */
    protected function finaliseGetResource($templateName = 'get-resource')
    {
        $this->data['javaScript'] = '<script type="text/javascript">var options = ' . $this->data['options'] . '; var items = ' . $this->data['items'] . ';</script>' . PHP_EOL . $this->data['javaScript'];

        // Parse control meta out into HTML comments
        $controlMeta = '';
        foreach ($this->data['controlMeta'] as $meta => $value) {
            $controlMeta .= '<!-- ' . $meta . '=' . $value . ' -->' . PHP_EOL;
        }
        $this->data['controlMeta'] = $controlMeta;

        try {
            return $this->renderTemplate($this->data, $templateName);
        } catch (Error $e) {
            throw new ConfigurationException(__('Problem with template'));
        }
    }

    /**
     * Append the view port width - usually the region width
     * @param int $width
     * @return $this
     */
    protected function appendViewPortWidth($width)
    {
        $this->data['viewPortWidth'] = ($this->data['isPreview']) ? $width : '[[ViewPortWidth]]';
        return $this;
    }

    /**
     * @param $meta
     * @param $value
     * @return $this
     */
    protected function appendControlMeta($meta, $value)
    {
        $this->data['controlMeta'][$meta] = $value;
        return $this;
    }

    /**
     * Append Font CSS
     * @return $this
     */
    protected function appendFontCss()
    {
        $this->data['styleSheet'] .= '<link href="' . (($this->isPreview()) ? $this->urlFor('library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen" />' . PHP_EOL;
        return $this;
    }

    /**
     * Append CSS File
     * @param string $uri The URI, according to whether this is a CMS preview or not
     * @return $this
     */
    protected function appendCssFile($uri)
    {
        $this->data['styleSheet'] .= '<link href="' . $this->getResourceUrl($uri) . '" rel="stylesheet" media="screen" />' . PHP_EOL;
        return $this;
    }

    /**
     * Append CSS content
     * @param string $css
     * @return $this
     */
    protected function appendCss($css)
    {
        if (!empty($css)) {
            if (stripos($css, '<style') !== false)
                $this->data['styleSheet'] .= $css . PHP_EOL;
            else
                $this->data['styleSheet'] .= '<style type="text/css">' . $css . '</style>' . PHP_EOL;
        }

        return $this;
    }

    /**
     * Append JavaScript file
     * @param string $uri
     * @return $this
     */
    protected function appendJavaScriptFile($uri)
    {
        $this->data['javaScript'] .= '<script type="text/javascript" src="' . $this->getResourceUrl($uri) . '"></script>' . PHP_EOL;
        return $this;
    }

    /**
     * Append JavaScript
     * @param string $javasScript
     * @return $this
     */
    protected function appendJavaScript($javasScript)
    {
        if (!empty($javasScript))
            $this->data['javaScript'] .= '<script type="text/javascript">' . $javasScript . '</script>' . PHP_EOL;

        return $this;
    }

    /**
     * Append Body
     * @param string $body
     * @return $this
     */
    protected function appendBody($body)
    {
        if (!empty($body))
            $this->data['body'] .= $body . PHP_EOL;

        return $this;
    }

    /**
     * Append Options
     * @param array $options
     * @return $this
     */
    protected function appendOptions($options)
    {
        $this->data['options'] = json_encode($options);
        return $this;
    }

    /**
     * Append Items
     * @param array $items
     * @return $this
     */
    protected function appendItems($items)
    {
        $this->data['items'] = json_encode($items);
        return $this;
    }

    /**
     * Append raw string
     * @param string $key
     * @param string $item
     * @return $this
     */
    protected function appendRaw($key, $item)
    {
        $this->data[$key] .= $item . PHP_EOL;
        return $this;
    }

    /**
     * Get Url For Route
     * @param string $route
     * @param array $data
     * @param array $params
     * @return string
     */
    protected function urlFor($route, $data = [], $params = [])
    {
        return ($this->isPreview()) ? $this->routeParser->urlFor($route, $data, $params) : '';
    }

   /**
     * Parse for any translation references
     * @param string $content containing translation references in ||tag||.
     * @param string $tokenRegEx
     * @return string The Parsed Content
     */
    final protected function parseTranslations($content, $tokenRegEx = '/\|\|.*?\|\|/')
    {
        $parsedContent = $content;
        $matches = '';
        preg_match_all($tokenRegEx, $content, $matches);

        foreach ($matches[0] as $sub) {
            // Parse out the translateTag
            $translateTag = str_replace('||', '', $sub);

            // We have a valid translateTag to substitute
            $replace = __($translateTag);

            // Substitute the replacement we have found (it might be '')
            $parsedContent = str_replace($sub, $replace, $parsedContent);
        }

        return $parsedContent;
    }


    /**
     * Does this Widget has a thumbnail>
     *
     * @return bool
     */
    public function hasThumbnail()
    {
        return false;
    }

    /**
     * Does this Widget has html editor available?
     *
     * @return bool
     */
    public function hasHtmlEditor()
    {
        return false;
    }

    /**
     * This is called on Layout Import to find and replace Library references from text editor.
     * For Widget with html editor, return an array of options that may contain Library references
     *
     * @return array
     */
    public function getHtmlWidgetOptions()
    {
        return [];
    }

    //</editor-fold>
}
