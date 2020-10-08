<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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

use Slim\Routing\RouteCollectorProxy;

// Special "root" route
$app->get('/', ['\Xibo\Controller\User', 'home'])->setName('home');
$app->get('/welcome', ['\Xibo\Controller\User', 'welcome'])->setName('welcome.view');

//
// Dashboards
//
$app->group('', function(RouteCollectorProxy $group) {
    $group->get('/statusdashboard', ['\Xibo\Controller\StatusDashboard', 'displayPage'])
        ->setName('statusdashboard.view');
    $group->get('/statusdashboard/displays', ['\Xibo\Controller\StatusDashboard', 'displays'])
        ->setName('statusdashboard.displays');
    $group->get('/statusdashboard/displayGroups', ['\Xibo\Controller\StatusDashboard', 'displayGroups'])
        ->setName('statusdashboard.displayGroups');
})->add(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['dashboard.status']));

$app->get('/icondashboard', ['\Xibo\Controller\IconDashboard', 'displayPage'])
    ->setName('icondashboard.view')
    ->add(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['dashboard.icon']));

$app->group('', function(RouteCollectorProxy $group) {
    $group->get('/mediamanager', ['\Xibo\Controller\MediaManager', 'displayPage'])
        ->setName('mediamanager.view');
    $group->get('/mediamanager/data', ['\Xibo\Controller\MediaManager', 'grid'])
        ->setName('mediamanager.search');
})->add(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['dashboard.media.manager']));

$app->group('', function(RouteCollectorProxy $group) {
    $group->get('/playlistdashboard', ['\Xibo\Controller\PlaylistDashboard', 'displayPage'])
        ->setName('playlistdashboard.view');
    $group->get('/playlistdashboard/data', ['\Xibo\Controller\PlaylistDashboard', 'grid'])
        ->setName('playlistdashboard.search');
    $group->get('/playlistdashboard/{id}', ['\Xibo\Controller\PlaylistDashboard', 'show'])
        ->setName('playlistdashboard.show');
    $group->get('/playlistdashboard/widget/form/delete/{id}', ['\Xibo\Controller\PlaylistDashboard', 'deletePlaylistWidgetForm'])
        ->setName('playlist.module.widget.delete.form');

    //TODO: why is this commented out?
    //$group->map('/playlistdashboard/library', '\Xibo\Controller\PlaylistDashboard:upload')->via('HEAD');
    $group->post('/playlistdashboard/library', ['\Xibo\Controller\PlaylistDashboard', 'upload'])
        ->setName('playlistdashboard.library.add');
})->add(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['dashboard.playlist']));

// Login Form
$app->get('/login', ['\Xibo\Controller\Login', 'loginForm'])->setName('login');

// Login Requests
$app->post('/login', ['\Xibo\Controller\Login','login']);
$app->post('/login/forgotten', ['\Xibo\Controller\Login','forgottenPassword'])->setName('login.forgotten');
$app->get('/tfa', ['\Xibo\Controller\Login','twoFactorAuthForm'])->setName('tfa');

// Logout Request
$app->get('/logout', ['\Xibo\Controller\Login','logout'])->setName('logout');

// Ping pong route
$app->get('/login/ping', ['\Xibo\Controller\Login','PingPong'])->setName('ping');

//
// schedule
//
$app->get('/schedule/view', ['\Xibo\Controller\Schedule','displayPage'])
    ->add(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['schedule.view']))
    ->setName('schedule.view');

$app->get('/schedule/form/add', ['\Xibo\Controller\Schedule','addForm'])
    ->add(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['schedule.add']))
    ->setName('schedule.add.form');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/schedule/form/edit/{id}', ['\Xibo\Controller\Schedule', 'editForm'])
        ->setName('schedule.edit.form');

    $group->get('/schedule/form/delete/{id}', ['\Xibo\Controller\Schedule', 'deleteForm'])
        ->setName('schedule.delete.form');

    $group->get('/schedulerecurrence/form/delete/{id}', ['\Xibo\Controller\Schedule', 'deleteRecurrenceForm'])
        ->setName('schedule.recurrence.delete.form');
})->add(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['schedule.modify']));

$app->get('/schedule/form/now/{from}/{id}', ['\Xibo\Controller\Schedule','scheduleNowForm'])
    ->add(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['schedule.now']))
    ->setName('schedule.now.form');

