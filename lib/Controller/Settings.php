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

namespace Xibo\Controller;

use Carbon\Carbon;
use Respect\Validation\Validator as v;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Event\PlaylistMaxNumberChangedEvent;
use Xibo\Event\SystemUserChangedEvent;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\TransitionFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

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

    /** @var UserFactory */
    private $userFactory;

    /**
     * Set common dependencies.
     * @param LayoutFactory $layoutFactory
     * @param UserGroupFactory $userGroupFactory
     * @param TransitionFactory $transitionfactory
     * @param UserFactory $userFactory
     */
    public function __construct($layoutFactory, $userGroupFactory, $transitionfactory, $userFactory)
    {
        $this->layoutFactory = $layoutFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->transitionfactory = $transitionfactory;
        $this->userFactory = $userFactory;
    }

    /**
     * Display Page
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function displayPage(Request $request, Response $response)
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
        foreach (DateFormatHelper::timezoneList() as $key => $value) {
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

        // The system User
        try {
            $systemUser = $this->userFactory->getById($this->getConfig()->getSetting('SYSTEM_USER'));
        } catch (NotFoundException $notFoundException) {
            $systemUser = null;
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

            if ($elevateLogUntil <= Carbon::now()->format('U')) {
                $elevateLogUntil = null;
            } else {
                $elevateLogUntil = Carbon::createFromTimestamp($elevateLogUntil)->format(DateFormatHelper::getSystemFormat());
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
            'defaultTransitionOut' => $defaultTransitionOut,
            'systemUser' => $systemUser
        ]);

        return $this->render($request, $response);
    }

    /**
     * Update settings
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @phpcs:disable Generic.Files.LineLength.TooLong
     */
    public function update(Request $request, Response $response)
    {
        if (!$this->getUser()->isSuperAdmin()) {
            throw new AccessDeniedException();
        }

        $changedSettings = [];

        $sanitizedParams = $this->getSanitizer($request->getParams());
        // Pull in all of the settings we're expecting to be submitted with this form.

        if ($this->getConfig()->isSettingEditable('LIBRARY_LOCATION')) {
            $libraryLocation = $sanitizedParams->getString('LIBRARY_LOCATION');

            // Validate library location
            // Check for a trailing slash and add it if its not there
            $libraryLocation = rtrim($libraryLocation, '/');
            $libraryLocation = rtrim($libraryLocation, '\\') . DIRECTORY_SEPARATOR;

            // Attempt to add the directory specified
            if (!file_exists($libraryLocation . 'temp')) {
                // Make the directory with broad permissions recursively (so will add the whole path)
                mkdir($libraryLocation . 'temp', 0777, true);
            }

            if (!is_writable($libraryLocation . 'temp')) {
                throw new InvalidArgumentException(__('The Library Location you have picked is not writeable'), 'LIBRARY_LOCATION');
            }

            $this->handleChangedSettings('LIBRARY_LOCATION', $this->getConfig()->getSetting('LIBRARY_LOCATION'), $libraryLocation, $changedSettings);
            $this->getConfig()->changeSetting('LIBRARY_LOCATION', $libraryLocation);
        }

        if ($this->getConfig()->isSettingEditable('SERVER_KEY')) {
            $this->handleChangedSettings('SERVER_KEY', $this->getConfig()->getSetting('SERVER_KEY'), $sanitizedParams->getString('SERVER_KEY'), $changedSettings);
            $this->getConfig()->changeSetting('SERVER_KEY', $sanitizedParams->getString('SERVER_KEY'));
        }

        if ($this->getConfig()->isSettingEditable('GLOBAL_THEME_NAME')) {
            $this->handleChangedSettings('GLOBAL_THEME_NAME', $this->getConfig()->getSetting('GLOBAL_THEME_NAME'), $sanitizedParams->getString('GLOBAL_THEME_NAME'), $changedSettings);
            $this->getConfig()->changeSetting('GLOBAL_THEME_NAME', $sanitizedParams->getString('GLOBAL_THEME_NAME'));
        }

        if ($this->getConfig()->isSettingEditable('NAVIGATION_MENU_POSITION')) {
            $this->handleChangedSettings('NAVIGATION_MENU_POSITION', $this->getConfig()->getSetting('NAVIGATION_MENU_POSITION'), $sanitizedParams->getString('NAVIGATION_MENU_POSITION'), $changedSettings);
            $this->getConfig()->changeSetting('NAVIGATION_MENU_POSITION', $sanitizedParams->getString('NAVIGATION_MENU_POSITION'));
        }

        if ($this->getConfig()->isSettingEditable('LIBRARY_MEDIA_UPDATEINALL_CHECKB')) {
            $this->handleChangedSettings('LIBRARY_MEDIA_UPDATEINALL_CHECKB', $this->getConfig()->getSetting('LIBRARY_MEDIA_UPDATEINALL_CHECKB'), $sanitizedParams->getCheckbox('LIBRARY_MEDIA_UPDATEINALL_CHECKB'), $changedSettings);
            $this->getConfig()->changeSetting('LIBRARY_MEDIA_UPDATEINALL_CHECKB', $sanitizedParams->getCheckbox('LIBRARY_MEDIA_UPDATEINALL_CHECKB'));
        }

        if ($this->getConfig()->isSettingEditable('LAYOUT_COPY_MEDIA_CHECKB')) {
            $this->handleChangedSettings('LAYOUT_COPY_MEDIA_CHECKB', $this->getConfig()->getSetting('LAYOUT_COPY_MEDIA_CHECKB'), $sanitizedParams->getCheckbox('LAYOUT_COPY_MEDIA_CHECKB'), $changedSettings);
            $this->getConfig()->changeSetting('LAYOUT_COPY_MEDIA_CHECKB', $sanitizedParams->getCheckbox('LAYOUT_COPY_MEDIA_CHECKB'));
        }

        if ($this->getConfig()->isSettingEditable('LIBRARY_MEDIA_DELETEOLDVER_CHECKB')) {
            $this->handleChangedSettings('LIBRARY_MEDIA_DELETEOLDVER_CHECKB', $this->getConfig()->getSetting('LIBRARY_MEDIA_DELETEOLDVER_CHECKB'), $sanitizedParams->getCheckbox('LIBRARY_MEDIA_DELETEOLDVER_CHECKB'), $changedSettings);
            $this->getConfig()->changeSetting('LIBRARY_MEDIA_DELETEOLDVER_CHECKB', $sanitizedParams->getCheckbox('LIBRARY_MEDIA_DELETEOLDVER_CHECKB'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_LAYOUT_AUTO_PUBLISH_CHECKB')) {
            $this->handleChangedSettings('DEFAULT_LAYOUT_AUTO_PUBLISH_CHECKB', $this->getConfig()->getSetting('DEFAULT_LAYOUT_AUTO_PUBLISH_CHECKB'), $sanitizedParams->getCheckbox('DEFAULT_LAYOUT_AUTO_PUBLISH_CHECKB'), $changedSettings);
            $this->getConfig()->changeSetting('DEFAULT_LAYOUT_AUTO_PUBLISH_CHECKB', $sanitizedParams->getCheckbox('DEFAULT_LAYOUT_AUTO_PUBLISH_CHECKB'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_TRANSITION_IN')) {
            $this->handleChangedSettings('DEFAULT_TRANSITION_IN', $this->getConfig()->getSetting('DEFAULT_TRANSITION_IN'), $sanitizedParams->getString('DEFAULT_TRANSITION_IN'), $changedSettings);
            $this->getConfig()->changeSetting('DEFAULT_TRANSITION_IN', $sanitizedParams->getString('DEFAULT_TRANSITION_IN'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_TRANSITION_OUT')) {
            $this->handleChangedSettings('DEFAULT_TRANSITION_OUT', $this->getConfig()->getSetting('DEFAULT_TRANSITION_OUT'), $sanitizedParams->getString('DEFAULT_TRANSITION_OUT'), $changedSettings);
            $this->getConfig()->changeSetting('DEFAULT_TRANSITION_OUT', $sanitizedParams->getString('DEFAULT_TRANSITION_OUT'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_TRANSITION_DURATION')) {
            $this->handleChangedSettings('DEFAULT_TRANSITION_DURATION', $this->getConfig()->getSetting('DEFAULT_TRANSITION_DURATION'), $sanitizedParams->getInt('DEFAULT_TRANSITION_DURATION'), $changedSettings);
            $this->getConfig()->changeSetting('DEFAULT_TRANSITION_DURATION', $sanitizedParams->getInt('DEFAULT_TRANSITION_DURATION'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_TRANSITION_AUTO_APPLY')) {
            $this->handleChangedSettings('DEFAULT_TRANSITION_AUTO_APPLY', $this->getConfig()->getSetting('DEFAULT_TRANSITION_AUTO_APPLY'), $sanitizedParams->getCheckbox('DEFAULT_TRANSITION_AUTO_APPLY'), $changedSettings);
            $this->getConfig()->changeSetting('DEFAULT_TRANSITION_AUTO_APPLY', $sanitizedParams->getCheckbox('DEFAULT_TRANSITION_AUTO_APPLY'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_RESIZE_THRESHOLD')) {
            $this->handleChangedSettings('DEFAULT_RESIZE_THRESHOLD', $this->getConfig()->getSetting('DEFAULT_RESIZE_THRESHOLD'), $sanitizedParams->getInt('DEFAULT_RESIZE_THRESHOLD'), $changedSettings);
            $this->getConfig()->changeSetting('DEFAULT_RESIZE_THRESHOLD', $sanitizedParams->getInt('DEFAULT_RESIZE_THRESHOLD'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_RESIZE_LIMIT')) {
            $this->handleChangedSettings('DEFAULT_RESIZE_LIMIT', $this->getConfig()->getSetting('DEFAULT_RESIZE_LIMIT'), $sanitizedParams->getInt('DEFAULT_RESIZE_LIMIT'), $changedSettings);
            $this->getConfig()->changeSetting('DEFAULT_RESIZE_LIMIT', $sanitizedParams->getInt('DEFAULT_RESIZE_LIMIT'));
        }

        if ($this->getConfig()->isSettingEditable('DATASET_HARD_ROW_LIMIT')) {
            $this->handleChangedSettings('DATASET_HARD_ROW_LIMIT', $this->getConfig()->getSetting('DATASET_HARD_ROW_LIMIT'), $sanitizedParams->getInt('DATASET_HARD_ROW_LIMIT'), $changedSettings);
            $this->getConfig()->changeSetting('DATASET_HARD_ROW_LIMIT', $sanitizedParams->getInt('DATASET_HARD_ROW_LIMIT'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_PURGE_LIST_TTL')) {
            $this->handleChangedSettings('DEFAULT_PURGE_LIST_TTL', $this->getConfig()->getSetting('DEFAULT_PURGE_LIST_TTL'), $sanitizedParams->getInt('DEFAULT_PURGE_LIST_TTL'), $changedSettings);
            $this->getConfig()->changeSetting('DEFAULT_PURGE_LIST_TTL', $sanitizedParams->getInt('DEFAULT_PURGE_LIST_TTL'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_LAYOUT')) {
            $this->handleChangedSettings('DEFAULT_LAYOUT', $this->getConfig()->getSetting('DEFAULT_LAYOUT'), $sanitizedParams->getInt('DEFAULT_LAYOUT'), $changedSettings);
            $this->getConfig()->changeSetting('DEFAULT_LAYOUT', $sanitizedParams->getInt('DEFAULT_LAYOUT'));
        }

        if ($this->getConfig()->isSettingEditable('XMR_ADDRESS')) {
            $this->handleChangedSettings('XMR_ADDRESS', $this->getConfig()->getSetting('XMR_ADDRESS'), $sanitizedParams->getString('XMR_ADDRESS'), $changedSettings);
            $this->getConfig()->changeSetting('XMR_ADDRESS', $sanitizedParams->getString('XMR_ADDRESS'));
        }

        if ($this->getConfig()->isSettingEditable('XMR_PUB_ADDRESS')) {
            $this->handleChangedSettings('XMR_PUB_ADDRESS', $this->getConfig()->getSetting('XMR_PUB_ADDRESS'), $sanitizedParams->getString('XMR_PUB_ADDRESS'), $changedSettings);
            $this->getConfig()->changeSetting('XMR_PUB_ADDRESS', $sanitizedParams->getString('XMR_PUB_ADDRESS'));
        }

        if ($this->getConfig()->isSettingEditable('XMR_WS_ADDRESS')) {
            $this->handleChangedSettings('XMR_WS_ADDRESS', $this->getConfig()->getSetting('XMR_WS_ADDRESS'), $sanitizedParams->getString('XMR_WS_ADDRESS'), $changedSettings);
            $this->getConfig()->changeSetting('XMR_WS_ADDRESS', $sanitizedParams->getString('XMR_WS_ADDRESS'), 1);
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_LAT')) {
            $value = $sanitizedParams->getString('DEFAULT_LAT');
            $this->handleChangedSettings('DEFAULT_LAT', $this->getConfig()->getSetting('DEFAULT_LAT'), $value, $changedSettings);
            $this->getConfig()->changeSetting('DEFAULT_LAT', $value);

            if (!v::latitude()->validate($value)) {
                throw new InvalidArgumentException(__('The latitude entered is not valid.'), 'DEFAULT_LAT');
            }
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_LONG')) {
            $value = $sanitizedParams->getString('DEFAULT_LONG');
            $this->handleChangedSettings('DEFAULT_LONG', $this->getConfig()->getSetting('DEFAULT_LONG'), $value, $changedSettings);
            $this->getConfig()->changeSetting('DEFAULT_LONG', $value);

            if (!v::longitude()->validate($value)) {
                throw new InvalidArgumentException(__('The longitude entered is not valid.'), 'DEFAULT_LONG');
            }
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER')) {
            $this->handleChangedSettings('DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER', $this->getConfig()->getSetting('DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER'), $sanitizedParams->getInt('DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER'), $changedSettings);
            $this->getConfig()->changeSetting('DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER', $sanitizedParams->getInt('DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER_LIMIT')) {
            $this->handleChangedSettings('DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER_LIMIT', $this->getConfig()->getSetting('DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER_LIMIT'), $sanitizedParams->getInt('DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER_LIMIT'), $changedSettings);
            $this->getConfig()->changeSetting('DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER_LIMIT', $sanitizedParams->getInt('DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER_LIMIT'));
        }

        if ($this->getConfig()->isSettingEditable('SHOW_DISPLAY_AS_VNCLINK')) {
            $this->handleChangedSettings('SHOW_DISPLAY_AS_VNCLINK', $this->getConfig()->getSetting('SHOW_DISPLAY_AS_VNCLINK'), $sanitizedParams->getString('SHOW_DISPLAY_AS_VNCLINK'), $changedSettings);
            $this->getConfig()->changeSetting('SHOW_DISPLAY_AS_VNCLINK', $sanitizedParams->getString('SHOW_DISPLAY_AS_VNCLINK'));
        }

        if ($this->getConfig()->isSettingEditable('SHOW_DISPLAY_AS_VNC_TGT')) {
            $this->handleChangedSettings('SHOW_DISPLAY_AS_VNC_TGT', $this->getConfig()->getSetting('SHOW_DISPLAY_AS_VNC_TGT'), $sanitizedParams->getString('SHOW_DISPLAY_AS_VNC_TGT'), $changedSettings);
            $this->getConfig()->changeSetting('SHOW_DISPLAY_AS_VNC_TGT', $sanitizedParams->getString('SHOW_DISPLAY_AS_VNC_TGT'));
        }

        if ($this->getConfig()->isSettingEditable('MAX_LICENSED_DISPLAYS')) {
            $this->handleChangedSettings('MAX_LICENSED_DISPLAYS', $this->getConfig()->getSetting('MAX_LICENSED_DISPLAYS'), $sanitizedParams->getInt('MAX_LICENSED_DISPLAYS'), $changedSettings);
            $this->getConfig()->changeSetting('MAX_LICENSED_DISPLAYS', $sanitizedParams->getInt('MAX_LICENSED_DISPLAYS'));
        }

        if ($this->getConfig()->isSettingEditable('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT')) {
            $this->handleChangedSettings('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT', $this->getConfig()->getSetting('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT'), $sanitizedParams->getString('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT'), $changedSettings);
            $this->getConfig()->changeSetting('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT', $sanitizedParams->getString('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT'));
        }

        if ($this->getConfig()->isSettingEditable('DISPLAY_PROFILE_STATS_DEFAULT')) {
            $this->handleChangedSettings('DISPLAY_PROFILE_STATS_DEFAULT', $this->getConfig()->getSetting('DISPLAY_PROFILE_STATS_DEFAULT'), $sanitizedParams->getCheckbox('DISPLAY_PROFILE_STATS_DEFAULT'), $changedSettings);
            $this->getConfig()->changeSetting('DISPLAY_PROFILE_STATS_DEFAULT', $sanitizedParams->getCheckbox('DISPLAY_PROFILE_STATS_DEFAULT'));
        }

        if ($this->getConfig()->isSettingEditable('LAYOUT_STATS_ENABLED_DEFAULT')) {
            $this->handleChangedSettings('LAYOUT_STATS_ENABLED_DEFAULT', $this->getConfig()->getSetting('LAYOUT_STATS_ENABLED_DEFAULT'), $sanitizedParams->getCheckbox('LAYOUT_STATS_ENABLED_DEFAULT'), $changedSettings);
            $this->getConfig()->changeSetting('LAYOUT_STATS_ENABLED_DEFAULT', $sanitizedParams->getCheckbox('LAYOUT_STATS_ENABLED_DEFAULT'));
        }

        if ($this->getConfig()->isSettingEditable('PLAYLIST_STATS_ENABLED_DEFAULT')) {
            $this->handleChangedSettings('PLAYLIST_STATS_ENABLED_DEFAULT', $this->getConfig()->getSetting('PLAYLIST_STATS_ENABLED_DEFAULT'), $sanitizedParams->getString('PLAYLIST_STATS_ENABLED_DEFAULT'), $changedSettings);
            $this->getConfig()->changeSetting('PLAYLIST_STATS_ENABLED_DEFAULT', $sanitizedParams->getString('PLAYLIST_STATS_ENABLED_DEFAULT'));
        }

        if ($this->getConfig()->isSettingEditable('MEDIA_STATS_ENABLED_DEFAULT')) {
            $this->handleChangedSettings('MEDIA_STATS_ENABLED_DEFAULT', $this->getConfig()->getSetting('MEDIA_STATS_ENABLED_DEFAULT'), $sanitizedParams->getString('MEDIA_STATS_ENABLED_DEFAULT'), $changedSettings);
            $this->getConfig()->changeSetting('MEDIA_STATS_ENABLED_DEFAULT', $sanitizedParams->getString('MEDIA_STATS_ENABLED_DEFAULT'));
        }

        if ($this->getConfig()->isSettingEditable('WIDGET_STATS_ENABLED_DEFAULT')) {
            $this->handleChangedSettings('WIDGET_STATS_ENABLED_DEFAULT', $this->getConfig()->getSetting('WIDGET_STATS_ENABLED_DEFAULT'), $sanitizedParams->getString('WIDGET_STATS_ENABLED_DEFAULT'), $changedSettings);
            $this->getConfig()->changeSetting('WIDGET_STATS_ENABLED_DEFAULT', $sanitizedParams->getString('WIDGET_STATS_ENABLED_DEFAULT'));
        }

        if ($this->getConfig()->isSettingEditable('DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED')) {
            $this->handleChangedSettings('DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED', $this->getConfig()->getSetting('DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED'), $sanitizedParams->getCheckbox('DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED'), $changedSettings);
            $this->getConfig()->changeSetting('DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED', $sanitizedParams->getCheckbox('DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED'));
        }

        if ($this->getConfig()->isSettingEditable('DISPLAY_LOCK_NAME_TO_DEVICENAME')) {
            $this->handleChangedSettings('DISPLAY_LOCK_NAME_TO_DEVICENAME', $this->getConfig()->getSetting('DISPLAY_LOCK_NAME_TO_DEVICENAME'), $sanitizedParams->getCheckbox('DISPLAY_LOCK_NAME_TO_DEVICENAME'), $changedSettings);
            $this->getConfig()->changeSetting('DISPLAY_LOCK_NAME_TO_DEVICENAME', $sanitizedParams->getCheckbox('DISPLAY_LOCK_NAME_TO_DEVICENAME'));
        }

        if ($this->getConfig()->isSettingEditable('DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED')) {
            $this->handleChangedSettings('DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED', $this->getConfig()->getSetting('DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED'), $sanitizedParams->getCheckbox('DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED'), $changedSettings);
            $this->getConfig()->changeSetting('DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED', $sanitizedParams->getCheckbox('DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED'));
        }

        if ($this->getConfig()->isSettingEditable('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT')) {
            $this->handleChangedSettings('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT', $this->getConfig()->getSetting('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT'), $sanitizedParams->getInt('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT'), $changedSettings);
            $this->getConfig()->changeSetting('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT', $sanitizedParams->getInt('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT'));
        }

        if ($this->getConfig()->isSettingEditable('DISPLAY_SCREENSHOT_TTL')) {
            $this->handleChangedSettings('DISPLAY_SCREENSHOT_TTL', $this->getConfig()->getSetting('DISPLAY_SCREENSHOT_TTL'), $sanitizedParams->getInt('DISPLAY_SCREENSHOT_TTL'), $changedSettings);
            $this->getConfig()->changeSetting('DISPLAY_SCREENSHOT_TTL', $sanitizedParams->getInt('DISPLAY_SCREENSHOT_TTL'));
        }

        if ($this->getConfig()->isSettingEditable('DISPLAY_AUTO_AUTH')) {
            $this->handleChangedSettings('DISPLAY_AUTO_AUTH', $this->getConfig()->getSetting('DISPLAY_AUTO_AUTH'), $sanitizedParams->getCheckbox('DISPLAY_AUTO_AUTH'), $changedSettings);
            $this->getConfig()->changeSetting('DISPLAY_AUTO_AUTH', $sanitizedParams->getCheckbox('DISPLAY_AUTO_AUTH'));
        }

        if ($this->getConfig()->isSettingEditable('DISPLAY_DEFAULT_FOLDER')) {
            $this->handleChangedSettings(
                'DISPLAY_DEFAULT_FOLDER',
                $this->getConfig()->getSetting('DISPLAY_DEFAULT_FOLDER'),
                $sanitizedParams->getInt('DISPLAY_DEFAULT_FOLDER'),
                $changedSettings
            );
            $this->getConfig()->changeSetting(
                'DISPLAY_DEFAULT_FOLDER',
                $sanitizedParams->getInt('DISPLAY_DEFAULT_FOLDER'),
                1
            );
        }

        if ($this->getConfig()->isSettingEditable('HELP_BASE')) {
            $this->handleChangedSettings('HELP_BASE', $this->getConfig()->getSetting('HELP_BASE'), $sanitizedParams->getString('HELP_BASE'), $changedSettings);
            $this->getConfig()->changeSetting('HELP_BASE', $sanitizedParams->getString('HELP_BASE'));
        }

        if ($this->getConfig()->isSettingEditable('QUICK_CHART_URL')) {
            $this->handleChangedSettings('QUICK_CHART_URL', $this->getConfig()->getSetting('QUICK_CHART_URL'), $sanitizedParams->getString('QUICK_CHART_URL'), $changedSettings);
            $this->getConfig()->changeSetting('QUICK_CHART_URL', $sanitizedParams->getString('QUICK_CHART_URL'));
        }

        if ($this->getConfig()->isSettingEditable('PHONE_HOME')) {
            $this->handleChangedSettings('PHONE_HOME', $this->getConfig()->getSetting('PHONE_HOME'), $sanitizedParams->getCheckbox('PHONE_HOME'), $changedSettings);
            $this->getConfig()->changeSetting('PHONE_HOME', $sanitizedParams->getCheckbox('PHONE_HOME'));
        }

        if ($this->getConfig()->isSettingEditable('PHONE_HOME_KEY')) {
            $this->handleChangedSettings('PHONE_HOME_KEY', $this->getConfig()->getSetting('PHONE_HOME_KEY'), $sanitizedParams->getString('PHONE_HOME_KEY'), $changedSettings);
            $this->getConfig()->changeSetting('PHONE_HOME_KEY', $sanitizedParams->getString('PHONE_HOME_KEY'));
        }

        if ($this->getConfig()->isSettingEditable('PHONE_HOME_DATE')) {
            $this->handleChangedSettings('PHONE_HOME_DATE', $this->getConfig()->getSetting('PHONE_HOME_DATE'), $sanitizedParams->getInt('PHONE_HOME_DATE'), $changedSettings);
            $this->getConfig()->changeSetting('PHONE_HOME_DATE', $sanitizedParams->getInt('PHONE_HOME_DATE'));
        }

        if ($this->getConfig()->isSettingEditable('PHONE_HOME_URL')) {
            $this->handleChangedSettings('PHONE_HOME_URL', $this->getConfig()->getSetting('PHONE_HOME_URL'), $sanitizedParams->getString('PHONE_HOME_URL'), $changedSettings);
            $this->getConfig()->changeSetting('PHONE_HOME_URL', $sanitizedParams->getString('PHONE_HOME_URL'));
        }

        if ($this->getConfig()->isSettingEditable('SCHEDULE_LOOKAHEAD')) {
            $this->handleChangedSettings('SCHEDULE_LOOKAHEAD', $this->getConfig()->getSetting('SCHEDULE_LOOKAHEAD'), $sanitizedParams->getCheckbox('SCHEDULE_LOOKAHEAD'), $changedSettings);
            $this->getConfig()->changeSetting('SCHEDULE_LOOKAHEAD', $sanitizedParams->getCheckbox('SCHEDULE_LOOKAHEAD'));
        }

        if ($this->getConfig()->isSettingEditable('REQUIRED_FILES_LOOKAHEAD')) {
            $this->handleChangedSettings('REQUIRED_FILES_LOOKAHEAD', $this->getConfig()->getSetting('REQUIRED_FILES_LOOKAHEAD'), $sanitizedParams->getInt('REQUIRED_FILES_LOOKAHEAD'), $changedSettings);
            $this->getConfig()->changeSetting('REQUIRED_FILES_LOOKAHEAD', $sanitizedParams->getInt('REQUIRED_FILES_LOOKAHEAD'));
        }

        if ($this->getConfig()->isSettingEditable('SETTING_IMPORT_ENABLED')) {
            $this->handleChangedSettings('SETTING_IMPORT_ENABLED', $this->getConfig()->getSetting('SETTING_IMPORT_ENABLED'), $sanitizedParams->getCheckbox('SETTING_IMPORT_ENABLED'), $changedSettings);
            $this->getConfig()->changeSetting('SETTING_IMPORT_ENABLED', $sanitizedParams->getCheckbox('SETTING_IMPORT_ENABLED'));
        }

        if ($this->getConfig()->isSettingEditable('SETTING_LIBRARY_TIDY_ENABLED')) {
            $this->handleChangedSettings('SETTING_LIBRARY_TIDY_ENABLED', $this->getConfig()->getSetting('SETTING_LIBRARY_TIDY_ENABLED'), $sanitizedParams->getCheckbox('SETTING_LIBRARY_TIDY_ENABLED'), $changedSettings);
            $this->getConfig()->changeSetting('SETTING_LIBRARY_TIDY_ENABLED', $sanitizedParams->getCheckbox('SETTING_LIBRARY_TIDY_ENABLED'));
        }

        if ($this->getConfig()->isSettingEditable('EMBEDDED_STATUS_WIDGET')) {
            $this->handleChangedSettings('EMBEDDED_STATUS_WIDGET', $this->getConfig()->getSetting('EMBEDDED_STATUS_WIDGET'), $sanitizedParams->getString('EMBEDDED_STATUS_WIDGET'), $changedSettings);
            $this->getConfig()->changeSetting('EMBEDDED_STATUS_WIDGET', $sanitizedParams->getString('EMBEDDED_STATUS_WIDGET'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULTS_IMPORTED')) {
            $this->handleChangedSettings('DEFAULTS_IMPORTED', $this->getConfig()->getSetting('DEFAULTS_IMPORTED'), $sanitizedParams->getCheckbox('DEFAULTS_IMPORTED'), $changedSettings);
            $this->getConfig()->changeSetting('DEFAULTS_IMPORTED', $sanitizedParams->getCheckbox('DEFAULTS_IMPORTED'));
        }

        if ($this->getConfig()->isSettingEditable('DASHBOARD_LATEST_NEWS_ENABLED')) {
            $this->handleChangedSettings('DASHBOARD_LATEST_NEWS_ENABLED', $this->getConfig()->getSetting('DASHBOARD_LATEST_NEWS_ENABLED'), $sanitizedParams->getCheckbox('DASHBOARD_LATEST_NEWS_ENABLED'), $changedSettings);
            $this->getConfig()->changeSetting('DASHBOARD_LATEST_NEWS_ENABLED', $sanitizedParams->getCheckbox('DASHBOARD_LATEST_NEWS_ENABLED'));
        }

        if ($this->getConfig()->isSettingEditable('INSTANCE_SUSPENDED')) {
            $this->handleChangedSettings('INSTANCE_SUSPENDED', $this->getConfig()->getSetting('INSTANCE_SUSPENDED'), $sanitizedParams->getCheckbox('INSTANCE_SUSPENDED'), $changedSettings);
            $this->getConfig()->changeSetting('INSTANCE_SUSPENDED', $sanitizedParams->getCheckbox('INSTANCE_SUSPENDED'));
        }

        if ($this->getConfig()->isSettingEditable('LATEST_NEWS_URL')) {
            $this->handleChangedSettings('LATEST_NEWS_URL', $this->getConfig()->getSetting('LATEST_NEWS_URL'), $sanitizedParams->getString('LATEST_NEWS_URL'), $changedSettings);
            $this->getConfig()->changeSetting('LATEST_NEWS_URL', $sanitizedParams->getString('LATEST_NEWS_URL'));
        }

        if ($this->getConfig()->isSettingEditable('REPORTS_EXPORT_SHOW_LOGO')) {
            $this->handleChangedSettings(
                'REPORTS_EXPORT_SHOW_LOGO',
                $this->getConfig()->getSetting('REPORTS_EXPORT_SHOW_LOGO'),
                $sanitizedParams->getCheckbox('REPORTS_EXPORT_SHOW_LOGO'),
                $changedSettings
            );
            $this->getConfig()->changeSetting(
                'REPORTS_EXPORT_SHOW_LOGO',
                $sanitizedParams->getCheckbox('REPORTS_EXPORT_SHOW_LOGO')
            );
        }

        if ($this->getConfig()->isSettingEditable('MAINTENANCE_ENABLED')) {
            $this->handleChangedSettings('MAINTENANCE_ENABLED', $this->getConfig()->getSetting('MAINTENANCE_ENABLED'), $sanitizedParams->getString('MAINTENANCE_ENABLED'), $changedSettings);
            $this->getConfig()->changeSetting('MAINTENANCE_ENABLED', $sanitizedParams->getString('MAINTENANCE_ENABLED'));
        }

        if ($this->getConfig()->isSettingEditable('MAINTENANCE_EMAIL_ALERTS')) {
            $this->handleChangedSettings('MAINTENANCE_EMAIL_ALERTS', $this->getConfig()->getSetting('MAINTENANCE_EMAIL_ALERTS'), $sanitizedParams->getCheckbox('MAINTENANCE_EMAIL_ALERTS'), $changedSettings);
            $this->getConfig()->changeSetting('MAINTENANCE_EMAIL_ALERTS', $sanitizedParams->getCheckbox('MAINTENANCE_EMAIL_ALERTS'));
        }

        if ($this->getConfig()->isSettingEditable('MAINTENANCE_LOG_MAXAGE')) {
            $this->handleChangedSettings('MAINTENANCE_LOG_MAXAGE', $this->getConfig()->getSetting('MAINTENANCE_LOG_MAXAGE'), $sanitizedParams->getInt('MAINTENANCE_LOG_MAXAGE'), $changedSettings);
            $this->getConfig()->changeSetting('MAINTENANCE_LOG_MAXAGE', $sanitizedParams->getInt('MAINTENANCE_LOG_MAXAGE'));
        }

        if ($this->getConfig()->isSettingEditable('MAINTENANCE_STAT_MAXAGE')) {
            $this->handleChangedSettings('MAINTENANCE_STAT_MAXAGE', $this->getConfig()->getSetting('MAINTENANCE_STAT_MAXAGE'), $sanitizedParams->getInt('MAINTENANCE_STAT_MAXAGE'), $changedSettings);
            $this->getConfig()->changeSetting('MAINTENANCE_STAT_MAXAGE', $sanitizedParams->getInt('MAINTENANCE_STAT_MAXAGE'));
        }

        if ($this->getConfig()->isSettingEditable('MAINTENANCE_ALERT_TOUT')) {
            $this->handleChangedSettings('MAINTENANCE_ALERT_TOUT', $this->getConfig()->getSetting('MAINTENANCE_ALERT_TOUT'), $sanitizedParams->getInt('MAINTENANCE_ALERT_TOUT'), $changedSettings);
            $this->getConfig()->changeSetting('MAINTENANCE_ALERT_TOUT', $sanitizedParams->getInt('MAINTENANCE_ALERT_TOUT'));
        }

        if ($this->getConfig()->isSettingEditable('MAINTENANCE_ALWAYS_ALERT')) {
            $this->handleChangedSettings('MAINTENANCE_ALWAYS_ALERT', $this->getConfig()->getSetting('MAINTENANCE_ALWAYS_ALERT'), $sanitizedParams->getCheckbox('MAINTENANCE_ALWAYS_ALERT'), $changedSettings);
            $this->getConfig()->changeSetting('MAINTENANCE_ALWAYS_ALERT', $sanitizedParams->getCheckbox('MAINTENANCE_ALWAYS_ALERT'));
        }

        if ($this->getConfig()->isSettingEditable('mail_to')) {
            $this->handleChangedSettings('mail_to', $this->getConfig()->getSetting('mail_to'), $sanitizedParams->getString('mail_to'), $changedSettings);
            $this->getConfig()->changeSetting('mail_to', $sanitizedParams->getString('mail_to'));
        }

        if ($this->getConfig()->isSettingEditable('mail_from')) {
            $this->handleChangedSettings('mail_from', $this->getConfig()->getSetting('mail_from'), $sanitizedParams->getString('mail_from'), $changedSettings);
            $this->getConfig()->changeSetting('mail_from', $sanitizedParams->getString('mail_from'));
        }

        if ($this->getConfig()->isSettingEditable('mail_from_name')) {
            $this->handleChangedSettings('mail_from_name', $this->getConfig()->getSetting('mail_from_name'), $sanitizedParams->getString('mail_from_name'), $changedSettings);
            $this->getConfig()->changeSetting('mail_from_name', $sanitizedParams->getString('mail_from_name'));
        }

        if ($this->getConfig()->isSettingEditable('SENDFILE_MODE')) {
            $this->handleChangedSettings('SENDFILE_MODE', $this->getConfig()->getSetting('SENDFILE_MODE'), $sanitizedParams->getString('SENDFILE_MODE'), $changedSettings);
            $this->getConfig()->changeSetting('SENDFILE_MODE', $sanitizedParams->getString('SENDFILE_MODE'));
        }

        if ($this->getConfig()->isSettingEditable('PROXY_HOST')) {
            $this->handleChangedSettings('PROXY_HOST', $this->getConfig()->getSetting('PROXY_HOST'), $sanitizedParams->getString('PROXY_HOST'), $changedSettings);
            $this->getConfig()->changeSetting('PROXY_HOST', $sanitizedParams->getString('PROXY_HOST'));
        }

        if ($this->getConfig()->isSettingEditable('PROXY_PORT')) {
            $this->handleChangedSettings('PROXY_PORT', $this->getConfig()->getSetting('PROXY_PORT'), $sanitizedParams->getString('PROXY_PORT'), $changedSettings);
            $this->getConfig()->changeSetting('PROXY_PORT', $sanitizedParams->getString('PROXY_PORT'));
        }

        if ($this->getConfig()->isSettingEditable('PROXY_AUTH')) {
            $this->handleChangedSettings('PROXY_AUTH', $this->getConfig()->getSetting('PROXY_AUTH'), $sanitizedParams->getString('PROXY_AUTH'), $changedSettings);
            $this->getConfig()->changeSetting('PROXY_AUTH', $sanitizedParams->getString('PROXY_AUTH'));
        }

        if ($this->getConfig()->isSettingEditable('PROXY_EXCEPTIONS')) {
            $this->handleChangedSettings('PROXY_EXCEPTIONS', $this->getConfig()->getSetting('PROXY_EXCEPTIONS'), $sanitizedParams->getString('PROXY_EXCEPTIONS'), $changedSettings);
            $this->getConfig()->changeSetting('PROXY_EXCEPTIONS', $sanitizedParams->getString('PROXY_EXCEPTIONS'));
        }

        if ($this->getConfig()->isSettingEditable('CDN_URL')) {
            $this->handleChangedSettings('CDN_URL', $this->getConfig()->getSetting('CDN_URL'), $sanitizedParams->getString('CDN_URL'), $changedSettings);
            $this->getConfig()->changeSetting('CDN_URL', $sanitizedParams->getString('CDN_URL'));
        }

        if ($this->getConfig()->isSettingEditable('MONTHLY_XMDS_TRANSFER_LIMIT_KB')) {
            $this->handleChangedSettings('MONTHLY_XMDS_TRANSFER_LIMIT_KB', $this->getConfig()->getSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB'), $sanitizedParams->getInt('MONTHLY_XMDS_TRANSFER_LIMIT_KB'), $changedSettings);
            $this->getConfig()->changeSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB', $sanitizedParams->getInt('MONTHLY_XMDS_TRANSFER_LIMIT_KB'));
        }

        if ($this->getConfig()->isSettingEditable('LIBRARY_SIZE_LIMIT_KB')) {
            $this->handleChangedSettings('LIBRARY_SIZE_LIMIT_KB', $this->getConfig()->getSetting('LIBRARY_SIZE_LIMIT_KB'), $sanitizedParams->getInt('LIBRARY_SIZE_LIMIT_KB'), $changedSettings);
            $this->getConfig()->changeSetting('LIBRARY_SIZE_LIMIT_KB', $sanitizedParams->getInt('LIBRARY_SIZE_LIMIT_KB'));
        }

        if ($this->getConfig()->isSettingEditable('FORCE_HTTPS')) {
            $this->handleChangedSettings('FORCE_HTTPS', $this->getConfig()->getSetting('FORCE_HTTPS'), $sanitizedParams->getCheckbox('FORCE_HTTPS'), $changedSettings);
            $this->getConfig()->changeSetting('FORCE_HTTPS', $sanitizedParams->getCheckbox('FORCE_HTTPS'));
        }

        if ($this->getConfig()->isSettingEditable('ISSUE_STS')) {
            $this->handleChangedSettings('ISSUE_STS', $this->getConfig()->getSetting('ISSUE_STS'), $sanitizedParams->getCheckbox('ISSUE_STS'), $changedSettings);
            $this->getConfig()->changeSetting('ISSUE_STS', $sanitizedParams->getCheckbox('ISSUE_STS'));
        }

        if ($this->getConfig()->isSettingEditable('STS_TTL')) {
            $this->handleChangedSettings('STS_TTL', $this->getConfig()->getSetting('STS_TTL'), $sanitizedParams->getInt('STS_TTL'), $changedSettings);
            $this->getConfig()->changeSetting('STS_TTL', $sanitizedParams->getInt('STS_TTL'));
        }

        if ($this->getConfig()->isSettingEditable('WHITELIST_LOAD_BALANCERS')) {
            $this->handleChangedSettings('WHITELIST_LOAD_BALANCERS', $this->getConfig()->getSetting('WHITELIST_LOAD_BALANCERS'), $sanitizedParams->getString('WHITELIST_LOAD_BALANCERS'), $changedSettings);
            $this->getConfig()->changeSetting('WHITELIST_LOAD_BALANCERS', $sanitizedParams->getString('WHITELIST_LOAD_BALANCERS'));
        }

        if ($this->getConfig()->isSettingEditable('REGION_OPTIONS_COLOURING')) {
            $this->handleChangedSettings('REGION_OPTIONS_COLOURING', $this->getConfig()->getSetting('REGION_OPTIONS_COLOURING'), $sanitizedParams->getString('REGION_OPTIONS_COLOURING'), $changedSettings);
            $this->getConfig()->changeSetting('REGION_OPTIONS_COLOURING', $sanitizedParams->getString('REGION_OPTIONS_COLOURING'));
        }

        if ($this->getConfig()->isSettingEditable('SCHEDULE_WITH_VIEW_PERMISSION')) {
            $this->handleChangedSettings('SCHEDULE_WITH_VIEW_PERMISSION', $this->getConfig()->getSetting('SCHEDULE_WITH_VIEW_PERMISSION'), $sanitizedParams->getCheckbox('SCHEDULE_WITH_VIEW_PERMISSION'), $changedSettings);
            $this->getConfig()->changeSetting('SCHEDULE_WITH_VIEW_PERMISSION', $sanitizedParams->getCheckbox('SCHEDULE_WITH_VIEW_PERMISSION'));
        }

        if ($this->getConfig()->isSettingEditable('SCHEDULE_SHOW_LAYOUT_NAME')) {
            $this->handleChangedSettings('SCHEDULE_SHOW_LAYOUT_NAME', $this->getConfig()->getSetting('SCHEDULE_SHOW_LAYOUT_NAME'), $sanitizedParams->getCheckbox('SCHEDULE_SHOW_LAYOUT_NAME'), $changedSettings);
            $this->getConfig()->changeSetting('SCHEDULE_SHOW_LAYOUT_NAME', $sanitizedParams->getCheckbox('SCHEDULE_SHOW_LAYOUT_NAME'));
        }

        if ($this->getConfig()->isSettingEditable('TASK_CONFIG_LOCKED_CHECKB')) {
            $this->handleChangedSettings('TASK_CONFIG_LOCKED_CHECKB', $this->getConfig()->getSetting('TASK_CONFIG_LOCKED_CHECKB'), $sanitizedParams->getCheckbox('TASK_CONFIG_LOCKED_CHECKB'), $changedSettings);
            $this->getConfig()->changeSetting('TASK_CONFIG_LOCKED_CHECKB', $sanitizedParams->getCheckbox('TASK_CONFIG_LOCKED_CHECKB'));
        }

        if ($this->getConfig()->isSettingEditable('TRANSITION_CONFIG_LOCKED_CHECKB')) {
            $this->handleChangedSettings('TRANSITION_CONFIG_LOCKED_CHECKB', $this->getConfig()->getSetting('TRANSITION_CONFIG_LOCKED_CHECKB'), $sanitizedParams->getCheckbox('TRANSITION_CONFIG_LOCKED_CHECKB'), $changedSettings);
            $this->getConfig()->changeSetting('TRANSITION_CONFIG_LOCKED_CHECKB', $sanitizedParams->getCheckbox('TRANSITION_CONFIG_LOCKED_CHECKB'));
        }

        if ($this->getConfig()->isSettingEditable('FOLDERS_ALLOW_SAVE_IN_ROOT')) {
            $this->handleChangedSettings('FOLDERS_ALLOW_SAVE_IN_ROOT', $this->getConfig()->getSetting('FOLDERS_ALLOW_SAVE_IN_ROOT'), $sanitizedParams->getCheckbox('FOLDERS_ALLOW_SAVE_IN_ROOT'), $changedSettings);
            $this->getConfig()->changeSetting('FOLDERS_ALLOW_SAVE_IN_ROOT', $sanitizedParams->getCheckbox('FOLDERS_ALLOW_SAVE_IN_ROOT'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_LANGUAGE')) {
            $this->handleChangedSettings('DEFAULT_LANGUAGE', $this->getConfig()->getSetting('DEFAULT_LANGUAGE'), $sanitizedParams->getString('DEFAULT_LANGUAGE'), $changedSettings);
            $this->getConfig()->changeSetting('DEFAULT_LANGUAGE', $sanitizedParams->getString('DEFAULT_LANGUAGE'));
        }

        if ($this->getConfig()->isSettingEditable('defaultTimezone')) {
            $this->handleChangedSettings('defaultTimezone', $this->getConfig()->getSetting('defaultTimezone'), $sanitizedParams->getString('defaultTimezone'), $changedSettings);
            $this->getConfig()->changeSetting('defaultTimezone', $sanitizedParams->getString('defaultTimezone'));
        }

        if ($this->getConfig()->isSettingEditable('DATE_FORMAT')) {
            $this->handleChangedSettings('DATE_FORMAT', $this->getConfig()->getSetting('DATE_FORMAT'), $sanitizedParams->getString('DATE_FORMAT'), $changedSettings);
            $this->getConfig()->changeSetting('DATE_FORMAT', $sanitizedParams->getString('DATE_FORMAT'));
        }

        if ($this->getConfig()->isSettingEditable('DETECT_LANGUAGE')) {
            $this->handleChangedSettings('DETECT_LANGUAGE', $this->getConfig()->getSetting('DETECT_LANGUAGE'), $sanitizedParams->getCheckbox('DETECT_LANGUAGE'), $changedSettings);
            $this->getConfig()->changeSetting('DETECT_LANGUAGE', $sanitizedParams->getCheckbox('DETECT_LANGUAGE'));
        }

        if ($this->getConfig()->isSettingEditable('CALENDAR_TYPE')) {
            $this->handleChangedSettings('CALENDAR_TYPE', $this->getConfig()->getSetting('CALENDAR_TYPE'), $sanitizedParams->getString('CALENDAR_TYPE'), $changedSettings);
            $this->getConfig()->changeSetting('CALENDAR_TYPE', $sanitizedParams->getString('CALENDAR_TYPE'));
        }

        if ($this->getConfig()->isSettingEditable('RESTING_LOG_LEVEL')) {
            $this->handleChangedSettings('RESTING_LOG_LEVEL', $this->getConfig()->getSetting('RESTING_LOG_LEVEL'), $sanitizedParams->getString('RESTING_LOG_LEVEL'), $changedSettings);
            $this->getConfig()->changeSetting('RESTING_LOG_LEVEL', $sanitizedParams->getString('RESTING_LOG_LEVEL'));
        }

        // Handle changes to log level
        $newLogLevel = null;
        $newElevateUntil = null;
        $currentLogLevel = $this->getConfig()->getSetting('audit');

        if ($this->getConfig()->isSettingEditable('audit')) {
            $newLogLevel = $sanitizedParams->getString('audit');
            $this->handleChangedSettings('audit', $this->getConfig()->getSetting('audit'), $newLogLevel, $changedSettings);
            $this->getConfig()->changeSetting('audit', $newLogLevel);
        }

        if ($this->getConfig()->isSettingEditable('ELEVATE_LOG_UNTIL') && $sanitizedParams->getDate('ELEVATE_LOG_UNTIL') != null) {
            $newElevateUntil = $sanitizedParams->getDate('ELEVATE_LOG_UNTIL')->format('U');
            $this->handleChangedSettings('ELEVATE_LOG_UNTIL', $this->getConfig()->getSetting('ELEVATE_LOG_UNTIL'), $newElevateUntil, $changedSettings);
            $this->getConfig()->changeSetting('ELEVATE_LOG_UNTIL', $newElevateUntil);
        }

        // Have we changed log level? If so, were we also provided the elevate until setting?
        if ($newElevateUntil === null && $currentLogLevel != $newLogLevel) {
            // We haven't provided an elevate until (meaning it is not visible)
            $this->getConfig()->changeSetting('ELEVATE_LOG_UNTIL', Carbon::now()->addHour()->format('U'));
        }

        if ($this->getConfig()->isSettingEditable('SERVER_MODE')) {
            $this->handleChangedSettings('SERVER_MODE', $this->getConfig()->getSetting('SERVER_MODE'), $sanitizedParams->getString('SERVER_MODE'), $changedSettings);
            $this->getConfig()->changeSetting('SERVER_MODE', $sanitizedParams->getString('SERVER_MODE'));
        }

        if ($this->getConfig()->isSettingEditable('SYSTEM_USER')) {
            $this->handleChangedSettings('SYSTEM_USER', $this->getConfig()->getSetting('SYSTEM_USER'), $sanitizedParams->getInt('SYSTEM_USER'), $changedSettings);
            $this->getConfig()->changeSetting('SYSTEM_USER', $sanitizedParams->getInt('SYSTEM_USER'));
        }

        if ($this->getConfig()->isSettingEditable('DEFAULT_USERGROUP')) {
            $this->handleChangedSettings('DEFAULT_USERGROUP', $this->getConfig()->getSetting('DEFAULT_USERGROUP'), $sanitizedParams->getInt('DEFAULT_USERGROUP'), $changedSettings);
            $this->getConfig()->changeSetting('DEFAULT_USERGROUP', $sanitizedParams->getInt('DEFAULT_USERGROUP'));
        }

        if ($this->getConfig()->isSettingEditable('defaultUsertype')) {
            $this->handleChangedSettings('defaultUsertype', $this->getConfig()->getSetting('defaultUsertype'), $sanitizedParams->getString('defaultUsertype'), $changedSettings);
            $this->getConfig()->changeSetting('defaultUsertype', $sanitizedParams->getString('defaultUsertype'));
        }

        if ($this->getConfig()->isSettingEditable('USER_PASSWORD_POLICY')) {
            $this->handleChangedSettings('USER_PASSWORD_POLICY', $this->getConfig()->getSetting('USER_PASSWORD_POLICY'), $sanitizedParams->getString('USER_PASSWORD_POLICY'), $changedSettings);
            $this->getConfig()->changeSetting('USER_PASSWORD_POLICY', $sanitizedParams->getString('USER_PASSWORD_POLICY'));
        }

        if ($this->getConfig()->isSettingEditable('USER_PASSWORD_ERROR')) {
            $this->handleChangedSettings('USER_PASSWORD_ERROR', $this->getConfig()->getSetting('USER_PASSWORD_ERROR'), $sanitizedParams->getString('USER_PASSWORD_ERROR'), $changedSettings);
            $this->getConfig()->changeSetting('USER_PASSWORD_ERROR', $sanitizedParams->getString('USER_PASSWORD_ERROR'));
        }

        if ($this->getConfig()->isSettingEditable('PASSWORD_REMINDER_ENABLED')) {
            $this->handleChangedSettings('PASSWORD_REMINDER_ENABLED', $this->getConfig()->getSetting('PASSWORD_REMINDER_ENABLED'), $sanitizedParams->getString('PASSWORD_REMINDER_ENABLED'), $changedSettings);
            $this->getConfig()->changeSetting('PASSWORD_REMINDER_ENABLED', $sanitizedParams->getString('PASSWORD_REMINDER_ENABLED'));
        }

        if ($this->getConfig()->isSettingEditable('TWOFACTOR_ISSUER')) {
            $this->handleChangedSettings('TWOFACTOR_ISSUER', $this->getConfig()->getSetting('TWOFACTOR_ISSUER'), $sanitizedParams->getString('TWOFACTOR_ISSUER'), $changedSettings);
            $this->getConfig()->changeSetting('TWOFACTOR_ISSUER', $sanitizedParams->getString('TWOFACTOR_ISSUER'));
        }

        if ($changedSettings != []) {
            $this->getLog()->audit('Settings', 0, 'Updated', $changedSettings);
        }

        // Return
        $this->getState()->hydrate([
            'message' => __('Settings Updated')
        ]);

        return $this->render($request, $response);
    }

    private function handleChangedSettings($setting, $oldValue, $newValue, &$changedSettings)
    {
        if ($oldValue != $newValue) {
            if ($setting === 'SYSTEM_USER') {
                $newSystemUser = $this->userFactory->getById($newValue);
                $oldSystemUser = $this->userFactory->getById($oldValue);
                $this->getDispatcher()->dispatch(SystemUserChangedEvent::$NAME, new SystemUserChangedEvent($oldSystemUser, $newSystemUser));
            } elseif ($setting === 'DEFAULT_DYNAMIC_PLAYLIST_MAXNUMBER_LIMIT') {
                $this->getDispatcher()->dispatch(PlaylistMaxNumberChangedEvent::$NAME, new PlaylistMaxNumberChangedEvent($newValue));
            }
            if ($setting === 'ELEVATE_LOG_UNTIL') {
                $changedSettings[$setting] = Carbon::createFromTimestamp($oldValue)->format(DateFormatHelper::getSystemFormat()) . ' > ' .  Carbon::createFromTimestamp($newValue)->format(DateFormatHelper::getSystemFormat());
            } else {
                $changedSettings[$setting] = $oldValue . ' > ' . $newValue;
            }
        }
    }
}
