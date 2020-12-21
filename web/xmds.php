<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (xmds.php) is part of Xibo.
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

define('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

error_reporting(0);
ini_set('display_errors', 0);

require PROJECT_ROOT . '/vendor/autoload.php';

if (!file_exists(PROJECT_ROOT . '/web/settings.php')) {
    die('Not configured');
}

// We create a Slim Object ONLY for logging
// Create a logger
$uidProcessor = new \Monolog\Processor\UidProcessor(7);
$logger = new \Xibo\Helper\AccessibleMonologWriter(array(
    'name' => 'XMDS',
    'handlers' => [new \Xibo\Helper\DatabaseLogHandler()],
    'processors' => [$uidProcessor]
));

// Slim Application
$app = new \Slim\Slim(array(
    'debug' => false,
    'log.writer' => $logger
));
$app->setName('api');
$app->startTime = microtime(true);

// Load the config
$app->configService = \Xibo\Service\ConfigService::Load(PROJECT_ROOT . '/web/settings.php');

// Set storage
\Xibo\Middleware\Storage::setStorage($app->container);

// Set state
\Xibo\Middleware\State::setState($app);

// Set XMR
\Xibo\Middleware\Xmr::setXmr($app, false);

$app->configService->setDependencies($app->store, '/');
$app->configService->loadTheme();

// Register Middleware Dispatchers
// Handle additional Middleware
if (isset($app->configService->middleware) && is_array($app->configService->middleware)) {
    foreach ($app->configService->middleware as $object) {
        if (method_exists($object, 'registerDispatcher')) {
            $object::registerDispatcher($app);
        }
    }
}

// Always have a version defined
$version = $app->sanitizerService->getInt('v', 3, $_REQUEST);

// Version Request?
if (isset($_GET['what']))
    die(\Xibo\Helper\Environment::$XMDS_VERSION);

// Is the WSDL being requested.
if (isset($_GET['wsdl']) || isset($_GET['WSDL'])) {
    $wsdl = new \Xibo\Xmds\Wsdl(PROJECT_ROOT . '/lib/Xmds/service_v' . $version . '.wsdl', $version);
    $wsdl->output();
    exit;
}

// We need a View for rendering GetResource Templates
// Twig templates
$twig = new \Slim\Views\Twig();
$twig->parserOptions = array(
    'debug' => true,
    'cache' => PROJECT_ROOT . '/cache'
);
$twig->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
    new \Xibo\Twig\TransExtension(),
    new \Xibo\Twig\UrlDecodeTwigExtension()
);

// Configure a user
$app->user = $app->userFactory->getSystemUser();

// Configure the template folder
$twig->twigTemplateDirs = array_merge($app->moduleFactory->getViewPaths(), [PROJECT_ROOT . '/views']);
$app->view($twig);

