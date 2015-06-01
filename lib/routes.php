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
$app->get('/schedule/data/events', '\Xibo\Controller\Schedule:eventData')->name('schedule.calendar.data');

//
// Layouts
//
$app->get('/layout', '\Xibo\Controller\Layout:grid')->name('layout.search');
$app->post('/layout', '\Xibo\Controller\Layout:add')->name('layout.add');
$app->put('/layout/:id', '\Xibo\Controller\Layout:modify')->name('layout.update');
$app->delete('/layout/:id', '\Xibo\Controller\Layout:delete')->name('layout.delete');
$app->put('/layout/retire/:id', '\Xibo\Controller\Layout:retire')->name('layout.retire');

//
// Campaign
//
$app->get('/campaign', '\Xibo\Controller\Campaign:grid')->name('campaign.search');
$app->post('/campaign', '\Xibo\Controller\Campaign:add')->name('campaign.add');
$app->put('/campaign/:id', '\Xibo\Controller\Campaign:modify')->name('campaign.update');
$app->delete('/campaign/:id', '\Xibo\Controller\Campaign:delete')->name('campaign.delete');

//
// Template
//
$app->get('/template', '\Xibo\Controller\Template:grid')->name('template.search');

//
// Resolutions
//
$app->get('/resolution', '\Xibo\Controller\Resolution:grid')->name('resolution.search');

//
// Library
//
$app->get('/library', '\Xibo\Controller\Library:grid')->name('library.search');

//
// Displays
//
$app->get('/display', '\Xibo\Controller\Display:grid')->name('display.search');

//
// Display Group
//
$app->get('/displaygroup', '\Xibo\Controller\DisplayGroup:grid')->name('displayGroup.search');

//
// Display Profile
//
$app->get('/displayprofile', '\Xibo\Controller\DisplayProfile:grid')->name('displayProfile.search');

//
// DataSet
//
$app->get('/dataset', '\Xibo\Controller\DataSet:grid')->name('dataSet.search');

//
// Statistics
//
$app->get('/stats', '\Xibo\Controller\Stats:grid')->name('stats.search');
$app->get('/stats/data/bandwidth', '\Xibo\Controller\Stats:bandwidthData')->name('stats.bandwidth.data');
$app->get('/stats/data/availability', '\Xibo\Controller\Stats:availabilityData')->name('stats.availability.data');

//
// Log
//
$app->get('/log', '\Xibo\Controller\Log:grid')->name('log.search');
$app->delete('/log', '\Xibo\Controller\Log:truncate')->name('log.truncate');

//
// User
//
$app->get('/user', '\Xibo\Controller\User:grid')->name('user.search');
$app->post('/user', '\Xibo\Controller\User:add')->name('user.add');
$app->post('/user/permissions/:entity/:id', '\Xibo\Controller\User:permissions')->name('user.permissions');
$app->put('/user/password/change', '\Xibo\Controller\User:changePassword')->name('user.change.password');
$app->put('/user/:id', '\Xibo\Controller\User:edit')->name('user.edit');
$app->delete('/user/:id', '\Xibo\Controller\User:delete')->name('user.delete');

//
// User Group
//
$app->get('/group', '\Xibo\Controller\UserGroup:grid')->name('group.search');
$app->post('/group', '\Xibo\Controller\UserGroup:add')->name('group.add');
$app->put('/group/:id', '\Xibo\Controller\UserGroup:edit')->name('group.edit');
$app->delete('/group/:id', '\Xibo\Controller\UserGroup:delete')->name('group.delete');
$app->post('/group/members/:id', '\Xibo\Controller\UserGroup:members')->name('group.members');
$app->post('/group/acl/:entity/:id', '\Xibo\Controller\UserGroup:acl')->name('group.acl');

//
// Applications
//
$app->get('/applications', '\Xibo\Controller\Applications:grid')->name('application.search');

//
// Modules
//
$app->get('/module', '\Xibo\Controller\Module:grid')->name('module.search');

//
// Transition
//
$app->get('/transition', '\Xibo\Controller\Transition:grid')->name('transition.search');

//
// Sessions
//
$app->get('/sessions', '\Xibo\Controller\Sessions:grid')->name('sessions.search');

//
// Help
//
$app->get('/help', '\Xibo\Controller\Help:grid')->name('help.search');

//
// Settings
//
$app->put('/admin', '\Xibo\Controller\Settings:update')->name('settings.update');
