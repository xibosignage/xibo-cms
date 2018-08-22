<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (routes-web.php) is part of Xibo.
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

// Special "root" route
$app->get('/', function () use ($app) {

    // Different controller depending on the homepage of the user.
    $controller = null;
    $user = $app->user;
    /* @var \Xibo\Entity\User $user */

    $app->logService->debug('Showing the homepage: %s', $user->homePageId);

    /** @var \Xibo\Entity\Page $page */
    $page = $app->container->get('pageFactory')->getById($user->homePageId);

    $app->redirectTo($page->getName() . '.view');

})->setName('home');

// Dashboards
$app->get('/dashboard/status', '\Xibo\Controller\StatusDashboard:displayPage')->name('statusdashboard.view');
$app->get('/dashboard/status/displays', '\Xibo\Controller\StatusDashboard:displays')->name('statusdashboard.displays');
$app->get('/dashboard/icon', '\Xibo\Controller\IconDashboard:displayPage')->name('dashboard.view');
$app->get('/dashboard/media', '\Xibo\Controller\MediaManager:displayPage')->name('mediamanager.view');
$app->get('/dashboard/media/data', '\Xibo\Controller\MediaManager:grid')->name('mediaManager.search');

// Login Form
$app->get('/login', '\Xibo\Controller\Login:loginForm')->name('login');

// Login Request
$app->post('/login', '\Xibo\Controller\Login:login');

// Logout Request
$app->get('/logout', '\Xibo\Controller\Login:logout')->name('logout');

// Ping pong route
$app->get('/login/ping', '\Xibo\Controller\Login:PingPong')->name('ping');

//
// upgrade
//
$app->get('/update', '\Xibo\Controller\Upgrade:displayPage')->name('upgrade.view');
$app->post('/update/step/:id', '\Xibo\Controller\Upgrade:doStep')->name('upgrade.doStep');
$app->delete('/update/step/:id', '\Xibo\Controller\Upgrade:skipStep')->name('upgrade.skipStep');

//
// schedule
//
$app->get('/schedule/view', '\Xibo\Controller\Schedule:displayPage')->name('schedule.view');
$app->get('/schedule/form/add', '\Xibo\Controller\Schedule:addForm')->name('schedule.add.form');
$app->get('/schedule/form/edit/:id', '\Xibo\Controller\Schedule:editForm')->name('schedule.edit.form');
$app->get('/schedule/form/delete/:id', '\Xibo\Controller\Schedule:deleteForm')->name('schedule.delete.form');
$app->get('/schedule/form/now/:from/:id', '\Xibo\Controller\Schedule:scheduleNowForm')->name('schedule.now.form');

//
// notification
//
$app->get('/notification/view', '\Xibo\Controller\Notification:displayPage')->name('notification.view');
$app->get('/drawer/notification/show/:id', '\Xibo\Controller\Notification:show')->name('notification.show');
$app->get('/drawer/notification/interrupt/:id', '\Xibo\Controller\Notification:interrupt')->name('notification.interrupt');
$app->get('/notification/form/add', '\Xibo\Controller\Notification:addForm')->name('notification.add.form');
$app->get('/notification/form/edit/:id', '\Xibo\Controller\Notification:editForm')->name('notification.edit.form');
$app->get('/notification/form/delete/:id', '\Xibo\Controller\Notification:deleteForm')->name('notification.delete.form');