// Check to see if we have a file attribute set (for HTTP file downloads)
if (isset($_GET['file'])) {

    // Check send file mode is enabled
    $sendFileMode = $app->configService->getSetting('SENDFILE_MODE');

    if ($sendFileMode == 'Off') {
        $app->logService->notice('HTTP GetFile request received but SendFile Mode is Off. Issuing 404');
        header('HTTP/1.0 404 Not Found');
        exit;
    }

    // Check nonce, output appropriate headers, log bandwidth and stop.
    try {
        /** @var \Xibo\Entity\RequiredFile $file */
        if (!isset($_REQUEST['displayId']) || !isset($_REQUEST['type']) || !isset($_REQUEST['itemId'])) {
            throw new \Xibo\Exception\NotFoundException('Missing params');
        }

        $displayId = intval($_REQUEST['displayId']);
        $itemId = intval($_REQUEST['itemId']);

        // Get the player nonce from the cache
        /** @var \Stash\Item $nonce */
        $nonce = $app->pool->getItem('/display/nonce/' . $displayId);

        if ($nonce->isMiss())
            throw new \Xibo\Exception\NotFoundException('No nonce cache');

        // Check the nonce against the nonce we received
        if ($nonce->get() != $_REQUEST['file'])
            throw new \Xibo\Exception\NotFoundException('Nonce mismatch');

        switch ($_REQUEST['type']) {
            case 'L':
                $file = $app->requiredFileFactory->getByDisplayAndLayout($displayId, $itemId);
                break;

            case 'M':
                $file = $app->requiredFileFactory->getByDisplayAndMedia($displayId, $itemId);
                break;

            default:
                throw new \Xibo\Exception\NotFoundException('Unknown type');
        }

        // Bandwidth
        // ---------
        // We don't check bandwidth allowances on DELETE.
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            // Check that we've not used all of our bandwidth already (if we have an allowance)
            if ($app->bandwidthFactory->isBandwidthExceeded($app->configService->GetSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB'))) {
                throw new \Xibo\Exception\InstanceSuspendedException('Bandwidth Exceeded');
            }

            // Check the display specific limit next.
            $display = $app->displayFactory->getById($displayId);
            $usage = 0;
            if ($app->bandwidthFactory->isBandwidthExceeded($display->bandwidthLimit, $usage, $displayId)) {
                throw new \Xibo\Exception\InstanceSuspendedException('Bandwidth Exceeded');
            }
        }

        // Only log bandwidth under certain conditions
        // also controls whether the nonce is updated
        $logBandwidth = false;
        $usedBandwidth = $file->size;

        // Are we a DELETE request or otherwise?
        if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
            // Supply a header only, pointing to the original file name
            header('Content-Disposition: attachment; filename="' . $file->path . '"');

            if (array_key_exists('X-CLOUD-ACC', $_SERVER)) {
                header('X-CLOUD-ACC', $_SERVER['X-CLOUD-ACC']);
            }

        } else if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
            // Log bandwidth for the file being requested
            $app->logService->info('Delete request for ' . $file->path);

            // Log bandwidth here if we are a CDN
            $logBandwidth = ($app->configService->getSetting('CDN_URL') != '');

            // Do we have a usage amount provided?
            if (array_key_exists('HTTP_X_CDN_BW', $_SERVER) && is_numeric($_SERVER['HTTP_X_CDN_BW'])) {
                $usedBandwidth = intval($_SERVER['HTTP_X_CDN_BW']);

                // Don't allow this if we get bandwidth lower than 0
                if ($usedBandwidth < 0) {
                    $usedBandwidth = $file->size;
                }
            }

        } else {
            // Log bandwidth here if we are NOT a CDN
            $logBandwidth = ($app->configService->getSetting('CDN_URL') == '');

            // Most likely a Get Request
            // Issue magic packet
            $app->logService->info('HTTP GetFile request redirecting to ' . $app->configService->getSetting('LIBRARY_LOCATION') . $file->path);

            // Send via Apache X-Sendfile header?
            if ($sendFileMode == 'Apache') {
                header('X-Sendfile: ' . $app->configService->getSetting('LIBRARY_LOCATION') . $file->path);
            } // Send via Nginx X-Accel-Redirect?
            else if ($sendFileMode == 'Nginx') {
                header('X-Accel-Redirect: /download/' . $file->path);
            } else {
                header('HTTP/1.0 404 Not Found');
            }
        }

        // Log bandwidth
        if ($logBandwidth) {
            // Add the size to the bytes we have already requested.
            $file->bytesRequested = $file->bytesRequested + $usedBandwidth;
            $file->save();

            $app->bandwidthFactory->createAndSave(4, $file->displayId, $usedBandwidth);
        }
    }
    catch (\Exception $e) {
        if ($e instanceof \Xibo\Exception\NotFoundException || $e instanceof \Xibo\Exception\FormExpiredException) {
            $app->logService->notice('HTTP GetFile request received but unable to find XMDS Nonce. Issuing 404. ' . $e->getMessage());
            // 404
            header('HTTP/1.0 404 Not Found');
        } else if ($e instanceof \Xibo\Exception\InstanceSuspendedException) {
            $app->logService->debug('Bandwidth exceeded');
            header('HTTP/1.0 403 Forbidden');
        } else {
            $app->logService->error('Unknown Error: ' . $e->getMessage());
            $app->logService->debug($e->getTraceAsString());

            // Issue a 500
            header('HTTP/1.0 500 Internal Server Error');
        }
    }

    exit;
}

// Town down all logging
$app->getLog()->setLevel(\Xibo\Service\LogService::resolveLogLevel('error'));

try {
    $wsdl = PROJECT_ROOT . '/lib/Xmds/service_v' . $version . '.wsdl';

    if (!file_exists($wsdl))
        throw new InvalidArgumentException(__('Your client is not the correct version to communicate with this CMS.'));

    // Create a log processor
    $logProcessor = new \Xibo\Xmds\LogProcessor($app->getLog(), $uidProcessor->getUid());
    $app->logWriter->addProcessor($logProcessor);

    // Create a SoapServer
    $soap = new SoapServer($wsdl);
    //$soap = new SoapServer($wsdl, array('cache_wsdl' => WSDL_CACHE_NONE));
    $soap->setClass('\Xibo\Xmds\Soap' . $version,
        $logProcessor,
        $app->pool,
        $app->store,
        $app->timeSeriesStore,
        $app->logService,
        $app->dateService,
        $app->sanitizerService,
        $app->configService,
        $app->requiredFileFactory,
        $app->moduleFactory,
        $app->layoutFactory,
        $app->dataSetFactory,
        $app->displayFactory,
        $app->userGroupFactory,
        $app->bandwidthFactory,
        $app->mediaFactory,
        $app->widgetFactory,
        $app->regionFactory,
        $app->notificationFactory,
        $app->displayEventFactory,
        $app->scheduleFactory,
        $app->dayPartFactory,
        $app->playerVersionFactory
    );
    $soap->handle();

    // Finish any XMR work that has been logged during the request
    \Xibo\Middleware\Xmr::finish($app);

    // Get the stats for this connection
    $stats = $app->store->stats();
    $stats['length'] = microtime(true) - $app->startTime;

    $app->logService->info('PDO stats: %s.', json_encode($stats, JSON_PRETTY_PRINT));

    if ($app->store->getConnection()->inTransaction())
        $app->store->getConnection()->commit();
}
catch (Exception $e) {
    $app->logService->error($e->getMessage());

    if ($app->store->getConnection()->inTransaction())
        $app->store->getConnection()->rollBack();

    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain');
    die (__('There has been an unknown error with XMDS, it has been logged. Please contact your administrator.'));
}