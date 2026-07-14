<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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
// Note: '/welcome' has no PHP route — it's the React welcome page, served via the
// SPA-shell NotFound fallback. Marking the wizard seen is handled client-side
// (see Welcome.tsx -> markWelcomeSeen() -> PUT /user/welcome).
$app->get('/', ['\Xibo\Controller\User', 'home'])->setName('home');

// Login Form
$app->get('/login', ['\Xibo\Controller\Login', 'loginForm'])->setName('login');

// Login Requests
$app->post('/login', ['\Xibo\Controller\Login','login']);
$app->post('/login/forgotten', ['\Xibo\Controller\Login','forgottenPassword'])->setName('login.forgotten');

// Logout Request
$app->get('/logout', ['\Xibo\Controller\Login','logout'])->setName('logout');

// Ping pong route
$app->get('/login/ping', ['\Xibo\Controller\Login','pingPong'])->setName('ping');

//
// notification
//
$app->get('/drawer/notification/show/{id}', ['\Xibo\Controller\Notification','show'])->setName('notification.show');
$app->get('/drawer/notification/interrupt/{id}', ['\Xibo\Controller\Notification','interrupt'])->setName('notification.interrupt');

//
// layouts
//
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/layout/xlf/{id}', ['\Xibo\Controller\Preview', 'getXlf'])->setName('layout.getXlf');
    $group->get('/layout/background/{id}', ['\Xibo\Controller\Layout', 'downloadBackground'])->setName('layout.download.background');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.view', 'template.view']));

// forms
$app->get('/layout/form/add', ['\Xibo\Controller\Layout','addForm'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.add']))
    ->setName('layout.add.form');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/layout/designer[/{id}]', ['\Xibo\Controller\Layout','displayDesigner'])->setName('layout.designer');
    $group->get('/layout/form/edit/{id}', ['\Xibo\Controller\Layout', 'editForm'])->setName('layout.edit.form');
    $group->get('/layout/form/background/{id}', ['\Xibo\Controller\Layout', 'editBackgroundForm'])->setName('layout.background.form');
    $group->get('/layout/form/delete/{id}', ['\Xibo\Controller\Layout', 'deleteForm'])->setName('layout.delete.form');
    $group->get('/layout/form/clear/{id}', ['\Xibo\Controller\Layout', 'clearForm'])->setName('layout.clear.form');
    $group->get('/layout/form/checkout/{id}', ['\Xibo\Controller\Layout', 'checkoutForm'])->setName('layout.checkout.form');
    $group->get('/layout/form/publish/{id}', ['\Xibo\Controller\Layout', 'publishForm'])->setName('layout.publish.form');
    $group->get('/layout/form/discard/{id}', ['\Xibo\Controller\Layout', 'discardForm'])->setName('layout.discard.form');
    $group->get('/layout/form/retire/{id}', ['\Xibo\Controller\Layout', 'retireForm'])->setName('layout.retire.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.modify', 'template.modify']));

//
// regions
//
$app->get('/region/preview/{id}', ['\Xibo\Controller\Region','preview'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.view']))
    ->setName('region.preview');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/region/{id}', ['\Xibo\Controller\Region', 'get'])->setName('region.get');

    // Designer
    $group->get('/playlist/form/library/assign/{id}', ['\Xibo\Controller\Playlist','libraryAssignForm'])->setName('playlist.library.assign.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.modify']));

$app->group('', function (\Slim\Routing\RouteCollectorProxy $group) {
    // Widget functions
    $group->get('/playlist/widget/{id}', ['\Xibo\Controller\Widget','getWidget'])->setName('module.widget.get');
    $group->get('/playlist/widget/form/transition/edit/{type}/{id}', ['\Xibo\Controller\Widget','editWidgetTransitionForm'])->setName('module.widget.transition.edit.form');
    $group->get('/playlist/widget/form/audio/{id}', ['\Xibo\Controller\Widget','widgetAudioForm'])->setName('module.widget.audio.form');
    $group->get('/playlist/widget/form/expiry/{id}', ['\Xibo\Controller\Widget','widgetExpiryForm'])->setName('module.widget.expiry.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.modify', 'playlist.modify']));

