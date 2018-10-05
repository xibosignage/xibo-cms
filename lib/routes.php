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

/**
 * @SWG\Swagger(
 *  basePath="/api",
 *  produces={"application/json"},
 *  schemes={"http"},
 *  security={
 *      {"auth": {"write:all", "read:all"}}
 *  },
 *  @SWG\ExternalDocumentation(
 *      description="Manual",
 *      url="http://xibo.org.uk/manual"
 *  )
 * )
 *
 * @SWG\Info(
 *  title="Xibo API",
 *  description="Xibo CMS API",
 *  version="1.8.0",
 *  termsOfService="http://xibo.org.uk/legal",
 *  @SWG\License(
 *      name="AGPLv3 or later",
 *      url="http://www.gnu.org/licenses/"
 *  ),
 *  @SWG\Contact(
 *      email="info@xibo.org.uk"
 *  )
 * )
 *
 * @SWG\SecurityScheme(
 *   securityDefinition="auth",
 *   type="oauth2",
 *   flow="accessCode",
 *   authorizationUrl="/api/authorize",
 *   tokenUrl="/api/authorize/access_token",
 *   scopes={
 *      "read:all": "read access",
 *      "write:all": "write access"
 *   }
 * )
 */

/**
 * Misc
 * @SWG\Tag(
 *  name="misc",
 *  description="Miscellaneous"
 * )
 */
$app->get('/about', '\Xibo\Controller\Login:About')->name('about');
$app->get('/clock', '\Xibo\Controller\Clock:clock')->name('clock');

/**
 * Schedule
 * @SWG\Tag(
 *  name="schedule",
 *  description="Schedule"
 * )
 */
$app->get('/schedule/data/events', '\Xibo\Controller\Schedule:eventData')->name('schedule.calendar.data');
$app->get('/schedule/:id/events', '\Xibo\Controller\Schedule:eventList')->name('schedule.events');
$app->post('/schedule', '\Xibo\Controller\Schedule:add')->name('schedule.add');
$app->put('/schedule/:id', '\Xibo\Controller\Schedule:edit')->name('schedule.edit');
$app->delete('/schedule/:id', '\Xibo\Controller\Schedule:delete')->name('schedule.delete');

/**
 * Notification
 * @SWG\Tag(
 *  name="notification",
 *  description="Notifications"
 * )
 */
$app->get('/notification', '\Xibo\Controller\Notification:grid')->name('notification.search');
$app->post('/notification', '\Xibo\Controller\Notification:add')->name('notification.add');
$app->put('/notification/:id', '\Xibo\Controller\Notification:edit')->name('notification.edit');
$app->delete('/notification/:id', '\Xibo\Controller\Notification:delete')->name('notification.delete');

/**
 * Layouts
 * @SWG\Tag(
 *  name="layout",
 *  description="Layouts"
 * )
 */
$app->get('/layout', '\Xibo\Controller\Layout:grid')->name('layout.search');
$app->post('/layout', '\Xibo\Controller\Layout:add')->name('layout.add');
$app->put('/layout/:id', '\Xibo\Controller\Layout:edit')->name('layout.edit');
$app->post('/layout/copy/:id', '\Xibo\Controller\Layout:copy')->name('layout.copy');
$app->delete('/layout/:id', '\Xibo\Controller\Layout:delete')->name('layout.delete');
$app->put('/layout/retire/:id', '\Xibo\Controller\Layout:retire')->name('layout.retire');
$app->get('/layout/status/:id', '\Xibo\Controller\Layout:status')->name('layout.status');
// Layout Import
$app->map('/layout/import', '\Xibo\Controller\Library:add')->via('HEAD');
$app->post('/layout/import', '\Xibo\Controller\Layout:import')->name('layout.import');
$app->post('/layout/:id/upgrade', '\Xibo\Controller\Layout:upgrade')->name('layout.upgrade');
// Tagging
$app->post('/layout/:id/tag', '\Xibo\Controller\Layout:tag')->name('layout.tag');
$app->post('/layout/:id/untag', '\Xibo\Controller\Layout:untag')->name('layout.untag');

/**
 * Region
 */
$app->post('/region/:id', '\Xibo\Controller\Region:add')->name('region.add');
$app->put('/region/:id', '\Xibo\Controller\Region:edit')->name('region.edit');
$app->delete('/region/:id', '\Xibo\Controller\Region:delete')->name('region.delete');
$app->put('/region/position/all/:id', '\Xibo\Controller\Region:positionAll')->name('region.position.all');

