<?php
/**
 * Copyright (C) 2022 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

use Psr\Container\ContainerInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Views\Twig;
use Xibo\Storage\PdoStorageService;
use Xibo\Support\Exception\InstallationError;

$app->get('/login', function(Request $request, Response $response) use ($app) {
    // Just a helper to get correct login route url
    return $response->withStatus(404, 'This function should not be called from install/.');
})->setName('login');

$app->map(['GET', 'POST'],'/{step}', function(Request $request, Response $response, $step = 1) use ($app) {
    session_start();

    $container = $app->getContainer();
    $routeParser = $app->getRouteCollector()->getRouteParser();

    $container->set('store', function(ContainerInterface $container) {
        return (new PdoStorageService($container->get('logService')));
    });

    $container->get('configService')->setDependencies($container->get('store'), $container->get('rootUri'));

    /** @var Twig $view */
    $view = $container->get('view');

    $twigEnvironment = $view->getEnvironment();
    $twigEnvironment->enableAutoReload();

    $container->get('logService')->info('Installer Step %s', $step);

    $install = new \Xibo\Helper\Install($container);
    $settingsExists = file_exists(PROJECT_ROOT . '/web/settings.php');
    $template = '';
    $data = [];

    switch ($step) {
        case 1:
            if ($settingsExists) {
                throw new InstallationError(__('The CMS has already been installed. Please contact your system administrator.'));
            }
            unset($_SESSION['error']);
            // Welcome to the installer (this should only show once)
            // Checks environment
            $template = 'install-step1';
            $data = $install->step1();
            break;

        case 2:
            if ($settingsExists) {
                throw new InstallationError(__('The CMS has already been installed. Please contact your system administrator.'));
            }

            unset($_SESSION['error']);
            // Collect details about the database
            $template = 'install-step2';
            $data = $install->step2();
            break;

        case 3:
            if ($settingsExists) {
                throw new InstallationError(__('The CMS has already been installed. Please contact your system administrator.'));
            }

            // Check and validate DB details
            if (defined('MAX_EXECUTION') && MAX_EXECUTION) {
                $app->getContainer()->get('logService')->info('Setting unlimited max execution time.');
                set_time_limit(0);
            }
            unset($_SESSION['error']);
            try {
                $install->step3($request, $response);
                // Redirect to step 4
                return $response->withRedirect($routeParser->urlFor('install', ['step' => 4]));
            } catch (InstallationError $e) {
                $container->get('logService')->error('Installation Exception on Step %d: %s', $step, $e->getMessage());

                $_SESSION['error'] = $e->getMessage();

                // Add our object properties to the flash vars, so we render the form with them set
                foreach (\Xibo\Helper\ObjectVars::getObjectVars($install) as $key => $value) {
                    $_SESSION[$key] = $value;
                }

                // Reload step 2
                $template = 'install-step2';
                $data = $install->step2();
            }
            break;

        case 4:
            // DB installed and we are ready to collect some more details
            // We should get the admin username and password
            $data = $install->step4();
            $template = 'install-step4';
            break;

        case 5:
            unset($_SESSION['error']);
            // Create a user account
            try {
                $install->step5($request, $response);
                return $response->withRedirect($routeParser->urlFor('install', ['step' => 6]));
            } catch (InstallationError $e) {
                $container->get('logService')->error('Installation Exception on Step %d: %s', $step, $e->getMessage());

                $_SESSION['error'] = $e->getMessage();

                // Reload step 4
                $template = 'install-step4';
                $data = $install->step4();
            }
            break;

        case 6:
            $template = 'install-step6';
            $data = $install->step6();
            break;

        case 7:
            unset($_SESSION['error']);
            // Create a user account
            try {
                $install->step7($request, $response);

                // Redirect to login
                // This will always be one folder down
                $login = str_replace('/install', '', $routeParser->urlFor('login'));

                $container->get('logService')->info('Installation Complete. Redirecting to %s', $login);
                session_destroy();
                return $response->withRedirect($login);
            } catch (InstallationError $e) {
                $container->get('logService')->error('Installation Exception on Step %d: %s', $step, $e->getMessage());

                $_SESSION['error'] = $e->getMessage();

                // Reload step 6
                $template = 'install-step6';
                $data = $install->step6();
            }
            break;
    }

    // Add in our session object
    $data['session'] = $_SESSION;

    // Render
    return $view->render($response, $template . '.twig', $data);

})->setName('install');
