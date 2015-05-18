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

$app->get('/clock', function() use ($app) {
    $app->session->refreshExpiry = false;
    $controller = new \Xibo\Controller\Clock($app);
    $controller->GetClock();
    $controller->render();
})->setName('clock');

//
// Layouts
//
$app->get('/layout', '\Xibo\Controller\Layout:LayoutGrid')->setName('layoutSearch');

$app->get('/layout/:id', function($id) use ($app) {
    $controller = new \Xibo\Controller\Layout($app);
    $controller->EditForm();
    $controller->render();
})->setName('layoutGet');

$app->put('/layout', function() use ($app) {
    $controller = new \Xibo\Controller\Layout($app);
    $controller->add();
})->setName('layoutAdd');

$app->post('/layout/:id', function($id) use ($app) {
    // Update the Layout
})->setName('layoutUpdate');

//
// Campaign
//
$app->get('/campaign', function() use ($app) {

})->name('campaignSearch');

//
// Log
//
$app->get('/log', function () use ($app) {
    $controller = new \Xibo\Controller\Log($app);
    $controller->Grid();
    $controller->render();
})->name('logSearch');

$app->delete('/log', function () use ($app) {
    $controller = new \Xibo\Controller\Log($app);
    $controller->Truncate();
    $controller->render();
})->name('logTruncate');