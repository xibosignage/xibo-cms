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
$app->post('/schedule', '\Xibo\Controller\Schedule:add')->name('schedule.add');
$app->put('/schedule/:id', '\Xibo\Controller\Schedule:edit')->name('schedule.edit');
$app->delete('/schedule/:id', '\Xibo\Controller\Schedule:delete')->name('schedule.delete');

//
// Layouts
//
$app->get('/layout', '\Xibo\Controller\Layout:grid')->name('layout.search');
$app->post('/layout', '\Xibo\Controller\Layout:add')->name('layout.add');
$app->put('/layout/:id', '\Xibo\Controller\Layout:edit')->name('layout.edit');
$app->post('/layout/copy/:id', '\Xibo\Controller\Layout:copy')->name('layout.copy');
$app->delete('/layout/:id', '\Xibo\Controller\Layout:delete')->name('layout.delete');
$app->put('/layout/retire/:id', '\Xibo\Controller\Layout:retire')->name('layout.retire');

// Region
$app->post('/region/:id', '\Xibo\Controller\Region:add')->name('region.add');
$app->put('/region/:id', '\Xibo\Controller\Region:edit')->name('region.edit');
$app->delete('/region/:id', '\Xibo\Controller\Region:delete')->name('region.delete');
$app->put('/region/position/all/:id', '\Xibo\Controller\Region:positionAll')->name('region.position.all');

//
// playlist
//
$app->get('/playlist/widget', '\Xibo\Controller\Playlist:widgetGrid')->name('playlist.widget.search');
$app->post('/playlist/order/:id', '\Xibo\Controller\Playlist:order')->name('playlist.order');
$app->post('/playlist/library/assign/:id', '\Xibo\Controller\Playlist:libraryAssign')->name('playlist.library.assign');

//
// Campaign
//
$app->get('/campaign', '\Xibo\Controller\Campaign:grid')->name('campaign.search');
$app->post('/campaign', '\Xibo\Controller\Campaign:add')->name('campaign.add');
$app->put('/campaign/:id', '\Xibo\Controller\Campaign:edit')->name('campaign.edit');
$app->delete('/campaign/:id', '\Xibo\Controller\Campaign:delete')->name('campaign.delete');

// We use POST requests so that we can support multiple records
$app->post('/campaign/layout/assign/:id', '\Xibo\Controller\Campaign:assignLayout')->name('campaign.assign.layout');
$app->post('/campaign/layout/unassign/:id', '\Xibo\Controller\Campaign:unassignLayout')->name('campaign.unassign.layout');

//
// Template
//
$app->get('/template', '\Xibo\Controller\Template:grid')->name('template.search');

//
// Resolutions
//
$app->get('/resolution', '\Xibo\Controller\Resolution:grid')->name('resolution.search');
$app->post('/resolution', '\Xibo\Controller\Resolution:add')->name('resolution.add');
$app->put('/resolution/:id', '\Xibo\Controller\Resolution:edit')->name('resolution.edit');
$app->delete('/resolution/:id', '\Xibo\Controller\Resolution:delete')->name('resolution.delete');

//
// Library
//
$app->map('/library', '\Xibo\Controller\Library:add')->via('HEAD');
$app->get('/library', '\Xibo\Controller\Library:grid')->name('library.search');
$app->get('/library/download/:id', '\Xibo\Controller\Library:download')->name('library.download');
$app->post('/library', '\Xibo\Controller\Library:add')->name('library.add');
$app->put('/library/:id', '\Xibo\Controller\Library:edit')->name('library.edit');
$app->delete('/library/:id', '\Xibo\Controller\Library:delete')->name('library.delete');
$app->delete('/library/tidy/', '\Xibo\Controller\Library:tidy')->name('library.tidy');

//
// Displays
//
$app->get('/display', '\Xibo\Controller\Display:grid')->name('display.search');
$app->put('/display/:id', '\Xibo\Controller\Display:edit')->name('display.edit');
$app->delete('/display/:id', '\Xibo\Controller\Display:delete')->name('display.delete');
$app->get('/display/wol/:id', '\Xibo\Controller\Display:wakeOnLan')->name('display.wol');
$app->put('/display/requestscreenshot/:id', '\Xibo\Controller\Display:requestScreenShot')->name('display.requestscreenshot');