//
// playlists
//
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/playlist/form/edit/{id}', ['\Xibo\Controller\Playlist', 'editForm'])
        ->setName('playlist.edit.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['playlist.modify']));

$app->get('/playlist/form/timeline/{id}', ['\Xibo\Controller\Playlist','timelineForm'])
    ->setName('playlist.timeline.form');

$app->get('/playlist/designer/{id}', ['\Xibo\Controller\Playlist', 'displayDesigner'])
    ->setName('playlist.designer');

//
// library
//
$app->get('/library/search', ['\Xibo\Controller\Library','search'])
    ->setName('library.search.all');

$app->get('/library/connector/list', ['\Xibo\Controller\Library','providersList'])
    ->setName('library.search.providers');

$app->post('/library/connector/import', ['\Xibo\Controller\Library', 'connectorImport'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['library.add']))
    ->setName('library.connector.import');

//
// display
//
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/display/map', ['\Xibo\Controller\Display', 'displayMap'])->setName('display.map');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['displays.view']));

//
// user
//
$app->get('/user/apps', ['\Xibo\Controller\User','myApplications'])->setName('user.applications');

$app->get('/user/form/profile', ['\Xibo\Controller\User','editProfileForm'])->setName('user.edit.profile.form');
$app->get('/user/form/preferences', ['\Xibo\Controller\User', 'preferencesForm'])->setName('user.preferences.form');
$app->get('/user/permissions/form/{entity}/{id}', ['\Xibo\Controller\User','permissionsForm'])->setName('user.permissions.form');
$app->get('/user/permissions/multiple/form/{entity}', ['\Xibo\Controller\User','permissionsMultiForm'])->setName('user.permissions.multi.form');


$app->get('/user/form/homepages', ['\Xibo\Controller\User', 'homepages'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['users.add', 'users.modify']))
    ->setName('user.homepages.search');

//
// template
//
$app->get('/template/connector/list', ['\Xibo\Controller\Template','providersList'])
    ->setName('template.search.providers');
$app->get('/template/search', ['\Xibo\Controller\Template', 'search'])->setName('template.search.all');

$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/template/form/layout/{id}', ['\Xibo\Controller\Template', 'addTemplateForm'])->setName('template.from.layout.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['template.add']));

//
// dataset
//
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->post('/dataset/cache/clear/{id}', ['\Xibo\Controller\DataSet', 'clearCache'])->setName('dataSet.clear.cache');

    // Data connector test page (embedded in an iframe by the React data connector page)
    $group->get('/dataset/dataConnector/test/{id}', ['\Xibo\Controller\DataSet', 'dataConnectorTest'])->setName('dataSet.dataConnector.test');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['dataset.modify']));

//
// maintenance
//
$app->get('/maintenance/form/tidy', ['\Xibo\Controller\Maintenance','tidyLibraryForm'])
    ->addMiddleware(new SuperAdminAuth($app->getContainer()))
    ->setName('maintenance.libraryTidy.form');

//
// Module
//
$app->get('/module/asset/{assetId}', ['\Xibo\Controller\Module', 'assetDownload'])
    ->setName('module.asset.download');

//
// transition
//
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/transition/form/edit/{id}', ['\Xibo\Controller\Transition','editForm'])->setName('transition.edit.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['transition.view']));

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
// Ad hoc report
//
$app->group('', function(\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/report/form/{name}', ['\Xibo\Controller\Report','getReportForm'])->setName('report.form');
    $group->get('/report/data/{name}', ['\Xibo\Controller\Report','getReportData'])->setName('report.data');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['report.view']));

$app->get('/fonts/fontcss', ['\Xibo\Controller\Font','fontCss'])->setName('library.font.css');

$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/schedule/form/sync', ['\Xibo\Controller\Schedule', 'syncForm'])->setName('schedule.add.sync.form');
    $group->get('/schedule/form/{id}/sync', ['\Xibo\Controller\Schedule', 'syncEditForm'])->setName('schedule.edit.sync.form');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['schedule.sync']));
