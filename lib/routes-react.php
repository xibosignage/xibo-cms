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

/**
 * Routes for the React/JSON frontend only.
 * Loaded by web/json/index.php — NOT exposed via the API entrypoint.
 */

global $app;

use Slim\Routing\RouteCollectorProxy;
use Xibo\Middleware\FeatureAuth;
use Xibo\Middleware\SuperAdminAuth;

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
// Reports
//
$app->get('/report/available', ['\Xibo\Controller\Stats', 'availableReports'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['report.view']))
    ->setName('report.available');

$app->get('/stats/export/count', ['\Xibo\Controller\Stats', 'exportStatsCount'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['proof-of-play']))
    ->setName('stats.export.count');

//
// Developer
//
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
    $group->delete('/developer/template/{id}', ['\Xibo\Controller\Developer', 'templateDelete'])
        ->setName('developer.templates.delete');
    $group->get('/developer/template/{id}/export', ['\Xibo\Controller\Developer', 'templateExport'])
        ->setName('developer.templates.export');
    $group->post('/developer/template/import', ['\Xibo\Controller\Developer', 'templateImport'])
        ->setName('developer.templates.import');
    $group->post('/developer/template/{id}/copy', ['\Xibo\Controller\Developer', 'templateCopy'])
        ->setName('developer.templates.copy');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['developer.edit']));