//
// layouts
//
$app->get('/layout/view', '\Xibo\Controller\Layout:displayPage')->name('layout.view');
$app->get('/layout/designer/:id', '\Xibo\Controller\Layout:displayDesigner')->name('layout.designer');
$app->get('/layout/preview/:id', '\Xibo\Controller\Preview:show')->name('layout.preview');
$app->get('/layout/xlf/:id', '\Xibo\Controller\Preview:getXlf')->name('layout.getXlf');
$app->get('/layout/export/:id', '\Xibo\Controller\Layout:export')->name('layout.export');
$app->get('/layout/background/:id', '\Xibo\Controller\Layout:downloadBackground')->name('layout.download.background');
// forms
$app->get('/layout/form/add', '\Xibo\Controller\Layout:addForm')->name('layout.add.form');
$app->get('/layout/form/edit/:id', '\Xibo\Controller\Layout:editForm')->name('layout.edit.form');
$app->get('/layout/form/copy/:id', '\Xibo\Controller\Layout:copyForm')->name('layout.copy.form');
$app->get('/layout/form/delete/:id', '\Xibo\Controller\Layout:deleteForm')->name('layout.delete.form');
$app->get('/layout/form/retire/:id', '\Xibo\Controller\Layout:retireForm')->name('layout.retire.form');
$app->get('/layout/form/upgrade/:id', '\Xibo\Controller\Layout:upgradeForm')->name('layout.upgrade.form');
$app->get('/layout/form/export/:id', '\Xibo\Controller\Layout:exportForm')->name('layout.export.form');
$app->get('/layout/form/campaign/assign/:id', '\Xibo\Controller\Layout:assignToCampaignForm')->name('layout.assignTo.campaign.form');

//
// regions
//
$app->get('/region/preview/:id', '\Xibo\Controller\Region:preview')->name('region.preview');
$app->get('/region/form/edit/:id', '\Xibo\Controller\Region:editForm')->name('region.edit.form');
$app->get('/region/form/delete/:id', '\Xibo\Controller\Region:deleteForm')->name('region.delete.form');
$app->get('/region/form/timeline/:id', '\Xibo\Controller\Region:timelineForm')->name('region.timeline.form');

//
// playlists
//
$app->get('/playlist/form/library/assign/:id', '\Xibo\Controller\Playlist:libraryAssignForm')->name('playlist.library.assign.form');
// Module functions
$app->get('/playlist/widget/form/add/:type/:id', '\Xibo\Controller\Module:addWidgetForm')->name('module.widget.add.form');
$app->get('/playlist/widget/form/edit/:id', '\Xibo\Controller\Module:editWidgetForm')->name('module.widget.edit.form');
$app->get('/playlist/widget/form/delete/:id', '\Xibo\Controller\Module:deleteWidgetForm')->name('module.widget.delete.form');
$app->get('/playlist/widget/form/transition/edit/:type/:id', '\Xibo\Controller\Module:editWidgetTransitionForm')->name('module.widget.transition.edit.form');
$app->get('/playlist/widget/form/audio/:id', '\Xibo\Controller\Module:widgetAudioForm')->name('module.widget.audio.form');
// Outputs
$app->get('/playlist/widget/tab/:tab/:id', '\Xibo\Controller\Module:getTab')->name('module.widget.tab.form');
$app->get('/playlist/widget/resource/:regionId/:id', '\Xibo\Controller\Module:getResource')->name('module.getResource');
$app->get('/playlist/widget/form/templateimage/:type/:templateId', '\Xibo\Controller\Module:getTemplateImage')->name('module.getTemplateImage');

//
// library
//
$app->get('/library/view', '\Xibo\Controller\Library:displayPage')->name('library.view');
$app->get('/library/form/edit/:id', '\Xibo\Controller\Library:editForm')->name('library.edit.form');
$app->get('/library/form/delete/:id', '\Xibo\Controller\Library:deleteForm')->name('library.delete.form');
$app->get('/library/form/tidy', '\Xibo\Controller\Library:tidyForm')->name('library.tidy.form');
$app->get('/library/form/usage/:id', '\Xibo\Controller\Library:usageForm')->name('library.usage.form');
$app->get('/library/fontcss', '\Xibo\Controller\Library:fontCss')->name('library.font.css');
$app->get('/library/fontlist', '\Xibo\Controller\Library:fontList')->name('library.font.list');


