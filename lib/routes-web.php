<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
use Xibo\Middleware\FeatureAuth;
use Xibo\Middleware\SuperAdminAuth;

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
})->add(new FeatureAuth($app->getContainer(), ['dashboard.status']));

// Everyone has access to this dashboard.
$app->get('/icondashboard', ['\Xibo\Controller\IconDashboard', 'displayPage'])
    ->setName('icondashboard.view');

$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/mediamanager', ['\Xibo\Controller\MediaManager', 'displayPage'])
        ->setName('mediamanager.view');
    $group->get('/mediamanager/data', ['\Xibo\Controller\MediaManager', 'grid'])
        ->setName('mediamanager.search');
})->add(new FeatureAuth($app->getContainer(), ['dashboard.media.manager']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/playlistdashboard', ['\Xibo\Controller\PlaylistDashboard', 'displayPage'])
        ->setName('playlistdashboard.view');
    $group->get('/playlistdashboard/data', ['\Xibo\Controller\PlaylistDashboard', 'grid'])
        ->setName('playlistdashboard.search');
    $group->get('/playlistdashboard/{id}', ['\Xibo\Controller\PlaylistDashboard', 'show'])
        ->setName('playlistdashboard.show');
    $group->get('/playlistdashboard/widget/form/delete/{id}', [
        '\Xibo\Controller\PlaylistDashboard',
        'deletePlaylistWidgetForm',
    ])->setName('playlist.module.widget.delete.form');
})->add(new FeatureAuth($app->getContainer(), ['dashboard.playlist']));

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
    ->add(new FeatureAuth($app->getContainer(), ['schedule.view']))
    ->setName('schedule.view');

$app->get('/schedule/grid/view', ['\Xibo\Controller\Schedule','gridPage'])
    ->add(new FeatureAuth($app->getContainer(), ['schedule.view']))
    ->setName('schedule.grid.view');

$app->get('/schedule/form/add[/{from}/{id}]', ['\Xibo\Controller\Schedule','addForm'])
    ->add(new FeatureAuth($app->getContainer(), ['schedule.add']))
    ->setName('schedule.add.form');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/schedule/form/edit/{id}', ['\Xibo\Controller\Schedule', 'editForm'])
        ->setName('schedule.edit.form');

    $group->get('/schedule/form/delete/{id}', ['\Xibo\Controller\Schedule', 'deleteForm'])
        ->setName('schedule.delete.form');

    $group->get('/schedulerecurrence/form/delete/{id}', ['\Xibo\Controller\Schedule', 'deleteRecurrenceForm'])
        ->setName('schedule.recurrence.delete.form');
})->add(new FeatureAuth($app->getContainer(), ['schedule.modify']));

//
// notification
//
$app->get('/drawer/notification/show/{id}', ['\Xibo\Controller\Notification','show'])->setName('notification.show');
$app->get('/drawer/notification/interrupt/{id}', ['\Xibo\Controller\Notification','interrupt'])->setName('notification.interrupt');
$app->get('/notification/export/{id}', ['\Xibo\Controller\Notification','exportAttachment'])->setName('notification.exportattachment');

$app->get('/notification/view', ['\Xibo\Controller\Notification','displayPage'])
    ->add(new FeatureAuth($app->getContainer(), ['notification.centre']))
    ->setName('notification.view');

