<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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

namespace Xibo\Controller;
use Respect\Validation\Validator as v;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\SettingsFactory;
use Xibo\Factory\TransitionFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class Settings
 * @package Xibo\Controller
 */
class Settings extends Base
{
    /** @var  LayoutFactory */
    private $layoutFactory;

    /** @var UserGroupFactory */
    private $userGroupFactory;

    /** @var TransitionFactory */
    private $transitionfactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param LayoutFactory $layoutFactory
     * @param UserGroupFactory $userGroupFactory
     * @param TransitionFactory $transitionfactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $layoutFactory, $userGroupFactory, $transitionfactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->layoutFactory = $layoutFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->transitionfactory = $transitionfactory;

        // Initialise extra validation rules
        v::with('Xibo\\Validation\\Rules\\');
    }

    /**
     * Display Page
     */
    public function displayPage()
    {
        // Should we hide other themes?
        $themes = [];
        $hideThemes = $this->getConfig()->getThemeConfig('hide_others');

        if (!$hideThemes) {
            // Get all theme options
            $directory = new \RecursiveDirectoryIterator(PROJECT_ROOT . '/web/theme', \FilesystemIterator::SKIP_DOTS);
            $filter = new \RecursiveCallbackFilterIterator($directory, function($current, $key, $iterator) {

                if ($current->isDir()) {
                    return true;
                }

                return strpos($current->getFilename(), 'config.php') === 0;
            });

            $iterator = new \RecursiveIteratorIterator($filter);

            // Add options for all themes installed
            foreach($iterator as $file) {
                /* @var \SplFileInfo $file */
                $this->getLog()->debug('Found ' . $file->getPath());

                // Include the config file
                include $file->getPath() . '/' . $file->getFilename();

                $themes[] = ['id' => basename($file->getPath()), 'value' => $config['theme_name']];
            }
        }

        // A list of timezones
        $timeZones = [];
        foreach ($this->getDate()->timezoneList() as $key => $value) {
            $timeZones[] = ['id' => $key, 'value' => $value];
        }

        // A list of languages
        // Build an array of supported languages
        $languages = [];
        $localeDir = PROJECT_ROOT . '/locale';
        foreach (array_map('basename', glob($localeDir . '/*.mo')) as $lang) {
            // Trim the .mo off the end
            $lang = str_replace('.mo', '', $lang);
            $languages[] = ['id' => $lang, 'value' => $lang];
        }

        // The default layout
        try {
            $defaultLayout = $this->layoutFactory->getById($this->getConfig()->getSetting('DEFAULT_LAYOUT'));
        } catch (NotFoundException $notFoundException) {
            $defaultLayout = null;
        }

        // The default user group
        try {
            $defaultUserGroup = $this->userGroupFactory->getById($this->getConfig()->getSetting('DEFAULT_USERGROUP'));
        } catch (NotFoundException $notFoundException) {
            $defaultUserGroup = null;
        }

        // The default Transition In
        try {
            $defaultTransitionIn = $this->transitionfactory->getByCode($this->getConfig()->getSetting('DEFAULT_TRANSITION_IN'));
        } catch (NotFoundException $notFoundException) {
            $defaultTransitionIn = null;
        }

        // The default Transition Out
        try {
            $defaultTransitionOut = $this->transitionfactory->getByCode($this->getConfig()->getSetting('DEFAULT_TRANSITION_OUT'));
        } catch (NotFoundException $notFoundException) {
            $defaultTransitionOut = null;
        }

        // Work out whether we're in a valid elevate log period
        $elevateLogUntil = $this->getConfig()->getSetting('ELEVATE_LOG_UNTIL');

        if ($elevateLogUntil != null) {
            $elevateLogUntil = intval($elevateLogUntil);

            if ($elevateLogUntil <= time()) {
                $elevateLogUntil = null;
            } else {
                $elevateLogUntil = $this->getDate()->getLocalDate($elevateLogUntil);
            }
        }

        // Render the Theme and output
        $this->getState()->template = 'settings-page';
        $this->getState()->setData([
            'hideThemes' => $hideThemes,
            'themes' => $themes,
            'languages' => $languages,
            'timeZones' => $timeZones,
            'defaultLayout' => $defaultLayout,
            'defaultUserGroup' => $defaultUserGroup,
            'elevateLogUntil' => $elevateLogUntil,
            'defaultTransitionIn' => $defaultTransitionIn,
            'defaultTransitionOut' => $defaultTransitionOut
        ]);
    }

    /**
     * Update settings
     * @throws \Xibo\Exception\XiboException
     */
    public function update()
    {
        if (!$this->getUser()->isSuperAdmin())
            throw new AccessDeniedException();

        // Pull in all of the settings we're expecting to be submitted with this form.

        if ($this->getConfig()->isSettingEditable('LIBRARY_LOCATION')) {
            $libraryLocation = $this->getSanitizer()->getString('LIBRARY_LOCATION');

            // Validate library location
            // Check for a trailing slash and add it if its not there
            $libraryLocation = rtrim($libraryLocation, '/');
            $libraryLocation = rtrim($libraryLocation, '\\') . DIRECTORY_SEPARATOR;

            // Attempt to add the directory specified
            if (!file_exists($libraryLocation . 'temp'))
                // Make the directory with broad permissions recursively (so will add the whole path)
                mkdir($libraryLocation . 'temp', 0777, true);

            if (!is_writable($libraryLocation . 'temp'))
                throw new InvalidArgumentException(__('The Library Location you have picked is not writeable'), 'LIBRARY_LOCATION');

            $this->getConfig()->changeSetting('LIBRARY_LOCATION', $libraryLocation);
        }

        if ($this->getConfig()->isSettingEditable('SERVER_KEY')) {
            $this->getConfig()->changeSetting('SERVER_KEY', $this->getSanitizer()->getString('SERVER_KEY'));
        }

        if ($this->getConfig()->isSettingEditable('GLOBAL_THEME_NAME')) {
            $this->getConfig()->changeSetting('GLOBAL_THEME_NAME', $this->getSanitizer()->getString('GLOBAL_THEME_NAME'));
        }

        if ($this->getConfig()->isSettingEditable('NAVIGATION_MENU_POSITION')) {
            $this->getConfig()->changeSetting('NAVIGATION_MENU_POSITION', $this->getSanitizer()->getString('NAVIGATION_MENU_POSITION'));
        }

        if ($this->getConfig()->isSettingEditable('LIBRARY_MEDIA_UPDATEINALL_CHECKB')) {
            $this->getConfig()->changeSetting('LIBRARY_MEDIA_UPDATEINALL_CHECKB', $this->getSanitizer()->getCheckbox('LIBRARY_MEDIA_UPDATEINALL_CHECKB'));
        }

        if ($this->getConfig()->isSettingEditable('LAYOUT_COPY_MEDIA_CHECKB')) {
            $this->getConfig()->changeSetting('LAYOUT_COPY_MEDIA_CHECKB', $this->getSanitizer()->getCheckbox('LAYOUT_COPY_MEDIA_CHECKB'));
        }

        if ($this->getConfig()->isSettingEditable('LIBRARY_MEDIA_DELETEOLDVER_CHECKB')) {
            $this->getConfig()->changeSetting('LIBRARY_MEDIA_DELETEOLDVER_CHECKB', $this->getSanitizer()->getCheckbox('LIBRARY_MEDIA_DELETEOLDVER_CHECKB'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_CASCADE_PERMISSION_CHECKB')) {
            $this->getConfig()->changeSetting('DEFAULT_CASCADE_PERMISSION_CHECKB', $this->getSanitizer()->getCheckbox('DEFAULT_CASCADE_PERMISSION_CHECKB'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_LAYOUT_AUTO_PUBLISH_CHECKB')) {
            $this->getConfig()->changeSetting('DEFAULT_LAYOUT_AUTO_PUBLISH_CHECKB', $this->getSanitizer()->getCheckbox('DEFAULT_LAYOUT_AUTO_PUBLISH_CHECKB'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_TRANSITION_IN')) {
            $this->getConfig()->changeSetting('DEFAULT_TRANSITION_IN', $this->getSanitizer()->getString('DEFAULT_TRANSITION_IN'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_TRANSITION_OUT')) {
            $this->getConfig()->changeSetting('DEFAULT_TRANSITION_OUT', $this->getSanitizer()->getString('DEFAULT_TRANSITION_OUT'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_TRANSITION_DURATION')) {
            $this->getConfig()->changeSetting('DEFAULT_TRANSITION_DURATION', $this->getSanitizer()->getInt('DEFAULT_TRANSITION_DURATION'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_TRANSITION_AUTO_APPLY')) {
            $this->getConfig()->changeSetting('DEFAULT_TRANSITION_AUTO_APPLY', $this->getSanitizer()->getCheckbox('DEFAULT_TRANSITION_AUTO_APPLY'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_RESIZE_THRESHOLD')) {
            $this->getConfig()->changeSetting('DEFAULT_RESIZE_THRESHOLD', $this->getSanitizer()->getInt('DEFAULT_RESIZE_THRESHOLD'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_RESIZE_LIMIT')) {
            $this->getConfig()->changeSetting('DEFAULT_RESIZE_LIMIT', $this->getSanitizer()->getInt('DEFAULT_RESIZE_LIMIT'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_LAYOUT')) {
            $this->getConfig()->changeSetting('DEFAULT_LAYOUT', $this->getSanitizer()->getInt('DEFAULT_LAYOUT'));
        }

        if ($this->getConfig()->isSettingEditable('XMR_ADDRESS')) {
            $this->getConfig()->changeSetting('XMR_ADDRESS', $this->getSanitizer()->getString('XMR_ADDRESS'));
        }

        if ($this->getConfig()->isSettingEditable('XMR_PUB_ADDRESS')) {
            $this->getConfig()->changeSetting('XMR_PUB_ADDRESS', $this->getSanitizer()->getString('XMR_PUB_ADDRESS'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_LAT')) {
            $value = $this->getSanitizer()->getString('DEFAULT_LAT');
            $this->getConfig()->changeSetting('DEFAULT_LAT', $value);

            if (!v::latitude()->validate($value)) {
                throw new InvalidArgumentException(__('The latitude entered is not valid.'), 'DEFAULT_LAT');
            }
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_LONG')) {
            $value = $this->getSanitizer()->getString('DEFAULT_LONG');
            $this->getConfig()->changeSetting('DEFAULT_LONG', $value);

            if (!v::longitude()->validate($value)) {
                throw new InvalidArgumentException(__('The longitude entered is not valid.'), 'DEFAULT_LONG');
            }
        }

        if ($this->getConfig()->isSettingEditable('SHOW_DISPLAY_AS_VNCLINK')) {
            $this->getConfig()->changeSetting('SHOW_DISPLAY_AS_VNCLINK', $this->getSanitizer()->getString('SHOW_DISPLAY_AS_VNCLINK'));
        }

        if ($this->getConfig()->isSettingEditable('SHOW_DISPLAY_AS_VNC_TGT')) {
            $this->getConfig()->changeSetting('SHOW_DISPLAY_AS_VNC_TGT', $this->getSanitizer()->getString('SHOW_DISPLAY_AS_VNC_TGT'));
        }

        if ($this->getConfig()->isSettingEditable('MAX_LICENSED_DISPLAYS')) {
            $this->getConfig()->changeSetting('MAX_LICENSED_DISPLAYS', $this->getSanitizer()->getInt('MAX_LICENSED_DISPLAYS'));
        }

        if ($this->getConfig()->isSettingEditable('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT')) {
            $this->getConfig()->changeSetting('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT', $this->getSanitizer()->getString('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT'));
        }

        if ($this->getConfig()->isSettingEditable('DISPLAY_PROFILE_STATS_DEFAULT')) {
            $this->getConfig()->changeSetting('DISPLAY_PROFILE_STATS_DEFAULT', $this->getSanitizer()->getCheckbox('DISPLAY_PROFILE_STATS_DEFAULT'));
        }

        if ($this->getConfig()->isSettingEditable('LAYOUT_STATS_ENABLED_DEFAULT')) {
            $this->getConfig()->changeSetting('LAYOUT_STATS_ENABLED_DEFAULT', $this->getSanitizer()->getCheckbox('LAYOUT_STATS_ENABLED_DEFAULT'));
        }

        if ($this->getConfig()->isSettingEditable('PLAYLIST_STATS_ENABLED_DEFAULT')) {
            $this->getConfig()->changeSetting('PLAYLIST_STATS_ENABLED_DEFAULT', $this->getSanitizer()->getString('PLAYLIST_STATS_ENABLED_DEFAULT'));
        }

        if ($this->getConfig()->isSettingEditable('MEDIA_STATS_ENABLED_DEFAULT')) {
            $this->getConfig()->changeSetting('MEDIA_STATS_ENABLED_DEFAULT', $this->getSanitizer()->getString('MEDIA_STATS_ENABLED_DEFAULT'));
        }

        if ($this->getConfig()->isSettingEditable('WIDGET_STATS_ENABLED_DEFAULT')) {
            $this->getConfig()->changeSetting('WIDGET_STATS_ENABLED_DEFAULT', $this->getSanitizer()->getString('WIDGET_STATS_ENABLED_DEFAULT'));
        }

        if ($this->getConfig()->isSettingEditable('DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED')) {
            $this->getConfig()->changeSetting('DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED', $this->getSanitizer()->getCheckbox('DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED'));
        }

        if ($this->getConfig()->isSettingEditable('DISPLAY_LOCK_NAME_TO_DEVICENAME')) {
            $this->getConfig()->changeSetting('DISPLAY_LOCK_NAME_TO_DEVICENAME', $this->getSanitizer()->getCheckbox('DISPLAY_LOCK_NAME_TO_DEVICENAME'));
        }

        if ($this->getConfig()->isSettingEditable('DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED')) {
            $this->getConfig()->changeSetting('DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED', $this->getSanitizer()->getCheckbox('DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED'));
        }

        if ($this->getConfig()->isSettingEditable('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT')) {
            $this->getConfig()->changeSetting('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT', $this->getSanitizer()->getInt('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT'));
        }

        if ($this->getConfig()->isSettingEditable('DISPLAY_AUTO_AUTH')) {
            $this->getConfig()->changeSetting('DISPLAY_AUTO_AUTH', $this->getSanitizer()->getCheckbox('DISPLAY_AUTO_AUTH'));
        }

        if ($this->getConfig()->isSettingEditable('HELP_BASE')) {
            $this->getConfig()->changeSetting('HELP_BASE', $this->getSanitizer()->getString('HELP_BASE'));
        }

        if ($this->getConfig()->isSettingEditable('QUICK_CHART_URL')) {
            $this->getConfig()->changeSetting('QUICK_CHART_URL', $this->getSanitizer()->getString('QUICK_CHART_URL'));
        }

        if ($this->getConfig()->isSettingEditable('PHONE_HOME')) {
            $this->getConfig()->changeSetting('PHONE_HOME', $this->getSanitizer()->getCheckbox('PHONE_HOME'));
        }

        if ($this->getConfig()->isSettingEditable('PHONE_HOME_KEY')) {
            $this->getConfig()->changeSetting('PHONE_HOME_KEY', $this->getSanitizer()->getString('PHONE_HOME_KEY'));
        }

        if ($this->getConfig()->isSettingEditable('PHONE_HOME_DATE')) {
            $this->getConfig()->changeSetting('PHONE_HOME_DATE', $this->getSanitizer()->getInt('PHONE_HOME_DATE'));
        }

        if ($this->getConfig()->isSettingEditable('PHONE_HOME_URL')) {
            $this->getConfig()->changeSetting('PHONE_HOME_URL', $this->getSanitizer()->getString('PHONE_HOME_URL'));
        }

        if ($this->getConfig()->isSettingEditable('SCHEDULE_LOOKAHEAD')) {
            $this->getConfig()->changeSetting('SCHEDULE_LOOKAHEAD', $this->getSanitizer()->getCheckbox('SCHEDULE_LOOKAHEAD'));
        }

        if ($this->getConfig()->isSettingEditable('EVENT_SYNC')) {
            $this->getConfig()->changeSetting('EVENT_SYNC', $this->getSanitizer()->getCheckbox('EVENT_SYNC'));
        }

        if ($this->getConfig()->isSettingEditable('REQUIRED_FILES_LOOKAHEAD')) {
            $this->getConfig()->changeSetting('REQUIRED_FILES_LOOKAHEAD', $this->getSanitizer()->getInt('REQUIRED_FILES_LOOKAHEAD'));
        }

        if ($this->getConfig()->isSettingEditable('SETTING_IMPORT_ENABLED')) {
            $this->getConfig()->changeSetting('SETTING_IMPORT_ENABLED', $this->getSanitizer()->getCheckbox('SETTING_IMPORT_ENABLED'));
        }

        if ($this->getConfig()->isSettingEditable('SETTING_LIBRARY_TIDY_ENABLED')) {
            $this->getConfig()->changeSetting('SETTING_LIBRARY_TIDY_ENABLED', $this->getSanitizer()->getCheckbox('SETTING_LIBRARY_TIDY_ENABLED'));
        }

        if ($this->getConfig()->isSettingEditable('EMBEDDED_STATUS_WIDGET')) {
            $this->getConfig()->changeSetting('EMBEDDED_STATUS_WIDGET', $this->getSanitizer()->getString('EMBEDDED_STATUS_WIDGET'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULTS_IMPORTED')) {
            $this->getConfig()->changeSetting('DEFAULTS_IMPORTED', $this->getSanitizer()->getCheckbox('DEFAULTS_IMPORTED'));
        }

        if ($this->getConfig()->isSettingEditable('DASHBOARD_LATEST_NEWS_ENABLED')) {
            $this->getConfig()->changeSetting('DASHBOARD_LATEST_NEWS_ENABLED', $this->getSanitizer()->getCheckbox('DASHBOARD_LATEST_NEWS_ENABLED'));
        }

        if ($this->getConfig()->isSettingEditable('INSTANCE_SUSPENDED')) {
            $this->getConfig()->changeSetting('INSTANCE_SUSPENDED', $this->getSanitizer()->getCheckbox('INSTANCE_SUSPENDED'));
        }

        if ($this->getConfig()->isSettingEditable('LATEST_NEWS_URL')) {
            $this->getConfig()->changeSetting('LATEST_NEWS_URL', $this->getSanitizer()->getString('LATEST_NEWS_URL'));
        }

        if ($this->getConfig()->isSettingEditable('MAINTENANCE_ENABLED')) {
            $this->getConfig()->changeSetting('MAINTENANCE_ENABLED', $this->getSanitizer()->getString('MAINTENANCE_ENABLED'));
        }

        if ($this->getConfig()->isSettingEditable('MAINTENANCE_EMAIL_ALERTS')) {
            $this->getConfig()->changeSetting('MAINTENANCE_EMAIL_ALERTS', $this->getSanitizer()->getCheckbox('MAINTENANCE_EMAIL_ALERTS'));
        }

        if ($this->getConfig()->isSettingEditable('MAINTENANCE_KEY')) {
            $this->getConfig()->changeSetting('MAINTENANCE_KEY', $this->getSanitizer()->getString('MAINTENANCE_KEY'));
        }

        if ($this->getConfig()->isSettingEditable('MAINTENANCE_LOG_MAXAGE')) {
            $this->getConfig()->changeSetting('MAINTENANCE_LOG_MAXAGE', $this->getSanitizer()->getInt('MAINTENANCE_LOG_MAXAGE'));
        }

        if ($this->getConfig()->isSettingEditable('MAINTENANCE_STAT_MAXAGE')) {
            $this->getConfig()->changeSetting('MAINTENANCE_STAT_MAXAGE', $this->getSanitizer()->getInt('MAINTENANCE_STAT_MAXAGE'));
        }

        if ($this->getConfig()->isSettingEditable('MAINTENANCE_ALERT_TOUT')) {
            $this->getConfig()->changeSetting('MAINTENANCE_ALERT_TOUT', $this->getSanitizer()->getInt('MAINTENANCE_ALERT_TOUT'));
        }

        if ($this->getConfig()->isSettingEditable('MAINTENANCE_ALWAYS_ALERT')) {
            $this->getConfig()->changeSetting('MAINTENANCE_ALWAYS_ALERT', $this->getSanitizer()->getCheckbox('MAINTENANCE_ALWAYS_ALERT'));
        }

        if ($this->getConfig()->isSettingEditable('mail_to')) {
            $this->getConfig()->changeSetting('mail_to', $this->getSanitizer()->getString('mail_to'));
        }

        if ($this->getConfig()->isSettingEditable('mail_from')) {
            $this->getConfig()->changeSetting('mail_from', $this->getSanitizer()->getString('mail_from'));
        }

        if ($this->getConfig()->isSettingEditable('mail_from_name')) {
            $this->getConfig()->changeSetting('mail_from_name', $this->getSanitizer()->getString('mail_from_name'));
        }

        if ($this->getConfig()->isSettingEditable('SENDFILE_MODE')) {
            $this->getConfig()->changeSetting('SENDFILE_MODE', $this->getSanitizer()->getString('SENDFILE_MODE'));
        }

        if ($this->getConfig()->isSettingEditable('PROXY_HOST')) {
            $this->getConfig()->changeSetting('PROXY_HOST', $this->getSanitizer()->getString('PROXY_HOST'));
        }

        if ($this->getConfig()->isSettingEditable('PROXY_PORT')) {
            $this->getConfig()->changeSetting('PROXY_PORT', $this->getSanitizer()->getString('PROXY_PORT'));
        }

        if ($this->getConfig()->isSettingEditable('PROXY_AUTH')) {
            $this->getConfig()->changeSetting('PROXY_AUTH', $this->getSanitizer()->getString('PROXY_AUTH'));
        }

        if ($this->getConfig()->isSettingEditable('PROXY_EXCEPTIONS')) {
            $this->getConfig()->changeSetting('PROXY_EXCEPTIONS', $this->getSanitizer()->getString('PROXY_EXCEPTIONS'));
        }

        if ($this->getConfig()->isSettingEditable('CDN_URL')) {
            $this->getConfig()->changeSetting('CDN_URL', $this->getSanitizer()->getString('CDN_URL'));
        }

        if ($this->getConfig()->isSettingEditable('MONTHLY_XMDS_TRANSFER_LIMIT_KB')) {
            $this->getConfig()->changeSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB', $this->getSanitizer()->getInt('MONTHLY_XMDS_TRANSFER_LIMIT_KB'));
        }

        if ($this->getConfig()->isSettingEditable('LIBRARY_SIZE_LIMIT_KB')) {
            $this->getConfig()->changeSetting('LIBRARY_SIZE_LIMIT_KB', $this->getSanitizer()->getInt('LIBRARY_SIZE_LIMIT_KB'));
        }

        if ($this->getConfig()->isSettingEditable('FORCE_HTTPS')) {
            $this->getConfig()->changeSetting('FORCE_HTTPS', $this->getSanitizer()->getCheckbox('FORCE_HTTPS'));
        }

        if ($this->getConfig()->isSettingEditable('ISSUE_STS')) {
            $this->getConfig()->changeSetting('ISSUE_STS', $this->getSanitizer()->getCheckbox('ISSUE_STS'));
        }

        if ($this->getConfig()->isSettingEditable('STS_TTL')) {
            $this->getConfig()->changeSetting('STS_TTL', $this->getSanitizer()->getInt('STS_TTL'));
        }

        if ($this->getConfig()->isSettingEditable('WHITELIST_LOAD_BALANCERS')) {
            $this->getConfig()->changeSetting('WHITELIST_LOAD_BALANCERS', $this->getSanitizer()->getString('WHITELIST_LOAD_BALANCERS'));
        }

        if ($this->getConfig()->isSettingEditable('LAYOUT_DEFAULT')) {
            $this->getConfig()->changeSetting('LAYOUT_DEFAULT', $this->getSanitizer()->getString('LAYOUT_DEFAULT'));
        }

        if ($this->getConfig()->isSettingEditable('MEDIA_DEFAULT')) {
            $this->getConfig()->changeSetting('MEDIA_DEFAULT', $this->getSanitizer()->getString('MEDIA_DEFAULT'));
        }

        if ($this->getConfig()->isSettingEditable('REGION_OPTIONS_COLOURING')) {
            $this->getConfig()->changeSetting('REGION_OPTIONS_COLOURING', $this->getSanitizer()->getString('REGION_OPTIONS_COLOURING'));
        }

        if ($this->getConfig()->isSettingEditable('SCHEDULE_WITH_VIEW_PERMISSION')) {
            $this->getConfig()->changeSetting('SCHEDULE_WITH_VIEW_PERMISSION', $this->getSanitizer()->getCheckbox('SCHEDULE_WITH_VIEW_PERMISSION'));
        }

        if ($this->getConfig()->isSettingEditable('SCHEDULE_SHOW_LAYOUT_NAME')) {
            $this->getConfig()->changeSetting('SCHEDULE_SHOW_LAYOUT_NAME', $this->getSanitizer()->getCheckbox('SCHEDULE_SHOW_LAYOUT_NAME'));
        }

        if ($this->getConfig()->isSettingEditable('INHERIT_PARENT_PERMISSIONS')) {
            $this->getConfig()->changeSetting('INHERIT_PARENT_PERMISSIONS', $this->getSanitizer()->getCheckbox('INHERIT_PARENT_PERMISSIONS'));
        }

        if ($this->getConfig()->isSettingEditable('MODULE_CONFIG_LOCKED_CHECKB')) {
            $this->getConfig()->changeSetting('MODULE_CONFIG_LOCKED_CHECKB', $this->getSanitizer()->getCheckbox('MODULE_CONFIG_LOCKED_CHECKB'));
        }

        if ($this->getConfig()->isSettingEditable('TASK_CONFIG_LOCKED_CHECKB')) {
            $this->getConfig()->changeSetting('TASK_CONFIG_LOCKED_CHECKB', $this->getSanitizer()->getCheckbox('TASK_CONFIG_LOCKED_CHECKB'));
        }

        if ($this->getConfig()->isSettingEditable('TRANSITION_CONFIG_LOCKED_CHECKB')) {
            $this->getConfig()->changeSetting('TRANSITION_CONFIG_LOCKED_CHECKB', $this->getSanitizer()->getCheckbox('TRANSITION_CONFIG_LOCKED_CHECKB'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_LANGUAGE')) {
            $this->getConfig()->changeSetting('DEFAULT_LANGUAGE', $this->getSanitizer()->getString('DEFAULT_LANGUAGE'));
        }

        if ($this->getConfig()->isSettingEditable('defaultTimezone')) {
            $this->getConfig()->changeSetting('defaultTimezone', $this->getSanitizer()->getString('defaultTimezone'));
        }

        if ($this->getConfig()->isSettingEditable('DATE_FORMAT')) {
            $this->getConfig()->changeSetting('DATE_FORMAT', $this->getSanitizer()->getString('DATE_FORMAT'));
        }

        if ($this->getConfig()->isSettingEditable('DETECT_LANGUAGE')) {
            $this->getConfig()->changeSetting('DETECT_LANGUAGE', $this->getSanitizer()->getCheckbox('DETECT_LANGUAGE'));
        }

        if ($this->getConfig()->isSettingEditable('CALENDAR_TYPE')) {
            $this->getConfig()->changeSetting('CALENDAR_TYPE', $this->getSanitizer()->getString('CALENDAR_TYPE'));
        }

        if ($this->getConfig()->isSettingEditable('RESTING_LOG_LEVEL')) {
            $this->getConfig()->changeSetting('RESTING_LOG_LEVEL', $this->getSanitizer()->getString('RESTING_LOG_LEVEL'));
        }

        // Handle changes to log level
        $newLogLevel = null;
        $newElevateUntil = null;
        $currentLogLevel = $this->getConfig()->getSetting('audit');

        if ($this->getConfig()->isSettingEditable('audit')) {
            $newLogLevel = $this->getSanitizer()->getString('audit');
            $this->getConfig()->changeSetting('audit', $newLogLevel);
        }

        if ($this->getConfig()->isSettingEditable('ELEVATE_LOG_UNTIL') && $this->getSanitizer()->getDate('ELEVATE_LOG_UNTIL') != null) {
            $newElevateUntil = $this->getSanitizer()->getDate('ELEVATE_LOG_UNTIL')->format('U');
            $this->getConfig()->changeSetting('ELEVATE_LOG_UNTIL', $newElevateUntil);
        }

        // Have we changed log level? If so, were we also provided the elevate until setting?
        if ($newElevateUntil === null && $currentLogLevel != $newLogLevel) {
            // We haven't provided an elevate until (meaning it is not visible)
            $this->getConfig()->changeSetting('ELEVATE_LOG_UNTIL', $this->getDate()->parse()->addHour(1)->format('U'));
        }

        if ($this->getConfig()->isSettingEditable('SERVER_MODE')) {
            $this->getConfig()->changeSetting('SERVER_MODE', $this->getSanitizer()->getString('SERVER_MODE'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_USERGROUP')) {
            $this->getConfig()->changeSetting('DEFAULT_USERGROUP', $this->getSanitizer()->getInt('DEFAULT_USERGROUP'));
        }

        if ($this->getConfig()->isSettingEditable('defaultUsertype')) {
            $this->getConfig()->changeSetting('defaultUsertype', $this->getSanitizer()->getString('defaultUsertype'));
        }

        if ($this->getConfig()->isSettingEditable('USER_PASSWORD_POLICY')) {
            $this->getConfig()->changeSetting('USER_PASSWORD_POLICY', $this->getSanitizer()->getString('USER_PASSWORD_POLICY'));
        }

        if ($this->getConfig()->isSettingEditable('USER_PASSWORD_ERROR')) {
            $this->getConfig()->changeSetting('USER_PASSWORD_ERROR', $this->getSanitizer()->getString('USER_PASSWORD_ERROR'));
        }

        if ($this->getConfig()->isSettingEditable('PASSWORD_REMINDER_ENABLED')) {
            $this->getConfig()->changeSetting('PASSWORD_REMINDER_ENABLED', $this->getSanitizer()->getString('PASSWORD_REMINDER_ENABLED'));
        }

        if ($this->getConfig()->isSettingEditable('TWOFACTOR_ISSUER')) {
            $this->getConfig()->changeSetting('TWOFACTOR_ISSUER', $this->getSanitizer()->getString('TWOFACTOR_ISSUER'));
        }

        // Return
        $this->getState()->hydrate([
            'message' => __('Settings Updated')
        ]);
    }
}