//
// display
//
$app->get('/display/view', '\Xibo\Controller\Display:displayPage')->name('display.view');
$app->get('/display/manage/:id', '\Xibo\Controller\Display:displayManage')->name('display.manage');
$app->get('/display/form/edit/:id', '\Xibo\Controller\Display:editForm')->name('display.edit.form');
$app->get('/display/form/delete/:id', '\Xibo\Controller\Display:deleteForm')->name('display.delete.form');
$app->get('/display/form/membership/:id', '\Xibo\Controller\Display:membershipForm')->name('display.membership.form');
$app->get('/display/form/screenshot/:id', '\Xibo\Controller\Display:requestScreenShotForm')->name('display.screenshot.form');
$app->get('/display/form/wol/:id', '\Xibo\Controller\Display:wakeOnLanForm')->name('display.wol.form');
$app->get('/display/form/authorise/:id', '\Xibo\Controller\Display:authoriseForm')->name('display.authorise.form');
$app->get('/display/form/defaultlayout/:id', '\Xibo\Controller\Display:defaultLayoutForm')->name('display.defaultlayout.form');

//
// user
//
$app->get('/user/view', '\Xibo\Controller\User:displayPage')->name('user.view');
$app->post('/user/welcome', '\Xibo\Controller\User:userWelcomeSetUnseen')->name('welcome.wizard.unseen');
$app->put('/user/welcome', '\Xibo\Controller\User:userWelcomeSetSeen')->name('welcome.wizard.seen');
$app->get('/user/apps', '\Xibo\Controller\User:myApplications')->name('user.applications');
$app->get('/user/form/password', '\Xibo\Controller\User:changePasswordForm')->name('user.change.password.form');
$app->get('/user/form/add', '\Xibo\Controller\User:addForm')->name('user.add.form');
$app->get('/user/form/edit/:id', '\Xibo\Controller\User:editForm')->name('user.edit.form');
$app->get('/user/form/delete/:id', '\Xibo\Controller\User:deleteForm')->name('user.delete.form');
$app->get('/user/form/membership/:id', '\Xibo\Controller\User:membershipForm')->name('user.membership.form');
// permissions
$app->get('/user/permissions/form/:entity/:id', '\Xibo\Controller\User:permissionsForm')->name('user.permissions.form');

//
// log
//
$app->get('/log/view', '\Xibo\Controller\Logging:displayPage')->name('log.view');
$app->get('/log/delete', '\Xibo\Controller\Logging:truncateForm')->name('log.truncate.form');

//
// campaign
//
$app->get('/campaign/view', '\Xibo\Controller\Campaign:displayPage')->name('campaign.view');
$app->get('/campaign/form/add', '\Xibo\Controller\Campaign:addForm')->name('campaign.add.form');
$app->get('/campaign/form/edit/:id', '\Xibo\Controller\Campaign:editForm')->name('campaign.edit.form');
$app->get('/campaign/form/copy/:id', '\Xibo\Controller\Campaign:copyForm')->name('campaign.copy.form');
$app->get('/campaign/form/delete/:id', '\Xibo\Controller\Campaign:deleteForm')->name('campaign.delete.form');
$app->get('/campaign/form/retire/:id', '\Xibo\Controller\Campaign:retireForm')->name('campaign.retire.form');
$app->get('/campaign/form/layouts/:id', '\Xibo\Controller\Campaign:layoutsForm')->name('campaign.layouts.form');
$app->get('/campaign/:id/preview', '\Xibo\Controller\Campaign:preview')->name('campaign.preview');

//
// template
//
$app->get('/template/view', '\Xibo\Controller\Template:displayPage')->name('template.view');
$app->get('/template/form/layout/:id', '\Xibo\Controller\Template:addTemplateForm')->name('template.from.layout.form');

//
// resolution
//
$app->get('/resolution/view', '\Xibo\Controller\Resolution:displayPage')->name('resolution.view');
$app->get('/resolution/form/add', '\Xibo\Controller\Resolution:addForm')->name('resolution.add.form');
$app->get('/resolution/form/edit/:id', '\Xibo\Controller\Resolution:editForm')->name('resolution.edit.form');
$app->get('/resolution/form/delete/:id', '\Xibo\Controller\Resolution:deleteForm')->name('resolution.delete.form');

