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
use Xibo\Factory\AuditLogFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class AuditLog
 * @package Xibo\Controller
 */
class AuditLog extends Base
{
    /**
     * @var AuditLogFactory
     */
    private $auditLogFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param AuditLogFactory $auditLogFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $auditLogFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->auditLogFactory = $auditLogFactory;
    }

    public function displayPage()
    {
        $this->getState()->template = 'auditlog-page';
    }

    function grid()
    {
        $filterFromDt = $this->getSanitizer()->getDate('fromDt');
        $filterToDt = $this->getSanitizer()->getDate('toDt');
        $filterUser = $this->getSanitizer()->getString('user');
        $filterEntity = $this->getSanitizer()->getString('entity');
        $filterEntityId = $this->getSanitizer()->getString('entityId');
        $filterMessage = $this->getSanitizer()->getString('message');

        $search = [];

        if ($filterFromDt != null && $filterFromDt == $filterToDt) {
            $filterToDt->addDay(1);
        }

        // Get the dates and times
        if ($filterFromDt == null)
            $filterFromDt = $this->getDate()->parse()->sub('1 day');

        if ($filterToDt == null)
            $filterToDt = $this->getDate()->parse();

        $search['fromTimeStamp'] = $filterFromDt->format('U');
        $search['toTimeStamp'] = $filterToDt->format('U');

        if ($filterUser != '') {
            $search['userName'] = $filterUser;
        }

        if ($filterEntity != '') {
            $search['entity'] = $filterEntity;
        }

        if ($filterEntityId != null) {
            $search['entityId'] = $filterEntityId;
        }

        if ($filterMessage != '') {
            $search['message'] = $filterMessage;
        }

        $rows = $this->auditLogFactory->query($this->gridRenderSort(), $this->gridRenderFilter($search));

        // Do some post processing
        foreach ($rows as $row) {
            /* @var \Xibo\Entity\AuditLog $row */
            $row->objectAfter = json_decode($row->objectAfter);
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->auditLogFactory->countLast();
        $this->getState()->setData($rows);
    }

    /**
     * Output CSV Form
     */
    public function exportForm()
    {
        $this->getState()->template = 'auditlog-form-export';
        $this->getState()->setData([
            'help' => $this->getHelp()->link('AuditLog', 'Export')
        ]);
    }

    /**
     * Outputs a CSV of audit trail messages
     */
    public function export()
    {
        // We are expecting some parameters
        $filterFromDt = $this->getSanitizer()->getDate('filterFromDt');
        $filterToDt = $this->getSanitizer()->getDate('filterToDt');

        if ($filterFromDt == null || $filterToDt == null)
            throw new \InvalidArgumentException(__('Please provide a from/to date.'));

        $fromTimeStamp = $filterFromDt->setTime(0, 0, 0)->format('U');
        $toTimeStamp = $filterToDt->setTime(0, 0, 0)->format('U');

        $rows = $this->auditLogFactory->query('logId', ['fromTimeStamp' => $fromTimeStamp, 'toTimeStamp' => $toTimeStamp]);

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Date', 'User', 'Entity', 'EntityId', 'Message', 'Object']);

        // Do some post processing
        foreach ($rows as $row) {
            /* @var \Xibo\Entity\AuditLog $row */
            fputcsv($out, [$row->logId, $this->getDate()->getLocalDate($row->logDate), $row->userName, $row->entity, $row->entityId, $row->message, $row->objectAfter]);
        }

        fclose($out);

        // We want to output a load of stuff to the browser as a text file.
        $app = $this->getApp();
        $app->response()->header('Content-Type', 'text/csv');
        $app->response()->header('Content-Disposition', 'attachment; filename="audittrail.csv"');
        $app->response()->header('Content-Transfer-Encoding', 'binary"');
        $app->response()->header('Accept-Ranges', 'bytes');
        $this->setNoOutput(true);
    }
}