/**
 * playlist
 * @SWG\Tag(
 *  name="playlist",
 *  description="Playlists"
 * )
 */
$app->get('/playlist', '\Xibo\Controller\Playlist:grid')->name('playlist.search');
$app->post('/playlist', '\Xibo\Controller\Playlist:add')->name('playlist.add');
$app->put('/playlist/:id', '\Xibo\Controller\Playlist:edit')->name('playlist.edit');
$app->delete('/playlist/:id', '\Xibo\Controller\Playlist:delete')->name('playlist.delete');
// Widgets Order
$app->get('/playlist/widget', '\Xibo\Controller\Playlist:widgetGrid')->name('playlist.widget.search');
$app->post('/playlist/order/:id', '\Xibo\Controller\Playlist:order')->name('playlist.order');
$app->post('/playlist/library/assign/:id', '\Xibo\Controller\Playlist:libraryAssign')->name('playlist.library.assign');
// Widget Modules
/**
 * @SWG\Tag(
 *  name="widget",
 *  description="Widgets"
 * )
 */
$app->post('/playlist/widget/:type/:id', '\Xibo\Controller\Module:addWidget')->name('module.widget.add');
$app->put('/playlist/widget/:id', '\Xibo\Controller\Module:editWidget')->name('module.widget.edit');
$app->delete('/playlist/widget/:id', '\Xibo\Controller\Module:deleteWidget')->name('module.widget.delete');
$app->put('/playlist/widget/transition/:type/:id', '\Xibo\Controller\Module:editWidgetTransition')->name('module.widget.transition.edit');
$app->put('/playlist/widget/:id/audio', '\Xibo\Controller\Module:widgetAudio')->name('module.widget.audio');
$app->delete('/playlist/widget/:id/audio', '\Xibo\Controller\Module:widgetAudioDelete');

/**
 * Campaign
 * @SWG\Tag(
 *  name="campaign",
 *  description="Campaigns"
 * )
 */
$app->get('/campaign', '\Xibo\Controller\Campaign:grid')->name('campaign.search');
$app->post('/campaign', '\Xibo\Controller\Campaign:add')->name('campaign.add');
$app->put('/campaign/:id', '\Xibo\Controller\Campaign:edit')->name('campaign.edit');
$app->delete('/campaign/:id', '\Xibo\Controller\Campaign:delete')->name('campaign.delete');

// We use POST requests so that we can support multiple records
$app->post('/campaign/layout/assign/:id', '\Xibo\Controller\Campaign:assignLayout')->name('campaign.assign.layout');
$app->post('/campaign/layout/unassign/:id', '\Xibo\Controller\Campaign:unassignLayout')->name('campaign.unassign.layout');

/**
 * Templates
 * @SWG\Tag(
 *  name="template",
 *  description="Templates"
 * )
 */
$app->get('/template', '\Xibo\Controller\Template:grid')->name('template.search');
$app->post('/template/:id', '\Xibo\Controller\Template:add')->name('template.add.from.layout');

/**
 * Resolutions
 * @SWG\Tag(
 *  name="resolution",
 *  description="Resolutions"
 * )
 */
$app->get('/resolution', '\Xibo\Controller\Resolution:grid')->name('resolution.search');
$app->post('/resolution', '\Xibo\Controller\Resolution:add')->name('resolution.add');
$app->put('/resolution/:id', '\Xibo\Controller\Resolution:edit')->name('resolution.edit');
$app->delete('/resolution/:id', '\Xibo\Controller\Resolution:delete')->name('resolution.delete');

/**
 * Library
 * @SWG\Tag(
 *  name="library",
 *  description="Library"
 * )
 */
$app->map('/library', '\Xibo\Controller\Library:add')->via('HEAD');
$app->get('/library', '\Xibo\Controller\Library:grid')->name('library.search');
$app->get('/library/usage/:id', '\Xibo\Controller\Library:usage')->name('library.usage');
$app->get('/library/usage/layouts/:id', '\Xibo\Controller\Library:usageLayouts')->name('library.usage.layouts');
$app->get('/library/download/:id(/:type)', '\Xibo\Controller\Library:download')->name('library.download');
$app->post('/library', '\Xibo\Controller\Library:add')->name('library.add');
$app->put('/library/:id', '\Xibo\Controller\Library:edit')->name('library.edit');
$app->delete('/library/tidy', '\Xibo\Controller\Library:tidy')->name('library.tidy');
$app->delete('/library/:id', '\Xibo\Controller\Library:delete')->name('library.delete');
// Tagging
$app->post('/library/:id/tag', '\Xibo\Controller\Library:tag')->name('library.tag');
$app->post('/library/:id/untag', '\Xibo\Controller\Library:untag')->name('library.untag');