//
// dataset
//
$app->get('/dataset/view', '\Xibo\Controller\DataSet:displayPage')->name('dataset.view');
$app->get('/dataset/data/view/:id', '\Xibo\Controller\DataSetData:displayPage')->name('dataSet.view.data');
$app->get('/dataset/form/add', '\Xibo\Controller\DataSet:addForm')->name('dataSet.add.form');
$app->get('/dataset/form/edit/:id', '\Xibo\Controller\DataSet:editForm')->name('dataSet.edit.form');
$app->get('/dataset/form/copy/:id', '\Xibo\Controller\DataSet:copyForm')->name('dataSet.copy.form');
$app->get('/dataset/form/delete/:id', '\Xibo\Controller\DataSet:deleteForm')->name('dataSet.delete.form');
$app->get('/dataset/form/import/:id', '\Xibo\Controller\DataSet:importForm')->name('dataSet.import.form');
// columns
$app->get('/dataset/:id/column/view', '\Xibo\Controller\DataSetColumn:displayPage')->name('dataSet.column.view');
$app->get('/dataset/:id/column/form/add', '\Xibo\Controller\DataSetColumn:addForm')->name('dataSet.column.add.form');
$app->get('/dataset/:id/column/form/edit/:colId', '\Xibo\Controller\DataSetColumn:editForm')->name('dataSet.column.edit.form');
$app->get('/dataset/:id/column/form/delete/:colId', '\Xibo\Controller\DataSetColumn:deleteForm')->name('dataSet.column.delete.form');
// data
$app->get('/dataset/data/form/add/:id', '\Xibo\Controller\DataSetData:addForm')->name('dataSet.data.add.form');
$app->get('/dataset/data/form/edit/:id/:rowId', '\Xibo\Controller\DataSetData:editForm')->name('dataSet.data.edit.form');
$app->get('/dataset/data/form/delete/:id/:rowId', '\Xibo\Controller\DataSetData:deleteForm')->name('dataSet.data.delete.form');
// RSS
$app->get('/dataset/:id/rss/view', '\Xibo\Controller\DataSetRss:displayPage')->name('dataSet.rss.view');
$app->get('/dataset/:id/rss/form/add', '\Xibo\Controller\DataSetRss:addForm')->name('dataSet.rss.add.form');
$app->get('/dataset/:id/rss/form/edit/:rssId', '\Xibo\Controller\DataSetRss:editForm')->name('dataSet.rss.edit.form');
$app->get('/dataset/:id/rss/form/delete/:rssId', '\Xibo\Controller\DataSetRss:deleteForm')->name('dataSet.rss.delete.form');

//
// displaygroup
//
$app->get('/displaygroup/view', '\Xibo\Controller\DisplayGroup:displayPage')->name('displaygroup.view');
$app->get('/displaygroup/form/add', '\Xibo\Controller\DisplayGroup:addForm')->name('displayGroup.add.form');
$app->get('/displaygroup/form/edit/:id', '\Xibo\Controller\DisplayGroup:editForm')->name('displayGroup.edit.form');
$app->get('/displaygroup/form/delete/:id', '\Xibo\Controller\DisplayGroup:deleteForm')->name('displayGroup.delete.form');
$app->get('/displaygroup/form/members/:id', '\Xibo\Controller\DisplayGroup:membersForm')->name('displayGroup.members.form');
$app->get('/displaygroup/form/media/:id', '\Xibo\Controller\DisplayGroup:mediaForm')->name('displayGroup.media.form');
$app->get('/displaygroup/form/layout/:id', '\Xibo\Controller\DisplayGroup:layoutsForm')->name('displayGroup.layout.form');
$app->get('/displaygroup/form/version/:id', '\Xibo\Controller\DisplayGroup:versionForm')->name('displayGroup.version.form');
$app->get('/displaygroup/form/command/:id', '\Xibo\Controller\DisplayGroup:commandForm')->name('displayGroup.command.form');
$app->get('/displaygroup/form/collect/:id', '\Xibo\Controller\DisplayGroup:collectNowForm')->name('displayGroup.collectNow.form');

