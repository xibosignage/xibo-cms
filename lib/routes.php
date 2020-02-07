<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
 *  description="Xibo CMS API.
       Using HTTP formData requests.
       All PUT requests require Content-Type:application/x-www-form-urlencoded header.",
 *  version="2.2",
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
$app->get('/about', ['\Xibo\Controller\Login', 'About'])->setName('about');
$app->get('/clock', ['\Xibo\Controller\Clock', 'clock'])->setName('clock');
$app->post('/tfa', ['\Xibo\Controller\Login' , 'twoFactorAuthValidate'])->setName('tfa.auth.validate');

/**
 * Schedule
 * @SWG\Tag(
 *  name="schedule",
 *  description="Schedule"
 * )
 */
$app->get('/schedule/data/events', ['\Xibo\Controller\Schedule','eventData'])->setName('schedule.calendar.data');
$app->get('/schedule/{id}/events', ['\Xibo\Controller\Schedule','eventList'])->setName('schedule.events');
$app->post('/schedule', ['\Xibo\Controller\Schedule','add'])->setName('schedule.add');
$app->put('/schedule/{id}', ['\Xibo\Controller\Schedule','edit'])->setName('schedule.edit');
$app->delete('/schedule/{id}', ['\Xibo\Controller\Schedule','delete'])->setName('schedule.delete');
$app->delete('/schedulerecurrence/{id}', ['\Xibo\Controller\Schedule','deleteRecurrence'])->setName('schedule.recurrence.delete');

/**
 * Notification
 * @SWG\Tag(
 *  name="notification",
 *  description="Notifications"
 * )
 */
$app->get('/notification', ['\Xibo\Controller\Notification','grid'])->setName('notification.search');
$app->post('/notification', ['\Xibo\Controller\Notification','add'])->setName('notification.add');
//$app->map(['HEAD'], '/notification/attachment', ['\Xibo\Controller\Notification','addAttachment']);
$app->post('/notification/attachment', ['\Xibo\Controller\Notification','addAttachment'])->setName('notification.addattachment');
$app->put('/notification/{id}', ['\Xibo\Controller\Notification','edit'])->setName('notification.edit');
$app->delete('/notification/{id}', ['\Xibo\Controller\Notification','delete'])->setName('notification.delete');

/**
 * Layouts
 * @SWG\Tag(
 *  name="layout",
 *  description="Layouts"
 * )
 */
$app->get('/layout', ['\Xibo\Controller\Layout','grid'])->setName('layout.search');
$app->post('/layout', ['\Xibo\Controller\Layout','add'])->setName('layout.add');
$app->put('/layout/{id}', ['\Xibo\Controller\Layout','edit'])->setName('layout.edit');
$app->post('/layout/copy/{id}', ['\Xibo\Controller\Layout','copy'])->setName('layout.copy');
$app->delete('/layout/{id}', ['\Xibo\Controller\Layout','delete'])->setName('layout.delete');

$app->put('/layout/background/{id}', ['\Xibo\Controller\Layout','editBackground'])->setName('layout.edit.background');
$app->put('/layout/checkout/{id}', ['\Xibo\Controller\Layout','checkout'])->setName('layout.checkout');
$app->put('/layout/publish/{id}', ['\Xibo\Controller\Layout','publish'])->setName('layout.publish');
$app->put('/layout/discard/{id}', ['\Xibo\Controller\Layout','discard'])->setName('layout.discard');
$app->put('/layout/retire/{id}', ['\Xibo\Controller\Layout','retire'])->setName('layout.retire');
$app->put('/layout/unretire/{id}', ['\Xibo\Controller\Layout','unretire'])->setName('layout.unretire');
$app->put('/layout/setenablestat/{id}', ['\Xibo\Controller\Layout','setEnableStat'])->setName('layout.setenablestat');
$app->get('/layout/status/{id}', ['\Xibo\Controller\Layout','status'])->setName('layout.status');
// Layout Import
//$app->map(['HEAD'],'/layout/import', ['\Xibo\Controller\Library','add');
$app->post('/layout/import', ['\Xibo\Controller\Layout','import'])->setName('layout.import');
// Tagging
$app->post('/layout/{id}/tag', ['\Xibo\Controller\Layout','tag'])->setName('layout.tag');
$app->post('/layout/{id}/untag', ['\Xibo\Controller\Layout','untag'])->setName('layout.untag');

