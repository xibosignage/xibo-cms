<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

/**
 * Routes for the React/JSON frontend only.
 * Loaded by web/json/index.php — NOT exposed via the API entrypoint.
 */

global $app;

use Slim\Routing\RouteCollectorProxy;
use Xibo\Middleware\FeatureAuth;
use Xibo\Middleware\SuperAdminAuth;

//
// Schedule
//
$app->get('/schedule/criteria', ['\Xibo\Controller\Schedule','getCriteria'])->setName('schedule.criteria');

//
// Applications
//
$app->get('/application/authorize', ['\Xibo\Controller\Applications','authorizeRequest'])
    ->setName('application.authorize.request');
$app->post('/application/authorize', ['\Xibo\Controller\Applications','authorize'])
    ->setName('application.authorize');

$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/application', ['\Xibo\Controller\Applications', 'grid'])
        ->setName('application.search');
    $group->get('/application/scope', ['\Xibo\Controller\Applications', 'scopeSearch'])
        ->setName('application.scope.search');
    $group->get('/application/{id}', ['\Xibo\Controller\Applications', 'getById'])
        ->setName('application.search.id');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['application.view']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/application', ['\Xibo\Controller\Applications','add'])->setName('application.add');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['application.add']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/application/{id}', ['\Xibo\Controller\Applications','edit'])->setName('application.edit');
    $group->delete('/application/{id}', ['\Xibo\Controller\Applications','delete'])->setName('application.delete');
})->addMiddleware(new SuperAdminAuth($app->getContainer()));

//
// Connectors
//
$app->group('', function (\Slim\Routing\RouteCollectorProxy $group) {
    // We can only view/edit these through the web app
    $group->get('/connectors', ['\Xibo\Controller\Connector','grid'])->setName('connector.search');
    $group->get('/connectors/{id}/fields', ['\Xibo\Controller\Connector','editFormFields'])
        ->setName('connector.edit.form.fields');
    $group->get('/connectors/{id}', ['\Xibo\Controller\Connector','searchById'])
        ->setName('connector.search.id');
    $group->map(
        ['GET', 'POST'],
        '/connectors/form/{id}/proxy/{method}',
        ['\Xibo\Controller\Connector', 'editFormProxy']
    )->setName('connector.edit.form.proxy');
    $group->put('/connectors/{id}', ['\Xibo\Controller\Connector','edit'])->setName('connector.edit');
})->addMiddleware(new SuperAdminAuth($app->getContainer()));

//
// Settings
//
$app->get('/admin', ['\Xibo\Controller\Settings', 'get'])
    ->addMiddleware(new SuperAdminAuth($app->getContainer()))
    ->setName('settings.get');

$app->put('/admin', ['\Xibo\Controller\Settings', 'update'])
    ->addMiddleware(new SuperAdminAuth($app->getContainer()))
    ->setName('settings.update');

//
// Dashboards
//
$app->get('/statusdashboard', ['\Xibo\Controller\StatusDashboard', 'displayPage'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['dashboard.status']))
    ->setName('statusdashboard.view');

$app->get('/mediamanager', ['\Xibo\Controller\MediaManager', 'getLibraryUsage'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['dashboard.media.manager']))
    ->setName('mediamanager.view');

$app->get('/playlistdashboard', ['\Xibo\Controller\PlaylistDashboard', 'displayPage'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['dashboard.playlist']))
    ->setName('playlistdashboard.view');

$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/playlistdashboard/data', ['\Xibo\Controller\PlaylistDashboard', 'grid'])
        ->setName('playlistdashboard.search');
    $group->get('/playlistdashboard/{id}', ['\Xibo\Controller\PlaylistDashboard', 'show'])
        ->setName('playlistdashboard.show');
})->add(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['dashboard.playlist']));

