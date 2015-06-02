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
        \Xibo\Helper\Log::debug('Showing the homepage: %s', $user->homePageId);
        $app->redirectTo($user->homePage . '.view');
    }

    if ($controller == null)
        throw new InvalidArgumentException(__('Homepage not set correctly'));

    $controller->render();

})->setName('home');

// Dashboards
$app->get('/dashboard/status', '\Xibo\Controller\StatusDashboard:displayPage')->name('statusdashboard.view');
$app->get('/dashboard/icon', '\Xibo\Controller\Dashboard:displayPage')->name('dashboard.view');
$app->get('/dashboard/media', '\Xibo\Controller\MediaManager:displayPage')->name('mediamanager.view');

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

        try {
            $redirect = $app->urlFor(($priorRoute == '' || stripos($priorRoute, 'login')) ? 'home' : $priorRoute);
        }
        catch (RuntimeException $e) {
            $redirect = $app->urlFor('home');
        }

        \Xibo\Helper\Log::debug('Redirect to %s', $redirect);
        $app->redirect($redirect);
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
$app->get('/update', '\Xibo\Controller\Upgrade:displayPage')->name('upgrade.view');

//
// schedule
//
$app->get('/schedule/view', '\Xibo\Controller\Schedule:displayPage')->name('schedule.view');
$app->get('/schedule/form/now/:id', '\Xibo\Controller\Schedule:scheduleNowForm')->name('schedule.now.form');
$app->get('/schedule/form/add', '\Xibo\Controller\Schedule:addForm')->name('schedule.add.form');

//
// layouts
//
$app->get('/layout/view', '\Xibo\Controller\Layout:displayPage')->name('layout.view');
$app->get('/layout/designer/:id', '\Xibo\Controller\Layout:displayDesigner')->name('layout.designer');
$app->get('/layout/status/:id', '\Xibo\Controller\Layout:LayoutStatus')->setName('layout.status');
$app->get('/layout/preview/:id', '\Xibo\Controller\Preview:render')->name('layout.preview');
$app->get('/layout/export/:id', '\Xibo\Controller\Layout:export')->name('layout.export');

// Layout forms
$app->get('/layout/form/add', '\Xibo\Controller\Layout:addForm')->name('layout.add.form');
$app->get('/layout/form/edit/:id', '\Xibo\Controller\Layout:editForm')->name('layout.edit.form');
$app->get('/layout/form/copy/:id', '\Xibo\Controller\Layout:copyForm')->name('layout.copy.form');
$app->get('/layout/form/delete/:id', '\Xibo\Controller\Layout:deleteForm')->name('layout.delete.form');
$app->get('/layout/form/retire/:id', '\Xibo\Controller\Layout:retireForm')->name('layout.retire.form');
$app->get('/layout/form/import', '\Xibo\Controller\Layout:retireForm')->name('layout.import.form');

//
// library
//
$app->get('/library/view', '\Xibo\Controller\Library:displayPage')->name('library.view');
$app->get('/library/form/add', '\Xibo\Controller\Library:fileUploadForm')->name('library.add.form');
$app->get('/library/form/tidy', '\Xibo\Controller\Library:tidyLibraryForm')->name('library.tidy.form');

//
// display
//
$app->get('/display/view', '\Xibo\Controller\Display:displayPage')->name('display.view');

//
// user
//
$app->get('/user/view', '\Xibo\Controller\User:displayPage')->name('user.view');
$app->get('/user/welcome', '\Xibo\Controller\Login:userWelcome')->name('welcome.wizard');
$app->get('/user/apps', '\Xibo\Controller\User:myApplications')->name('user.applications');
$app->get('/user/form/password', '\Xibo\Controller\User:changePasswordForm')->name('user.change.password.form');
$app->get('/user/form/add', '\Xibo\Controller\User:addForm')->name('user.add.form');
$app->get('/user/form/edit/:id', '\Xibo\Controller\User:editForm')->name('user.edit.form');
$app->get('/user/form/delete/:id', '\Xibo\Controller\User:deleteForm')->name('user.delete.form');
$app->get('/user/form/permissions/:entity/:id', '\Xibo\Controller\User:permissionsForm')->name('user.permissions.form');

//
// log
//
$app->get('/log/view', '\Xibo\Controller\Log:displayPage')->name('log.view');
$app->get('/log/delete', '\Xibo\Controller\Log:truncateForm')->name('log.truncate.form');