/**
 * Region
 */
$app->post('/region/{id}', ['\Xibo\Controller\Region','add'])->setName('region.add');
$app->put('/region/{id}', ['\Xibo\Controller\Region','edit'])->setName('region.edit');
$app->delete('/region/{id}', ['\Xibo\Controller\Region','delete'])->setName('region.delete');
$app->put('/region/position/all/{id}', ['\Xibo\Controller\Region','positionAll'])->setName('region.position.all');

/**
 * playlist
 * @SWG\Tag(
 *  name="playlist",
 *  description="Playlists"
 * )
 */
$app->get('/playlist', ['\Xibo\Controller\Playlist','grid'])->setName('playlist.search');
$app->post('/playlist', ['\Xibo\Controller\Playlist','add'])->setName('playlist.add');
$app->put('/playlist/{id}', ['\Xibo\Controller\Playlist','edit'])->setName('playlist.edit');
$app->delete('/playlist/{id}', ['\Xibo\Controller\Playlist','delete'])->setName('playlist.delete');
$app->post('/playlist/copy/{id}', ['\Xibo\Controller\Playlist','copy'])->setName('playlist.copy');
$app->put('/playlist/setenablestat/{id}', ['\Xibo\Controller\Playlist','setEnableStat'])->setName('playlist.setenablestat');
$app->get('/playlist/usage/{id}', ['\Xibo\Controller\Playlist','usage'])->setName('playlist.usage');
$app->get('/playlist/usage/layouts/{id}', ['\Xibo\Controller\Playlist','usageLayouts'])->setName('playlist.usage.layouts');

// Widgets Order
$app->get('/playlist/widget', ['\Xibo\Controller\Playlist','widgetGrid'])->setName('playlist.widget.search');
$app->post('/playlist/order/{id}', ['\Xibo\Controller\Playlist','order'])->setName('playlist.order');
$app->post('/playlist/library/assign/{id}', ['\Xibo\Controller\Playlist','libraryAssign'])->setName('playlist.library.assign');
// Widget Modules
/**
 * @SWG\Tag(
 *  name="widget",
 *  description="Widgets"
 * )
 */
$app->post('/playlist/widget/{type}/{id}', ['\Xibo\Controller\Module','addWidget'])->setName('module.widget.add');
$app->put('/playlist/widget/{id}', ['\Xibo\Controller\Module','editWidget'])->setName('module.widget.edit');
$app->delete('/playlist/widget/{id}', ['\Xibo\Controller\Module','deleteWidget'])->setName('module.widget.delete');
$app->put('/playlist/widget/transition/{type}/{id}', ['\Xibo\Controller\Module','editWidgetTransition'])->setName('module.widget.transition.edit');
$app->put('/playlist/widget/{id}/audio', ['\Xibo\Controller\Module','widgetAudio'])->setName('module.widget.audio');
$app->delete('/playlist/widget/{id}/audio', ['\Xibo\Controller\Module','widgetAudioDelete']);
$app->put('/playlist/widget/{id}/expiry', ['\Xibo\Controller\Module','widgetExpiry'])->setName('module.widget.expiry');

/**
 * Campaign
 * @SWG\Tag(
 *  name="campaign",
 *  description="Campaigns"
 * )
 */
$app->get('/campaign', ['\Xibo\Controller\Campaign','grid'])->setName('campaign.search');
$app->post('/campaign', ['\Xibo\Controller\Campaign','add'])->setName('campaign.add');
$app->put('/campaign/{id}', ['\Xibo\Controller\Campaign','edit'])->setName('campaign.edit');
$app->delete('/campaign/{id}', ['\Xibo\Controller\Campaign','delete'])->setName('campaign.delete');
$app->post('/campaign/{id}/copy', ['\Xibo\Controller\Campaign','copy'])->setName('campaign.copy');