//
// notification
//
$app->get('/drawer/notification/show/{id}', ['\Xibo\Controller\Notification','show'])->setName('notification.show');
$app->get('/drawer/notification/interrupt/{id}', ['\Xibo\Controller\Notification','interrupt'])->setName('notification.interrupt');

$app->get('/notification/view', ['\Xibo\Controller\Notification','displayPage'])
    ->add(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['notification.centre']))
    ->setName('notification.view');

$app->get('/notification/form/add', ['\Xibo\Controller\Notification','addForm'])->setName('notification.add.form');
$app->get('/notification/form/edit/{id}', ['\Xibo\Controller\Notification','editForm'])->setName('notification.edit.form');
$app->get('/notification/form/delete/{id}', ['\Xibo\Controller\Notification','deleteForm'])->setName('notification.delete.form');
$app->get('/notification/export/{id}', ['\Xibo\Controller\Notification','exportAttachment'])->setName('notification.exportattachment');

//
// layouts
//
$app->get('/layout/view', ['\Xibo\Controller\Layout','displayPage'])->setName('layout.view');
$app->get('/layout/designer[/{id}]', ['\Xibo\Controller\Layout','displayDesigner'])->setName('layout.designer');
$app->get('/layout/preview/{id}', ['\Xibo\Controller\Preview','show'])->setName('layout.preview');
$app->get('/layout/xlf/{id}', ['\Xibo\Controller\Preview','getXlf'])->setName('layout.getXlf');
$app->get('/layout/export/{id}', ['\Xibo\Controller\Layout','export'])->setName('layout.export');
$app->get('/layout/background/{id}', ['\Xibo\Controller\Layout','downloadBackground'])->setName('layout.download.background');
// forms
$app->get('/layout/form/add', ['\Xibo\Controller\Layout','addForm'])->setName('layout.add.form');
$app->get('/layout/form/edit/{id}', ['\Xibo\Controller\Layout','editForm'])->setName('layout.edit.form');
$app->get('/layout/form/background/{id}', ['\Xibo\Controller\Layout','editBackgroundForm'])->setName('layout.background.form');
$app->get('/layout/form/copy/{id}', ['\Xibo\Controller\Layout','copyForm'])->setName('layout.copy.form');
$app->get('/layout/form/delete/{id}', ['\Xibo\Controller\Layout','deleteForm'])->setName('layout.delete.form');
$app->get('/layout/form/checkout/{id}', ['\Xibo\Controller\Layout','checkoutForm'])->setName('layout.checkout.form');
$app->get('/layout/form/publish/{id}', ['\Xibo\Controller\Layout','publishForm'])->setName('layout.publish.form');
$app->get('/layout/form/discard/{id}', ['\Xibo\Controller\Layout','discardForm'])->setName('layout.discard.form');
$app->get('/layout/form/retire/{id}', ['\Xibo\Controller\Layout','retireForm'])->setName('layout.retire.form');
$app->get('/layout/form/unretire/{id}', ['\Xibo\Controller\Layout','unretireForm'])->setName('layout.unretire.form');
$app->get('/layout/form/setenablestat/{id}', ['\Xibo\Controller\Layout','setEnableStatForm'])->setName('layout.setenablestat.form');
$app->get('/layout/form/export/{id}', ['\Xibo\Controller\Layout','exportForm'])->setName('layout.export.form');
$app->get('/layout/form/campaign/assign/{id}', ['\Xibo\Controller\Layout','assignToCampaignForm'])->setName('layout.assignTo.campaign.form');
// Layout with Codes
$app->get('/layout/codes', ['\Xibo\Controller\Layout', 'getLayoutCodes'])->setName('layout.code.search');

//
// regions
//
$app->get('/region/preview/{id}', ['\Xibo\Controller\Region','preview'])->setName('region.preview');
$app->get('/region/form/edit/{id}', ['\Xibo\Controller\Region','editForm'])->setName('region.edit.form');
$app->get('/region/form/delete/{id}', ['\Xibo\Controller\Region','deleteForm'])->setName('region.delete.form');

