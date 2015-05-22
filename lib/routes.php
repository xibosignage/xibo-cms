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

$app->get('/clock', '\Xibo\Controller\Clock:clock')->name('clock');

//
// Schedule
//
$app->get('/schedule/data/events', '\Xibo\Controller\Schedule:eventData')->name('scheduleCalendarData');

//
// Layouts
//
$app->get('/layout', '\Xibo\Controller\Layout:grid')->name('layoutSearch');
$app->post('/layout', '\Xibo\Controller\Layout:add')->name('layoutAdd');
$app->put('/layout/:id', '\Xibo\Controller\Layout:modify')->name('layoutUpdate');
$app->delete('/layout/:id', '\Xibo\Controller\Layout:delete')->name('layoutDelete');
$app->put('/layout/retire/:id', '\Xibo\Controller\Layout:retire')->name('layoutRetire');

//
// Campaign
//
$app->get('/campaign', '\Xibo\Controller\Campaign:grid')->name('campaignSearch');
$app->post('/campaign', '\Xibo\Controller\Campaign:add')->name('campaignAdd');
$app->put('/campaign/:id', '\Xibo\Controller\Campaign:modify')->name('campaignUpdate');
$app->delete('/campaign/:id', '\Xibo\Controller\Campaign:delete')->name('campaignDelete');

//
// Template
//
$app->get('/template', '\Xibo\Controller\Template:grid')->name('templateSearch');

//
// Resolutions
//
$app->get('/resolution', '\Xibo\Controller\Resolution:grid')->name('resolutionSearch');

//
// Library
//
$app->get('/library', '\Xibo\Controller\Library:grid')->name('librarySearch');

//
// Displays
//
$app->get('/display', '\Xibo\Controller\Display:grid')->name('displaySearch');

//
// Display Group
//
$app->get('/displaygroup', '\Xibo\Controller\DisplayGroup:grid')->name('displayGroupSearch');

//
// Display Profile
//
$app->get('/displayprofile', '\Xibo\Controller\DisplayProfile:grid')->name('displayProfileSearch');

//
// DataSet
//
$app->get('/dataset', '\Xibo\Controller\DataSet:grid')->name('dataSetSearch');

//
// Statistics
//
$app->get('/stats', '\Xibo\Controller\Stats:grid')->name('statsSearch');
$app->get('/stats/data/bandwidth', '\Xibo\Controller\Stats:bandwidthData')->name('statsBandwidthData');
$app->get('/stats/data/availability', '\Xibo\Controller\Stats:availabilityData')->name('statsAvailabilityData');

//
// Log
//
$app->get('/log', '\Xibo\Controller\Log:grid')->name('logSearch');
$app->delete('/log', '\Xibo\Controller\Log:truncate')->name('logTruncate');

//
// User
//
$app->get('/user', '\Xibo\Controller\User:grid')->name('userSearch');
$app->post('/user', '\Xibo\Controller\User:add')->name('userAdd');
$app->put('/user/:id', '\Xibo\Controller\User:edit')->name('userEdit');
$app->delete('/user/:id', '\Xibo\Controller\User:delete')->name('userDelete');

//
// User Group
//
$app->get('/group', '\Xibo\Controller\UserGroup:grid')->name('groupSearch');

//
// Applications
//
$app->get('/applications', '\Xibo\Controller\Applications:grid')->name('applicationsSearch');

//
// Modules
//
$app->get('/module', '\Xibo\Controller\Module:grid')->name('moduleSearch');

//
// Transition
//
$app->get('/transition', '\Xibo\Controller\Transition:grid')->name('transitionSearch');

//
// Sessions
//
$app->get('/sessions', '\Xibo\Controller\Sessions:grid')->name('sessionsSearch');

//
// Help
//
$app->get('/help', '\Xibo\Controller\Help:grid')->name('helpSearch');

//
// Settings
//
$app->put('/admin', '\Xibo\Controller\Settings:update')->name('settingsUpdate');
