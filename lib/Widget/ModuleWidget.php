<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
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

use Slim\Slim;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Media;
use Xibo\Entity\User;
use Xibo\Exception\ControllerNotImplemented;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\TransitionFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Service\ConfigService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\FactoryServiceInterface;
use Xibo\Service\LogService;
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

    //
    // Injected Factory Classes and Services Follow
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

    /**
     * ModuleWidget constructor.
     * @param Slim $app
     * @param StorageServiceInterface $store
     * @param PoolInterface $pool
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param DateServiceInterface $date
     * @param SanitizerServiceInterface $sanitizer
     * @param MediaFactory $mediaFactory
     * @param DataSetFactory $dataSetFactory
     * @param DataSetColumnFactory $dataSetColumnFactory
     * @param TransitionFactory $transitionFactory
     * @param DisplayFactory $displayFactory
     * @param CommandFactory $commandFactory
     */
    public function __construct($app, $store, $pool, $log, $config, $date, $sanitizer, $mediaFactory, $dataSetFactory, $dataSetColumnFactory, $transitionFactory, $displayFactory, $commandFactory)
    {
        $this->app = $app;
        $this->store = $store;
        $this->pool = $pool;
        $this->logService = $log;
        $this->configService = $config;
        $this->dateService = $date;
        $this->sanitizerService = $sanitizer;

        $this->mediaFactory = $mediaFactory;
        $this->dataSetFactory = $dataSetFactory;
        $this->dataSetColumnFactory = $dataSetColumnFactory;
        $this->transitionFactory = $transitionFactory;
        $this->displayFactory = $displayFactory;
        $this->commandFactory = $commandFactory;

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
     * @return LogService
     */
    protected function getLog()
    {
        return $this->logService;
    }

    /**
     * Get Config
     * @return ConfigService
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
     */
    final protected function clearMedia()
    {
        $this->widget->clearCachedMedia();
    }

    /**
     *
     */
    final protected function hasMediaChanged()
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
        return ($this->widget->calculatedDuration == 0) ? $this->getModule()->defaultDuration : $this->widget->calculatedDuration;
    }

    /**
     * Save the Widget
     */
    final protected function saveWidget()
    {
        $this->widget->save();
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
        $this->setDuration($this->getSanitizer()->getInt('duration'));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));

        $this->widget->save();
    }

    /**
     * Delete Widget
     */
    public function delete()
    {
        // By default this doesn't do anything
        // Module specific delete functionality should go here in the super class
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
            $media = $this->mediaFactory->getById($this->widget->mediaIds[0]);
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
     * Default Get Resource
     * @param int $displayId
     * @return mixed
     * @throws ControllerNotImplemented
     */
    public function getResource($displayId = 0)
    {
        throw new ControllerNotImplemented();
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
     * Get Resource Url
     * @param $uri
     * @return string
     */
    protected function getResourceUrl($uri)
    {
        $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);

        if ($isPreview)
            $uri = $this->getApp()->rootUri . 'modules/' . $uri;
        else
            $uri = basename($uri);

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
                $code = '';
                trigger_error(_('Unknown transition type'), E_USER_ERROR);
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
        $this->getLog()->debug('Getting first MediaID for Widget: %d. Media: %s', $this->getWidgetId(), json_encode($this->widget->mediaIds));

        if (count($this->widget->mediaIds) <= 0)
            throw new NotFoundException(__('No file to return'));

        return $this->widget->mediaIds[0];
    }

    /**
     * Get Media
     * @return \Xibo\Entity\Media
     * @throws NotFoundException
     */
    public function getMedia()
    {
        $media = $this->mediaFactory->getById($this->getMediaId());
        $media->setChildObjectDependencies($this->layoutFactory, $this->widgetFactory, $this->displayGroupFactory);
        return $media;
    }

    /**
     * Return File
     */
    protected function download()
    {
        $media = $this->mediaFactory->getById($this->getMediaId());

        // This widget is expected to output a file - usually this is for file based media
        // Get the name with library
        $libraryLocation = $this->getConfig()->GetSetting('LIBRARY_LOCATION');
        $libraryPath = $libraryLocation . $media->storedAs;
        $attachmentName = $this->getSanitizer()->getString('attachment', $media->storedAs);

        $size = filesize($libraryPath);

        // Issue some headers
        $this->getApp()->etag($media->md5);
        $this->getApp()->expires('+1 week');
        header('Content-Type: application/octet-stream');
        header('Content-Transfer-Encoding: Binary');
        header('Content-disposition: attachment; filename="' . $attachmentName . '"');
        header('Content-Length: ' . $size);

        // Send via Apache X-Sendfile header?
        if ($this->getConfig()->GetSetting('SENDFILE_MODE') == 'Apache') {
            header("X-Sendfile: $libraryPath");
        }
        // Send via Nginx X-Accel-Redirect?
        else if ($this->getConfig()->GetSetting('SENDFILE_MODE') == 'Nginx') {
            header("X-Accel-Redirect: /download/" . $media->storedAs);
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
     * Set template data
     * @param array $data
     * @return array
     */
    public function setTemplateData($data)
    {
        return $data;
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
}
