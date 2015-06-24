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
use DateInterval;
use DateTime;
use Kit;
use Xibo\Factory\AuditLogFactory;
use Xibo\Helper\Date;
use Xibo\Helper\Form;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Session;
use Xibo\Helper\Theme;

class AuditLog extends Base
{
    public function displayPage()
    {
        // Construct Filter Form
        if (Session::Get('auditlog', 'Filter') == 1) {
            $filter_pinned = 1;
            $filterFromDt = Session::Get('auditlog', 'filterFromDt');
            $filterToDt = Session::Get('auditlog', 'filterToDt');
            $filterUser = Session::Get('auditlog', 'filterUser');
            $filterEntity = Session::Get('auditlog', 'filterEntity');
        } else {
            $filter_pinned = 0;
            $filterFromDt = NULL;
            $filterToDt = NULL;
            $filterUser = NULL;
            $filterEntity = NULL;
        }

        $data = [
            'defaults' => [
                'filterPinned' => $filter_pinned,
                'fromDt' => $filterFromDt,
                'toDt' => $filterToDt,
                'user' => $filterUser,
                'entity' => $filterEntity
            ]
        ];

        $this->getState()->template = 'auditlog-page';
        $this->getState()->setData($data);
    }

    function grid()
    {
        Session::Set('auditlog', 'Filter', Sanitize::getCheckbox('XiboFilterPinned'));
        $filterFromDt = Session::Set('auditlog', 'filterFromDt', Sanitize::getString('filterFromDt'));
        $filterToDt = Session::Set('auditlog', 'filterToDt', Sanitize::getString('filterToDt'));
        $filterUser = Session::Set('auditlog', 'filterUser', Sanitize::getString('filterUser'));
        $filterEntity = Session::Set('auditlog', 'filterEntity', Sanitize::getString('filterEntity'));

        $search = [];

        // Get the dates and times
        if ($filterFromDt == '') {
            $fromTimestamp = (new DateTime())->sub(new DateInterval("P1D"));
        } else {
            $fromTimestamp = DateTime::createFromFormat('Y-m-d', $filterFromDt);
            $fromTimestamp->setTime(0, 0, 0);
        }

        if ($filterToDt == '') {
            $toTimestamp = (new DateTime());
        } else {
            $toTimestamp = DateTime::createFromFormat('Y-m-d', $filterToDt);
            $toTimestamp->setTime(0, 0, 0);
        }

        $search[] = ['fromTimeStamp', $fromTimestamp->format('U')];
        $search[] = ['toTimeStamp', $toTimestamp->format('U')];

        if ($filterUser != '')
            $search[] = ['userName', $filterUser];

        if ($filterEntity != '')
            $search[] = ['entity', $filterEntity];

        // Build the search string
        $search = implode(' ', array_map(function ($element) {
            return implode('|', $element);
        }, $search));

        $rows = AuditLogFactory::query($this->gridRenderSort(), $this->gridRenderFilter(['search' => $search]));

        // Do some post processing
        foreach ($rows as $row) {
            /* @var \Xibo\Entity\AuditLog $row */
            $row->logDate = Date::getLocalDate($row->logDate);
            $row->objectAfter = json_decode($row->objectAfter);
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($rows);
    }

    /**
     * Output CSV Form
     */
    public function outputCsvForm()
    {
        Theme::Set('form_id', 'OutputCsvForm');
        Theme::Set('form_action', 'index.php?p=auditlog&q=OutputCSV');

        $formFields = array();
        $formFields[] = Form::AddText('filterFromDt', __('From Date'), Date::getLocalDate(time() - (86400 * 35), 'Y-m-d'), NULL, 'f');
        $formFields[] = Form::AddText('filterToDt', __('To Date'), Date::getLocalDate(null, 'Y-m-d'), NULL, 't');

        Theme::Set('header_text', __('Audit Trail'));
        Theme::Set('form_fields', $formFields);
        Theme::Set('form_class', 'XiboManualSubmit');

        $this->getState()->SetFormRequestResponse(NULL, __('Output Audit Trail as CSV'), '550px', '275px');
        $this->getState()->AddButton(__('Export'), '$("#OutputCsvForm").submit()');
        $this->getState()->AddButton(__('Close'), 'XiboDialogClose()');
        $this->getState()->Respond();
    }

    /**
     * Outputs a CSV of audit trail messages
     */
    public function outputCSV()
    {
        // We are expecting some parameters
        $filterFromDt = Kit::GetParam('filterFromDt', _REQUEST, _STRING);
        $filterToDt = Kit::GetParam('filterToDt', _REQUEST, _STRING);

        $fromTimestamp = DateTime::createFromFormat('Y-m-d', $filterFromDt);
        $fromTimestamp->setTime(0, 0, 0);
        $toTimestamp = DateTime::createFromFormat('Y-m-d', $filterToDt);
        $toTimestamp->setTime(0, 0, 0);

        $search = [
            ['fromTimeStamp', $fromTimestamp->format('U')],
            ['toTimeStamp', $toTimestamp->format('U')]
        ];

        // Build the search string
        $search = implode(' ', array_map(function ($element) {
            return implode('|', $element);
        }, $search));

        $rows = AuditLogFactory::query('logId', ['search' => $search]);

        // We want to output a load of stuff to the browser as a text file.
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="audittrail.csv"');
        header("Content-Transfer-Encoding: binary");
        header('Accept-Ranges: bytes');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Date', 'User', 'Entity', 'Message', 'Object']);

        // Do some post processing
        foreach ($rows as $row) {
            /* @var \Xibo\Entity\AuditLog $row */
            fputcsv($out, [$row->logId, Date::getLocalDate($row->logDate), $row->userName, $row->entity, $row->message, $row->objectAfter]);
        }

        fclose($out);
        exit;
    }
}