//
// playlists
//
$app->get('/playlist/view', ['\Xibo\Controller\Playlist','displayPage'])->setName('playlist.view');
$app->get('/playlist/form/add', ['\Xibo\Controller\Playlist','addForm'])->setName('playlist.add.form');
$app->get('/playlist/form/edit/{id}', ['\Xibo\Controller\Playlist','editForm'])->setName('playlist.edit.form');
$app->get('/playlist/form/copy/{id}', ['\Xibo\Controller\Playlist','copyForm'])->setName('playlist.copy.form');
$app->get('/playlist/form/delete/{id}', ['\Xibo\Controller\Playlist','deleteForm'])->setName('playlist.delete.form');
$app->get('/playlist/form/timeline/{id}', ['\Xibo\Controller\Playlist','timelineForm'])->setName('playlist.timeline.form');
$app->get('/playlist/form/setenablestat/{id}', ['\Xibo\Controller\Playlist','setEnableStatForm'])->setName('playlist.setenablestat.form');
$app->get('/playlist/form/usage/{id}', ['\Xibo\Controller\Playlist','usageForm'])->setName('playlist.usage.form');

// Designer
$app->get('/playlist/form/library/assign/{id}', ['\Xibo\Controller\Playlist','libraryAssignForm'])->setName('playlist.library.assign.form');
// Module functions
$app->get('/playlist/widget/form/edit/{id}', ['\Xibo\Controller\Module','editWidgetForm'])->setName('module.widget.edit.form');
$app->get('/playlist/widget/form/delete/{id}', ['\Xibo\Controller\Module','deleteWidgetForm'])->setName('module.widget.delete.form');
$app->get('/playlist/widget/form/transition/edit/{type}/{id}', ['\Xibo\Controller\Module','editWidgetTransitionForm'])->setName('module.widget.transition.edit.form');
$app->get('/playlist/widget/form/audio/{id}', ['\Xibo\Controller\Module','widgetAudioForm'])->setName('module.widget.audio.form');
$app->get('/playlist/widget/form/expiry/{id}', ['\Xibo\Controller\Module','widgetExpiryForm'])->setName('module.widget.expiry.form');
$app->get('/playlist/widget/dataset', ['\Xibo\Controller\Module','getDataSets'])->setName('module.widget.dataset.search');
// Outputs
$app->get('/playlist/widget/tab/{tab}/{id}', ['\Xibo\Controller\Module','getTab'])->setName('module.widget.tab.form');
$app->get('/playlist/widget/resource/{regionId}/{id}', ['\Xibo\Controller\Module','getResource'])->setName('module.getResource');
$app->get('/playlist/widget/form/templateimage/{type}/{templateId}', ['\Xibo\Controller\Module','getTemplateImage'])->setName('module.getTemplateImage');

//
// library
//
$app->get('/library/view', ['\Xibo\Controller\Library','displayPage'])->setName('library.view');
$app->get('/library/form/edit/{id}', ['\Xibo\Controller\Library','editForm'])->setName('library.edit.form');
$app->get('/library/form/delete/{id}', ['\Xibo\Controller\Library','deleteForm'])->setName('library.delete.form');
$app->get('/library/form/tidy', ['\Xibo\Controller\Library','tidyForm'])->setName('library.tidy.form');
$app->get('/library/form/uploadUrl', ['\Xibo\Controller\Library','uploadFromUrlForm'])->setName('library.uploadUrl.form');
$app->get('/library/form/usage/{id}', ['\Xibo\Controller\Library','usageForm'])->setName('library.usage.form');
$app->get('/library/fontcss', ['\Xibo\Controller\Library','fontCss'])->setName('library.font.css');
$app->get('/library/fontlist', ['\Xibo\Controller\Library','fontList'])->setName('library.font.list');
$app->get('/library/form/copy/{id}', ['\Xibo\Controller\Library','copyForm'])->setName('library.copy.form');
$app->get('/library/form/setenablestat/{id}', ['\Xibo\Controller\Library','setEnableStatForm'])->setName('library.setenablestat.form');