/**
 * Displays
 * @SWG\Tag(
 *  name="display",
 *  description="Displays"
 * )
 */
$app->get('/display', '\Xibo\Controller\Display:grid')->name('display.search');
$app->put('/display/:id', '\Xibo\Controller\Display:edit')->name('display.edit');
$app->delete('/display/:id', '\Xibo\Controller\Display:delete')->name('display.delete');
$app->post('/display/wol/:id', '\Xibo\Controller\Display:wakeOnLan')->name('display.wol');
$app->put('/display/authorise/:id', '\Xibo\Controller\Display:toggleAuthorise')->name('display.authorise');
$app->put('/display/defaultlayout/:id', '\Xibo\Controller\Display:setDefaultLayout')->name('display.defaultlayout');
$app->put('/display/requestscreenshot/:id', '\Xibo\Controller\Display:requestScreenShot')->name('display.requestscreenshot');
$app->get('/display/screenshot/:id', '\Xibo\Controller\Display:screenShot')->name('display.screenShot');
$app->post('/display/:id/displaygroup/assign', '\Xibo\Controller\Display:assignDisplayGroup')->name('display.assign.displayGroup');

/**
 * Display Groups
 * @SWG\Tag(
 *  name="displayGroup",
 *  description="Display Groups"
 * )
 */
$app->get('/displaygroup', '\Xibo\Controller\DisplayGroup:grid')->name('displayGroup.search');
$app->post('/displaygroup', '\Xibo\Controller\DisplayGroup:add')->name('displayGroup.add');
$app->put('/displaygroup/:id', '\Xibo\Controller\DisplayGroup:edit')->name('displayGroup.edit');
$app->delete('/displaygroup/:id', '\Xibo\Controller\DisplayGroup:delete')->name('displayGroup.delete');
$app->post('/displaygroup/:id/version', '\Xibo\Controller\DisplayGroup:version')->name('displayGroup.version');

$app->post('/displaygroup/:id/display/assign', '\Xibo\Controller\DisplayGroup:assignDisplay')->name('displayGroup.assign.display');
$app->post('/displaygroup/:id/display/unassign', '\Xibo\Controller\DisplayGroup:unassignDisplay')->name('displayGroup.unassign.display');
$app->post('/displaygroup/:id/displayGroup/assign', '\Xibo\Controller\DisplayGroup:assignDisplayGroup')->name('displayGroup.assign.displayGroup');
$app->post('/displaygroup/:id/displayGroup/unassign', '\Xibo\Controller\DisplayGroup:unassignDisplayGroup')->name('displayGroup.unassign.displayGroup');
$app->post('/displaygroup/:id/media/assign', '\Xibo\Controller\DisplayGroup:assignMedia')->name('displayGroup.assign.media');
$app->post('/displaygroup/:id/media/unassign', '\Xibo\Controller\DisplayGroup:unassignMedia')->name('displayGroup.unassign.media');
$app->post('/displaygroup/:id/layout/assign', '\Xibo\Controller\DisplayGroup:assignLayouts')->name('displayGroup.assign.layout');
$app->post('/displaygroup/:id/layout/unassign', '\Xibo\Controller\DisplayGroup:unassignLayouts')->name('displayGroup.unassign.layout');

$app->post('/displaygroup/:id/action/collectNow', '\Xibo\Controller\DisplayGroup:collectNow')->name('displayGroup.action.collectNow');
$app->post('/displaygroup/:id/action/clearStatsAndLogs', '\Xibo\Controller\DisplayGroup:clearStatsAndLogs')->name('displayGroup.action.clearStatsAndLogs');
$app->post('/displaygroup/:id/action/changeLayout', '\Xibo\Controller\DisplayGroup:changeLayout')->name('displayGroup.action.changeLayout');
$app->post('/displaygroup/:id/action/overlayLayout', '\Xibo\Controller\DisplayGroup:overlayLayout')->name('displayGroup.action.overlayLayout');
$app->post('/displaygroup/:id/action/revertToSchedule', '\Xibo\Controller\DisplayGroup:revertToSchedule')->name('displayGroup.action.revertToSchedule');
$app->post('/displaygroup/:id/action/command', '\Xibo\Controller\DisplayGroup:command')->name('displayGroup.action.command');

/**
 * Display Profile
 * @SWG\Tag(
 *  name="displayprofile",
 *  description="Display Settings"
 * )
 */