$app->get('/notification/form/add', ['\Xibo\Controller\Notification', 'addForm'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['notification.add']))
    ->setName('notification.add.form');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/notification/form/edit/{id}', ['\Xibo\Controller\Notification', 'editForm'])
        ->setName('notification.edit.form');
    $group->get('/notification/form/delete/{id}', ['\Xibo\Controller\Notification', 'deleteForm'])
        ->setName('notification.delete.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['notification.modify']));

//
// layouts
//
$app->get('/layout/view', ['\Xibo\Controller\Layout', 'displayPage'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.view']))
    ->setName('layout.view');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/layout/xlf/{id}', ['\Xibo\Controller\Preview', 'getXlf'])->setName('layout.getXlf');
    $group->get('/layout/background/{id}', ['\Xibo\Controller\Layout', 'downloadBackground'])->setName('layout.download.background');
    $group->get('/layout/thumbnail/{id}', ['\Xibo\Controller\Layout', 'downloadThumbnail'])->setName('layout.download.thumbnail');
    $group->get('/layout/playerBundle', ['\Xibo\Controller\Preview', 'playerBundle'])->setName('layout.preview.bundle');
    $group->get('/connector/widget/preview', ['\Xibo\Controller\Connector', 'connectorPreview'])->setName('layout.preview.connector');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.view', 'template.view']));

$app->get('/layout/preview/{id}', ['\Xibo\Controller\Preview', 'show'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.view', 'template.view', 'campaign.view']))
    ->setName('layout.preview');

// forms
$app->get('/layout/form/add', ['\Xibo\Controller\Layout','addForm'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.add']))
    ->setName('layout.add.form');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/layout/designer[/{id}]', ['\Xibo\Controller\Layout','displayDesigner'])->setName('layout.designer');
    $group->get('/layout/form/edit/{id}', ['\Xibo\Controller\Layout', 'editForm'])->setName('layout.edit.form');
    $group->get('/layout/form/background/{id}', ['\Xibo\Controller\Layout', 'editBackgroundForm'])->setName('layout.background.form');
    $group->get('/layout/form/copy/{id}', ['\Xibo\Controller\Layout', 'copyForm'])->setName('layout.copy.form');
    $group->get('/layout/form/delete/{id}', ['\Xibo\Controller\Layout', 'deleteForm'])->setName('layout.delete.form');
    $group->get('/layout/form/checkout/{id}', ['\Xibo\Controller\Layout', 'checkoutForm'])->setName('layout.checkout.form');
    $group->get('/layout/form/publish/{id}', ['\Xibo\Controller\Layout', 'publishForm'])->setName('layout.publish.form');
    $group->get('/layout/form/discard/{id}', ['\Xibo\Controller\Layout', 'discardForm'])->setName('layout.discard.form');
    $group->get('/layout/form/retire/{id}', ['\Xibo\Controller\Layout', 'retireForm'])->setName('layout.retire.form');
    $group->get('/layout/form/unretire/{id}', ['\Xibo\Controller\Layout', 'unretireForm'])->setName('layout.unretire.form');
    $group->get('/layout/form/setenablestat/{id}', ['\Xibo\Controller\Layout', 'setEnableStatForm'])->setName('layout.setenablestat.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.modify', 'template.modify']));

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/layout/form/export/{id}', ['\Xibo\Controller\Layout', 'exportForm'])->setName('layout.export.form');
    $group->get('/layout/export/{id}', ['\Xibo\Controller\Layout', 'export'])->setName('layout.export');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.export']));

$app->get('/layout/form/campaign/assign/{id}', ['\Xibo\Controller\Layout','assignToCampaignForm'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['campaign.modify']))
    ->setName('layout.assignTo.campaign.form');

// Layout with Codes
$app->get('/layout/codes', ['\Xibo\Controller\Layout', 'getLayoutCodes'])->setName('layout.code.search');

//
// regions
//
$app->get('/region/preview/{id}', ['\Xibo\Controller\Region','preview'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.view']))
    ->setName('region.preview');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/region/form/edit/{id}', ['\Xibo\Controller\Region', 'editForm'])->setName('region.edit.form');
    $group->get('/region/form/delete/{id}', ['\Xibo\Controller\Region', 'deleteForm'])->setName('region.delete.form');

    // Designer
    $group->get('/playlist/form/library/assign/{id}', ['\Xibo\Controller\Playlist','libraryAssignForm'])->setName('playlist.library.assign.form');

    // Outputs
    $group->get('/playlist/widget/resource/{regionId}[/{id}]', [
        '\Xibo\Controller\Widget', 'getResource'
    ])->setName('module.getResource');

    $group->get('/playlist/widget/data/{regionId}/{id}', [
        '\Xibo\Controller\Widget', 'getData'
    ])->setName('module.getData');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.modify']));

$app->group('', function (\Slim\Routing\RouteCollectorProxy $group) {
    // Widget functions
    $group->get('/playlist/widget/form/edit/{id}', ['\Xibo\Controller\Widget','editWidgetForm'])->setName('module.widget.edit.form');
    $group->get('/playlist/widget/form/delete/{id}', ['\Xibo\Controller\Widget','deleteWidgetForm'])->setName('module.widget.delete.form');
    $group->get('/playlist/widget/form/transition/edit/{type}/{id}', ['\Xibo\Controller\Widget','editWidgetTransitionForm'])->setName('module.widget.transition.edit.form');
    $group->get('/playlist/widget/form/audio/{id}', ['\Xibo\Controller\Widget','widgetAudioForm'])->setName('module.widget.audio.form');
    $group->get('/playlist/widget/form/expiry/{id}', ['\Xibo\Controller\Widget','widgetExpiryForm'])->setName('module.widget.expiry.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.modify', 'playlist.modify']));

//
// playlists
//
$app->get('/playlist/view', ['\Xibo\Controller\Playlist','displayPage'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['playlist.view']))
    ->setName('playlist.view');

$app->get('/playlist/form/add', ['\Xibo\Controller\Playlist','addForm'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['playlist.add']))
    ->setName('playlist.add.form');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/playlist/form/edit/{id}', ['\Xibo\Controller\Playlist', 'editForm'])->setName('playlist.edit.form');
    $group->get('/playlist/form/copy/{id}', ['\Xibo\Controller\Playlist', 'copyForm'])->setName('playlist.copy.form');
    $group->get('/playlist/form/delete/{id}', ['\Xibo\Controller\Playlist', 'deleteForm'])->setName('playlist.delete.form');
    $group->get('/playlist/form/setenablestat/{id}', ['\Xibo\Controller\Playlist','setEnableStatForm'])->setName('playlist.setenablestat.form');
    $group->get('/playlist/form/{id}/selectfolder', ['\Xibo\Controller\Playlist','selectFolderForm'])->setName('playlist.selectfolder.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['playlist.modify']));

$app->get('/playlist/form/timeline/{id}', ['\Xibo\Controller\Playlist','timelineForm'])->setName('playlist.timeline.form');

$app->get('/playlist/form/usage/{id}', ['\Xibo\Controller\Playlist','usageForm'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['schedule.view', 'layout.view']))
    ->setName('playlist.usage.form');

//
// library
//
$app->get('/library/search', ['\Xibo\Controller\Library','search'])
    ->setName('library.search.all');

$app->get('/library/connector/list', ['\Xibo\Controller\Library','providersList'])
    ->setName('library.search.providers');

$app->get('/library/view', ['\Xibo\Controller\Library','displayPage'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['library.view']))
    ->setName('library.view');

$app->get('/library/form/uploadUrl', ['\Xibo\Controller\Library','uploadFromUrlForm'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['library.add']))
    ->setName('library.uploadUrl.form');

$app->post('/library/connector/import', ['\Xibo\Controller\Library', 'connectorImport'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['library.add']))
    ->setName('library.connector.import');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/library/form/edit/{id}', ['\Xibo\Controller\Library', 'editForm'])->setName('library.edit.form');
    $group->get('/library/form/delete/{id}', ['\Xibo\Controller\Library', 'deleteForm'])->setName('library.delete.form');
    $group->get('/library/form/tidy', ['\Xibo\Controller\Library', 'tidyForm'])->setName('library.tidy.form');
    $group->get('/library/form/copy/{id}', ['\Xibo\Controller\Library','copyForm'])->setName('library.copy.form');
    $group->get('/library/form/setenablestat/{id}', ['\Xibo\Controller\Library','setEnableStatForm'])->setName('library.setenablestat.form');
    $group->get('/library/form/{id}/selectfolder', ['\Xibo\Controller\Library','selectFolderForm'])->setName('library.selectfolder.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['library.modify']));

$app->get('/library/form/usage/{id}', ['\Xibo\Controller\Library','usageForm'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['schedule.view', 'layout.view']))
    ->setName('library.usage.form');

//
// display
//
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/display/map', ['\Xibo\Controller\Display', 'displayMap'])->setName('display.map');
    $group->get('/display/view', ['\Xibo\Controller\Display', 'displayPage'])->setName('display.view');
    $group->get('/display/manage/{id}', ['\Xibo\Controller\Display', 'displayManage'])->setName('display.manage');
    $group->get('/display/form/screenshot/{id}', ['\Xibo\Controller\Display','requestScreenShotForm'])->setName('display.screenshot.form');
    $group->get('/display/form/wol/{id}', ['\Xibo\Controller\Display','wakeOnLanForm'])->setName('display.wol.form');
    $group->get('/display/form/licenceCheck/{id}', ['\Xibo\Controller\Display','checkLicenceForm'])->setName('display.licencecheck.form');
    $group->get('/display/form/purgeAll/{id}', ['\Xibo\Controller\Display','purgeAllForm'])->setName('display.purge.all.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['displays.view']));

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/display/form/addViaCode', ['\Xibo\Controller\Display','addViaCodeForm'])->setName('display.addViaCode.form');
    $group->get('/display/form/authorise/{id}', ['\Xibo\Controller\Display','authoriseForm'])->setName('display.authorise.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['displays.add']));

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/display/form/edit/{id}', ['\Xibo\Controller\Display', 'editForm'])->setName('display.edit.form');
    $group->get('/display/form/delete/{id}', ['\Xibo\Controller\Display', 'deleteForm'])->setName('display.delete.form');
    $group->get('/display/form/defaultlayout/{id}', ['\Xibo\Controller\Display','defaultLayoutForm'])->setName('display.defaultlayout.form');
    $group->get('/display/form/moveCms/{id}', ['\Xibo\Controller\Display','moveCmsForm'])->setName('display.moveCms.form');
    $group->get('/display/form/moveCmsCancel/{id}', ['\Xibo\Controller\Display','moveCmsCancelForm'])->setName('display.moveCmsCancel.form');
    $group->get('/display/form/membership/{id}', ['\Xibo\Controller\Display','membershipForm'])->setName('display.membership.form');
    $group->get('/display/form/setBandwidthLimit', ['\Xibo\Controller\Display','setBandwidthLimitMultipleForm'])->setName('display.setBandwidthLimitMultiple.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['displays.modify']));

//
// user
//
$app->get('/user/view', ['\Xibo\Controller\User', 'displayPage'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['users.view']))
    ->setName('user.view');

$app->post('/user/welcome', ['\Xibo\Controller\User','userWelcomeSetUnseen'])->setName('welcome.wizard.unseen');
$app->put('/user/welcome', ['\Xibo\Controller\User','userWelcomeSetSeen'])->setName('welcome.wizard.seen');

$app->get('/user/apps', ['\Xibo\Controller\User','myApplications'])->setName('user.applications');

$app->get('/user/form/profile', ['\Xibo\Controller\User','editProfileForm'])->setName('user.edit.profile.form');
$app->get('/user/form/preferences', ['\Xibo\Controller\User', 'preferencesForm'])->setName('user.preferences.form');
$app->get('/user/permissions/form/{entity}/{id}', ['\Xibo\Controller\User','permissionsForm'])->setName('user.permissions.form');
$app->get('/user/permissions/multiple/form/{entity}', ['\Xibo\Controller\User','permissionsMultiForm'])->setName('user.permissions.multi.form');
$app->get('/user/page/password', ['\Xibo\Controller\User','forceChangePasswordPage'])->setName('user.force.change.password.page');

$app->get('/user/form/add', ['\Xibo\Controller\User','addForm'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['users.add']))
    ->setName('user.add.form');

$app->get('/user/form/onboarding', ['\Xibo\Controller\User','onboardingForm'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['users.add']))
    ->setName('user.onboarding.form');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/user/form/edit/{id}', ['\Xibo\Controller\User', 'editForm'])->setName('user.edit.form');
    $group->get('/user/form/delete/{id}', ['\Xibo\Controller\User', 'deleteForm'])->setName('user.delete.form');
    $group->get('/user/form/membership/{id}', ['\Xibo\Controller\User', 'membershipForm'])->setName('user.membership.form');
    $group->get('/user/form/setHomeFolder/{id}', ['\Xibo\Controller\User', 'setHomeFolderForm'])
        ->addMiddleware(new FeatureAuth($group->getContainer(), ['folder.userHome']))
        ->setName('user.homeFolder.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['users.modify']));

$app->get('/user/form/homepages', ['\Xibo\Controller\User', 'homepages'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['users.add', 'users.modify']))
    ->setName('user.homepages.search');

//
// log
//
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/log/view', ['\Xibo\Controller\Logging', 'displayPage'])->setName('log.view');
    $group->get('/log/delete', ['\Xibo\Controller\Logging', 'truncateForm'])->setName('log.truncate.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['log.view']));

//
// campaign
//
$app->get('/campaign/view', ['\Xibo\Controller\Campaign','displayPage'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['campaign.view']))
    ->setName('campaign.view');

$app->get('/campaign/form/add', ['\Xibo\Controller\Campaign','addForm'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['campaign.add']))
    ->setName('campaign.add.form');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/campaign/form/edit/{id}', ['\Xibo\Controller\Campaign', 'editForm'])->setName('campaign.edit.form');
    $group->get('/campaign/form/copy/{id}', ['\Xibo\Controller\Campaign', 'copyForm'])->setName('campaign.copy.form');
    $group->get('/campaign/form/delete/{id}', ['\Xibo\Controller\Campaign', 'deleteForm'])->setName('campaign.delete.form');
    $group->get('/campaign/form/retire/{id}', ['\Xibo\Controller\Campaign', 'retireForm'])->setName('campaign.retire.form');
    $group->get('/campaign/form/layout/remove/{id}', ['\Xibo\Controller\Campaign', 'removeLayoutForm'])
        ->setName('campaign.layout.remove.form');

    $group->get('/campaign-builder/{id}', ['\Xibo\Controller\Campaign', 'displayCampaignBuilder'])
        ->addMiddleware(new FeatureAuth($group->getContainer(), ['ad.campaign']))
        ->setName('campaign.builder');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['campaign.modify']));

$app->get('/campaign/form/{id}/selectfolder', ['\Xibo\Controller\Campaign','selectFolderForm'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['campaign.modify', 'layout.modify']))
    ->setName('campaign.selectfolder.form');

$app->get('/campaign/{id}/preview', ['\Xibo\Controller\Campaign','preview'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['campaign.view', 'layout.view']))
    ->setName('campaign.preview');

//
// template
//
$app->get('/template/connector/list', ['\Xibo\Controller\Template','providersList'])
    ->setName('template.search.providers');
$app->get('/template/search', ['\Xibo\Controller\Template', 'search'])->setName('template.search.all');
$app->get('/template/view', ['\Xibo\Controller\Template','displayPage'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['template.view']))
    ->setName('template.view');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/template/form/layout/{id}', ['\Xibo\Controller\Template', 'addTemplateForm'])->setName('template.from.layout.form');
    $group->get('/template/form/add', ['\Xibo\Controller\Template', 'addForm'])->setName('template.add.form');
    $group->get('/template/form/edit/{id}', ['\Xibo\Controller\Template', 'editForm'])->setName('template.edit.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['template.add']));

//
// resolution
//
$app->get('/resolution/view', ['\Xibo\Controller\Resolution','displayPage'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['resolution.view']))
    ->setName('resolution.view');

$app->get('/resolution/form/add', ['\Xibo\Controller\Resolution','addForm'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['resolution.add']))
    ->setName('resolution.add.form');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/resolution/form/edit/{id}', ['\Xibo\Controller\Resolution', 'editForm'])->setName('resolution.edit.form');
    $group->get('/resolution/form/delete/{id}', ['\Xibo\Controller\Resolution', 'deleteForm'])->setName('resolution.delete.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['resolution.modify']));

//
// dataset
//
$app->get('/dataset/view', ['\Xibo\Controller\DataSet','displayPage'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['dataset.view']))
    ->setName('dataset.view');

$app->get('/dataset/form/add', ['\Xibo\Controller\DataSet','addForm'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['dataset.add']))
    ->setName('dataSet.add.form');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/dataset/form/edit/{id}', ['\Xibo\Controller\DataSet', 'editForm'])->setName('dataSet.edit.form');
    $group->get('/dataset/form/copy/{id}', ['\Xibo\Controller\DataSet', 'copyForm'])->setName('dataSet.copy.form');
    $group->get('/dataset/form/delete/{id}', ['\Xibo\Controller\DataSet', 'deleteForm'])->setName('dataSet.delete.form');
    $group->get('/dataset/form/import/{id}', ['\Xibo\Controller\DataSet', 'importForm'])->setName('dataSet.import.form');
    $group->get('/dataset/form/cache/clear/{id}', ['\Xibo\Controller\DataSet', 'clearCacheForm'])->setName('dataSet.clear.cache.form');
    $group->post('/dataset/cache/clear/{id}', ['\Xibo\Controller\DataSet', 'clearCache'])->setName('dataSet.clear.cache');
    $group->get('/dataset/form/{id}/selectfolder', ['\Xibo\Controller\DataSet', 'selectFolderForm'])->setName('dataSet.selectfolder.form');

    $group->get('/dataset/dataConnector/{id}', ['\Xibo\Controller\DataSet', 'dataConnectorView'])->setName('dataSet.dataConnector.view');
    $group->get('/dataset/dataConnector/request/{id}', ['\Xibo\Controller\DataSet', 'dataConnectorRequest'])->setName('dataSet.dataConnector.request');
    $group->get('/dataset/dataConnector/test/{id}', ['\Xibo\Controller\DataSet', 'dataConnectorTest'])->setName('dataSet.dataConnector.test');

    // columns
    $group->get('/dataset/{id}/column/view', ['\Xibo\Controller\DataSetColumn','displayPage'])->setName('dataSet.column.view');
    $group->get('/dataset/{id}/column/form/add', ['\Xibo\Controller\DataSetColumn','addForm'])->setName('dataSet.column.add.form');
    $group->get('/dataset/{id}/column/form/edit/{colId}', ['\Xibo\Controller\DataSetColumn','editForm'])->setName('dataSet.column.edit.form');
    $group->get('/dataset/{id}/column/form/delete/{colId}', ['\Xibo\Controller\DataSetColumn','deleteForm'])->setName('dataSet.column.delete.form');

    // RSS
    $group->get('/dataset/{id}/rss/view', ['\Xibo\Controller\DataSetRss','displayPage'])->setName('dataSet.rss.view');
    $group->get('/dataset/{id}/rss/form/add', ['\Xibo\Controller\DataSetRss','addForm'])->setName('dataSet.rss.add.form');
    $group->get('/dataset/{id}/rss/form/edit/{rssId}', ['\Xibo\Controller\DataSetRss','editForm'])->setName('dataSet.rss.edit.form');
    $group->get('/dataset/{id}/rss/form/delete/{rssId}', ['\Xibo\Controller\DataSetRss','deleteForm'])->setName('dataSet.rss.delete.form');

})->addMiddleware(new FeatureAuth($app->getContainer(), ['dataset.modify']));

// data
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/dataset/data/view/{id}', ['\Xibo\Controller\DataSetData','displayPage'])->setName('dataSet.view.data');
    $group->get('/dataset/data/form/add/{id}', ['\Xibo\Controller\DataSetData','addForm'])->setName('dataSet.data.add.form');
    $group->get('/dataset/data/form/edit/{id}/{rowId}', ['\Xibo\Controller\DataSetData','editForm'])->setName('dataSet.data.edit.form');
    $group->get('/dataset/data/form/delete/{id}/{rowId}', ['\Xibo\Controller\DataSetData','deleteForm'])->setName('dataSet.data.delete.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['dataset.data']));

//
// displaygroup
//
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/displaygroup/view', ['\Xibo\Controller\DisplayGroup','displayPage'])->setName('displaygroup.view');
    $group->get('/displaygroup/form/command/{id}', ['\Xibo\Controller\DisplayGroup','commandForm'])->setName('displayGroup.command.form');
    $group->get('/displaygroup/form/collect/{id}', ['\Xibo\Controller\DisplayGroup','collectNowForm'])->setName('displayGroup.collectNow.form');
    $group->get('/displaygroup/form/trigger/webhook/{id}', ['\Xibo\Controller\DisplayGroup','triggerWebhookForm'])->setName('displayGroup.trigger.webhook.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['displaygroup.view']));

$app->get('/displaygroup/form/add', ['\Xibo\Controller\DisplayGroup','addForm'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['displaygroup.add']))
    ->setName('displayGroup.add.form');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/displaygroup/form/edit/{id}', ['\Xibo\Controller\DisplayGroup','editForm'])->setName('displayGroup.edit.form');
    $group->get('/displaygroup/form/delete/{id}', ['\Xibo\Controller\DisplayGroup','deleteForm'])->setName('displayGroup.delete.form');
    $group->get('/displaygroup/form/members/{id}', ['\Xibo\Controller\DisplayGroup','membersForm'])->setName('displayGroup.members.form');
    $group->get('/displaygroup/form/media/{id}', ['\Xibo\Controller\DisplayGroup','mediaForm'])->setName('displayGroup.media.form');
    $group->get('/displaygroup/form/layout/{id}', ['\Xibo\Controller\DisplayGroup','layoutsForm'])->setName('displayGroup.layout.form');
    $group->get('/displaygroup/form/copy/{id}', ['\Xibo\Controller\DisplayGroup','copyForm'])->setName('displayGroup.copy.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['displaygroup.modify']));

$app->get('/displaygroup/form/{id}/selectfolder', ['\Xibo\Controller\DisplayGroup','selectFolderForm'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['displaygroup.modify', 'display.modify']))
    ->setName('displayGroup.selectfolder.form');

//
// displayprofile
//
$app->get('/displayprofile/view', ['\Xibo\Controller\DisplayProfile','displayPage'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['displayprofile.view']))
    ->setName('displayprofile.view');

$app->get('/displayprofile/form/add', ['\Xibo\Controller\DisplayProfile','addForm'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['displayprofile.add']))
    ->setName('displayProfile.add.form');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/displayprofile/form/edit/{id}', ['\Xibo\Controller\DisplayProfile','editForm'])->setName('displayProfile.edit.form');
    $group->get('/displayprofile/form/delete/{id}', ['\Xibo\Controller\DisplayProfile','deleteForm'])->setName('displayProfile.delete.form');
    $group->get('/displayprofile/form/copy/{id}', ['\Xibo\Controller\DisplayProfile','copyForm'])->setName('displayProfile.copy.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['displayprofile.modify']));

//
// group
//
$app->get('/group/view', ['\Xibo\Controller\UserGroup','displayPage'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['usergroup.view']))
    ->setName('group.view');

$app->get('/group/form/add', ['\Xibo\Controller\UserGroup','addForm'])->setName('group.add.form');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/group/form/edit/{id}', ['\Xibo\Controller\UserGroup','editForm'])->setName('group.edit.form');
    $group->get('/group/form/delete/{id}', ['\Xibo\Controller\UserGroup','deleteForm'])->setName('group.delete.form');
    $group->get('/group/form/copy/{id}', ['\Xibo\Controller\UserGroup','copyForm'])->setName('group.copy.form');
    $group->get('/group/form/acl/{id}/[{userId}]', ['\Xibo\Controller\UserGroup','aclForm'])->setName('group.acl.form');
    $group->get('/group/form/members/{id}', ['\Xibo\Controller\UserGroup','membersForm'])->setName('group.members.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['usergroup.modify']));

//
// admin
//
$app->get('/admin/view', ['\Xibo\Controller\Settings','displayPage'])
    ->addMiddleware(new SuperAdminAuth($app->getContainer()))
    ->setName('admin.view');

//
// maintenance
//
$app->get('/maintenance/form/tidy', ['\Xibo\Controller\Maintenance','tidyLibraryForm'])
    ->addMiddleware(new SuperAdminAuth($app->getContainer()))
    ->setName('maintenance.libraryTidy.form');

//
// Folders
//
$app->group('', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/folders/view', ['\Xibo\Controller\Folder', 'displayPage'])->setName('folders.view');
    $group->get('/folders/form/add', ['\Xibo\Controller\Folder', 'addForm'])->setName('folders.add.form');
    $group->get('/folders/form/edit/{id}', ['\Xibo\Controller\Folder', 'editForm'])->setName('folders.edit.form');
    $group->get('/folders/form/delete/{id}', ['\Xibo\Controller\Folder', 'deleteForm'])->setName('folders.delete.form');
})->addMiddleware(new SuperAdminAuth($app->getContainer()));

//
// Applications and connectors
//
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/application/view', ['\Xibo\Controller\Applications','displayPage'])->setName('application.view');
    $group->get('/application/data/activity', ['\Xibo\Controller\Applications','viewActivity'])->setName('application.view.activity');
    $group->get('/application/form/add', ['\Xibo\Controller\Applications','addForm'])->setName('application.add.form');
    $group->get('/application/form/edit/{id}', ['\Xibo\Controller\Applications','editForm'])->setName('application.edit.form');
    $group->get('/application/form/delete/{id}', ['\Xibo\Controller\Applications','deleteForm'])->setName('application.delete.form');
    $group->put('/application/{id}', ['\Xibo\Controller\Applications','edit'])->setName('application.edit');
    $group->delete('/application/{id}', ['\Xibo\Controller\Applications','delete'])->setName('application.delete');

    // We can only view/edit these through the web app
    $group->get('/connectors', ['\Xibo\Controller\Connector','grid'])->setName('connector.search');
    $group->get('/connectors/form/edit/{id}', ['\Xibo\Controller\Connector','editForm'])
        ->setName('connector.edit.form');
    $group->map(
        ['GET', 'POST'],
        '/connectors/form/{id}/proxy/{method}',
        ['\Xibo\Controller\Connector', 'editFormProxy']
    )->setName('connector.edit.form.proxy');
    $group->put('/connectors/{id}', ['\Xibo\Controller\Connector','edit'])->setName('connector.edit');
})->addMiddleware(new SuperAdminAuth($app->getContainer()));

$app->get('/application/authorize', ['\Xibo\Controller\Applications','authorizeRequest'])->setName('application.authorize.request');
$app->post('/application/authorize', ['\Xibo\Controller\Applications','authorize'])->setName('application.authorize');
$app->delete('/application/revoke/{id}/{userId}', ['\Xibo\Controller\Applications', 'revokeAccess'])->setName('application.revoke');

//
// module
//
$app->group('', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/module/view', ['\Xibo\Controller\Module','displayPage'])->setName('module.view');
    $group->get('/module/form/clear-cache/{id}', ['\Xibo\Controller\Module','clearCacheForm'])
        ->setName('module.clear.cache.form');

    $group->get('/module/form/settings/{id}', ['\Xibo\Controller\Module','settingsForm'])
        ->setName('module.settings.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['module.view']));

//
// Developer
//
$app->group('', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/developer/template/datatypes', ['\Xibo\Controller\Developer', 'getAvailableDataTypes'])
        ->setName('developer.templates.datatypes.search');
    $group->get('/developer/template/view', ['\Xibo\Controller\Developer', 'displayTemplatePage'])
        ->setName('developer.templates.view');
    $group->get('/developer/template', ['\Xibo\Controller\Developer', 'templateGrid'])
        ->setName('developer.templates.search');

    $group->get('/developer/template/{id}', ['\Xibo\Controller\Developer', 'displayTemplateEditPage'])
        ->setName('developer.templates.view.edit');

    $group->get('/developer/template/form/add', ['\Xibo\Controller\Developer', 'templateAddForm'])
        ->setName('developer.templates.form.add');

    $group->get('/developer/template/form/edit/{id}', ['\Xibo\Controller\Developer', 'templateEditForm'])
        ->setName('developer.templates.form.edit');

    $group->get('/developer/template/form/delete/{id}', ['\Xibo\Controller\Developer', 'templateDeleteForm'])
        ->setName('developer.templates.form.delete');

    $group->get('/developer/template/form/copy/{id}', ['\Xibo\Controller\Developer', 'templateCopyForm'])
        ->setName('developer.templates.form.copy');

    $group->post('/developer/template', ['\Xibo\Controller\Developer', 'templateAdd'])
        ->setName('developer.templates.add');
    $group->put('/developer/template/{id}', ['\Xibo\Controller\Developer', 'templateEdit'])
        ->setName('developer.templates.edit');
    $group->delete('/developer/template/{id}', ['\Xibo\Controller\Developer', 'templateDelete'])
        ->setName('developer.templates.delete');
    $group->get('/developer/template/{id}/export', ['\Xibo\Controller\Developer', 'templateExport'])
        ->setName('developer.templates.export');
    $group->post('/developer/template/import', ['\Xibo\Controller\Developer', 'templateImport'])
        ->setName('developer.templates.import');
    $group->post('/developer/template/{id}/copy', ['\Xibo\Controller\Developer', 'templateCopy'])
        ->setName('developer.templates.copy');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['developer.edit']));

//
// transition
//
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/transition/view', ['\Xibo\Controller\Transition','displayPage'])->setName('transition.view');
    $group->get('/transition/form/edit/{id}', ['\Xibo\Controller\Transition','editForm'])->setName('transition.edit.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['transition.view']));

//
// sessions
//
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/sessions/view', ['\Xibo\Controller\Sessions','displayPage'])->setName('sessions.view');
    $group->get('/sessions/form/logout/{id}', ['\Xibo\Controller\Sessions','confirmLogoutForm'])->setName('sessions.confirm.logout.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['session.view']));

//
// fault
//
$app->get('/fault/view', ['\Xibo\Controller\Fault','displayPage'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['fault.view']))
    ->setName('fault.view');

//
// license
//
$app->get('/license/view', ['\Xibo\Controller\Login','about'])->setName('license.view');

//
// Reporting
//
$app->get('/report/view', ['\Xibo\Controller\Stats','displayReportPage'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['report.view']))
    ->setName('report.view');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/stats/form/export', ['\Xibo\Controller\Stats','exportForm'])->setName('stats.export.form');
    $group->get('/stats/getExportStatsCount', ['\Xibo\Controller\Stats','getExportStatsCount'])->setName('stats.getExportStatsCount');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['proof-of-play']));

// Used in Display Manage
$app->get('/stats/data/bandwidth', ['\Xibo\Controller\Stats','bandwidthData'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['displays.reporting']))
    ->setName('stats.bandwidth.data');

//
// Audit Log
//
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/audit/view', ['\Xibo\Controller\AuditLog','displayPage'])->setName('auditlog.view');
    $group->get('/audit/form/export', ['\Xibo\Controller\AuditLog','exportForm'])->setName('auditLog.export.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['auditlog.view']));

//
// Commands
//
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/command/view', ['\Xibo\Controller\Command','displayPage'])->setName('command.view');
    $group->get('/command/form/add', ['\Xibo\Controller\Command','addForm'])->setName('command.add.form');
    $group->get('/command/form/edit/{id}', ['\Xibo\Controller\Command','editForm'])->setName('command.edit.form');
    $group->get('/command/form/delete/{id}', ['\Xibo\Controller\Command','deleteForm'])->setName('command.delete.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['command.view']));

//
// Daypart
//
$app->get('/daypart/view', ['\Xibo\Controller\DayPart','displayPage'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['daypart.view']))
    ->setName('daypart.view');

$app->get('/daypart/form/add', ['\Xibo\Controller\DayPart','addForm'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['daypart.add']))
    ->setName('daypart.add.form');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/daypart/form/edit/{id}', ['\Xibo\Controller\DayPart','editForm'])->setName('daypart.edit.form');
    $group->get('/daypart/form/delete/{id}', ['\Xibo\Controller\DayPart','deleteForm'])->setName('daypart.delete.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['daypart.modify']));

//
// Tasks
//
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/task/view', ['\Xibo\Controller\Task','displayPage'])->setName('task.view');
    $group->get('/task/form/add', ['\Xibo\Controller\Task','addForm'])->setName('task.add.form');
    $group->get('/task/form/edit/{id}', ['\Xibo\Controller\Task','editForm'])->setName('task.edit.form');
    $group->get('/task/form/delete/{id}', ['\Xibo\Controller\Task','deleteForm'])->setName('task.delete.form');
    $group->get('/task/form/runNow/{id}', ['\Xibo\Controller\Task','runNowForm'])->setName('task.runNow.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['task.view']));


//
// Report Schedule
//
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/report/reportschedule/view', ['\Xibo\Controller\ScheduleReport','displayReportSchedulePage'])->setName('reportschedule.view');
    $group->get('/report/reportschedule/form/add', ['\Xibo\Controller\ScheduleReport','addReportScheduleForm'])->setName('reportschedule.add.form');
    $group->get('/report/reportschedule/form/edit/{id}', ['\Xibo\Controller\ScheduleReport','editReportScheduleForm'])->setName('reportschedule.edit.form');
    $group->get('/report/reportschedule/form/delete/{id}', ['\Xibo\Controller\ScheduleReport','deleteReportScheduleForm'])->setName('reportschedule.delete.form');
    $group->get('/report/reportschedule/form/deleteall/{id}', ['\Xibo\Controller\ScheduleReport','deleteAllSavedReportReportScheduleForm'])->setName('reportschedule.deleteall.form');
    $group->get('/report/reportschedule/form/toggleactive/{id}', ['\Xibo\Controller\ScheduleReport','toggleActiveReportScheduleForm'])->setName('reportschedule.toggleactive.form');
    $group->get('/report/reportschedule/form/reset/{id}', ['\Xibo\Controller\ScheduleReport','resetReportScheduleForm'])->setName('reportschedule.reset.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['report.scheduling']));

//
// Saved reports
//
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/report/savedreport/view', ['\Xibo\Controller\SavedReport','displaySavedReportPage'])->setName('savedreport.view');
    $group->get('/report/savedreport/{id}/report/{name}/open', ['\Xibo\Controller\SavedReport','savedReportOpen'])->setName('savedreport.open');
    $group->get('/report/savedreport/{id}/report/{name}/export', ['\Xibo\Controller\SavedReport','savedReportExport'])->setName('savedreport.export');
    $group->get('/report/savedreport/form/delete/{id}', ['\Xibo\Controller\SavedReport','deleteSavedReportForm'])->setName('savedreport.delete.form');
    $group->get('/report/savedreport/{id}/report/{name}/convert', ['\Xibo\Controller\OldReport','savedReportConvert'])->setName('savedreport.convert');
    $group->get('/report/savedreport/form/convert/{id}', ['\Xibo\Controller\OldReport','convertSavedReportForm'])->setName('savedreport.convert.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['report.saving']));

//
// Ad hoc report
//
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/report/form/{name}', ['\Xibo\Controller\Report','getReportForm'])->setName('report.form');
    $group->get('/report/data/{name}', ['\Xibo\Controller\Report','getReportData'])->setName('report.data');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['report.view']));

// Player Software
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/playersoftware/view', ['\Xibo\Controller\PlayerSoftware','displayPage'])->setName('playersoftware.view');
    $group->get('/playersoftware/form/edit/{id}', ['\Xibo\Controller\PlayerSoftware','editForm'])->setName('playersoftware.edit.form');
    $group->get('/playersoftware/form/delete/{id}', ['\Xibo\Controller\PlayerSoftware','deleteForm'])->setName('playersoftware.delete.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['playersoftware.view']));

// Tags
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/tag/view', ['\Xibo\Controller\Tag','displayPage'])->setName('tag.view');
    $group->get('/tag/form/add', ['\Xibo\Controller\Tag','addForm'])->setName('tag.add.form');
    $group->get('/tag/form/edit/{id}', ['\Xibo\Controller\Tag','editForm'])->setName('tag.edit.form');
    $group->get('/tag/form/delete/{id}', ['\Xibo\Controller\Tag','deleteForm'])->setName('tag.delete.form');
    $group->get('/tag/form/usage/{id}', ['\Xibo\Controller\Tag', 'usageForm'])->setName('tag.usage.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['tag.view']));

// Menu Boards
$app->group('', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/menuboard/view', ['\Xibo\Controller\MenuBoard','displayPage'])->setName('menuBoard.view');
    $group->get('/menuboard/form/add', ['\Xibo\Controller\MenuBoard', 'addForm'])->setName('menuBoard.add.form');
    $group->get('/menuboard/form/{id}/edit', ['\Xibo\Controller\MenuBoard', 'editForm'])->setName('menuBoard.edit.form');
    $group->get('/menuboard/form/{id}/delete', ['\Xibo\Controller\MenuBoard', 'deleteForm'])->setName('menuBoard.delete.form');
    $group->get('/menuboard/form/{id}/selectfolder', ['\Xibo\Controller\MenuBoard', 'selectFolderForm'])->setName('menuBoard.selectfolder.form');

    $group->get('/menuboard/{id}/categories/view', ['\Xibo\Controller\MenuBoardCategory', 'displayPage'])->setName('menuBoard.category.view');
    $group->get('/menuboard/{id}/category/form/add', ['\Xibo\Controller\MenuBoardCategory', 'addForm'])->setName('menuBoard.category.add.form');
    $group->get('/menuboard/{id}/category/form/edit', ['\Xibo\Controller\MenuBoardCategory', 'editForm'])->setName('menuBoard.category.edit.form');
    $group->get('/menuboard/{id}/category/form/delete', ['\Xibo\Controller\MenuBoardCategory', 'deleteForm'])->setName('menuBoard.category.delete.form');

    $group->get('/menuboard/{id}/products/view', ['\Xibo\Controller\MenuBoardProduct', 'displayPage'])->setName('menuBoard.product.view');
    $group->get('/menuboard/{id}/product/form/add', ['\Xibo\Controller\MenuBoardProduct', 'addForm'])->setName('menuBoard.product.add.form');
    $group->get('/menuboard/{id}/product/form/edit', ['\Xibo\Controller\MenuBoardProduct', 'editForm'])->setName('menuBoard.product.edit.form');
    $group->get('/menuboard/{id}/product/form/delete', ['\Xibo\Controller\MenuBoardProduct', 'deleteForm'])->setName('menuBoard.product.delete.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['menuBoard.view']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/folders/form/{folderId}/move', ['\Xibo\Controller\Folder', 'moveForm'])->setName('folders.move.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['folder.modify']));

$app->get('/fonts/fontcss', ['\Xibo\Controller\Font','fontCss'])->setName('library.font.css');

$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/fonts/view', ['\Xibo\Controller\Font', 'displayPage'])->setName('font.view');
    $group->get('/fonts/{id}/form/delete', ['\Xibo\Controller\Font', 'deleteForm'])->setName('font.form.delete');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['font.view']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/syncgroup/view', ['\Xibo\Controller\SyncGroup', 'displayPage'])->setName('syncgroup.view');
    $group->get('/syncgroup/form/add', ['\Xibo\Controller\SyncGroup', 'addForm'])->setName('syncgroup.form.add');
    $group->get('/syncgroup/form/{id}/members', ['\Xibo\Controller\SyncGroup', 'membersForm'])->setName('syncgroup.form.members');
    $group->get('/syncgroup/form/{id}/edit', ['\Xibo\Controller\SyncGroup', 'editForm'])->setName('syncgroup.form.edit');
    $group->get('/syncgroup/form/{id}/delete', ['\Xibo\Controller\SyncGroup', 'deleteForm'])->setName('syncgroup.form.delete');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['display.syncView']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/schedule/form/sync', ['\Xibo\Controller\Schedule', 'syncForm'])->setName('schedule.add.sync.form');
    $group->get('/schedule/form/{id}/sync', ['\Xibo\Controller\Schedule', 'syncEditForm'])->setName('schedule.edit.sync.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['schedule.sync']));