// We use POST requests so that we can support multiple records
$app->post('/campaign/layout/assign/{id}', ['\Xibo\Controller\Campaign','assignLayout'])->setName('campaign.assign.layout');
$app->post('/campaign/layout/unassign/{id}', ['\Xibo\Controller\Campaign','unassignLayout'])->setName('campaign.unassign.layout');

/**
 * Templates
 * @SWG\Tag(
 *  name="template",
 *  description="Templates"
 * )
 */
$app->get('/template', ['\Xibo\Controller\Template','grid'])->setName('template.search');
$app->post('/template/{id}', ['\Xibo\Controller\Template','add'])->setName('template.add.from.layout');

/**
 * Resolutions
 * @SWG\Tag(
 *  name="resolution",
 *  description="Resolutions"
 * )
 */
$app->get('/resolution', ['\Xibo\Controller\Resolution','grid'])->setName('resolution.search');
$app->post('/resolution', ['\Xibo\Controller\Resolution','add'])->setName('resolution.add');
$app->put('/resolution/{id}', ['\Xibo\Controller\Resolution','edit'])->setName('resolution.edit');
$app->delete('/resolution/{id}', ['\Xibo\Controller\Resolution','delete'])->setName('resolution.delete');

/**
 * Library
 * @SWG\Tag(
 *  name="library",
 *  description="Library"
 * )
 */
//$app->map(['HEAD'],'/library', ['\Xibo\Controller\Library','add']);
$app->get('/library', ['\Xibo\Controller\Library','grid'])->setName('library.search');
$app->get('/library/usage/{id}', ['\Xibo\Controller\Library','usage'])->setName('library.usage');
$app->get('/library/usage/layouts/{id}', ['\Xibo\Controller\Library','usageLayouts'])->setName('library.usage.layouts');

$app->get('/library/download[/{id}[/{type}]]', ['\Xibo\Controller\Library','download'])->setName('library.download');
$app->post('/library', ['\Xibo\Controller\Library','add'])->setName('library.add');
$app->post('/library/uploadUrl', ['\Xibo\Controller\Library','uploadFromUrl'])->setName('library.uploadFromUrl');
$app->put('/library/{id}', ['\Xibo\Controller\Library','edit'])->setName('library.edit');
$app->put('/library/setenablestat/{id}', ['\Xibo\Controller\Library','setEnableStat'])->setName('library.setenablestat');
$app->delete('/library/tidy', ['\Xibo\Controller\Library','tidy'])->setName('library.tidy');
$app->delete('/library/{id}', ['\Xibo\Controller\Library','delete'])->setName('library.delete');
$app->post('/library/copy/{id}', ['\Xibo\Controller\Library','copy'])->setName('library.copy');
$app->get('/library/{id}/isused', ['\Xibo\Controller\Library','isUsed'])->setName('library.isused');
// Tagging
$app->post('/library/{id}/tag', ['\Xibo\Controller\Library','tag'])->setName('library.tag');
$app->post('/library/{id}/untag', ['\Xibo\Controller\Library','untag'])->setName('library.untag');

/**
 * Displays
 * @SWG\Tag(
 *  name="display",
 *  description="Displays"
 * )
 */
$app->get('/display', ['\Xibo\Controller\Display', 'grid'])->setName('display.search');
$app->put('/display/{id}', ['\Xibo\Controller\Display','edit'])->setName('display.edit');
$app->delete('/display/{id}', ['\Xibo\Controller\Display','delete'])->setName('display.delete');
$app->post('/display/wol/{id}', ['\Xibo\Controller\Display','wakeOnLan'])->setName('display.wol');
$app->put('/display/authorise/{id}', ['\Xibo\Controller\Display','toggleAuthorise'])->setName('display.authorise');
$app->put('/display/defaultlayout/{id}', ['\Xibo\Controller\Display','setDefaultLayout'])->setName('display.defaultlayout');
$app->put('/display/requestscreenshot/{id}', ['\Xibo\Controller\Display','requestScreenShot'])->setName('display.requestscreenshot');
$app->put('/display/licenceCheck/{id}', ['\Xibo\Controller\Display','checkLicence'])->setName('display.licencecheck');
$app->get('/display/screenshot/{id}', ['\Xibo\Controller\Display','screenShot'])->setName('display.screenShot');
$app->post('/display/{id}/displaygroup/assign', ['\Xibo\Controller\Display','assignDisplayGroup'])->setName('display.assign.displayGroup');
$app->put('/display/{id}/moveCms', ['\Xibo\Controller\Display','moveCms'])->setName('display.moveCms');
$app->post('/display/addViaCode', ['\Xibo\Controller\Display','addViaCode'])->setName('display.addViaCode');

