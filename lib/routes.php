<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (routes.php) is part of Xibo.
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
defined('XIBO') or die('Sorry, you are not allowed to directly access this page.');

// About Page
$app->get('/about', '\Xibo\Controller\Login:About')->name('about');

$app->get('/clock', '\Xibo\Controller\Clock:clock')->setName('clock');

//
// Layouts
//
$app->get('/layout', '\Xibo\Controller\Layout:grid')->setName('layoutSearch');
$app->post('/layout', '\Xibo\Controller\Layout:add')->setName('layoutAdd');
$app->put('/layout/:id', '\Xibo\Controller\Layout:modify')->setName('layoutUpdate');
$app->delete('/layout/:id', '\Xibo\Controller\Layout:delete')->setName('layoutDelete');
$app->put('/layout/retire/:id', '\Xibo\Controller\Layout:retire')->setName('layoutRetire');

//
// Campaign
//
$app->get('/campaign', '\Xibo\Controller\Campaign:grid')->setName('campaignSearch');
$app->post('/campaign', '\Xibo\Controller\Campaign:add')->setName('campaignAdd');
$app->put('/campaign/:id', '\Xibo\Controller\Campaign:modify')->setName('campaignUpdate');
$app->delete('/campaign/:id', '\Xibo\Controller\Campaign:delete')->setName('campaignDelete');

//
// Log
//
$app->get('/log', '\Xibo\Controller\Log:grid')->name('logSearch');
$app->delete('/log', '\Xibo\Controller\Log:truncate')->name('logTruncate');