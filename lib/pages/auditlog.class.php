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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

include_once('lib/Entity/AuditLog.php');
include_once('lib/Factory/AuditLogFactory.php');

class auditlogDAO extends baseDAO {

	public function displayPage() 
	{
		// Configure the theme
        Theme::Set('id', 'LogGridForRefresh');
        Theme::Set('form_meta', '<input type="hidden" name="p" value="auditlog"><input type="hidden" name="q" value="Grid">');
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ResponseManager::Pager('LogGridForRefresh'));
        
        // Construct Filter Form
        if (Kit::IsFilterPinned('auditlog', 'Filter')) {
            $filter_pinned = 1;
            $filterFromDt = Session::Get('auditlog', 'filterFromDt');
            $filterToDt = Session::Get('auditlog', 'filterToDt');
            $filterUser = Session::Get('auditlog', 'filterUser');
            $filterEntity = Session::Get('auditlog', 'filterEntity');
        }
        else {
            $filter_pinned = 0;
            $filterFromDt = NULL;
            $filterToDt = NULL;
            $filterUser = NULL;
            $filterEntity = NULL;
        }

        // Fields
        $formFields = [];
        $formFields[] = FormManager::AddDatePicker('filterFromDt', __('From Date'), $filterFromDt, NULL, 'f');
        $formFields[] = FormManager::AddDatePicker('filterToDt', __('To Date'), $filterToDt, NULL, 't');

        $formFields[] = FormManager::AddText('filterUser', __('User'), $filterUser, NULL, 'u');
        $formFields[] = FormManager::AddText('filterEntity', __('Entity'), $filterEntity, NULL, 'e');

        $formFields[] = FormManager::AddCheckbox('XiboFilterPinned', __('Keep Open'),
            $filter_pinned, NULL,
            'k');

        // Call to render the template
        Theme::Set('header_text', __('Audit Trail'));
        Theme::Set('form_fields', $formFields);
        Theme::Render('grid_render');
	}

    function actionMenu() {

        return array(
                array('title' => __('Refresh'),
                    'class' => '',
                    'selected' => false,
                    'link' => '#',
                    'help' => __('Truncate the Log'),
                    'onclick' => 'XiboGridRender(\'LogGridForRefresh\')'
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
		$response = new ResponseManager();
		
		$filterUser = Kit::GetParam('filterUser', _REQUEST, _STRING);
		$filterEntity = Kit::GetParam('filterEntity', _REQUEST, _STRING);
		$filterFromDt	= Kit::GetParam('filterFromDt', _REQUEST, _STRING);
		$filterToDt = Kit::GetParam('filterToDt', _REQUEST, _STRING);

        setSession('auditlog', 'Filter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));
        setSession('auditlog', 'filterFromDt', $filterFromDt);
        setSession('auditlog', 'filterToDt', $filterToDt);
        setSession('auditlog', 'filterUser', $filterUser);
        setSession('auditlog', 'filterEntity', $filterEntity);

        $search = [];

		// Get the dates and times
		if ($filterFromDt == '') {
			$fromTimestamp = (new DateTime())->sub(new DateInterval("P1D"));
		}
		else {
            $fromTimestamp = DateTime::createFromFormat('Y-m-d', $filterFromDt);
            $fromTimestamp->setTime(0, 0, 0);
		}

		if ($filterToDt == '') {
			$toTimestamp = (new DateTime());
		}
		else {
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

        $rows = \Xibo\Factory\AuditLogFactory::query('logId', ['search' => $search]);

        // Do some post processing
        foreach ($rows as $row) {
            /* @var \Xibo\Entity\AuditLog $row */
            $row->logDate = DateManager::getLocalDate($row->logDate);
            $row->objectAfter = json_decode($row->objectAfter);
        }

		Theme::Set('table_rows', json_decode(json_encode($rows), true));
        
        $output = Theme::RenderReturn('table_render');
		
        $response->initialSortOrder = 2;
		$response->initialSortColumn = 1;
		$response->pageSize = 20;
		$response->SetGridResponse($output);
		$response->Respond();
	}
}
