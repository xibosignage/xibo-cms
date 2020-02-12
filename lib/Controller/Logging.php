<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
 *
 * This file (Log.php) is part of Xibo.
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

use Jenssegers\Date\Date;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LogFactory;
use Xibo\Factory\UserFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Logging
 * @package Xibo\Controller
 */
class Logging extends Base
{
    /**
     * @var LogFactory
     */
    private $logFactory;

    /** @var StorageServiceInterface  */
    private $store;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /** @var  UserFactory */
    private $userFactory;

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
     * @param UserFactory $userFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $logFactory, $displayFactory, $userFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->store = $store;
        $this->logFactory = $logFactory;
        $this->displayFactory = $displayFactory;
        $this->userFactory = $userFactory;
    }

    public function displayPage()
    {
        $this->getState()->template = 'log-page';
        $this->getState()->setData([
            'users' => $this->userFactory->query()
        ]);
    }

    function grid()
    {
        // Date time criteria
        $seconds = $this->getSanitizer()->getInt('seconds', 120);
        $intervalType = $this->getSanitizer()->getInt('intervalType', 1);
        $fromDt = $this->getSanitizer()->getDate('fromDt', $this->getDate()->getLocalDate());

        $logs = $this->logFactory->query($this->gridRenderSort(), $this->gridRenderFilter([
            'fromDt' => $fromDt->format('U') - ($seconds * $intervalType),
            'toDt' => $fromDt->format('U'),
            'type' => $this->getSanitizer()->getString('level'),
            'page' => $this->getSanitizer()->getString('page'),
            'channel' => $this->getSanitizer()->getString('channel'),
            'function' => $this->getSanitizer()->getString('function'),
            'displayId' => $this->getSanitizer()->getInt('displayId'),
            'userId' => $this->getSanitizer()->getInt('userId'),
            'excludeLog' => $this->getSanitizer()->getCheckbox('excludeLog'),
            'runNo' => $this->getSanitizer()->getString('runNo'),
            'message' => $this->getSanitizer()->getString('message'),
            'display' => $this->getSanitizer()->getString('display'),
            'useRegexForName' => $this->getSanitizer()->getCheckbox('useRegexForName'),
            'displayGroupId' => $this->getSanitizer()->getInt('displayGroupId'),
        ]));

        foreach ($logs as $log) {
            // Normalise the date
            $log->logDate = $this->getDate()->getLocalDate(Date::createFromFormat($this->getDate()->getSystemFormat(), $log->logDate));
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->logFactory->countLast();
        $this->getState()->setData($logs);
    }

    /**
     * Truncate Log Form
     */
    public function truncateForm()
    {
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException(__('Only Administrator Users can truncate the log'));

        $this->getState()->template = 'log-form-truncate';
        $this->getState()->setData([
            'help' => $this->getHelp()->link('Log', 'Truncate')
        ]);
    }

    /**
     * Truncate the Log
     */
    public function truncate()
    {
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException(__('Only Administrator Users can truncate the log'));

        $this->store->update('TRUNCATE TABLE log', array());

        // Return
        $this->getState()->hydrate([
            'message' => __('Log Truncated')
        ]);
    }
}
