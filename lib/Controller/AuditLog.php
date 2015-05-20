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
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Date;
use Xibo\Helper\Form;
use Xibo\Helper\Session;
use Xibo\Helper\Theme;

class AuditLog extends Base
{

    public function displayPage()
    {
        // Configure the theme
        Theme::Set('id', 'LogGridForRefresh');
        Theme::Set('form_meta', '<input type="hidden" name="p" value="auditlog"><input type="hidden" name="q" value="Grid">');
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ApplicationState::Pager('LogGridForRefresh'));

        // Construct Filter Form
        if (Kit::IsFilterPinned('auditlog', 'Filter')) {
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

        // Fields
        $formFields = [];
        $formFields[] = Form::AddDatePicker('filterFromDt', __('From Date'), $filterFromDt, NULL, 'f');
        $formFields[] = Form::AddDatePicker('filterToDt', __('To Date'), $filterToDt, NULL, 't');

        $formFields[] = Form::AddText('filterUser', __('User'), $filterUser, NULL, 'u');
        $formFields[] = Form::AddText('filterEntity', __('Entity'), $filterEntity, NULL, 'e');

        $formFields[] = Form::AddCheckbox('XiboFilterPinned', __('Keep Open'),
            $filter_pinned, NULL,
            'k');

        // Call to render the template
        Theme::Set('header_text', __('Audit Trail'));
        Theme::Set('form_fields', $formFields);
        $this->getState()->html = Theme::RenderReturn('grid_render');
    }

    function actionMenu()
    {

        return array(
            array('title' => __('Refresh'),
                'class' => '',
                'selected' => false,
                'link' => '#',
                'help' => __('Truncate the Log'),
                'onclick' => 'XiboGridRender(\'LogGridForRefresh\')'
            ),
            array(
                'title' => __('Export'),
                'class' => 'XiboFormButton',
                'selected' => false,
                'link' => 'index.php?p=auditlog&q=outputCsvForm',
                'help' => __('Export raw data to CSV'),
                'onclick' => ''
            ),
            array('title' => __('Filter'),
                'class' => '',
                'selected' => false,
                'link' => '#',
                'help' => __('Open the filter form'),
                'onclick' => 'ToggleFilterView(\'Filter\')'
            )
        );
    }

    function Grid()
    {
        $filterUser = Kit::GetParam('filterUser', _REQUEST, _STRING);
        $filterEntity = Kit::GetParam('filterEntity', _REQUEST, _STRING);
        $filterFromDt = Kit::GetParam('filterFromDt', _REQUEST, _STRING);
        $filterToDt = Kit::GetParam('filterToDt', _REQUEST, _STRING);

        Session::Set('auditlog', 'Filter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));
        Session::Set('auditlog', 'filterFromDt', $filterFromDt);
        Session::Set('auditlog', 'filterToDt', $filterToDt);
        Session::Set('auditlog', 'filterUser', $filterUser);
        Session::Set('auditlog', 'filterEntity', $filterEntity);

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

        $cols = array(
            array('name' => 'logId', 'title' => __('ID')),
            array('name' => 'logDate', 'title' => __('Date')),
            array('name' => 'userName', 'title' => __('User')),
            array('name' => 'entity', 'title' => __('Entity')),
            array('name' => 'message', 'title' => __('Message')),
            array('name' => 'objectAfter', 'title' => __('Object'), 'array' => true)
        );
        Theme::Set('table_cols', $cols);

        $rows = AuditLogFactory::query('logId', ['search' => $search]);

        // Do some post processing
        foreach ($rows as $row) {
            /* @var \Xibo\Entity\AuditLog $row */
            $row->logDate = Date::getLocalDate($row->logDate);
            $row->objectAfter = json_decode($row->objectAfter);
        }

        Theme::Set('table_rows', json_decode(json_encode($rows), true));

        $output = Theme::RenderReturn('table_render');

        $this->getState()->initialSortOrder = 2;
        $this->getState()->initialSortColumn = 1;
        $this->getState()->pageSize = 20;
        $this->getState()->SetGridResponse($output);
        $this->getState()->Respond();
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