//
// display
//
$app->get('/display/view', ['\Xibo\Controller\Display', 'displayPage'])->setName('display.view');
$app->get('/display/manage/{id}', ['\Xibo\Controller\Display','displayManage'])->setName('display.manage');
$app->get('/display/form/edit/{id}', ['\Xibo\Controller\Display','editForm'])->setName('display.edit.form');
$app->get('/display/form/delete/{id}', ['\Xibo\Controller\Display','deleteForm'])->setName('display.delete.form');
$app->get('/display/form/membership/{id}', ['\Xibo\Controller\Display','membershipForm'])->setName('display.membership.form');
$app->get('/display/form/screenshot/{id}', ['\Xibo\Controller\Display','requestScreenShotForm'])->setName('display.screenshot.form');
$app->get('/display/form/wol/{id}', ['\Xibo\Controller\Display','wakeOnLanForm'])->setName('display.wol.form');
$app->get('/display/form/authorise/{id}', ['\Xibo\Controller\Display','authoriseForm'])->setName('display.authorise.form');
$app->get('/display/form/defaultlayout/{id}', ['\Xibo\Controller\Display','defaultLayoutForm'])->setName('display.defaultlayout.form');
$app->get('/display/form/moveCms/{id}', ['\Xibo\Controller\Display','moveCmsForm'])->setName('display.moveCms.form');
$app->get('/display/form/addViaCode', ['\Xibo\Controller\Display','addViaCodeForm'])->setName('display.addViaCode.form');
$app->get('/display/form/licenceCheck/{id}', ['\Xibo\Controller\Display','checkLicenceForm'])->setName('display.licencecheck.form');

//
// user
//
$app->get('/user/view', ['\Xibo\Controller\User', 'displayPage'])->setName('user.view');
$app->post('/user/welcome', ['\Xibo\Controller\User','userWelcomeSetUnseen'])->setName('welcome.wizard.unseen');
$app->put('/user/welcome', ['\Xibo\Controller\User','userWelcomeSetSeen'])->setName('welcome.wizard.seen');
$app->get('/user/apps', ['\Xibo\Controller\User','myApplications'])->setName('user.applications');
$app->get('/user/form/profile', ['\Xibo\Controller\User','editProfileForm'])->setName('user.edit.profile.form');
$app->get('/user/page/password', ['\Xibo\Controller\User','forceChangePasswordPage'])->setName('user.force.change.password.page');
$app->get('/user/form/add', ['\Xibo\Controller\User','addForm'])->setName('user.add.form');
$app->get('/user/form/edit/{id}', ['\Xibo\Controller\User','editForm'])->setName('user.edit.form');
$app->get('/user/form/delete/{id}', ['\Xibo\Controller\User','deleteForm'])->setName('user.delete.form');
$app->get('/user/form/membership/{id}', ['\Xibo\Controller\User','membershipForm'])->setName('user.membership.form');
$app->get('/user/form/preferences', ['\Xibo\Controller\User', 'preferencesForm'])->setName('user.preferences.form');
$app->get('/user/form/homepages', ['\Xibo\Controller\User', 'homepages'])->setName('user.homepages.search');
// permissions
$app->get('/user/permissions/form/{entity}/{id}', ['\Xibo\Controller\User','permissionsForm'])->setName('user.permissions.form');
$app->get('/user/permissions/multiple/form/{entity}', ['\Xibo\Controller\User','permissionsMultiForm'])->setName('user.permissions.multi.form');

//
// log
//
$app->get('/log/view', ['\Xibo\Controller\Logging','displayPage'])->setName('log.view');
$app->get('/log/delete', ['\Xibo\Controller\Logging','truncateForm'])->setName('log.truncate.form');

//
// campaign
//
$app->get('/campaign/view', ['\Xibo\Controller\Campaign','displayPage'])->setName('campaign.view');
$app->get('/campaign/form/add', ['\Xibo\Controller\Campaign','addForm'])->setName('campaign.add.form');
$app->get('/campaign/form/edit/{id}', ['\Xibo\Controller\Campaign','editForm'])->setName('campaign.edit.form');
$app->get('/campaign/form/copy/{id}', ['\Xibo\Controller\Campaign','copyForm'])->setName('campaign.copy.form');
$app->get('/campaign/form/delete/{id}', ['\Xibo\Controller\Campaign','deleteForm'])->setName('campaign.delete.form');
$app->get('/campaign/form/retire/{id}', ['\Xibo\Controller\Campaign','retireForm'])->setName('campaign.retire.form');
$app->get('/campaign/form/layouts/{id}', ['\Xibo\Controller\Campaign','layoutsForm'])->setName('campaign.layouts.form');
$app->get('/campaign/{id}/preview', ['\Xibo\Controller\Campaign','preview'])->setName('campaign.preview');

