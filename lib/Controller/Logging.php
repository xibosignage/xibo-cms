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
use Xibo\Helper\Sanitize;
use Xibo\Helper\Theme;


class Logging extends Base
{
    public function displayPage()
    {
        $this->getState()->template = 'log-page';
        $this->getState()->setData([
            'displays' => (new DisplayFactory($this->getApp()))->query()
        ]);
    }

    function grid()
    {
        // Date time criteria
        $seconds = Sanitize::getInt('seconds', 120);
        $intervalType = Sanitize::getInt('intervalType', 1);
        $fromDt = Sanitize::getDate('fromDt', Date::getLocalDate());

        $logs = (new LogFactory($this->getApp()))->query($this->gridRenderSort(), $this->gridRenderFilter([
            'fromDt' => $fromDt->format('U') - ($seconds * $intervalType),
            'toDt' => $fromDt->format('U'),
            'type' => Sanitize::getString('level'),
            'page' => Sanitize::getString('page'),
            'channel' => Sanitize::getString('channel'),
            'function' => Sanitize::getString('function'),
            'displayId' => Sanitize::getInt('displayId'),
            'excludeLog' => 1,
            'runNo' => Sanitize::getString('runNo')
        ]));

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = (new LogFactory($this->getApp()))->countLast();
        $this->getState()->setData($logs);
    }

    function LastHundredForDisplay()
    {
        $response = $this->getState();
        $displayId = Sanitize::getInt('displayid');

        try {
            $dbh = $this->getStore()->getConnection();

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

        $this->getStore()->update('TRUNCATE TABLE log', array());

        // Return
        $this->getState()->hydrate([
            'message' => __('Log Truncated')
        ]);
    }
}
