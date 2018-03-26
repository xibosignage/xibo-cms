<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2018 Spring Signage Ltd
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

use Intervention\Image\ImageManagerStatic as Img;
use Mimey\MimeTypes;
use Slim\Slim;
use Stash\Interfaces\PoolInterface;
use Stash\Invalidation;
use Stash\Item;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\Media;
use Xibo\Entity\User;
use Xibo\Exception\ControllerNotImplemented;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\TransitionFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class ModuleWidget
 * @package Xibo\Widget
 *
 * @SWG\Definition()
 */
abstract class ModuleWidget implements ModuleInterface
{
    /**
     * @var Slim
     */
    private $app;

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

    //
    // <editor-fold desc="Injected Factory Classes and Services Follow">
    //

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
     * @var DateServiceInterface
     */
    private $dateService;

    /**
     * @var SanitizerServiceInterface
     */
    private $sanitizerService;

    /** @var  EventDispatcherInterface */
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

    /** @var  LayoutFactory */
    protected $layoutFactory;

    /** @var  WidgetFactory */
    protected $widgetFactory;

    /** @var  DisplayGroupFactory */
    protected $displayGroupFactory;

    /** @var  ScheduleFactory */
    protected $scheduleFactory;

    /** @var  PermissionFactory */
    protected $permissionFactory;

    /** @var  UserGroupFactory */
    protected $userGroupFactory;

    /** @var PlaylistFactory */
    protected $playlistFactory;

    // </editor-fold>

    /**
     * ModuleWidget constructor.
     * @param Slim $app
     * @param StorageServiceInterface $store
     * @param PoolInterface $pool
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param DateServiceInterface $date
     * @param SanitizerServiceInterface $sanitizer
     * @param EventDispatcherInterface $dispatcher
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
     */
    public function __construct($app, $store, $pool, $log, $config, $date, $sanitizer, $dispatcher, $moduleFactory, $mediaFactory, $dataSetFactory, $dataSetColumnFactory, $transitionFactory, $displayFactory, $commandFactory, $scheduleFactory, $permissionFactory, $userGroupFactory, $playlistFactory)
    {
        $this->app = $app;
        $this->store = $store;
        $this->pool = $pool;
        $this->logService = $log;
        $this->configService = $config;
        $this->dateService = $date;
        $this->sanitizerService = $sanitizer;
        $this->dispatcher = $dispatcher;

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

        $this->init();
    }

    /**
     * Set Child Object Dependencies
     * @param LayoutFactory $layoutFactory
     * @param WidgetFactory $widgetFactory
     * @param DisplayGroupFactory $displayGroupFactory
     */
    public function setChildObjectDependencies($layoutFactory, $widgetFactory, $displayGroupFactory)
    {
        $this->layoutFactory = $layoutFactory;
        $this->widgetFactory = $widgetFactory;
        $this->displayGroupFactory = $displayGroupFactory;
    }