//
// template
//
$app->get('/template/view', ['\Xibo\Controller\Template','displayPage'])->setName('template.view');
$app->get('/template/form/layout/{id}', ['\Xibo\Controller\Template','addTemplateForm'])->setName('template.from.layout.form');

//
// resolution
//
$app->get('/resolution/view', ['\Xibo\Controller\Resolution','displayPage'])->setName('resolution.view');
$app->get('/resolution/form/add', ['\Xibo\Controller\Resolution','addForm'])->setName('resolution.add.form');
$app->get('/resolution/form/edit/{id}', ['\Xibo\Controller\Resolution','editForm'])->setName('resolution.edit.form');
$app->get('/resolution/form/delete/{id}', ['\Xibo\Controller\Resolution','deleteForm'])->setName('resolution.delete.form');

//
// dataset
//
$app->get('/dataset/view', ['\Xibo\Controller\DataSet','displayPage'])->setName('dataset.view');
$app->get('/dataset/data/view/{id}', ['\Xibo\Controller\DataSetData','displayPage'])->setName('dataSet.view.data');
$app->get('/dataset/form/add', ['\Xibo\Controller\DataSet','addForm'])->setName('dataSet.add.form');
$app->get('/dataset/form/edit/{id}', ['\Xibo\Controller\DataSet','editForm'])->setName('dataSet.edit.form');
$app->get('/dataset/form/copy/{id}', ['\Xibo\Controller\DataSet','copyForm'])->setName('dataSet.copy.form');
$app->get('/dataset/form/delete/{id}', ['\Xibo\Controller\DataSet','deleteForm'])->setName('dataSet.delete.form');
$app->get('/dataset/form/import/{id}', ['\Xibo\Controller\DataSet','importForm'])->setName('dataSet.import.form');
// columns
$app->get('/dataset/{id}/column/view', ['\Xibo\Controller\DataSetColumn','displayPage'])->setName('dataSet.column.view');
$app->get('/dataset/{id}/column/form/add', ['\Xibo\Controller\DataSetColumn','addForm'])->setName('dataSet.column.add.form');
$app->get('/dataset/{id}/column/form/edit/{colId}', ['\Xibo\Controller\DataSetColumn','editForm'])->setName('dataSet.column.edit.form');
$app->get('/dataset/{id}/column/form/delete/{colId}', ['\Xibo\Controller\DataSetColumn','deleteForm'])->setName('dataSet.column.delete.form');
// data
$app->get('/dataset/data/form/add/{id}', ['\Xibo\Controller\DataSetData','addForm'])->setName('dataSet.data.add.form');
$app->get('/dataset/data/form/edit/{id}/{rowId}', ['\Xibo\Controller\DataSetData','editForm'])->setName('dataSet.data.edit.form');
$app->get('/dataset/data/form/delete/{id}/{rowId}', ['\Xibo\Controller\DataSetData','deleteForm'])->setName('dataSet.data.delete.form');
// RSS
$app->get('/dataset/{id}/rss/view', ['\Xibo\Controller\DataSetRss','displayPage'])->setName('dataSet.rss.view');
$app->get('/dataset/{id}/rss/form/add', ['\Xibo\Controller\DataSetRss','addForm'])->setName('dataSet.rss.add.form');
$app->get('/dataset/{id}/rss/form/edit/{rssId}', ['\Xibo\Controller\DataSetRss','editForm'])->setName('dataSet.rss.edit.form');
$app->get('/dataset/{id}/rss/form/delete/{rssId}', ['\Xibo\Controller\DataSetRss','deleteForm'])->setName('dataSet.rss.delete.form');