//
// Display Group
//
$app->get('/displaygroup', '\Xibo\Controller\DisplayGroup:grid')->name('displayGroup.search');
$app->post('/displaygroup', '\Xibo\Controller\DisplayGroup:add')->name('displayGroup.add');
$app->put('/displaygroup/:id', '\Xibo\Controller\DisplayGroup:edit')->name('displayGroup.edit');
$app->delete('/displaygroup/:id', '\Xibo\Controller\DisplayGroup:delete')->name('displayGroup.delete');
$app->post('/displaygroup/version/:id', '\Xibo\Controller\DisplayGroup:version')->name('displayGroup.version');

$app->post('/displaygroup/display/assign/:id', '\Xibo\Controller\DisplayGroup:assignDisplay')->name('displayGroup.assign.display');
$app->post('/displaygroup/display/unassign/:id', '\Xibo\Controller\DisplayGroup:unassignDisplay')->name('displayGroup.unassign.display');
$app->post('/displaygroup/media/assign/:id', '\Xibo\Controller\DisplayGroup:assignMedia')->name('displayGroup.assign.media');
$app->post('/displaygroup/media/unassign/:id', '\Xibo\Controller\DisplayGroup:unassignMedia')->name('displayGroup.unassign.media');

//
// Display Profile
//
$app->get('/displayprofile', '\Xibo\Controller\DisplayProfile:grid')->name('displayProfile.search');
$app->post('/displayprofile', '\Xibo\Controller\DisplayProfile:add')->name('displayProfile.add');
$app->put('/displayprofile/:id', '\Xibo\Controller\DisplayProfile:edit')->name('displayProfile.edit');
$app->delete('/displayprofile/:id', '\Xibo\Controller\DisplayProfile:delete')->name('displayProfile.delete');

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

$app->post('/group/members/assign/:id', '\Xibo\Controller\UserGroup:assignUser')->name('group.members.assign');
$app->post('/group/members/unassign/:id', '\Xibo\Controller\UserGroup:unassignUser')->name('group.members.unassign');

$app->post('/group/acl/:entity/:id', '\Xibo\Controller\UserGroup:acl')->name('group.acl');

//
// Applications
//
$app->get('/applications', '\Xibo\Controller\Applications:grid')->name('application.search');

//
// Modules
//
$app->get('/module', '\Xibo\Controller\Module:grid')->name('module.search');
$app->put('/module/settings/:id', '\Xibo\Controller\Module:settings')->name('module.settings');
$app->put('/module/verify', '\Xibo\Controller\Module:verify')->name('module.verify');
$app->post('/module/:type/:id', '\Xibo\Controller\Module:addWidget')->name('module.widget.add');
$app->put('/module/:id', '\Xibo\Controller\Module:editWidget')->name('module.widget.edit');
$app->delete('/module/:id', '\Xibo\Controller\Module:deleteWidget')->name('module.widget.delete');
$app->put('/module/transition/:type/:id', '\Xibo\Controller\Module:editWidgetTransition')->name('module.widget.transition.edit');

//
// Transition
//
$app->get('/transition', '\Xibo\Controller\Transition:grid')->name('transition.search');
$app->put('/transition/:id', '\Xibo\Controller\Transition:edit')->name('transition.edit');

//
// Sessions
//
$app->get('/sessions', '\Xibo\Controller\Sessions:grid')->name('sessions.search');
$app->delete('/sessions/logout/:id', '\Xibo\Controller\Sessions:logout')->name('sessions.confirm.logout');

//
// Help
//
$app->get('/help', '\Xibo\Controller\Help:grid')->name('help.search');
$app->post('/help/add', '\Xibo\Controller\Help:add')->name('help.add');
$app->put('/help/edit/:id', '\Xibo\Controller\Help:edit')->name('help.edit');
$app->delete('/help/delete/:id', '\Xibo\Controller\Help:delete')->name('help.delete');

//
// Settings
//
$app->put('/admin', '\Xibo\Controller\Settings:update')->name('settings.update');

//
// Audit Log
//
$app->get('/audit', '\Xibo\Controller\AuditLog:grid')->name('auditLog.search');
$app->get('/audit/export', '\Xibo\Controller\AuditLog:export')->name('auditLog.export');