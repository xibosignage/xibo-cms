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

use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\LogFactory;
use Xibo\Service\ConfigService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class Fault
 * @package Xibo\Controller
 */
class Fault extends Base
{
    /**
     * @var LogFactory
     */
    private $logFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param LogFactory $logFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $logFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->logFactory = $logFactory;
    }

    function displayPage()
    {
        $config = new ConfigService();
        $data = [
            'environmentCheck' => $config->CheckEnvironment(),
            'environmentFault' => $config->envFault,
            'environmentWarning' => $config->envWarning
        ];

        $this->getState()->template = 'fault-page';
        $this->getState()->setData($data);
    }

    public function collect()
    {
        $out = fopen('php://output', 'w');
        fputcsv($out, ['logId', 'runNo', 'logDate', 'channel', 'page', 'function', 'message', 'display.display', 'type']);

        // Do some post processing
        foreach ($this->logFactory->query(['logId'], ['fromDt' => (time() - (60 * 10))]) as $row) {
            /* @var \Xibo\Entity\LogEntry $row */
            fputcsv($out, [$row->logId, $row->runNo, $row->logDate, $row->channel, $row->page, $row->function, $row->message, $row->display, $row->type]);
        }

        fclose($out);

        // We want to output a load of stuff to the browser as a text file.
        $app = $this->getApp();
        $app->response()->header('Content-Type', 'text/csv');
        $app->response()->header('Content-Disposition', 'attachment; filename="troubleshoot.csv"');
        $app->response()->header('Content-Transfer-Encoding', 'binary"');
        $app->response()->header('Accept-Ranges', 'bytes');
        $this->setNoOutput(true);
    }

    public function debugOn()
    {
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $this->getConfig()->ChangeSetting('audit', 'DEBUG');

        // Return
        $this->getState()->hydrate([
            'message' => __('Switched to Debug Mode')
        ]);
    }

    public function debugOff()
    {
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException();

        $this->getConfig()->ChangeSetting('audit', 'EMERGENCY');

        // Return
        $this->getState()->hydrate([
            'message' => __('Switched to Normal Mode')
        ]);
    }
}