//
// displaygroup
//
$app->get('/displaygroup/view', ['\Xibo\Controller\DisplayGroup','displayPage'])->setName('displaygroup.view');
$app->get('/displaygroup/form/add', ['\Xibo\Controller\DisplayGroup','addForm'])->setName('displayGroup.add.form');
$app->get('/displaygroup/form/edit/{id}', ['\Xibo\Controller\DisplayGroup','editForm'])->setName('displayGroup.edit.form');
$app->get('/displaygroup/form/delete/{id}', ['\Xibo\Controller\DisplayGroup','deleteForm'])->setName('displayGroup.delete.form');
$app->get('/displaygroup/form/members/{id}', ['\Xibo\Controller\DisplayGroup','membersForm'])->setName('displayGroup.members.form');
$app->get('/displaygroup/form/media/{id}', ['\Xibo\Controller\DisplayGroup','mediaForm'])->setName('displayGroup.media.form');
$app->get('/displaygroup/form/layout/{id}', ['\Xibo\Controller\DisplayGroup','layoutsForm'])->setName('displayGroup.layout.form');
$app->get('/displaygroup/form/command/{id}', ['\Xibo\Controller\DisplayGroup','commandForm'])->setName('displayGroup.command.form');
$app->get('/displaygroup/form/collect/{id}', ['\Xibo\Controller\DisplayGroup','collectNowForm'])->setName('displayGroup.collectNow.form');
$app->get('/displaygroup/form/copy/{id}', ['\Xibo\Controller\DisplayGroup','copyForm'])->setName('displayGroup.copy.form');

//
// displayprofile
//
$app->get('/displayprofile/view', ['\Xibo\Controller\DisplayProfile','displayPage'])->setName('displayprofile.view');
$app->get('/displayprofile/form/add', ['\Xibo\Controller\DisplayProfile','addForm'])->setName('displayProfile.add.form');
$app->get('/displayprofile/form/edit/{id}', ['\Xibo\Controller\DisplayProfile','editForm'])->setName('displayProfile.edit.form');
$app->get('/displayprofile/form/delete/{id}', ['\Xibo\Controller\DisplayProfile','deleteForm'])->setName('displayProfile.delete.form');
$app->get('/displayprofile/form/copy/{id}', ['\Xibo\Controller\DisplayProfile','copyForm'])->setName('displayProfile.copy.form');

//
// group
//
$app->get('/group/view', ['\Xibo\Controller\UserGroup','displayPage'])->setName('group.view');
$app->get('/group/form/add', ['\Xibo\Controller\UserGroup','addForm'])->setName('group.add.form');
$app->get('/group/form/edit/{id}', ['\Xibo\Controller\UserGroup','editForm'])->setName('group.edit.form');
$app->get('/group/form/delete/{id}', ['\Xibo\Controller\UserGroup','deleteForm'])->setName('group.delete.form');
$app->get('/group/form/copy/{id}', ['\Xibo\Controller\UserGroup','copyForm'])->setName('group.copy.form');
$app->get('/group/form/acl/{id}/[{userId}]', ['\Xibo\Controller\UserGroup','aclForm'])->setName('group.acl.form');
$app->get('/group/form/members/{id}', ['\Xibo\Controller\UserGroup','membersForm'])->setName('group.members.form');

//
// admin
//
$app->get('/admin/view', ['\Xibo\Controller\Settings','displayPage'])->setName('admin.view');

//
// maintenance
//
$app->get('/maintenance/form/export', ['\Xibo\Controller\Maintenance','exportForm'])->setName('maintenance.export.form');
$app->get('/maintenance/form/tidy', ['\Xibo\Controller\Maintenance','tidyLibraryForm'])->setName('maintenance.libraryTidy.form');

//
// oauth
//
$app->get('/application/view', ['\Xibo\Controller\Applications','displayPage'])->setName('application.view');
$app->get('/application/data/activity', ['\Xibo\Controller\Applications','viewActivity'])->setName('application.view.activity');
$app->get('/application/form/add', ['\Xibo\Controller\Applications','addForm'])->setName('application.add.form');
$app->get('/application/form/addDooh', ['\Xibo\Controller\Applications','addDoohForm'])->setName('application.addDooh.form');
$app->get('/application/form/edit/{id}', ['\Xibo\Controller\Applications','editForm'])->setName('application.edit.form');
$app->get('/application/form/delete/{id}', ['\Xibo\Controller\Applications','deleteForm'])->setName('application.delete.form');
$app->get('/application/authorize', ['\Xibo\Controller\Applications','authorizeRequest'])->setName('application.authorize.request');
$app->post('/application/authorize', ['\Xibo\Controller\Applications','authorize'])->setName('application.authorize');
$app->put('/application/{id}', ['\Xibo\Controller\Applications','edit'])->setName('application.edit');
$app->delete('/application/{id}', ['\Xibo\Controller\Applications','delete'])->setName('application.delete');