//
// campaign
//
$app->get('/campaign/view', '\Xibo\Controller\Campaign:displayPage')->name('campaign.view');
$app->get('/campaign/form/add', '\Xibo\Controller\Campaign:addForm')->name('campaign.add.form');
$app->get('/campaign/form/edit/:id', '\Xibo\Controller\Campaign:editForm')->name('campaign.edit.form');
$app->get('/campaign/form/copy/:id', '\Xibo\Controller\Campaign:copyForm')->name('campaign.copy.form');
$app->get('/campaign/form/delete/:id', '\Xibo\Controller\Campaign:deleteForm')->name('campaign.delete.form');
$app->get('/campaign/form/retire/:id', '\Xibo\Controller\Campaign:retireForm')->name('campaign.retire.form');

//
// template
//
$app->get('/template/view', '\Xibo\Controller\Template:displayPage')->name('template.view');

//
// resolution
//
$app->get('/resolution/view', '\Xibo\Controller\Resolution:displayPage')->name('resolution.view');
$app->get('/resolution/form/add', '\Xibo\Controller\Resolution:addForm')->name('resolution.add.form');

//
// dataset
//
$app->get('/dataset/view', '\Xibo\Controller\DataSet:displayPage')->name('dataset.view');
$app->get('/dataset/form/add', '\Xibo\Controller\DataSet:addForm')->name('dataSet.add.form');

//
// displaygroup
//
$app->get('/displaygroup/view', '\Xibo\Controller\DisplayGroup:displayPage')->name('displaygroup.view');
$app->get('/displaygroup/form/add', '\Xibo\Controller\DisplayGroup:addForm')->name('displayGroup.add.form');

//
// displayprofile
//
$app->get('/displayprofile/view', '\Xibo\Controller\DisplayProfile:displayPage')->name('displayprofile.view');
$app->get('/displayprofile/form/add', '\Xibo\Controller\DisplayProfile:addForm')->name('displayProfile.add.form');

//
// group
//
$app->get('/group/view', '\Xibo\Controller\UserGroup:displayPage')->name('group.view');
$app->get('/group/form/add', '\Xibo\Controller\UserGroup:addForm')->name('group.add.form');
$app->get('/group/form/edit/:id', '\Xibo\Controller\UserGroup:editForm')->name('group.edit.form');
$app->get('/group/form/delete/:id', '\Xibo\Controller\UserGroup:deleteForm')->name('group.delete.form');
$app->get('/group/form/acl/:entity/:id', '\Xibo\Controller\UserGroup:aclForm')->name('group.acl.form');
$app->get('/group/form/members/:id', '\Xibo\Controller\UserGroup:membersForm')->name('group.members.form');

//
// admin
//
$app->get('/admin/view', '\Xibo\Controller\Settings:displayPage')->name('admin.view');
$app->get('/admin/form/export', '\Xibo\Controller\Settings:exportForm')->name('settings.export.form');
$app->get('/admin/form/import', '\Xibo\Controller\Settings:importForm')->name('settings.import.form');
$app->get('/admin/form/tidy', '\Xibo\Controller\Settings:tidyLibraryForm')->name('settings.libraryTidy.form');

//
// oauth
//
$app->get('/applications/view', '\Xibo\Controller\Applications:displayPage')->name('applications.view');
$app->get('/applications/data/activity', '\Xibo\Controller\Applications:viewActivity')->name('application.view.activity');
$app->get('/applications/form/add', '\Xibo\Controller\Applications:addForm')->name('application.add.form');

//
// module
//
$app->get('/module/view', '\Xibo\Controller\Module:displayPage')->name('module.view');
$app->get('/module/install/:id', '\Xibo\Controller\Module:install')->name('module.install');
$app->get('/module/form/verify', '\Xibo\Controller\Module:verifyForm')->name('module.verify.form');

//
// transition
//
$app->get('/transition/view', '\Xibo\Controller\Transition:displayPage')->name('transition.view');
$app->get('/transition/form/edit/:id', '\Xibo\Controller\Transition:editForm')->name('transition.edit.form');

//
// sessions
//
$app->get('/sessions/view', '\Xibo\Controller\Sessions:displayPage')->name('sessions.view');

//
// fault
//
$app->get('/fault/view', '\Xibo\Controller\Fault:displayPage')->name('fault.view');

//
// license
//
$app->get('/license/view', '\Xibo\Controller\Login:about')->name('license.view');

//
// help
//
$app->get('/help/view', '\Xibo\Controller\Help:displayPage')->name('help.view');
$app->get('/help/form/add', '\Xibo\Controller\Help:addForm')->name('help.add.form');

//
// Stats
//
$app->get('/stats', '\Xibo\Controller\Stats:displayPage')->name('stats.view');
$app->get('/stats/form/export', '\Xibo\Controller\Stats:exportForm')->name('stats.export.form');

//
// Audit Log
//
$app->get('/audit', '\Xibo\Controller\AuditLog:displayPage')->name('auditlog.view');