//
// Display Manage
//
$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/display/manage/{id}', ['\Xibo\Controller\Display', 'displayManage'])
        ->setName('display.manage.json');
    $group->get('/stats/data/bandwidth', ['\Xibo\Controller\Stats', 'bandwidthData'])
        ->setName('stats.bandwidth.data.json');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['displays.view']));

//
// Add Display
//
$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/display/licence/usage', ['\Xibo\Controller\DisplayClaim', 'licenceUsage'])
        ->setName('display.licence.usage');
    $group->get('/display/connect/details', ['\Xibo\Controller\DisplayClaim', 'connectDetails'])
        ->setName('display.connect.details');
    $group->post('/display/connect/code', ['\Xibo\Controller\DisplayClaim', 'connectCode'])
        ->setName('display.connect.code');
    $group->get('/display/connect/status', ['\Xibo\Controller\DisplayClaim', 'connectStatus'])
        ->setName('display.connect.status');
    $group->put('/display/connect/{code:[A-Za-z0-9]{4}}', ['\Xibo\Controller\DisplayClaim', 'connectApply'])
        ->setName('display.connect.apply');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['displays.add']));

//
// notification
//
$app->get('/notification/mynotifications', ['\Xibo\Controller\Notification', 'myNotifications'])
    ->setName('notification.mynotifications');
$app->get('/notification/interrupt', ['\Xibo\Controller\Notification', 'getInterrupt'])
    ->setName('notification.interrupt.list');
$app->put('/notification/markAsRead', ['\Xibo\Controller\Notification', 'markAsRead'])
    ->setName('notification.markAsRead');
$app->get('/notification/export/{id}', ['\Xibo\Controller\Notification', 'exportAttachment'])
    ->setName('notification.exportattachment');

//
// Reports
//
$app->get('/report/available', ['\Xibo\Controller\Stats', 'availableReports'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['report.view']))
    ->setName('report.available');

//
// Saved reports
//
$app->group('', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/report/savedreport/{id}/report/{name}/open', ['\Xibo\Controller\SavedReport', 'savedReportOpen'])
        ->setName('savedreport.open');
    $group->get('/report/savedreport/{id}/report/{name}/export', ['\Xibo\Controller\SavedReport', 'savedReportExport'])
        ->setName('savedreport.export');
    $group->get(
        '/report/savedreport/{id}/report/{name}/convert',
        ['\Xibo\Controller\SavedReport', 'savedReportConvert']
    )->setName('savedreport.convert');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['report.saving']));

$app->get('/stats/export/count', ['\Xibo\Controller\Stats', 'exportStatsCount'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['proof-of-play']))
    ->setName('stats.export.count');

//
// Developer
//
$app->delete('/developer/template/{id}', ['\Xibo\Controller\Developer', 'templateDelete'])
    ->setName('developer.templates.delete')
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['developer.delete']));

$app->group('', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/developer/template/datatypes', ['\Xibo\Controller\Developer', 'getAvailableDataTypes'])
        ->setName('developer.templates.datatypes.search');
    $group->get('/developer/template', ['\Xibo\Controller\Developer', 'templateGrid'])
        ->setName('developer.templates.search');
    $group->get('/developer/template/{id}', ['\Xibo\Controller\Developer', 'searchById'])
        ->setName('developer.templates.search.id');
    $group->post('/developer/template', ['\Xibo\Controller\Developer', 'templateAdd'])
        ->setName('developer.templates.add');
    $group->put('/developer/template/{id}', ['\Xibo\Controller\Developer', 'templateEdit'])
        ->setName('developer.templates.edit');
    $group->get('/developer/template/{id}/export', ['\Xibo\Controller\Developer', 'templateExport'])
        ->setName('developer.templates.export');
    $group->post('/developer/template/import', ['\Xibo\Controller\Developer', 'templateImport'])
        ->setName('developer.templates.import');
    $group->post('/developer/template/{id}/copy', ['\Xibo\Controller\Developer', 'templateCopy'])
        ->setName('developer.templates.copy');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['developer.edit']));