$app->get('/displayprofile', '\Xibo\Controller\DisplayProfile:grid')->name('displayProfile.search');
$app->post('/displayprofile', '\Xibo\Controller\DisplayProfile:add')->name('displayProfile.add');
$app->put('/displayprofile/:id', '\Xibo\Controller\DisplayProfile:edit')->name('displayProfile.edit');
$app->delete('/displayprofile/:id', '\Xibo\Controller\DisplayProfile:delete')->name('displayProfile.delete');

/**
 * DataSet
 * @SWG\Tag(
 *  name="dataset",
 *  description="DataSets"
 * )
 */
$app->get('/dataset', '\Xibo\Controller\DataSet:grid')->name('dataSet.search');
$app->post('/dataset', '\Xibo\Controller\DataSet:add')->name('dataSet.add');
$app->put('/dataset/:id', '\Xibo\Controller\DataSet:edit')->name('dataSet.edit');
$app->delete('/dataset/:id', '\Xibo\Controller\DataSet:delete')->name('dataSet.delete');
$app->post('/dataset/copy/:id', '\Xibo\Controller\DataSet:copy')->name('dataSet.copy');
$app->map('/dataset/import/:id', '\Xibo\Controller\DataSet:import')->via('HEAD');
$app->post('/dataset/import/:id', '\Xibo\Controller\DataSet:import')->name('dataSet.import');
$app->post('/dataset/importjson/:id', '\Xibo\Controller\DataSet:importJson')->name('dataSet.import.json');
$app->post('/dataset/remote/test', '\Xibo\Controller\DataSet:testRemoteRequest')->name('dataSet.test.remote');

// Columns
$app->get('/dataset/:id/column', '\Xibo\Controller\DataSetColumn:grid')->name('dataSet.column.search');
$app->post('/dataset/:id/column', '\Xibo\Controller\DataSetColumn:add')->name('dataSet.column.add');
$app->put('/dataset/:id/column/:colId', '\Xibo\Controller\DataSetColumn:edit')->name('dataSet.column.edit');
$app->delete('/dataset/:id/column/:colId', '\Xibo\Controller\DataSetColumn:delete')->name('dataSet.column.delete');
// Data
$app->get('/dataset/data/:id', '\Xibo\Controller\DataSetData:grid')->name('dataSet.data.search');
$app->post('/dataset/data/:id', '\Xibo\Controller\DataSetData:add')->name('dataSet.data.add');
$app->put('/dataset/data/:id/:rowId', '\Xibo\Controller\DataSetData:edit')->name('dataSet.data.edit');
$app->delete('/dataset/data/:id/:rowId', '\Xibo\Controller\DataSetData:delete')->name('dataSet.data.delete');

/**
 * Statistics
 * @SWG\Tag(
 *  name="statistics",
 *  description="Statistics"
 * )
 */
$app->get('/stats', '\Xibo\Controller\Stats:grid')->name('stats.search');
$app->get('/stats/data/bandwidth', '\Xibo\Controller\Stats:bandwidthData')->name('stats.bandwidth.data');
$app->get('/stats/data/availability', '\Xibo\Controller\Stats:availabilityData')->name('stats.availability.data');
$app->get('/stats/export', '\Xibo\Controller\Stats:export')->name('stats.export');

/**
 * Log
 * @SWG\Tag(
 *  name="log",
 *  description="Logs"
 * )
 */
$app->get('/log', '\Xibo\Controller\Logging:grid')->name('log.search');
$app->delete('/log', '\Xibo\Controller\Logging:truncate')->name('log.truncate');

/**
 * User
 * @SWG\Tag(
 *  name="user",
 *  description="Users"
 * )
 */
$app->get('/user/me', '\Xibo\Controller\User:myDetails')->name('user.me');
$app->get('/user', '\Xibo\Controller\User:grid')->name('user.search');
$app->post('/user', '\Xibo\Controller\User:add')->name('user.add');
$app->put('/user/password/change', '\Xibo\Controller\User:changePassword')->name('user.change.password');
$app->put('/user/password/forceChange', '\Xibo\Controller\User:forceChangePassword')->name('user.force.change.password');
$app->put('/user/:id', '\Xibo\Controller\User:edit')->name('user.edit');
$app->delete('/user/:id', '\Xibo\Controller\User:delete')->name('user.delete');
$app->post('/user/:id/usergroup/assign', '\Xibo\Controller\User:assignUserGroup')->name('user.assign.userGroup');
// permissions
$app->get('/user/permissions/:entity/:id', '\Xibo\Controller\User:permissionsGrid')->name('user.permissions');
$app->post('/user/permissions/:entity/:id', '\Xibo\Controller\User:permissions');
// preferences
$app->get('/user/pref', '\Xibo\Controller\User:pref')->name('user.pref');
$app->post('/user/pref', '\Xibo\Controller\User:prefEdit');