    /**
     * Get the App
     * @return Slim
     */
    protected function getApp()
    {
        if ($this->app == null)
            throw new \RuntimeException(__('Module Widget Application not set'));

        return $this->app;
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
     * Get Date
     * @return DateServiceInterface
     */
    protected function getDate()
    {
        return $this->dateService;
    }

    /**
     * Get Sanitizer
     * @return SanitizerServiceInterface
     */
    protected function getSanitizer()
    {
        return $this->sanitizerService;
    }

    /**
     * @inheritdoc
     */
    protected function getDispatcher()
    {
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
     */
    final public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * Set the duration
     * @param int $duration
     */
    final protected function setDuration($duration)
    {
        $this->widget->duration = $duration;
    }

    /**
     * Set the duration
     * @param int $useDuration
     */
    final protected function setUseDuration($useDuration)
    {
        $this->widget->useDuration = $useDuration;
    }

    /**
     * @return \Xibo\Entity\Playlist[]
     */
    final public function getAssignablePlaylists()
    {
        return $this->playlistFactory->query(null, ['regionSpecific' => 0, 'notPlaylistId' => $this->widget->playlistId]);
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
     */
    final protected function setOption($name, $value)
    {
        $this->widget->setOptionValue($name, 'attrib', $value);
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
     */
    final protected function setRawNode($name, $value)
    {
        $this->widget->setOptionValue($name, 'cdata', $value);
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
     * Get the duration
     * @param array $options
     * @return int
     */
    final public function getDuration($options = [])
    {
        $options = array_merge([
            'real' => false
        ], $options);

        if ($options['real']) {
            try {
                // Get the duration from the parent media record.
                return $this->getMedia()->duration;
            }
            catch (NotFoundException $e) {
                $this->getLog()->error('Tried to get real duration from a widget without media. widgetId: %d', $this->getWidgetId());
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
     * Save the Widget
     */
    final protected function saveWidget()
    {
        $this->widget->calculateDuration($this)->save();
    }

    /**
     * Add Media
     */
    public function add()
    {
        // Nothing to do
    }

    /**
     * Edit Media
     */
    public function edit()
    {
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));

        $this->widget->save();
    }

    /**
     * Delete Widget
     */
    public function delete()
    {
        $cachePath = $this->getConfig()->GetSetting('LIBRARY_LOCATION')
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
    }

    /**
     * Get Name
     * @return string
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
        return '<div style="text-align:center;"><img alt="' . $this->getModuleType() . ' thumbnail" src="' . $this->getConfig()->uri('img/' . $this->getModule()->imageUri) . '" /></div>';
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

        $url = $this->getApp()->urlFor('module.getResource', ['regionId' => $this->region->regionId, 'id' => $this->getWidgetId()]);

        return '<iframe scrolling="no" src="' . $url . '?raw=true&preview=true&scale_override=' . $scaleOverride . '&width=' . $width . '&height=' . $height . '" width="' . $widthPx . '" height="' . $heightPx . '" style="border:0;"></iframe>';
    }

    /**
     * Default code for the hover preview
     * @return string
     */
    public function hoverPreview()
    {
        // Default Hover window contains a thumbnail, media type and duration
        $output = '<div class="well">';
        $output .= '<div class="preview-module-image"><img alt="' . __($this->module->name) . ' thumbnail" src="' . $this->getConfig()->uri('img/' . $this->module->imageUri) . '" /></div>';
        $output .= '<div class="info">';
        $output .= '    <ul>';
        $output .= '    <li>' . __('Type') . ': ' . $this->module->name . '</li>';
        $output .= '    <li>' . __('Name') . ': ' . $this->getName() . '</li>';
        if ($this->getUseDuration() == 1)
            $output .= '    <li>' . __('Duration') . ': ' . $this->widget->duration . ' ' . __('seconds') . '</li>';
        $output .= '    </ul>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
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
        $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);
        $params = ['id' => $file->mediaId];

        if ($type !== null) {
            $params['type'] = $type;
        }

        if ($isPreview) {
            return $this->getApp()->urlFor('library.download', $params) . '?preview=1"';
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
        $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);

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

                return $this->getApp()->urlFor('library.download', $params) . '?preview=1';

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
     * @return mixed
     */
    protected function renderTemplate($data, $template = 'get-resource')
    {
        // Get the Twig Engine
        return $this->getApp()->view()->getInstance()->render($template . '.twig', $data);
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
     * @throws ControllerNotImplemented
     */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module->renderAs != 'native')
            throw new ControllerNotImplemented(__('Module must implement InstallOrUpgrade'));
    }

    /**
     * Installs any files specific to this module
     */
    public function installFiles()
    {

    }

    /**
     * Validates and Installs a Module
     * @throws \InvalidArgumentException
     */
    public function installModule()
    {
        $this->getLog()->notice('Request to install module with name: ' . $this->module->name, 'module', 'InstallModule');

        // Validate some things.
        if ($this->module->type == '')
            throw new \InvalidArgumentException(__('Module has not set the module type'));

        if ($this->module->name == '')
            throw new \InvalidArgumentException(__('Module has not set the module name'));

        if ($this->module->description == '')
            throw new \InvalidArgumentException(__('Module has not set the description'));

        if (!is_numeric($this->module->previewEnabled))
            throw new \InvalidArgumentException(__('Preview Enabled variable must be a number'));

        if (!is_numeric($this->module->assignable))
            throw new \InvalidArgumentException(__('Assignable variable must be a number'));

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
     * Process any module settings
     */
    public function settings()
    {

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
     * Default view for add form
     */
    public function addForm()
    {
        return $this->getModuleType() . '-form-add';
    }

    /**
     * Default view for edit form
     */
    public function editForm()
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
    public function getMedia()
    {
        $media = $this->mediaFactory->getById($this->getMediaId());
        $media->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory, $this->displayFactory, $this->scheduleFactory);
        return $media;
    }

    /**
     * Return File
     */
    protected function download()
    {
        $media = $this->mediaFactory->getById($this->getMediaId());

        $this->getLog()->debug('Download for mediaId ' . $media->mediaId);

        // Are we a preview or not?
        $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);

        // The file path
        $libraryPath = $this->getConfig()->GetSetting('LIBRARY_LOCATION') . $media->storedAs;

        // Set the content length
        $headers = $this->getApp()->response()->headers();
        $headers->set('Content-Length', filesize($libraryPath));

        // Different behaviour depending on whether we are a preview or not.
        if ($isPreview) {
            // correctly grab the MIME type of the file we want to serve
            $mimeTypes = new MimeTypes();
            $ext = explode('.', $media->storedAs);
            $headers->set('Content-Type', $mimeTypes->getMimeType($ext[count($ext) - 1]));
        } else {
            // This widget is expected to output a file - usually this is for file based media
            // Get the name with library
            $attachmentName = $this->getSanitizer()->getString('attachment', $media->storedAs);

            // Issue some headers
            $this->getApp()->etag($media->md5);
            $this->getApp()->expires('+1 week');

            $headers->set('Content-Type', 'application/octet-stream');
            $headers->set('Content-Transfer-Encoding', 'Binary');
            $headers->set('Content-disposition', 'attachment; filename="' . $attachmentName . '"');
        }

        // Output the file
        if ($this->getConfig()->GetSetting('SENDFILE_MODE') == 'Apache') {
            // Send via Apache X-Sendfile header?
            $headers->set('X-Sendfile', $libraryPath);
        }
        else if ($this->getConfig()->GetSetting('SENDFILE_MODE') == 'Nginx') {
            // Send via Nginx X-Accel-Redirect?
            $headers->set('X-Accel-Redirect', '/download/' . $media->storedAs);
        }
        else {
            // Return the file with PHP
            readfile($libraryPath);
        }
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
                $replace = ($isPreview) ? $this->getApp()->urlFor('library.download', ['id' => $entry->mediaId]) . '?preview=1' : $entry->storedAs;

                // Substitute the replacement we have found (it might be '')
                $parsedContent = str_replace($sub, $replace, $parsedContent);
            }
            catch (NotFoundException $e) {
                $this->getLog()->info('Reference to Unknown mediaId %d', $mediaId);
            }
        }

        return $parsedContent;
    }

    /**
     * Get templatesAvailable
     * @param bool $loadImage Should the image URL be loaded?
     * @return array
     */
    public function templatesAvailable($loadImage = true)
    {
        if (!isset($this->module->settings['templates'])) {

            $this->module->settings['templates'] = [];

            // Scan the folder for template files
            foreach (glob(PROJECT_ROOT . '/modules/' . $this->module->type . '/*.template.json') as $template) {
                // Read the contents, json_decode and add to the array
                $template = json_decode(file_get_contents($template), true);

                if (isset($template['image'])) {
                    $template['fileName'] = $template['image'];

                    if ($loadImage) {
                        // We ltrim this because the control is expecting a relative URL
                        $template['image'] = ltrim($this->getApp()->urlFor('module.getTemplateImage', ['type' => $this->module->type, 'templateId' => $template['id']]), '/');
                    }
                } else {
                    $template['fileName'] = '';
                    $template['image'] = '';
                }

                $this->module->settings['templates'][] = $template;
            }
        }

        return $this->module->settings['templates'];
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
     * @param int $templateId
     * @throws NotFoundException
     */
    public function getTemplateImage($templateId)
    {
        $template = $this->getTemplateById($templateId);

        if ($template === null || !isset($template['fileName']) || $template['fileName'] == '')
            throw new NotFoundException();

        // Output the image associated with this template
        echo Img::make(PROJECT_ROOT . '/' . $template['fileName'])->response();
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
    public function preProcess($media, $filePath) {

    }

    /**
     * Post-processing
     *  this is run after the media item has been created and before it is saved.
     * @param Media $media
     */
    public function postProcess($media)
    {

    }

    /**
     * Set Default Widget Options
     */
    public function setDefaultWidgetOptions()
    {
        $this->getLog()->debug('Default Widget Options: Setting use duration to 0');
        $this->setUseDuration(0);
    }

    /**
     * Get Status Message
     * @return string
     */
    public function getStatusMessage()
    {
        return $this->statusMessage;
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
        return $this->getWidgetId();
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
        return $this->getDate()->parse($this->widget->modifiedDt, 'U');
    }

    /** @inheritdoc */
    public final function getCacheDate($displayId)
    {
        $item = $this->getPool()->getItem($this->makeCacheKey('html/' . $this->getCacheKey($displayId)));
        $date = $item->get();

        // If not cached set it to have cached a long time in the past
        if ($date === null)
            return $this->getDate()->parse()->subYear(1);

        // Parse the date
        return $this->getDate()->parse($date, 'Y-m-d H:i:s');
    }

    /** @inheritdoc */
    public final function setCacheDate($displayId, $overrideDuration = null)
    {
        $now = $this->getDate()->parse();
        $item = $this->getPool()->getItem($this->makeCacheKey('html/' . $this->getCacheKey($displayId)));

        $item->set($now->format('Y-m-d H:i:s'));
        $item->expiresAt($now->addYear(1));

        $this->getPool()->save($item);
    }

    /** @inheritdoc */
    public final function getResourceOrCache($displayId)
    {
        $this->getLog()->debug('getResourceOrCache for displayId ' . $displayId . ' and widgetId ' . $this->getWidgetId());

        // End game - we will return this.
        $resource = null;

        // Have we changed since we last cached this widget
        $now = $this->getDate()->parse();
        $modifiedDt = $this->getModifiedDate($displayId);
        $cachedDt = $this->getCacheDate($displayId);
        $cacheDuration = $this->getCacheDuration();
        $cachePath = $this->getConfig()->GetSetting('LIBRARY_LOCATION')
            . 'widget'
            . DIRECTORY_SEPARATOR
            . $this->getWidgetId()
            . DIRECTORY_SEPARATOR;

        $cacheFile = $this->getCacheKey($displayId);

        $this->getLog()->debug('Cache details - modifiedDt: ' . (($modifiedDt === null) ? 'layoutDt' : $modifiedDt->format('Y-m-d H:i:s'))
            . ', cacheDt: ' . $cachedDt->format('Y-m-d H:i:s')
            . ', cacheDuration: ' . $cacheDuration
            . ', cacheFile: ' . $cacheFile);

        if (!file_exists($cachePath))
            mkdir($cachePath, 0777, true);

        if ( ($modifiedDt !== null && $modifiedDt->greaterThan($cachedDt))
                || $cachedDt->addSeconds($cacheDuration)->lessThan($now)
                || !file_exists($cachePath . $cacheFile) ) {

            $this->getLog()->debug('We will need to regenerate');

            try {
                // Get and hold a lock
                $this->concurrentRequestLock();

                // We need to generate and cache this resource
                try {
                    // Clear the resources widget content
                    $this->clearMedia();

                    // Generate the resource
                    $resource = $this->getResource($displayId);

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

                    $this->getLog()->debug('Regenerate complete');

                } catch (\Exception $e) {
                    $this->getLog()->error('Problem with Widget ' . $this->getWidgetId() . ' for displayId ' . $displayId . '. E = ' . $e->getMessage());
                    $this->getLog()->debug($e->getTraceAsString());

                    // Update the cache date
                    // error scenario so drop the duration by 1/3rd
                    $this->setCacheDate($cacheDuration / 3);
                }

                // Unlock
                $this->concurrentRequestRelease();

            } catch (\Exception $exception) {
                // Unlock
                $this->concurrentRequestRelease();

                throw new XiboException($exception->getMessage(), $exception->getCode(), $exception);
            }
        } else {
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
     * @throws XiboException
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
            $this->getLog()->debug('LOCK hit for ' . $key . ' expires ' . $this->lock->getExpiration()->format('Y-m-d H:i:s') . ', created ' . $this->lock->getCreation()->format('Y-m-d H:i:s'));

            // Try again?
            $tries--;

            if ($tries <= 0) {
                // We've waited long enough
                throw new XiboException('Concurrent record locked, time out.');
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
        $this->data['isPreview'] = ($this->getSanitizer()->getCheckbox('preview') == 1);
        $this->data['javaScript'] = '';
        $this->data['styleSheet'] = '';
        $this->data['head'] = '';
        $this->data['body'] = '';
        $this->data['controlMeta'] = '';
        $this->data['options'] = '{}';
        $this->data['items'] = '{}';
        return $this;
    }

    /**
     * @return bool Is Preview
     */
    protected function isPreview()
    {
        return $this->data['isPreview'];
    }

    /**
     * Finalise getResource
     * @param string $templateName an optional template name
     * @return string the rendered template
     */
    protected function finaliseGetResource($templateName = 'get-resource')
    {
        $this->data['javaScript'] = '<script type="text/javascript">var options = ' . $this->data['options'] . '; var items = ' . $this->data['items'] . ';</script>' . PHP_EOL . $this->data['javaScript'];
        return $this->renderTemplate($this->data, $templateName);
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
     * Append Font CSS
     * @return $this
     */
    protected function appendFontCss()
    {
        $this->data['styleSheet'] .= '<link href="' . (($this->isPreview()) ? $this->getApp()->urlFor('library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen" />' . PHP_EOL;
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

    //</editor-fold>
}