//
// module
//
$app->get('/module/view', ['\Xibo\Controller\Module','displayPage'])->setName('module.view');
$app->post('/module/inst/{name}', ['\Xibo\Controller\Module','install'])->setName('module.install');
$app->get('/module/form/inst/{name}', ['\Xibo\Controller\Module','installForm'])->setName('module.install.form');
$app->get('/module/form/instlist', ['\Xibo\Controller\Module','installListForm'])->setName('module.install.list.form');
$app->get('/module/form/verify', ['\Xibo\Controller\Module','verifyForm'])->setName('module.verify.form');
$app->get('/module/form/clear-cache/{id}', ['\Xibo\Controller\Module','clearCacheForm'])->setName('module.clear.cache.form');
$app->get('/module/form/settings/{id}', ['\Xibo\Controller\Module','settingsForm'])->setName('module.settings.form');
$app->get('/module/form/{id}/custom/{name}', ['\Xibo\Controller\Module','customFormRender'])->setName('module.custom.form');
$app->map(['GET','POST'], '/module/{id}/custom/{name}', ['\Xibo\Controller\Module','customFormExecute'])->setName('module.custom');

//
// transition
//
$app->get('/transition/view', ['\Xibo\Controller\Transition','displayPage'])->setName('transition.view');
$app->get('/transition/form/edit/{id}', ['\Xibo\Controller\Transition','editForm'])->setName('transition.edit.form');

//
// sessions
//
$app->get('/sessions/view', ['\Xibo\Controller\Sessions','displayPage'])->setName('sessions.view');
$app->get('/sessions/form/logout/{id}', ['\Xibo\Controller\Sessions','confirmLogoutForm'])->setName('sessions.confirm.logout.form');

//
// fault
//
$app->get('/fault/view', ['\Xibo\Controller\Fault','displayPage'])->setName('fault.view');

//
// license
//
$app->get('/license/view', ['\Xibo\Controller\Login','about'])->setName('license.view');

//
// help
//
$app->get('/help/view', ['\Xibo\Controller\Help','displayPage'])->setName('help.view');
$app->get('/help/form/add', ['\Xibo\Controller\Help','addForm'])->setName('help.add.form');
$app->get('/help/form/edit/{id}', ['\Xibo\Controller\Help','editForm'])->setName('help.edit.form');
$app->get('/help/form/delete/{id}', ['\Xibo\Controller\Help','deleteForm'])->setName('help.delete.form');

//
// Stats
//
$app->get('/report/view', ['\Xibo\Controller\Stats','displayReportPage'])->setName('report.view');
$app->get('/stats/form/export', ['\Xibo\Controller\Stats','exportForm'])->setName('stats.export.form');
$app->get('/stats/getExportStatsCount', ['\Xibo\Controller\Stats','getExportStatsCount'])->setName('stats.getExportStatsCount');

// Used in Display Manage
$app->get('/stats/data/bandwidth', ['\Xibo\Controller\Stats','bandwidthData'])->setName('stats.bandwidth.data');

//
// Audit Log
//
$app->get('/audit/view', ['\Xibo\Controller\AuditLog','displayPage'])->setName('auditlog.view');
$app->get('/audit/form/export', ['\Xibo\Controller\AuditLog','exportForm'])->setName('auditLog.export.form');

//
// Commands
//
$app->get('/command/view', ['\Xibo\Controller\Command','displayPage'])->setName('command.view');
$app->get('/command/form/add', ['\Xibo\Controller\Command','addForm'])->setName('command.add.form');
$app->get('/command/form/edit/{id}', ['\Xibo\Controller\Command','editForm'])->setName('command.edit.form');
$app->get('/command/form/delete/{id}', ['\Xibo\Controller\Command','deleteForm'])->setName('command.delete.form');

//
// Daypart
//
$app->get('/daypart/view', ['\Xibo\Controller\DayPart','displayPage'])->setName('daypart.view');
$app->get('/daypart/form/add', ['\Xibo\Controller\DayPart','addForm'])->setName('daypart.add.form');
$app->get('/daypart/form/edit/{id}', ['\Xibo\Controller\DayPart','editForm'])->setName('daypart.edit.form');
$app->get('/daypart/form/delete/{id}', ['\Xibo\Controller\DayPart','deleteForm'])->setName('daypart.delete.form');

