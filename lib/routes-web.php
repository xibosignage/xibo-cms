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
        $controller->setNoOutput();
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

//
// upgrade
//
$app->get('/update', function () use ($app) {
    $controller = new \Xibo\Controller\Upgrade($app);
    $controller->displayPage();
    $controller->render();
})->name('upgradeView');

//
// schedule
//
$app->get('/schedule/view', function () use ($app) {

})->name('scheduleView');

//
// layouts
//
$app->get('/layout/view', function () use ($app) {
    // This is a full page
    $controller = new \Xibo\Controller\Layout($app);
    $controller->displayPage();
    $controller->render();
})->name('layoutView');

$app->get('/layout/designer/:id', function ($id) use ($app) {
    // This is a full page
    $controller = new \Xibo\Controller\Layout($app);
    $controller->displayDesigner($id);
})->name('layoutDesigner');

$app->get('/layout/status/:id', function($id) use ($app) {
    $controller = new \Xibo\Controller\Layout($app);
    $controller->LayoutStatus();
})->setName('layoutStatus');

//
// content
//
$app->get('/content/view', function () use ($app) {

})->name('contentView');

//
// display
//
$app->get('/display/view', function () use ($app) {

})->name('displayView');

//
// user
//
$app->get('/user/view', function () use ($app) {

})->name('userView');

//
// log
//
$app->get('/log/view', function () use ($app) {
    $controller = new \Xibo\Controller\Log($app);
    $controller->displayPage();
    $controller->render();
})->name('logView');

$app->get('/log/delete', function () use ($app) {
    $controller = new \Xibo\Controller\Log($app);
    $controller->TruncateForm();
})->name('logTruncateForm');

//
// campaign
//
$app->get('/campaign/view', function () use ($app) {

})->name('campaignView');

//
// template
//
$app->get('/template/view', function () use ($app) {

})->name('templateView');

//
// resolution
//
$app->get('/resolution/view', function () use ($app) {

})->name('resolutionView');

//
// dataset
//
$app->get('/dataset/view', function () use ($app) {

})->name('datasetView');

//
// displaygroup
//
$app->get('/displaygroup/view', function () use ($app) {

})->name('displaygroupView');

//
// displayprofile
//
$app->get('/displayprofile/view', function () use ($app) {

})->name('displayprofileView');

//
// group
//
$app->get('/group/view', function () use ($app) {

})->name('groupView');

//
// admin
//
$app->get('/admin/view', function () use ($app) {

})->name('adminView');

//
// oauth
//
$app->get('/oauth/view', function () use ($app) {

})->name('oauthView');

//
// module
//
$app->get('/module/view', function () use ($app) {

})->name('moduleView');

//
// transition
//
$app->get('/transition/view', function () use ($app) {

})->name('transitionView');

//
// sessions
//
$app->get('/sessions/view', function () use ($app) {

})->name('sessionsView');

//
// fault
//
$app->get('/fault/view', function () use ($app) {

})->name('faultView');

//
// license
//
$app->get('/license/view', function () use ($app) {
    $controller = new \Xibo\Controller\Login($app);
    $controller->render('About');
})->name('licenseView');

//
// help
//
$app->get('/help/view', function () use ($app) {

})->name('helpView');
//

//
$app->get('/layout/add', function () use ($app) {
    $controller = new \Xibo\Controller\Layout($app);
    $controller->AddForm();
})->setName('layoutAddForm');

//
// Users
//
$app->get('/user/welcome', function () use ($app) {
    $controller = new \Xibo\Controller\Login($app);
    $controller->userWelcome();
})->setName('welcomeWizard');

// Change Password
$app->get('/user/password/view', function () use ($app) {

})->setName('userChangePassword');

//
// Stats
//
$app->get('/stats', function () use ($app) {

})->name('statsView');