/**
 * Display Groups
 * @SWG\Tag(
 *  name="displayGroup",
 *  description="Display Groups"
 * )
 */
$app->get('/displaygroup', ['\Xibo\Controller\DisplayGroup','grid'])->setName('displayGroup.search');
$app->post('/displaygroup', ['\Xibo\Controller\DisplayGroup','add'])->setName('displayGroup.add');
$app->put('/displaygroup/{id}', ['\Xibo\Controller\DisplayGroup','edit'])->setName('displayGroup.edit');
$app->delete('/displaygroup/{id}', ['\Xibo\Controller\DisplayGroup','delete'])->setName('displayGroup.delete');

$app->post('/displaygroup/{id}/display/assign', ['\Xibo\Controller\DisplayGroup','assignDisplay'])->setName('displayGroup.assign.display');
$app->post('/displaygroup/{id}/display/unassign', ['\Xibo\Controller\DisplayGroup','unassignDisplay'])->setName('displayGroup.unassign.display');
$app->post('/displaygroup/{id}/displayGroup/assign', ['\Xibo\Controller\DisplayGroup','assignDisplayGroup'])->setName('displayGroup.assign.displayGroup');
$app->post('/displaygroup/{id}/displayGroup/unassign', ['\Xibo\Controller\DisplayGroup','unassignDisplayGroup'])->setName('displayGroup.unassign.displayGroup');
$app->post('/displaygroup/{id}/media/assign', ['\Xibo\Controller\DisplayGroup','assignMedia'])->setName('displayGroup.assign.media');
$app->post('/displaygroup/{id}/media/unassign', ['\Xibo\Controller\DisplayGroup','unassignMedia'])->setName('displayGroup.unassign.media');
$app->post('/displaygroup/{id}/layout/assign', ['\Xibo\Controller\DisplayGroup','assignLayouts'])->setName('displayGroup.assign.layout');
$app->post('/displaygroup/{id}/layout/unassign', ['\Xibo\Controller\DisplayGroup','unassignLayouts'])->setName('displayGroup.unassign.layout');

$app->post('/displaygroup/{id}/action/collectNow', ['\Xibo\Controller\DisplayGroup','collectNow'])->setName('displayGroup.action.collectNow');
$app->post('/displaygroup/{id}/action/clearStatsAndLogs', ['\Xibo\Controller\DisplayGroup','clearStatsAndLogs'])->setName('displayGroup.action.clearStatsAndLogs');
$app->post('/displaygroup/{id}/action/changeLayout', ['\Xibo\Controller\DisplayGroup','changeLayout'])->setName('displayGroup.action.changeLayout');
$app->post('/displaygroup/{id}/action/overlayLayout', ['\Xibo\Controller\DisplayGroup','overlayLayout'])->setName('displayGroup.action.overlayLayout');
$app->post('/displaygroup/{id}/action/revertToSchedule', ['\Xibo\Controller\DisplayGroup','revertToSchedule'])->setName('displayGroup.action.revertToSchedule');
$app->post('/displaygroup/{id}/action/command', ['\Xibo\Controller\DisplayGroup','command'])->setName('displayGroup.action.command');
$app->post('/displaygroup/{id}/copy', ['\Xibo\Controller\DisplayGroup','copy'])->setName('displayGroup.copy');

