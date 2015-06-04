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
use Kit;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\LogFactory;
use Xibo\Helper\Date;
use Xibo\Helper\Form;
use Xibo\Helper\Help;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Session;
use Xibo\Helper\Theme;
use Xibo\Storage\PDOConnect;


class Log extends Base
{
    public function displayPage()
    {
        // Construct Filter Form
        if (Session::Get(get_class(), 'Filter') == 1) {
            $filter_pinned = 1;
            $filter_type = Session::Get('log', 'filter_type');
            $filter_page = Session::Get('log', 'filter_page');
            $filter_function = Session::Get('log', 'filter_function');
            $filter_display = Session::Get('log', 'filter_display');
            $filter_fromdt = Session::Get('log', 'filter_fromdt');
            $filter_seconds = Session::Get('log', 'filter_seconds');
            $filter_intervalTypeId = Session::Get('log', 'filter_intervalTypeId');
        } else {
            $filter_pinned = 0;
            $filter_type = 0;
            $filter_page = NULL;
            $filter_function = NULL;
            $filter_display = 0;
            $filter_fromdt = NULL;
            $filter_seconds = 120;
            $filter_intervalTypeId = 1;
        }

        // Display
        $displays = $this->getUser()->DisplayList();
        array_unshift($displays, array('displayId' => 0, 'display' => 'All'));

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
                'type' => array(
                    array('id' => 0, 'value' => __('All')),
                    array('id' => 2, 'value' => __('Audit')),
                    array('id' => 1, 'value' => __('Error'))
                ),
                'displays' => $displays
            ]
        ];

        $this->getState()->template = 'log-page';
        $this->getState()->setData($data);
    }

    function grid()
    {
        $type = Sanitize::getInt('filter_type', 0);
        $function = Sanitize::getString('filter_function');
        $page = Sanitize::getString('filter_page');
        $fromdt = Sanitize::getString('filter_fromdt');
        $displayid = Sanitize::getInt('filter_display');
        $seconds = Sanitize::getInt('filter_seconds', 120);
        $filter_intervalTypeId = Sanitize::getInt('filter_intervalTypeId', 1);

        Session::Set('log', 'Filter', Sanitize::getCheckbox('XiboFilterPinned'));
        Session::Set('log', 'filter_type', $type);
        Session::Set('log', 'filter_function', $function);
        Session::Set('log', 'filter_page', $page);
        Session::Set('log', 'filter_fromdt', $fromdt);
        Session::Set('log', 'filter_display', $displayid);
        Session::Set('log', 'filter_seconds', $seconds);
        Session::Set('log', 'filter_intervalTypeId', $filter_intervalTypeId);

        // get the dates and times
        if ($fromdt == '') {
            $starttime_timestamp = time();
        } else {
            $start_date = Date::getTimestampFromString($fromdt);
            $starttime_timestamp = strtotime($start_date[1] . "/" . $start_date[0] . "/" . $start_date[2] . ' ' . date("H", time()) . ":" . date("i", time()) . ':59');
        }

        $logs = LogFactory::query($this->gridRenderSort(), $this->gridRenderFilter([
            'fromDt' => $starttime_timestamp - ($seconds * $filter_intervalTypeId),
            'toDt' => $starttime_timestamp,
            'type' => $type,
            'page' => $page,
            'function' => $function,
            'displayId' => $displayid,
            'excludeLog' => 1
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
