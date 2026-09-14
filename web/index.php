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

use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Slim\Views\TwigMiddleware;
use Xibo\Factory\ContainerFactory;

DEFINE('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

require PROJECT_ROOT . '/vendor/autoload.php';

// Enable/Disable logging
if (\Xibo\Helper\Environment::isDevMode() || \Xibo\Helper\Environment::isForceDebugging()) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

if (!file_exists('settings.php')) {
    die('Not configured: Please create web/settings.php — see MANUAL_INSTALL.md');
}

// Check that the cache folder if writeable - if it isn't we're in big trouble
if (!is_writable(PROJECT_ROOT . '/cache')) {
    die('Installation Error: Cannot write files into the Cache Folder');
}

// Create the container for dependency injection.
try {
    $container = ContainerFactory::create();
} catch (Exception $e) {
    die($e->getMessage());
}

// Configure Monolog
$container->set('logger', function (ContainerInterface $container) {
    $logger = new Logger('WEB');

    $uidProcessor = new UidProcessor();
    // db
    $dbhandler  =  new \Xibo\Helper\DatabaseLogHandler();

    $logger->pushProcessor($uidProcessor);
    $logger->pushHandler($dbhandler);

    return $logger;
});

// Create a Slim application
$app = \DI\Bridge\Slim\Bridge::create($container);
$app->setBasePath($container->get('basePath'));

// Config
$container->get('configService');
$container->set('name', 'web');

//
// Middleware (onion, outside inwards and then out again - i.e. the last one is first and last);
//
$app->add(new \Xibo\Middleware\Actions($app));
$app->add(new \Xibo\Middleware\ConnectorMiddleware($app));
$app->add(new \Xibo\Middleware\ListenersMiddleware($app));
$app->add(new \Xibo\Middleware\Theme($app));
$app->add(new \Xibo\Middleware\CsrfGuard($app));
$app->add(new \Xibo\Middleware\Csp($container));

// Authentication
$authentication = ($container->get('configService')->authentication != null)
    ? $container->get('configService')->authentication
    : (new \Xibo\Middleware\WebAuthentication());
$app->add($authentication->setDependencies($app)->addRoutes());

// Trusted reverse-proxy list for X-Forwarded-For client IP resolution (used for login/2FA/password-reset
// rate limiting and audit logging). Combines the settings.php-only $trustedProxyIps (supports CIDR/wildcard)
// with the admin-editable WHITELIST_LOAD_BALANCERS setting (Settings > Network), so operators who already
// listed their load balancer there (e.g. for HSTS trust) get it trusted here too.
// setDependencies() must be called explicitly here (mirroring web/xmds.php) because getSetting() requires
// it, and State::setState() only calls it per-request — too late, since this list is fixed at bootstrap.
$configService = $container->get('configService');
$configService->setDependencies($container->get('store'), $container->get('rootUri'));
$trustedProxyIps = $configService->getTrustedProxyIpList();
$app->add(new \Xibo\Middleware\TrustedProxyIpAddress($trustedProxyIps));
// Handle additional Middleware
\Xibo\Middleware\State::setMiddleWare($app);

// TODO reconfigure this and enable
//$app->add(new Xibo\Middleware\HttpCache());
$app->add(new \Xibo\Middleware\State($app));
$app->add(new \Xibo\Middleware\Log($app));
$app->add(TwigMiddleware::createFromContainer($app));
$app->add(new \Xibo\Middleware\Storage($app));
$app->add(new \Xibo\Middleware\Xmr($app));
$app->addRoutingMiddleware();
//
// End Middleware
//

// Add Error Middleware
$app->addErrorMiddleware(
    \Xibo\Helper\Environment::isDevMode() || \Xibo\Helper\Environment::isForceDebugging(),
    true,
    true
)
    ->setDefaultErrorHandler(\Xibo\Middleware\Handlers::webErrorHandler($container, false));

// All application routes
require PROJECT_ROOT . '/lib/routes-web.php';
require PROJECT_ROOT . '/lib/routes.php';

// React SPA shell routes - must be required last, see comment in the file.
require PROJECT_ROOT . '/lib/routes-spa.php';

// Run App
try {
    $app->run();
} catch (Exception $e) {
    echo 'Fatal Error - sorry this shouldn\'t happen. ';
    echo '<br>' . $e->getMessage();

    // Only output debug trace if we're configured to display errors
    if (ini_get('display_errors') == 1) {
        echo '<br><br><code>' . nl2br($e->getTraceAsString()) . '</code>';
    }
}
