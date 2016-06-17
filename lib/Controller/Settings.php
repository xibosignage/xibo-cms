<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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
use Stash\Interfaces\PoolInterface;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\SettingsFactory;
use Xibo\Helper\Form;
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
    /**
     * @var PoolInterface
     */
    private $pool;

    /**
     * @var SettingsFactory
     */
    private $settingsFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param PoolInterface $pool
     * @param SettingsFactory $settingsFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $pool, $settingsFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->pool = $pool;
        $this->settingsFactory = $settingsFactory;
    }

    /**
     *
     */
    function displayPage()
    {
        // Get all of the settings in an array
        $settings = $this->settingsFactory->query(null, ['userSee' => 1]);

        $currentCategory = '';
        $categories = array();
        $formFields = array();

        // Should we hide other themes?
        $hideThemes = $this->getConfig()->getThemeConfig('hide_others');

        // Go through each setting, validate it and add it to the array
        foreach ($settings as $setting) {

            if ($currentCategory != $setting['cat']) {
                $currentCategory = $setting['cat'];
                $categories[] = array('tabId' => $setting['cat'], 'tabName' => ucfirst($setting['cat']));
            }

            // Special handling for the theme selector
            if (!$hideThemes && $setting['setting'] == 'GLOBAL_THEME_NAME') {
                // Convert to a drop down containing the theme names that are available
                $setting['fieldType'] = 'dropdown';

                $directory = new \RecursiveDirectoryIterator(PROJECT_ROOT . '/web/theme', \FilesystemIterator::SKIP_DOTS);
                $filter = new \RecursiveCallbackFilterIterator($directory, function($current, $key, $iterator) {

                    if ($current->isDir()) {
                        return true;
                    }

                    return strpos($current->getFilename(), 'config.php') === 0;
                });

                $iterator = new \RecursiveIteratorIterator($filter);

                // Add options for all themes installed
                $options = [];
                foreach($iterator as $file) {
                    /* @var \SplFileInfo $file */
                    $this->getLog()->debug('Found %s', $file->getPath());

                    // Include the config file
                    include $file->getPath() . '/' . $file->getFilename();

                    $options[] = ['id' => basename($file->getPath()), 'value' => $config['theme_name']];
                }

            } else if ($setting['setting'] == 'ELEVATE_LOG_UNTIL') {

                // If we are less that the current date, then show as empty
                if (intval($setting['value']) >= time()) {
                    $setting['value'] = $this->getDate()->getLocalDate($setting['value']);
                } else {
                    $setting['value'] = null;
                }

            } else {
                // Are there any options
                $options = NULL;
                if (!empty($setting['options'])) {
                    // Change to an id=>value array
                    foreach (explode('|', $setting['options']) as $tempOption)
                        $options[] = array('id' => $tempOption, 'value' => $tempOption);
                }
            }

            // Validate the current setting
            if ($setting['type'] == 'checkbox' && isset($setting['value']))
                $validated = $setting['value'];
            else if (isset($setting['value']))
                $validated = $setting['value'];
            else
                $validated = $setting['default'];

            // Time zone type requires special handling.
            if ($setting['fieldType'] == 'timezone') {
                $options = [];
                foreach ($this->getDate()->timezoneList() as $key => $value) {
                    $options[] = ['id' => $key, 'value' => $value];
                }
            }

            // Get a list of settings and assign them to the settings field
            $formFields[] = array(
                'name' => $setting['setting'],
                'type' => $setting['type'],
                'fieldType' => $setting['fieldType'],
                'helpText' => __($setting['helptext']),
                'title' => __($setting['title']),
                'options' => $options,
                'validation' => $setting['validation'],
                'value' => $validated,
                'enabled' => $setting['userChange'],
                'catId' => $setting['cat'],
                'cat' => ucfirst($setting['cat'])
            );
        }

        $data = [
            'categories' => $categories,
            'fields' => $formFields
        ];

        // Render the Theme and output
        $this->getState()->template = 'settings-page';
        $this->getState()->setData($data);
    }

    function update()
    {
        if (!$this->getUser()->userTypeId == 1)
            throw new AccessDeniedException();

        // Clear all cache
        $this->pool->deleteItem('config/');

        // Get all of the settings in an array
        $settings = $this->settingsFactory->query(null, ['userChange' => 1, 'userSee' => 1]);

        // Go through each setting, validate it and add it to the array
        foreach ($settings as $setting) {
            // Check to see if we have a setting that matches in the provided POST vars.
            switch ($setting['type']) {
                case 'string':
                    $value = $this->getSanitizer()->getString($setting['setting'], $setting['default']);
                    break;

                case 'int':
                    $value = $this->getSanitizer()->getInt($setting['setting'], $setting['default']);
                    break;

                case 'double':
                    $value = $this->getSanitizer()->getDouble($setting['setting'], $setting['default']);
                    break;

                case 'checkbox':
                    $value = $this->getSanitizer()->getCheckbox($setting['setting']);
                    break;

                case 'datetime':
                    $value = $this->getSanitizer()->getDate($setting['setting']);

                    if ($value !== null)
                        $value = $value->format('U');
                    else
                        $value = '';

                    break;

                default:
                    $value = $this->getSanitizer()->getParam($setting['setting'], $setting['default']);
            }

            // Check the library location setting
            if ($setting['setting'] == 'LIBRARY_LOCATION') {
                // Check for a trailing slash and add it if its not there
                $value = rtrim($value, '/');
                $value = rtrim($value, '\\') . DIRECTORY_SEPARATOR;

                // Attempt to add the directory specified
                if (!file_exists($value . 'temp'))
                    // Make the directory with broad permissions recursively (so will add the whole path)
                    mkdir($value . 'temp', 0777, true);

                if (!is_writable($value . 'temp'))
                    trigger_error(__('The Library Location you have picked is not writeable'), E_USER_ERROR);
            }

            $this->getConfig()->ChangeSetting($setting['setting'], $value);
        }

        // Return
        $this->getState()->hydrate([
            'message' => __('Settings Updated')
        ]);
    }
}