//
// Tasks
//
$app->get('/task/view', ['\Xibo\Controller\Task','displayPage'])->setName('task.view');
$app->get('/task/form/add', ['\Xibo\Controller\Task','addForm'])->setName('task.add.form');
$app->get('/task/form/edit/{id}', ['\Xibo\Controller\Task','editForm'])->setName('task.edit.form');
$app->get('/task/form/delete/{id}', ['\Xibo\Controller\Task','deleteForm'])->setName('task.delete.form');
$app->get('/task/form/runNow/{id}', ['\Xibo\Controller\Task','runNowForm'])->setName('task.runNow.form');

//
// Report Schedule
//
$app->get('/report/reportschedule/view', ['\Xibo\Controller\Report','displayReportSchedulePage'])->setName('reportschedule.view');
$app->get('/report/reportschedule/form/add', ['\Xibo\Controller\Report','addReportScheduleForm'])->setName('reportschedule.add.form');
$app->get('/report/reportschedule/form/edit/{id}', ['\Xibo\Controller\Report','editReportScheduleForm'])->setName('reportschedule.edit.form');
$app->get('/report/reportschedule/form/delete/{id}', ['\Xibo\Controller\Report','deleteReportScheduleForm'])->setName('reportschedule.delete.form');
$app->get('/report/reportschedule/form/deleteall/{id}', ['\Xibo\Controller\Report','deleteAllSavedReportReportScheduleForm'])->setName('reportschedule.deleteall.form');
$app->get('/report/reportschedule/form/toggleactive/{id}', ['\Xibo\Controller\Report','toggleActiveReportScheduleForm'])->setName('reportschedule.toggleactive.form');
$app->get('/report/reportschedule/form/reset/{id}', ['\Xibo\Controller\Report','resetReportScheduleForm'])->setName('reportschedule.reset.form');

//
// Saved reports
//
$app->get('/report/savedreport/view', ['\Xibo\Controller\Report','displaySavedReportPage'])->setName('savedreport.view');
$app->get('/report/savedreport/{id}/report/{name}/open', ['\Xibo\Controller\Report','savedReportOpen'])->setName('savedreport.open');
$app->get('/report/savedreport/{id}/report/{name}/export', ['\Xibo\Controller\Report','savedReportExport'])->setName('savedreport.export');
$app->get('/report/savedreport/form/delete/{id}', ['\Xibo\Controller\Report','deleteSavedReportForm'])->setName('savedreport.delete.form');
$app->get('/report/savedreport/{id}/report/{name}/convert', ['\Xibo\Controller\Report','savedReportConvert'])->setName('savedreport.convert');
$app->get('/report/savedreport/form/convert/{id}', ['\Xibo\Controller\Report','convertSavedReportForm'])->setName('savedreport.convert.form');

//
// Ad hoc report
//
$app->get('/report/form/{name}', ['\Xibo\Controller\Report','getReportForm'])->setName('report.form');
$app->get('/report/data/{name}', ['\Xibo\Controller\Report','getReportData'])->setName('report.data');

// Player Software
$app->get('/playersoftware/view', ['\Xibo\Controller\PlayerSoftware','displayPage'])->setName('playersoftware.view');
$app->get('/playersoftware/form/edit/{id}', ['\Xibo\Controller\PlayerSoftware','editForm'])->setName('playersoftware.edit.form');
$app->get('/playersoftware/form/delete/{id}', ['\Xibo\Controller\PlayerSoftware','deleteForm'])->setName('playersoftware.delete.form');

// Tags
$app->get('/tag/view', ['\Xibo\Controller\Tag','displayPage'])->setName('tag.view');
$app->get('/tag/form/add', ['\Xibo\Controller\Tag','addForm'])->setName('tag.add.form');
$app->get('/tag/form/edit/{id}', ['\Xibo\Controller\Tag','editForm'])->setName('tag.edit.form');
$app->get('/tag/form/delete/{id}', ['\Xibo\Controller\Tag','deleteForm'])->setName('tag.delete.form');

// Actions
$app->get('/action/form/add/{source}/{id}', ['\Xibo\Controller\Action', 'addForm'])->setName('action.add.form');
$app->get('/action/form/edit/{id}', ['\Xibo\Controller\Action', 'editForm'])->setName('action.edit.form');
$app->get('/action/form/delete/{id}', ['\Xibo\Controller\Action', 'deleteForm'])->setName('action.delete.form');