/**
 * Display Profile
 * @SWG\Tag(
 *  name="displayprofile",
 *  description="Display Settings"
 * )
 */
$app->get('/displayprofile', ['\Xibo\Controller\DisplayProfile','grid'])->setName('displayProfile.search');
$app->post('/displayprofile', ['\Xibo\Controller\DisplayProfile','add'])->setName('displayProfile.add');
$app->put('/displayprofile/{id}', ['\Xibo\Controller\DisplayProfile','edit'])->setName('displayProfile.edit');
$app->delete('/displayprofile/{id}', ['\Xibo\Controller\DisplayProfile','delete'])->setName('displayProfile.delete');
$app->post('/displayprofile/{id}/copy', ['\Xibo\Controller\DisplayProfile','copy'])->setName('displayProfile.copy');

/**
 * DataSet
 * @SWG\Tag(
 *  name="dataset",
 *  description="DataSets"
 * )
 */
$app->get('/dataset', ['\Xibo\Controller\DataSet','grid'])->setName('dataSet.search');
$app->post('/dataset', ['\Xibo\Controller\DataSet','add'])->setName('dataSet.add');
$app->put('/dataset/{id}', ['\Xibo\Controller\DataSet','edit'])->setName('dataSet.edit');
$app->delete('/dataset/{id}', ['\Xibo\Controller\DataSet','delete'])->setName('dataSet.delete');
$app->post('/dataset/copy/{id}', ['\Xibo\Controller\DataSet','copy'])->setName('dataSet.copy');
//$app->map(['HEAD'],'/dataset/import/{id}', ['\Xibo\Controller\DataSet','import');
$app->post('/dataset/import/{id}', ['\Xibo\Controller\DataSet','import'])->setName('dataSet.import');
$app->post('/dataset/importjson/{id}', ['\Xibo\Controller\DataSet','importJson'])->setName('dataSet.import.json');
$app->post('/dataset/remote/test', ['\Xibo\Controller\DataSet','testRemoteRequest'])->setName('dataSet.test.remote');

// Columns
$app->get('/dataset/{id}/column', ['\Xibo\Controller\DataSetColumn','grid'])->setName('dataSet.column.search');
$app->post('/dataset/{id}/column', ['\Xibo\Controller\DataSetColumn','add'])->setName('dataSet.column.add');
$app->put('/dataset/{id}/column/{colId}', ['\Xibo\Controller\DataSetColumn','edit'])->setName('dataSet.column.edit');
$app->delete('/dataset/{id}/column/{colId}', ['\Xibo\Controller\DataSetColumn','delete'])->setName('dataSet.column.delete');
// Data
$app->get('/dataset/data/{id}', ['\Xibo\Controller\DataSetData','grid'])->setName('dataSet.data.search');
$app->post('/dataset/data/{id}', ['\Xibo\Controller\DataSetData','add'])->setName('dataSet.data.add');
$app->put('/dataset/data/{id}/{rowId}', ['\Xibo\Controller\DataSetData','edit'])->setName('dataSet.data.edit');
$app->delete('/dataset/data/{id}/{rowId}', ['\Xibo\Controller\DataSetData','delete'])->setName('dataSet.data.delete');
// RSS
$app->get('/dataset/{id}/rss', ['\Xibo\Controller\DataSetRss','grid'])->setName('dataSet.rss.search');
$app->post('/dataset/{id}/rss', ['\Xibo\Controller\DataSetRss','add'])->setName('dataSet.rss.add');
$app->put('/dataset/{id}/rss/{rssId}', ['\Xibo\Controller\DataSetRss','edit'])->setName('dataSet.rss.edit');
$app->delete('/dataset/{id}/rss/{rssId}', ['\Xibo\Controller\DataSetRss','delete'])->setName('dataSet.rss.delete');
$app->get('/rss/{psk}', ['\Xibo\Controller\DataSetRss','feed'])->setName('dataSet.rss.feed');

/**
 * Statistics
 * @SWG\Tag(
 *  name="statistics",
 *  description="Statistics"
 * )
 */