/**
 * User Group
 * @SWG\Tag(
 *  name="usergroup",
 *  description="User Groups"
 * )
 */
$app->get('/group', '\Xibo\Controller\UserGroup:grid')->name('group.search');
$app->post('/group', '\Xibo\Controller\UserGroup:add')->name('group.add');
$app->put('/group/:id', '\Xibo\Controller\UserGroup:edit')->name('group.edit');
$app->delete('/group/:id', '\Xibo\Controller\UserGroup:delete')->name('group.delete');
$app->post('/group/:id/copy', '\Xibo\Controller\UserGroup:copy')->name('group.copy');

$app->post('/group/members/assign/:id', '\Xibo\Controller\UserGroup:assignUser')->name('group.members.assign');
$app->post('/group/members/unassign/:id', '\Xibo\Controller\UserGroup:unassignUser')->name('group.members.unassign');

$app->post('/group/acl/:id', '\Xibo\Controller\UserGroup:acl')->name('group.acl');

//
// Applications
//
$app->get('/application', '\Xibo\Controller\Applications:grid')->name('application.search');
$app->post('/application', '\Xibo\Controller\Applications:add')->name('application.add');

/**
 * Modules
 * @SWG\Tag(
 *  name="module",
 *  description="Modules and Widgets"
 * )
 */
$app->get('/module', '\Xibo\Controller\Module:grid')->name('module.search');
$app->put('/module/settings/:id', '\Xibo\Controller\Module:settings')->name('module.settings');
$app->put('/module/verify', '\Xibo\Controller\Module:verify')->name('module.verify');
$app->put('/module/clear-cache/:id', '\Xibo\Controller\Module:clearCache')->name('module.clear.cache');

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
// Maintenance
//
$app->post('/maintenance/tidy', '\Xibo\Controller\Maintenance:tidyLibrary')->name('maintenance.tidy');
$app->get('/maintenance/export', '\Xibo\Controller\Maintenance:export')->name('maintenance.export');
$app->post('/maintenance/import', '\Xibo\Controller\Maintenance:import')->name('maintenance.import');
$app->map('/maintenance/import', '\Xibo\Controller\Library:add')->via('HEAD');

//
// Audit Log
//
$app->get('/audit', '\Xibo\Controller\AuditLog:grid')->name('auditLog.search');
$app->get('/audit/export', '\Xibo\Controller\AuditLog:export')->name('auditLog.export');

//
// Fault
//
$app->put('/fault/debug/on', '\Xibo\Controller\Fault:debugOn')->name('fault.debug.on');
$app->put('/fault/debug/off', '\Xibo\Controller\Fault:debugOff')->name('fault.debug.off');
$app->get('/fault/collect', '\Xibo\Controller\Fault:collect')->name('fault.collect');

/**
 * Commands
 * @SWG\Tag(
 *  name="command",
 *  description="Commands"
 * )
 */
$app->get('/command', '\Xibo\Controller\Command:grid')->name('command.search');
$app->post('/command', '\Xibo\Controller\Command:add')->name('command.add');
$app->put('/command/:id', '\Xibo\Controller\Command:edit')->name('command.edit');
$app->delete('/command/:id', '\Xibo\Controller\Command:delete')->name('command.delete');

/**
 * Dayparts
 * @SWG\Tag(
 *  name="dayPart",
 *  description="Dayparting"
 * )
 */
$app->get('/daypart', '\Xibo\Controller\DayPart:grid')->name('daypart.search');
$app->post('/daypart', '\Xibo\Controller\DayPart:add')->name('daypart.add');
$app->put('/daypart/:id', '\Xibo\Controller\DayPart:edit')->name('daypart.edit');
$app->delete('/daypart/:id', '\Xibo\Controller\DayPart:delete')->name('daypart.delete');

/**
 * Tasks
 * @SWG\Tag(
 *  name="task",
 *  description="Tasks"
 * )
 */
$app->get('/task', '\Xibo\Controller\Task:grid')->name('task.search');
$app->post('/task', '\Xibo\Controller\Task:add')->name('task.add');
$app->put('/task/:id', '\Xibo\Controller\Task:edit')->name('task.edit');
$app->delete('/task/:id', '\Xibo\Controller\Task:delete')->name('task.delete');
$app->post('/task/:id/run', '\Xibo\Controller\Task:runNow')->name('task.runNow');