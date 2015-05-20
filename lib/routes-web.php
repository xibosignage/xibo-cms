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
        throw new InvalidArgumentException(__('Homepage not set correctly'));

    $controller->render();

})->setName('home');

// Login Form
$app->get('/login', '\Xibo\Controller\Login:loginForm')->name('login');

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
$app->get('/logout', '\Xibo\Controller\Login:logout')->name('logout');

// Ping pong route
$app->get('/login/ping', '\Xibo\Controller\Login:PingPong')->name('ping');

//
// upgrade
//
$app->get('/update', '\Xibo\Controller\Upgrade:displayPage')->name('upgradeView');

//
// schedule
//
$app->get('/schedule/view', '\Xibo\Controller\Schedule:displayPage')->name('scheduleView');
$app->get('/schedule/form/now/:id', '\Xibo\Controller\Schedule:scheduleNowForm')->name('scheduleNowForm');

//
// layouts
//
$app->get('/layout/view', '\Xibo\Controller\Layout:displayPage')->name('layoutView');
$app->get('/layout/designer/:id', '\Xibo\Controller\Layout:displayDesigner')->name('layoutDesigner');
$app->get('/layout/status/:id', '\Xibo\Controller\Layout:LayoutStatus')->setName('layoutStatus');
$app->get('/layout/preview/:id', '\Xibo\Controller\Preview:render')->name('layoutPreview');
$app->get('/layout/export/:id', '\Xibo\Controller\Layout:export')->name('layoutExport');

// Layout forms
$app->get('/layout/form/add', '\Xibo\Controller\Layout:addForm')->name('layoutAddForm');
$app->get('/layout/form/edit/:id', '\Xibo\Controller\Layout:editForm')->name('layoutEditForm');
$app->get('/layout/form/copy/:id', '\Xibo\Controller\Layout:copyForm')->name('layoutCopyForm');
$app->get('/layout/form/delete/:id', '\Xibo\Controller\Layout:deleteForm')->name('layoutDeleteForm');
$app->get('/layout/form/retire/:id', '\Xibo\Controller\Layout:retireForm')->name('layoutRetireForm');
$app->get('/layout/form/import', '\Xibo\Controller\Layout:retireForm')->name('layoutImportForm');

//
// library
//
$app->get('/library/view', '\Xibo\Controller\Library:displayPage')->name('libraryView');
$app->get('/library/form/add', '\Xibo\Controller\Library:fileUploadForm')->name('libraryAddForm');
$app->get('/library/form/tidy', '\Xibo\Controller\Library:tidyLibraryForm')->name('libraryTidyForm');

//
// display
//
$app->get('/display/view', '\Xibo\Controller\Display:displayPage')->name('displayView');

//
// user
//
$app->get('/user/view', '\Xibo\Controller\User:displayPage')->name('userView');
$app->get('/user/welcome', '\Xibo\Controller\Login:userWelcome')->name('welcomeWizard');
$app->get('/user/apps', '\Xibo\Controller\User:myApplications')->name('userMyApplications');
$app->get('/user/password/view', '\Xibo\Controller\User:changePassword')->name('userChangePassword');
$app->get('/user/form/:entity/:id', '\Xibo\Controller\User:permissionsForm')->name('permissionsForm');
$app->get('/user/form/add', '\Xibo\Controller\User:addForm')->name('userAddForm');

//
// log
//
$app->get('/log/view', '\Xibo\Controller\Log:displayPage')->name('logView');
$app->get('/log/delete', '\Xibo\Controller\Log:truncateForm')->name('logTruncateForm');

//
// campaign
//
$app->get('/campaign/view', '\Xibo\Controller\Campaign:displayPage')->name('campaignView');
$app->get('/campaign/form/add', '\Xibo\Controller\Campaign:addForm')->name('campaignAddForm');
$app->get('/campaign/form/edit/:id', '\Xibo\Controller\Campaign:editForm')->name('campaignEditForm');
$app->get('/campaign/form/copy/:id', '\Xibo\Controller\Campaign:copyForm')->name('campaignCopyForm');
$app->get('/campaign/form/delete/:id', '\Xibo\Controller\Campaign:deleteForm')->name('campaignDeleteForm');
$app->get('/campaign/form/retire/:id', '\Xibo\Controller\Campaign:retireForm')->name('campaignRetireForm');

//
// template
//
$app->get('/template/view', '\Xibo\Controller\Template:displayPage')->name('templateView');

//
// resolution
//
$app->get('/resolution/view', '\Xibo\Controller\Resolution:displayPage')->name('resolutionView');
$app->get('/resolution/form/add', '\Xibo\Controller\Resolution:addForm')->name('resolutionAddForm');

//
// dataset
//
$app->get('/dataset/view', '\Xibo\Controller\DataSet:displayPage')->name('datasetView');
$app->get('/dataset/form/add', '\Xibo\Controller\DataSet:addForm')->name('dataSetAddForm');

//
// displaygroup
//
$app->get('/displaygroup/view', '\Xibo\Controller\DisplayGroup:displayPage')->name('displaygroupView');
$app->get('/displaygroup/form/add', '\Xibo\Controller\DisplayGroup:addForm')->name('displayGroupAddForm');

//
// displayprofile
//
$app->get('/displayprofile/view', '\Xibo\Controller\DisplayProfile:displayPage')->name('displayprofileView');
$app->get('/displayprofile/form/add', '\Xibo\Controller\DisplayProfile:addForm')->name('displayProfileAddForm');

//
// group
//
$app->get('/group/view', '\Xibo\Controller\UserGroup:displayPage')->name('groupView');
$app->get('/group/form/add', '\Xibo\Controller\UserGroup:addForm')->name('userGroupAddForm');

//
// admin
//
$app->get('/admin/view', '\Xibo\Controller\Settings:displayPage')->name('adminView');

//
// oauth
//
$app->get('/applications/view', '\Xibo\Controller\Applications:displayPage')->name('applicationsView');
$app->get('/applications/data/activity', '\Xibo\Controller\Applications:viewActivity')->name('applicationViewActivity');
$app->get('/applications/form/add', '\Xibo\Controller\Applications:addForm')->name('applicationAddForm');

//
// module
//
$app->get('/module/view', '\Xibo\Controller\Module:displayPage')->name('moduleView');
$app->get('/module/install/:id', '\Xibo\Controller\Module:install')->name('moduleInstall');
$app->get('/module/form/verify', '\Xibo\Controller\Module:verifyForm')->name('moduleVerifyForm');

//
// transition
//
$app->get('/transition/view', '\Xibo\Controller\Transition:displayPage')->name('transitionView');

//
// sessions
//
$app->get('/sessions/view', '\Xibo\Controller\Sessions:displayPage')->name('sessionsView');

//
// fault
//
$app->get('/fault/view', '\Xibo\Controller\Fault:displayPage')->name('faultView');

//
// license
//
$app->get('/license/view', '\Xibo\Controller\Login:about')->name('licenseView');

//
// help
//
$app->get('/help/view', '\Xibo\Controller\Help:displayPage')->name('helpView');

//
// Stats
//
$app->get('/stats', '\Xibo\Controller\Stats:displayPage')->name('statsView');
$app->get('/stats/form/export', '\Xibo\Controller\Stats:exportForm')->name('statsExportForm');