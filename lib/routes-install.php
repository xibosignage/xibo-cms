<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (routes-install.php)
 */

$app->map('/(:step)', function($step = 1) use($app) {

    $app->logService->info('Installer Step %s', $step);

    $install = new \Xibo\Helper\Install($app->sanitizerService);
    $settingsExists = $app->settingsExists;
    $template = '';
    $data = [];

    switch ($step) {

        case 1:
            if ($settingsExists)
                throw new \Xibo\Exception\InstallationError(__('The CMS has already been installed. Please contact your system administrator.'));

            // Welcome to the installer (this should only show once)
            // Checks environment
            $template = 'install-step1';
            $data = $install->Step1();
            break;

        case 2:
            if ($settingsExists)
                throw new \Xibo\Exception\InstallationError(__('The CMS has already been installed. Please contact your system administrator.'));

            // Collect details about the database
            $template = 'install-step2';
            $data = $install->Step2();
            break;

        case 3:
            if ($settingsExists)
                throw new \Xibo\Exception\InstallationError(__('The CMS has already been installed. Please contact your system administrator.'));

            // Check and validate DB details
            if (defined('MAX_EXECUTION') && MAX_EXECUTION) {
                $app->logService->info('Setting unlimited max execution time.');
                set_time_limit(0);
            }

            try {
                // We won't have a storageservice registered with the app yet,
                // so we create one for this step.
                $install->Step3((new \Xibo\Storage\PdoStorageService($app->logService)));

                // Redirect to step 4
                $app->redirectTo('install', ['step' => 4]);
            }
            catch (\Xibo\Exception\InstallationError $e) {

                $app->logService->error('Installation Exception on Step %d: %s', $step, $e->getMessage());

                $app->flashNow('error', $e->getMessage());

                // Add our object properties to the flash vars, so we render the form with them set
                foreach (\Xibo\Helper\ObjectVars::getObjectVars($install) as $key => $value) {
                    $app->flashNow($key, $value);
                }

                // Reload step 2
                $template = 'install-step2';
                $data = $install->Step2();
            }
            break;

        case 4:
            // DB installed and we are ready to collect some more details
            // We should get the admin username and password
            $data = $install->Step4();
            $template = 'install-step4';
            break;

        case 5:
            // Create a user account
            try {
                $install->Step5($app->store);

                // Redirect to step 6
                $app->redirectTo('install', ['step' => 6]);
            }
            catch (\Xibo\Exception\InstallationError $e) {

                $app->logService->error('Installation Exception on Step %d: %s', $step, $e->getMessage());

                $app->flashNow('error', $e->getMessage());

                // Reload step 4
                $template = 'install-step4';
                $data = $install->Step4();
            }
            break;

        case 6:
            $template = 'install-step6';
            $data = $install->Step6();
            break;

        case 7:
            // Create a user account
            try {
                $template = 'install-step7';
                $install->Step7($app->store);

                // Redirect to login
                // This will always be one folder down
                $login = str_replace('/install', '', $app->urlFor('login'));

                $app->logService->info('Installation Complete. Redirecting to %s', $login);

                $app->redirect($login);
            }
            catch (\Xibo\Exception\InstallationError $e) {
                $app->logService->error('Installation Exception on Step %d: %s', $step, $e->getMessage());

                $app->flashNow('error', $e->getMessage());

                // Reload step 6
                $template = 'install-step6';
                $data = $install->Step6();
            }
            break;
    }

    // Render
    $app->render($template . '.twig', $data);

})->via('GET', 'POST')->name('install');

$app->get('/login', function() use ($app) {
    // Just a helper to get correct login route url
    $app->halt(404, __('This function should not be called from install/.'));
})->name('login');