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

    if ($user->newUserWizard == 0) {
        $controller = new \Xibo\Controller\Login($app);
        $controller->userWelcome();

        // We've seen it
        $user->newUserWizard = 1;
    }
    else {
        \Xibo\Helper\Log::debug('Showing the homepage: %s', $user->homePage);
        switch ($user->homePage) {

            case 'xmediamanager':

                break;

            case 'statusdashboard':
                $controller = new \Xibo\Controller\StatusDashboard($app);
                $controller->displayPage();
                break;

            case 'xdashboard':

                break;

            default:
                $controller = new \Xibo\Controller\Layout($app);
                $controller->displayPage();
        }
    }

    if ($controller == null)
        throw new \Psr\Log\InvalidArgumentException(__('Homepage not set correctly'));

    $controller->render();

})->setName('home');

// Login Form
$app->get('/login', function () use ($app) {

    // Login form
    $controller = new \Xibo\Controller\Login($app);
    $controller->setNotAutomaticFullPage();
    $controller->render('loginForm');

})->setName('login');

// Login Request
$app->post('/login', function () use ($app) {

    // Capture the prior route (if there is one)
    $priorRoute = ($app->request()->post('priorPage'));

    try {
        $controller = new \Xibo\Controller\Login($app);
        $controller->login();

        \Xibo\Helper\Log::info('%s user logged in.', $app->user->userName);

        $app->redirect($app->request->getRootUri() . (($priorRoute == '' || stripos($priorRoute, 'login')) ? '' : $priorRoute));
    }
    catch (\Xibo\Exception\AccessDeniedException $e) {
        \Xibo\Helper\Log::warning($e->getMessage());
        $app->flash('login_message', __('Username or Password incorrect'));
        $app->flash('priorRoute', $priorRoute);
    }
    catch (\Xibo\Exception\FormExpiredException $e) {
        $app->flash('priorRoute', $priorRoute);
    }
    $app->redirectTo('login');
});

// Logout Request
$app->get('/logout', function () use ($app) {
    $controller = new \Xibo\Controller\Login($app);
    $controller->logout();
    $app->redirectTo('login');
})->setName('logout');

// Token Exchange
$app->post('/ExchangeGridTokenForFormToken', function () use ($app) {
    $controller = new \Xibo\Controller\Login($app);
    $controller->ExchangeGridTokenForFormToken();
    $controller->render();
});

// Ping pong route
$app->get('/login/ping', function () use ($app) {
    $app->session->refreshExpiry = false;
    $controller = new \Xibo\Controller\Login($app);
    $controller->PingPong();
    $controller->render();
})->setName('ping');

// Layouts
$app->get('/layout/view', function () use ($app) {
    // This is a full page
    $controller = new \Xibo\Controller\Layout($app);
    $controller->displayPage();
    $controller->render();
});

$app->get('/layout/add', function () use ($app) {
    $controller = new \Xibo\Controller\Layout($app);
    $controller->AddForm();
    $controller->render();
})->setName('layoutAddForm');

// Users
$app->get('/user/welcome', function () use ($app) {
    $controller = new \Xibo\Controller\Login($app);
    $controller->userWelcome();
    $controller->render();
})->setName('welcomeWizard');

$app->get('/user/password/view', function () use ($app) {

})->setName('userChangePassword');

// Stats
$app->get('/stats', function () use ($app) {

})->name('stats');