//
// displayprofile
//
$app->get('/displayprofile/view', '\Xibo\Controller\DisplayProfile:displayPage')->name('displayprofile.view');
$app->get('/displayprofile/form/add', '\Xibo\Controller\DisplayProfile:addForm')->name('displayProfile.add.form');
$app->get('/displayprofile/form/edit/:id', '\Xibo\Controller\DisplayProfile:editForm')->name('displayProfile.edit.form');
$app->get('/displayprofile/form/delete/:id', '\Xibo\Controller\DisplayProfile:deleteForm')->name('displayProfile.delete.form');

//
// group
//
$app->get('/group/view', '\Xibo\Controller\UserGroup:displayPage')->name('group.view');
$app->get('/group/form/add', '\Xibo\Controller\UserGroup:addForm')->name('group.add.form');
$app->get('/group/form/edit/:id', '\Xibo\Controller\UserGroup:editForm')->name('group.edit.form');
$app->get('/group/form/delete/:id', '\Xibo\Controller\UserGroup:deleteForm')->name('group.delete.form');
$app->get('/group/form/copy/:id', '\Xibo\Controller\UserGroup:copyForm')->name('group.copy.form');
$app->get('/group/form/acl/:id', '\Xibo\Controller\UserGroup:aclForm')->name('group.acl.form');
$app->get('/group/form/members/:id', '\Xibo\Controller\UserGroup:membersForm')->name('group.members.form');

//
// admin
//
$app->get('/admin/view', '\Xibo\Controller\Settings:displayPage')->name('admin.view');

//
// maintenance
//
$app->get('/maintenance/form/export', '\Xibo\Controller\Maintenance:exportForm')->name('maintenance.export.form');
$app->get('/maintenance/form/tidy', '\Xibo\Controller\Maintenance:tidyLibraryForm')->name('maintenance.libraryTidy.form');

//
// oauth
//
$app->get('/application/view', '\Xibo\Controller\Applications:displayPage')->name('application.view');
$app->get('/application/data/activity', '\Xibo\Controller\Applications:viewActivity')->name('application.view.activity');
$app->get('/application/form/add', '\Xibo\Controller\Applications:addForm')->name('application.add.form');
$app->get('/application/form/edit/:id', '\Xibo\Controller\Applications:editForm')->name('application.edit.form');
$app->get('/application/form/delete/:id', '\Xibo\Controller\Applications:deleteForm')->name('application.delete.form');
$app->get('/application/authorize', '\Xibo\Controller\Applications:authorizeRequest')->name('application.authorize.request');
$app->post('/application/authorize', '\Xibo\Controller\Applications:authorize')->name('application.authorize');
$app->put('/application/:id', '\Xibo\Controller\Applications:edit')->name('application.edit');
$app->delete('/application/:id', '\Xibo\Controller\Applications:delete')->name('application.delete');

//
// module
//
$app->get('/module/view', '\Xibo\Controller\Module:displayPage')->name('module.view');
$app->post('/module/inst/:name', '\Xibo\Controller\Module:install')->name('module.install');
$app->get('/module/form/inst/:name', '\Xibo\Controller\Module:installForm')->name('module.install.form');
$app->get('/module/form/instlist', '\Xibo\Controller\Module:installListForm')->name('module.install.list.form');
$app->get('/module/form/verify', '\Xibo\Controller\Module:verifyForm')->name('module.verify.form');
$app->get('/module/form/clear-cache/:id', '\Xibo\Controller\Module:clearCacheForm')->name('module.clear.cache.form');
$app->get('/module/form/settings/:id', '\Xibo\Controller\Module:settingsForm')->name('module.settings.form');
$app->get('/module/form/:id/custom/:name', '\Xibo\Controller\Module:customFormRender')->name('module.custom.form');
$app->map('/module/:id/custom/:name', '\Xibo\Controller\Module:customFormExecute')->name('module.custom')->via('GET', 'POST');