$app->get('/stats', ['\Xibo\Controller\Stats','grid'])->setName('stats.search');
$app->get('/stats/data/bandwidth', ['\Xibo\Controller\Stats','bandwidthData'])->setName('stats.bandwidth.data');
$app->get('/stats/data/timeDisconnected', ['\Xibo\Controller\Stats','timeDisconnectedGrid'])->setName('stats.timeDisconnected.search');
$app->get('/stats/export', ['\Xibo\Controller\Stats','export'])->setName('stats.export');

/**
 * Log
 * @SWG\Tag(
 *  name="log",
 *  description="Logs"
 * )
 */
$app->get('/log', ['\Xibo\Controller\Logging','grid'])->setName('log.search');
$app->delete('/log', ['\Xibo\Controller\Logging','truncate'])->setName('log.truncate');

/**
 * User
 * @SWG\Tag(
 *  name="user",
 *  description="Users"
 * )
 */
// preferences
$app->get('/user/pref', ['\Xibo\Controller\User' , 'pref'])->setName('user.pref');
$app->post('/user/pref', ['\Xibo\Controller\User' ,'prefEdit']);
$app->put('/user/pref', ['\Xibo\Controller\User' ,'prefEditFromForm']);

$app->get('/user/me', ['\Xibo\Controller\User','myDetails'])->setName('user.me');
$app->get('/user', ['\Xibo\Controller\User','grid'])->setName('user.search');
$app->post('/user', ['\Xibo\Controller\User','add'])->setName('user.add');
$app->put('/user/profile/edit', ['\Xibo\Controller\User','editProfile'])->setName('user.edit.profile');
$app->get('/user/profile/setup', ['\Xibo\Controller\User','tfaSetup'])->setName('user.setup.profile');
$app->post('/user/profile/validate', ['\Xibo\Controller\User','tfaValidate'])->setName('user.validate.profile');
$app->get('/user/profile/recoveryGenerate', ['\Xibo\Controller\User','tfaRecoveryGenerate'])->setName('user.recovery.generate.profile');
$app->get('/user/profile/recoveryShow', ['\Xibo\Controller\User','tfaRecoveryShow'])->setName('user.recovery.show.profile');
$app->put('/user/password/forceChange', ['\Xibo\Controller\User','forceChangePassword'])->setName('user.force.change.password');
$app->put('/user/{id}', ['\Xibo\Controller\User','edit'])->setName('user.edit');
$app->delete('/user/{id}', ['\Xibo\Controller\User','delete'])->setName('user.delete');
$app->post('/user/{id}/usergroup/assign', ['\Xibo\Controller\User','assignUserGroup'])->setName('user.assign.userGroup');
// permissions
$app->get('/user/permissions/{entity}/{id}', ['\Xibo\Controller\User','permissionsGrid'])->setName('user.permissions');
$app->post('/user/permissions/{entity}/{id}', ['\Xibo\Controller\User','permissions']);

/**
 * User Group
 * @SWG\Tag(
 *  name="usergroup",
 *  description="User Groups"
 * )
 */
$app->get('/group', ['\Xibo\Controller\UserGroup','grid'])->setName('group.search');
$app->post('/group', ['\Xibo\Controller\UserGroup','add'])->setName('group.add');
$app->put('/group/{id}', ['\Xibo\Controller\UserGroup','edit'])->setName('group.edit');
$app->delete('/group/{id}', ['\Xibo\Controller\UserGroup','delete'])->setName('group.delete');
$app->post('/group/{id}/copy', ['\Xibo\Controller\UserGroup','copy'])->setName('group.copy');

$app->post('/group/members/assign/{id}', ['\Xibo\Controller\UserGroup','assignUser'])->setName('group.members.assign');
$app->post('/group/members/unassign/{id}', ['\Xibo\Controller\UserGroup','unassignUser'])->setName('group.members.unassign');

$app->post('/group/acl/{id}', ['\Xibo\Controller\UserGroup','acl'])->setName('group.acl');

