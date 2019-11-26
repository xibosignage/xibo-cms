<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-13 Daniel Garner
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

use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LogFactory;
use Xibo\Helper\Environment;
use Xibo\Helper\Random;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Fault
 * @package Xibo\Controller
 */
class Fault extends Base
{
    /** @var  StorageServiceInterface */
    private $store;

    /**
     * @var LogFactory
     */
    private $logFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param StorageServiceInterface $store
     * @param LogFactory $logFactory
     * @param DisplayFactory $displayFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $logFactory, $displayFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->store = $store;
        $this->logFactory = $logFactory;
        $this->displayFactory = $displayFactory;
    }

    function displayPage()
    {
        $url = $this->getApp()->request()->getUrl() . $this->getApp()->request()->getPathInfo();

        $config = $this->getConfig();
        $data = [
            'environmentCheck' => $config->checkEnvironment(),
            'environmentFault' => $config->envFault,
            'environmentWarning' => $config->envWarning,
            'binLogError' => ($config->checkBinLogEnabled() && !$config->checkBinLogFormat()),
            'urlError' => !Environment::checkUrl($url)
        ];

        $this->getState()->template = 'fault-page';
        $this->getState()->setData($data);
    }

    public function collect()
    {
        $this->setNoOutput(true);

        // Create a ZIP file
        $tempFileName = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/' . Random::generateString();
        $zip = new \ZipArchive();

        $result = $zip->open($tempFileName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($result !== true)
            throw new \InvalidArgumentException(__('Can\'t create ZIP. Error Code: ' . $result));

        // Decide what we output based on the options selected.
        $outputVersion = $this->getSanitizer()->getCheckbox('outputVersion') == 1;
        $outputLog = $this->getSanitizer()->getCheckbox('outputLog') == 1;
        $outputEnvCheck = $this->getSanitizer()->getCheckbox('outputEnvCheck') == 1;
        $outputSettings = $this->getSanitizer()->getCheckbox('outputSettings') == 1;
        $outputDisplays = $this->getSanitizer()->getCheckbox('outputDisplays') == 1;
        $outputDisplayProfile = $this->getSanitizer()->getCheckbox('outputDisplayProfile') == 1;

        if (!$outputVersion && !$outputLog && !$outputEnvCheck && !$outputSettings && !$outputDisplays && !$outputDisplayProfile)
            throw new \InvalidArgumentException(__('Please select at least one option'));

        $environmentVariables = [
            'app_ver' => Environment::$WEBSITE_VERSION_NAME,
            'XmdsVersion' => Environment::$XMDS_VERSION,
            'XlfVersion' => Environment::$XLF_VERSION
        ];

        // Should we output the version?
        if ($outputVersion) {
            $zip->addFromString('version.json', json_encode($environmentVariables, JSON_PRETTY_PRINT));
        }

        // Should we output a log?
        if ($outputLog) {
            $tempLogFile = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/log_' . Random::generateString();
            $out = fopen($tempLogFile, 'w');
            fputcsv($out, ['logId', 'runNo', 'logDate', 'channel', 'page', 'function', 'message', 'display.display', 'type']);

            // Do some post processing
            foreach ($this->logFactory->query(['logId'], ['fromDt' => (time() - (60 * 10))]) as $row) {
                /* @var \Xibo\Entity\LogEntry $row */
                fputcsv($out, [$row->logId, $row->runNo, $row->logDate, $row->channel, $row->page, $row->function, $row->message, $row->display, $row->type]);
            }

            fclose($out);

            $zip->addFile($tempLogFile, 'log.csv');
        }

        // Output ENV Check
        if ($outputEnvCheck) {
            $zip->addFromString('environment.json', json_encode(array_map(function ($element) {
                unset($element['advice']);
                return $element;
            }, $this->getConfig()->checkEnvironment()), JSON_PRETTY_PRINT));
        }

        // Output Settings
        if ($outputSettings) {
            $zip->addFromString('settings.json', json_encode(array_map(function($element) {
                return [$element['setting'] => $element['value']];
            }, $this->store->select('SELECT setting, `value` FROM `setting`', [])), JSON_PRETTY_PRINT));
        }

        // Output Displays
        if ($outputDisplays) {

            $displays = $this->displayFactory->query(['display']);

            // Output Profiles
            if ($outputDisplayProfile) {
                foreach ($displays as $display) {
                    /** @var \Xibo\Entity\Display $display */
                    $display->settingProfile = array_map(function ($element) {
                        unset($element['helpText']);
                        return $element;
                    }, $display->getSettings());
                }
            }

            $zip->addFromString('displays.json', json_encode($displays, JSON_PRETTY_PRINT));
        }

        // Close the ZIP file
        $zip->close();

        // Prepare the download
        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }

        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=troubleshoot.zip");
        header('Content-Length: ' . filesize($tempFileName));

        // Send via Apache X-Sendfile header?
        if ($this->getConfig()->getSetting('SENDFILE_MODE') == 'Apache') {
            header("X-Sendfile: $tempFileName");
            $this->getApp()->halt(200);
        }
        // Send via Nginx X-Accel-Redirect?
        if ($this->getConfig()->getSetting('SENDFILE_MODE') == 'Nginx') {
            header("X-Accel-Redirect: /download/temp/" . basename($tempFileName));
            $this->getApp()->halt(200);
        }

        // Return the file with PHP
        // Disable any buffering to prevent OOM errors.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        readfile($tempFileName);
        exit;
    }

    public function debugOn()
    {
        $this->getConfig()->changeSetting('audit', 'DEBUG');
        $this->getConfig()->changeSetting('ELEVATE_LOG_UNTIL', $this->getDate()->parse()->addMinutes(30)->format('U'));

        // Return
        $this->getState()->hydrate([
            'message' => __('Switched to Debug Mode')
        ]);
    }

    public function debugOff()
    {
        $this->getConfig()->changeSetting('audit', $this->getConfig()->getSetting('RESTING_LOG_LEVEL'));
        $this->getConfig()->changeSetting('ELEVATE_LOG_UNTIL', '');

        // Return
        $this->getState()->hydrate([
            'message' => __('Switched to Normal Mode')
        ]);
    }
}