//
// transition
//
$app->get('/transition/view', '\Xibo\Controller\Transition:displayPage')->name('transition.view');
$app->get('/transition/form/edit/:id', '\Xibo\Controller\Transition:editForm')->name('transition.edit.form');

//
// sessions
//
$app->get('/sessions/view', '\Xibo\Controller\Sessions:displayPage')->name('sessions.view');
$app->get('/sessions/form/logout/:id', '\Xibo\Controller\Sessions:confirmLogoutForm')->name('sessions.confirm.logout.form');

//
// fault
//
$app->get('/fault/view', '\Xibo\Controller\Fault:displayPage')->name('fault.view');

//
// license
//
$app->get('/license/view', '\Xibo\Controller\Login:about')->name('license.view');

//
// help
//
$app->get('/help/view', '\Xibo\Controller\Help:displayPage')->name('help.view');
$app->get('/help/form/add', '\Xibo\Controller\Help:addForm')->name('help.add.form');
$app->get('/help/form/edit/:id', '\Xibo\Controller\Help:editForm')->name('help.edit.form');
$app->get('/help/form/delete/:id', '\Xibo\Controller\Help:deleteForm')->name('help.delete.form');

//
// Stats
//
$app->get('/stats/view', '\Xibo\Controller\Stats:displayPage')->name('stats.view');
$app->get('/stats/proofofplay/view', '\Xibo\Controller\Stats:displayProofOfPlayPage')->name('stats.proofofplay.view');
$app->get('/stats/library/view', '\Xibo\Controller\Stats:displayLibraryPage')->name('stats.library.view');
$app->get('/stats/form/export', '\Xibo\Controller\Stats:exportForm')->name('stats.export.form');
$app->get('/stats/library', '\Xibo\Controller\Stats:libraryUsageGrid')->name('stats.library.grid');

//
// Audit Log
//
$app->get('/audit/view', '\Xibo\Controller\AuditLog:displayPage')->name('auditlog.view');
$app->get('/audit/form/export', '\Xibo\Controller\AuditLog:exportForm')->name('auditLog.export.form');

//
// Commands
//
$app->get('/command/view', '\Xibo\Controller\Command:displayPage')->name('command.view');
$app->get('/command/form/add', '\Xibo\Controller\Command:addForm')->name('command.add.form');
$app->get('/command/form/edit/:id', '\Xibo\Controller\Command:editForm')->name('command.edit.form');
$app->get('/command/form/delete/:id', '\Xibo\Controller\Command:deleteForm')->name('command.delete.form');

//
// Daypart
//
$app->get('/daypart/view', '\Xibo\Controller\DayPart:displayPage')->name('daypart.view');
$app->get('/daypart/form/add', '\Xibo\Controller\DayPart:addForm')->name('daypart.add.form');
$app->get('/daypart/form/edit/:id', '\Xibo\Controller\DayPart:editForm')->name('daypart.edit.form');
$app->get('/daypart/form/delete/:id', '\Xibo\Controller\DayPart:deleteForm')->name('daypart.delete.form');

//
// Tasks
//
$app->get('/task/view', '\Xibo\Controller\Task:displayPage')->name('task.view');
$app->get('/task/form/add', '\Xibo\Controller\Task:addForm')->name('task.add.form');
$app->get('/task/form/edit/:id', '\Xibo\Controller\Task:editForm')->name('task.edit.form');
$app->get('/task/form/delete/:id', '\Xibo\Controller\Task:deleteForm')->name('task.delete.form');
$app->get('/task/form/runNow/:id', '\Xibo\Controller\Task:runNowForm')->name('task.runNow.form');