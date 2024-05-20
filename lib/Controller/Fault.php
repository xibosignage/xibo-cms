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
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LogFactory;
use Xibo\Helper\Environment;
use Xibo\Helper\Random;
use Xibo\Helper\SendFile;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

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
     * @param StorageServiceInterface $store
     * @param LogFactory $logFactory
     * @param DisplayFactory $displayFactory
     */
    public function __construct($store, $logFactory, $displayFactory)
    {
        $this->store = $store;
        $this->logFactory = $logFactory;
        $this->displayFactory = $displayFactory;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function displayPage(Request $request, Response $response)
    {
        $url = $request->getUri() . $request->getUri()->getPath();

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

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function collect(Request $request, Response $response)
    {
        $this->setNoOutput(true);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Create a ZIP file
        $tempFileName = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/' . Random::generateString();
        $zip = new \ZipArchive();

        $result = $zip->open($tempFileName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new InvalidArgumentException(__('Can\'t create ZIP. Error Code: ' . $result));
        }

        // Decide what we output based on the options selected.
        $outputVersion = $sanitizedParams->getCheckbox('outputVersion') == 1;
        $outputLog = $sanitizedParams->getCheckbox('outputLog') == 1;
        $outputEnvCheck = $sanitizedParams->getCheckbox('outputEnvCheck') == 1;
        $outputSettings = $sanitizedParams->getCheckbox('outputSettings') == 1;
        $outputDisplays = $sanitizedParams->getCheckbox('outputDisplays') == 1;
        $outputDisplayProfile = $sanitizedParams->getCheckbox('outputDisplayProfile') == 1;

        if (!$outputVersion &&
            !$outputLog &&
            !$outputEnvCheck &&
            !$outputSettings &&
            !$outputDisplays &&
            !$outputDisplayProfile
        ) {
            throw new InvalidArgumentException(__('Please select at least one option'));
        }

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
            fputcsv(
                $out,
                [
                    'logId',
                    'runNo',
                    'logDate',
                    'channel',
                    'page',
                    'function',
                    'message',
                    'display.display',
                    'type',
                    'sessionHistoryId'
                ]
            );

            $fromDt = Carbon::now()->subSeconds(60 * 10)->format('U');
            // Do some post processing
            foreach ($this->logFactory->query(['logId'], ['fromDt' => $fromDt]) as $row) {
                /* @var \Xibo\Entity\LogEntry $row */
                fputcsv(
                    $out,
                    [
                        $row->logId,
                        $row->runNo,
                        $row->logDate,
                        $row->channel,
                        $row->page,
                        $row->function,
                        $row->message,
                        $row->display,
                        $row->type,
                        $row->sessionHistoryId
                    ]
                );
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
            $zip->addFromString('settings.json', json_encode(array_map(function ($element) {
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
                    $display->setUnmatchedProperty('settingProfile', array_map(function ($element) {
                        unset($element['helpText']);
                        return $element;
                    }, $display->getSettings()));
                }
            }

            $zip->addFromString('displays.json', json_encode($displays, JSON_PRETTY_PRINT));
        }

        // Close the ZIP file
        $zip->close();

        return $this->render($request, SendFile::decorateResponse(
            $response,
            $this->getConfig()->getSetting('SENDFILE_MODE'),
            $tempFileName,
            'troubleshoot.zip'
        ));
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function debugOn(Request $request, Response $response)
    {
        $this->getConfig()->changeSetting('audit', 'debug');
        $this->getConfig()->changeSetting('ELEVATE_LOG_UNTIL', Carbon::now()->addMinutes(30)->format('U'));

        // Return
        $this->getState()->hydrate([
            'message' => __('Switched to Debug Mode')
        ]);

        return $this->render($request, $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function debugOff(Request $request, Response $response)
    {
        $this->getConfig()->changeSetting('audit', $this->getConfig()->getSetting('RESTING_LOG_LEVEL'));
        $this->getConfig()->changeSetting('ELEVATE_LOG_UNTIL', '');

        // Return
        $this->getState()->hydrate([
            'message' => __('Switched to Normal Mode')
        ]);

        return $this->render($request, $response);
    }
}