//
// Applications
//
$app->get('/application', ['\Xibo\Controller\Applications','grid'])->setName('application.search');
$app->post('/application', ['\Xibo\Controller\Applications','add'])->setName('application.add');
$app->post('/application/dooh', ['\Xibo\Controller\Applications','addDooh'])->setName('application.addDooh');

/**
 * Modules
 * @SWG\Tag(
 *  name="module",
 *  description="Modules and Widgets"
 * )
 */
$app->get('/module', ['\Xibo\Controller\Module','grid'])->setName('module.search');
$app->put('/module/settings/{id}', ['\Xibo\Controller\Module','settings'])->setName('module.settings');
$app->put('/module/verify', ['\Xibo\Controller\Module','verify'])->setName('module.verify');
$app->put('/module/clear-cache/{id}', ['\Xibo\Controller\Module','clearCache'])->setName('module.clear.cache');

//
// Transition
//
$app->get('/transition', ['\Xibo\Controller\Transition','grid'])->setName('transition.search');
$app->put('/transition/{id}', ['\Xibo\Controller\Transition','edit'])->setName('transition.edit');

//
// Sessions
//
$app->get('/sessions', ['\Xibo\Controller\Sessions','grid'])->setName('sessions.search');
$app->delete('/sessions/logout/{id}', ['\Xibo\Controller\Sessions','logout'])->setName('sessions.confirm.logout');

//
// Help
//
$app->get('/help', ['\Xibo\Controller\Help','grid'])->setName('help.search');
$app->post('/help/add', ['\Xibo\Controller\Help','add'])->setName('help.add');
$app->put('/help/edit/{id}', ['\Xibo\Controller\Help','edit'])->setName('help.edit');
$app->delete('/help/delete/{id}', ['\Xibo\Controller\Help','delete'])->setName('help.delete');

//
// Settings
//
$app->put('/admin', ['\Xibo\Controller\Settings','update'])->setName('settings.update');

//
// Maintenance
//
$app->post('/maintenance/tidy', ['\Xibo\Controller\Maintenance','tidyLibrary'])->setName('maintenance.tidy');
$app->get('/maintenance/export', ['\Xibo\Controller\Maintenance','export'])->setName('maintenance.export');
$app->post('/maintenance/import', ['\Xibo\Controller\Maintenance','import'])->setName('maintenance.import');
$app->map(['HEAD'],'/maintenance/import', ['\Xibo\Controller\Library','add']);

//
// Audit Log
//
$app->get('/audit', ['\Xibo\Controller\AuditLog','grid'])->setName('auditLog.search');
$app->get('/audit/export', ['\Xibo\Controller\AuditLog','export'])->setName('auditLog.export');

//
// Fault
//
$app->put('/fault/debug/on', ['\Xibo\Controller\Fault','debugOn'])->setName('fault.debug.on');
$app->put('/fault/debug/off', ['\Xibo\Controller\Fault','debugOff'])->setName('fault.debug.off');
$app->get('/fault/collect', ['\Xibo\Controller\Fault','collect'])->setName('fault.collect');

/**
 * Commands
 * @SWG\Tag(
 *  name="command",
 *  description="Commands"
 * )
 */
$app->get('/command', ['\Xibo\Controller\Command','grid'])->setName('command.search');
$app->post('/command', ['\Xibo\Controller\Command','add'])->setName('command.add');
$app->put('/command/{id}', ['\Xibo\Controller\Command','edit'])->setName('command.edit');
$app->delete('/command/{id}', ['\Xibo\Controller\Command','delete'])->setName('command.delete');

/**
 * Dayparts
 * @SWG\Tag(
 *  name="dayPart",
 *  description="Dayparting"
 * )
 */
$app->get('/daypart', ['\Xibo\Controller\DayPart','grid'])->setName('daypart.search');
$app->post('/daypart', ['\Xibo\Controller\DayPart','add'])->setName('daypart.add');
$app->put('/daypart/{id}', ['\Xibo\Controller\DayPart','edit'])->setName('daypart.edit');
$app->delete('/daypart/{id}', ['\Xibo\Controller\DayPart','delete'])->setName('daypart.delete');

