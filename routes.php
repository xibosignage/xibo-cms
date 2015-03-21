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

$app->get('/clock', function() use ($app) {
    $controller = new \Xibo\Controller\Clock($app);
    $controller->GetClock();
    $controller->render();
})->name('clock');

$app->get('/layouts', function() use ($app) {
    $controller = new \Xibo\Controller\Layout($app);
    $controller->render('LayoutGrid');
})->name('layoutSearch');

$app->get('/layouts/:id', function($id) use ($app) {
    $app->render(200, array('layout' => \Xibo\Factory\LayoutFactory::getById($id)));
})->name('layoutGet');

$app->post('/layouts/:id', function($id) use ($app) {
    // Update the Layout
})->name('layoutUpdate');