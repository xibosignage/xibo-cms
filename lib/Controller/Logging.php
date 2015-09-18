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

use Exception;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LogFactory;
use Xibo\Helper\Date;
use Xibo\Helper\Help;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Theme;
use Xibo\Storage\PDOConnect;


class Logging extends Base
{
    public function displayPage()
    {
        // Construct Filter Form
        if ($this->getSession()->get('log', 'Filter') == 1) {
            $filter_pinned = 1;
            $filter_type = $this->getSession()->get('log', 'filter_type');
            $filter_page = $this->getSession()->get('log', 'filter_page');
            $filter_function = $this->getSession()->get('log', 'filter_function');
            $filter_display = $this->getSession()->get('log', 'filter_display');
            $filter_seconds = $this->getSession()->get('log', 'filter_seconds');
            $filter_intervalTypeId = $this->getSession()->get('log', 'filter_intervalTypeId');

            // Never remember the fromDt
            $filter_fromdt = NULL;
        } else {
            $filter_pinned = 0;
            $filter_type = NULL;
            $filter_page = NULL;
            $filter_function = NULL;
            $filter_display = 0;
            $filter_fromdt = NULL;
            $filter_seconds = 120;
            $filter_intervalTypeId = 1;
        }

        $data = [
            'defaults' => [
                'filterPinned' => $filter_pinned,
                'type' => $filter_type,
                'page' => $filter_page,
                'function' => $filter_function,
                'display' => $filter_display,
                'fromDt' => $filter_fromdt,
                'seconds' => $filter_seconds,
                'intervalType' => $filter_intervalTypeId
            ],
            'options' => [
                'intervalType' => array(
                    array('id' => 1, 'value' => __('Seconds')),
                    array('id' => 60, 'value' => __('Minutes')),
                    array('id' => 3600, 'value' => __('Hours'))
                ),
                'displays' => DisplayFactory::query()
            ]
        ];

        $this->getState()->template = 'log-page';
        $this->getState()->setData($data);
    }

    function grid()
    {
        $type = Sanitize::getString('filter_type');
        $function = Sanitize::getString('filter_function');
        $page = Sanitize::getString('filter_page');
        $displayId = Sanitize::getInt('filter_display');
        $seconds = Sanitize::getInt('filter_seconds', 120);
        $filter_intervalTypeId = Sanitize::getInt('filter_intervalTypeId', 1);

        // Get the provided date, or go from today
        $fromDt = Sanitize::getDate('filter_fromdt', Date::getLocalDate());

        $this->getSession()->set('log', 'Filter', Sanitize::getCheckbox('XiboFilterPinned'));
        $this->getSession()->set('log', 'filter_type', $type);
        $this->getSession()->set('log', 'filter_function', $function);
        $this->getSession()->set('log', 'filter_page', $page);
        $this->getSession()->set('log', 'filter_fromdt', Date::getLocalDate($fromDt));
        $this->getSession()->set('log', 'filter_display', $displayId);
        $this->getSession()->set('log', 'filter_seconds', $seconds);
        $this->getSession()->set('log', 'filter_intervalTypeId', $filter_intervalTypeId);

        $logs = LogFactory::query($this->gridRenderSort(), $this->gridRenderFilter([
            'fromDt' => $fromDt->format('U') - ($seconds * $filter_intervalTypeId),
            'toDt' => $fromDt->format('U'),
            'type' => $type,
            'page' => $page,
            'function' => $function,
            'displayId' => $displayId,
            'excludeLog' => 1,
            'runNo' => Sanitize::getString('runNo')
        ]));

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = LogFactory::countLast();
        $this->getState()->setData($logs);
    }

    function LastHundredForDisplay()
    {
        $response = $this->getState();
        $displayId = Sanitize::getInt('displayid');

        try {
            $dbh = \Xibo\Storage\PDOConnect::init();

            $sth = $dbh->prepare('SELECT logid, logdate, page, function, message FROM log WHERE displayid = :displayid ORDER BY logid DESC LIMIT 100');
            $sth->execute(array(
                'displayid' => $displayId
            ));

            $log = $sth->fetchAll();

            if (count($log) <= 0)
                throw new Exception(__('No log messages for this display'));

            $cols = array(
                array('name' => 'logid', 'title' => __('ID')),
                array('name' => 'logdate', 'title' => __('Date')),
                array('name' => 'page', 'title' => __('Page')),
                array('name' => 'function', 'title' => __('Function')),
                array('name' => 'message', 'title' => __('Message'))
            );
            Theme::Set('table_cols', $cols);

            $rows = array();

            foreach ($log as $row) {

                $row['logid'] = Sanitize::int($row['logid']);
                $row['logdate'] = Sanitize::string($row['logdate']);
                $row['page'] = Sanitize::string($row['page']);
                $row['function'] = Sanitize::string($row['function']);
                $row['message'] = nl2br(htmlspecialchars($row['message']));

                $rows[] = $row;
            }

            Theme::Set('table_rows', $rows);

            $output = Theme::RenderReturn('table_render');

            $response->initialSortOrder = 2;
            $response->dialogClass = 'modal-big';
            $response->dialogTitle = __('Recent Log Messages');
            $response->pageSize = 10;
            $response->SetGridResponse($output);

        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
        }
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
            'help' => Help::Link('Log', 'Truncate')
        ]);
    }

    /**
     * Truncate the Log
     */
    public function truncate()
    {
        if ($this->getUser()->userTypeId != 1)
            throw new AccessDeniedException(__('Only Administrator Users can truncate the log'));

        PDOConnect::update('TRUNCATE TABLE log', array());

        // Return
        $this->getState()->hydrate([
            'message' => __('Log Truncated')
        ]);
    }
}