/**
 * Tasks
 * @SWG\Tag(
 *  name="task",
 *  description="Tasks"
 * )
 */
$app->get('/task', ['\Xibo\Controller\Task','grid'])->setName('task.search');
$app->post('/task', ['\Xibo\Controller\Task','add'])->setName('task.add');
$app->put('/task/{id}', ['\Xibo\Controller\Task','edit'])->setName('task.edit');
$app->delete('/task/{id}', ['\Xibo\Controller\Task','delete'])->setName('task.delete');
$app->post('/task/{id}/run', ['\Xibo\Controller\Task','runNow'])->setName('task.runNow');

/**
 * Report schedule
 * @SWG\Tag(
 *  name="report",
 *  description="Report schedule"
 * )
 */

$app->get('/report/reportschedule', ['\Xibo\Controller\Report','reportScheduleGrid'])->setName('reportschedule.search');
$app->post('/report/reportschedule', ['\Xibo\Controller\Report','reportScheduleAdd'])->setName('reportschedule.add');
$app->put('/report/reportschedule/{id}', ['\Xibo\Controller\Report','reportScheduleEdit'])->setName('reportschedule.edit');
$app->delete('/report/reportschedule/{id}', ['\Xibo\Controller\Report','reportScheduleDelete'])->setName('reportschedule.delete');
$app->post('/report/reportschedule/{id}/deletesavedreport', ['\Xibo\Controller\Report','reportScheduleDeleteAllSavedReport'])->setName('reportschedule.deleteall');
$app->post('/report/reportschedule/{id}/toggleactive', ['\Xibo\Controller\Report','reportScheduleToggleActive'])->setName('reportschedule.toggleactive');
$app->post('/report/reportschedule/{id}/reset', ['\Xibo\Controller\Report','reportScheduleReset'])->setName('reportschedule.reset');

//
// Saved reports
//
$app->get('/report/savedreport', ['\Xibo\Controller\Report','savedReportGrid'])->setName('savedreport.search');
$app->delete('/report/savedreport/{id}', ['\Xibo\Controller\Report','savedReportDelete'])->setName('savedreport.delete');

//
// Ad hoc report
//
$app->get('/report/data/{name}', ['\Xibo\Controller\Report','getReportData'])->setName('report.data');

/**
 * Player Versions
 * @SWG\Tag(
 *  name="Player Software",
 * )
 */
$app->get('/playersoftware', ['\Xibo\Controller\PlayerSoftware','grid'])->setName('playersoftware.search');
$app->put('/playersoftware/{id}', ['\Xibo\Controller\PlayerSoftware','edit'])->setName('playersoftware.edit');
$app->delete('/playersoftware/{id}', ['\Xibo\Controller\PlayerSoftware','delete'])->setName('playersoftware.delete');

// Install
$app->get('/sssp_config.xml', ['\Xibo\Controller\PlayerSoftware','getSsspInstall'])->setName('playersoftware.sssp.install');
$app->get('/sssp_dl.wgt', ['\Xibo\Controller\PlayerSoftware','getSsspInstallDownload'])->setName('playersoftware.sssp.install.download');
$app->get('/playersoftware/nonce/sssp_config.xml', ['\Xibo\Controller\PlayerSoftware','getSssp'])->setName('playersoftware.sssp');
$app->get('/playersoftware/nonce/sssp_dl.wgt', ['\Xibo\Controller\PlayerSoftware','getVersionFile'])->setName('playersoftware.version.file');


/**
 * Tags
 * @SWG\Tag(
 *  name="tags",
 *  description="Tags"
 * )
 */
$app->get('/tag', ['\Xibo\Controller\Tag','grid'])->setName('tag.search');
$app->post('/tag', ['\Xibo\Controller\Tag','add'])->setName('tag.add');
$app->put('/tag/{id}', ['\Xibo\Controller\Tag','edit'])->setName('tag.edit');
$app->delete('/tag/{id}', ['\Xibo\Controller\Tag','delete'])->setName('tag.delete');
$app->get('/tag/name', ['\Xibo\Controller\Tag','loadTagOptions'])->setName('tag.getByName');

