<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
use Nyholm\Psr7\ServerRequest;
use Slim\Http\ServerRequest as Request;
use Xibo\Event\XmdsDependencyRequestEvent;
use Xibo\Factory\ContainerFactory;
use Xibo\Support\Exception\NotFoundException;

define('XIBO', true);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));

error_reporting(0);
ini_set('display_errors', 0);

require PROJECT_ROOT . '/vendor/autoload.php';

if (!file_exists(PROJECT_ROOT . '/web/settings.php')) {
    die('Not configured');
}

// Create the container for dependency injection.
try {
    $container = ContainerFactory::create();
} catch (Exception $e) {
    die($e->getMessage());
}

// Logger
$uidProcessor = new \Monolog\Processor\UidProcessor(7);
$container->set('logger', function () use ($uidProcessor) {
    return (new Logger('XMDS'))
        ->pushProcessor($uidProcessor)
        ->pushHandler(new \Xibo\Helper\DatabaseLogHandler());
});

// Create a Slim application
$app = \DI\Bridge\Slim\Bridge::create($container);
$app->setBasePath($container->get('basePath'));

// Mock a request
$request = new Request(new ServerRequest('GET', $app->getBasePath()));
$request = $request->withAttribute('name', 'xmds');
$container->set('name', 'xmds');

// Start time of transaction (for logs)
$startTime = microtime(true);

// Set state
\Xibo\Middleware\State::setState($app, $request);

// Set XMR
\Xibo\Middleware\Xmr::setXmr($app, false);

// Set listeners
\Xibo\Middleware\ListenersMiddleware::setListeners($app);

// Set connectors
\Xibo\Middleware\ConnectorMiddleware::setConnectors($app);

// XMDS specific listeners
\Xibo\Middleware\ListenersMiddleware::setXmdsListeners($app);

// Configure
$container->get('configService')->setDependencies($container->get('store'), '/');
$container->get('configService')->loadTheme();

// Register Middleware Dispatchers
// Handle additional Middleware
if (isset($container->get('configService')->middleware) && is_array($container->get('configService')->middleware)) {
    foreach ($container->get('configService')->middleware as $object) {
        if (method_exists($object, 'registerDispatcher')) {
            $object::registerDispatcher($app);
        }
    }
}

// Always have a version defined
$sanitizer = $container->get('sanitizerService')->getSanitizer($_REQUEST);
$version = $sanitizer->getInt('v', ['default' => 3]);

// Version Request?
if (isset($_GET['what'])) {
    die(\Xibo\Helper\Environment::$XMDS_VERSION);
}

// Is the WSDL being requested.
if (isset($_GET['wsdl']) || isset($_GET['WSDL'])) {
    $wsdl = new \Xibo\Xmds\Wsdl(PROJECT_ROOT . '/lib/Xmds/service_v' . $version . '.wsdl', $version);
    $wsdl->output();
    exit;
}

// Check to see if we have a file attribute set (for HTTP file downloads)
if (isset($_GET['file'])) {
    // Check send file mode is enabled
    $sendFileMode = $container->get('configService')->getSetting('SENDFILE_MODE');

    if ($sendFileMode == 'Off') {
        $container->get('logService')->notice('HTTP GetFile request received but SendFile Mode is Off. Issuing 404');
        header('HTTP/1.0 404 Not Found');
        exit;
    }

    // Check nonce, output appropriate headers, log bandwidth and stop.
    try {
        /** @var \Xibo\Entity\RequiredFile $file */
        if (!isset($_REQUEST['displayId']) || !isset($_REQUEST['type']) || !isset($_REQUEST['itemId'])) {
            throw new NotFoundException(__('Missing params'));
        }

        $displayId = intval($_REQUEST['displayId']);
        $itemId = intval($_REQUEST['itemId']);

        // Get the player nonce from the cache
        /** @var \Stash\Item $nonce */
        $nonce = $container->get('pool')->getItem('/display/nonce/' . $displayId);

        if ($nonce->isMiss()) {
            throw new NotFoundException(__('No nonce cache'));
        }

        // Check the nonce against the nonce we received
        if ($nonce->get() != $_REQUEST['file']) {
            throw new NotFoundException(__('Nonce mismatch'));
        }

        /** @var \Xibo\Factory\RequiredFileFactory $requiredFileFactory */
        $requiredFileFactory = $container->get('requiredFileFactory');
        switch ($_REQUEST['type']) {
            case 'L':
                $file = $requiredFileFactory->getByDisplayAndLayout($displayId, $itemId);
                break;

            case 'M':
                $file = $requiredFileFactory->getByDisplayAndMedia($displayId, $itemId);
                break;

            case 'P':
                $fileType = $_REQUEST['fileType'];
                if (empty($fileType)) {
                    throw new NotFoundException('Missing fileType');
                }
                $file = $requiredFileFactory->getByDisplayAndDependency($displayId, $fileType, $itemId);

                // Update $file->path with the path on disk (likely /dependencies/$fileType/$itemId)
                $event = new XmdsDependencyRequestEvent($file);
                $container->get('dispatcher')->dispatch($event, XmdsDependencyRequestEvent::$NAME);

                // Path should be set - we only want the relative path here.
                $file->path = $event->getRelativePath();
                if (empty($file->path)) {
                    throw new NotFoundException(__('File not found'));
                }
                break;

            default:
                throw new NotFoundException(__('Unknown type'));
        }

        // Bandwidth
        // ---------
        // We don't check bandwidth allowances on DELETE.
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            // Check that we've not used all of our bandwidth already (if we have an allowance)
            if ($container->get('bandwidthFactory')->isBandwidthExceeded($container->get('configService')->getSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB'))) {
                throw new \Xibo\Support\Exception\InstanceSuspendedException('Bandwidth Exceeded');
            }

            // Check the display specific limit next.
            $display = $container->get('displayFactory')->getById($displayId);
            $usage = 0;
            if ($container->get('bandwidthFactory')->isBandwidthExceeded($display->bandwidthLimit, $usage, $displayId)) {
                throw new \Xibo\Support\Exception\InstanceSuspendedException('Bandwidth Exceeded');
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
            $container->get('logService')->info('Delete request for ' . $file->path);

            // Log bandwidth here if we are a CDN
            $logBandwidth = ($container->get('configService')->getSetting('CDN_URL') != '');

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
            $logBandwidth = ($container->get('configService')->getSetting('CDN_URL') == '');

            // Most likely a Get Request
            // Issue magic packet
            $container->get('logService')->info('HTTP GetFile request redirecting to ' . $container->get('configService')->getSetting('LIBRARY_LOCATION') . $file->path);

            // Send via Apache X-Sendfile header?
            if ($sendFileMode == 'Apache') {
                header('X-Sendfile: ' . $container->get('configService')->getSetting('LIBRARY_LOCATION') . $file->path);
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

            $container->get('bandwidthFactory')->createAndSave(4, $file->displayId, $usedBandwidth);
        }
    }
    catch (\Exception $e) {
        if ($e instanceof \Xibo\Support\Exception\NotFoundException || $e instanceof \Xibo\Support\Exception\ExpiredException) {
            $container->get('logService')->notice('HTTP GetFile request received but unable to find XMDS Nonce. Issuing 404. ' . $e->getMessage());
            // 404
            header('HTTP/1.0 404 Not Found');
        } else if ($e instanceof \Xibo\Support\Exception\InstanceSuspendedException) {
            $container->get('logService')->debug('Bandwidth exceeded');
            header('HTTP/1.0 403 Forbidden');
        }
        else {
            $container->get('logService')->error('Unknown Error: ' . $e->getMessage());
            $container->get('logService')->debug($e->getTraceAsString());

            // Issue a 500
            header('HTTP/1.0 500 Internal Server Error');
        }
    }

    exit;
}

// Connector request?
if (isset($_GET['connector'])) {
    try {
        if (!isset($_GET['token'])) {
            header('HTTP/1.0 403 Forbidden');
            exit;
        }

        // Dispatch an event to check the token
        $tokenEvent = new \Xibo\Event\XmdsConnectorTokenEvent();
        $tokenEvent->setToken($_GET['token']);
        $container->get('dispatcher')->dispatch($tokenEvent, \Xibo\Event\XmdsConnectorTokenEvent::$NAME);

        if (empty($tokenEvent->getWidgetId())) {
            header('HTTP/1.0 403 Forbidden');
            exit;
        }

        // Check the widgetId is permissible, and in required files for the display.
        /** @var \Xibo\Entity\RequiredFile $file */
        $file = $container->get('requiredFileFactory')->getByDisplayAndWidget(
            $tokenEvent->getDisplayId(),
            $tokenEvent->getWidgetId()
        );

        // Get the widget
        $widget = $container->get('widgetFactory')->getById($tokenEvent->getWidgetId());

        // It has been found, so we raise an event here to see if any connector can provide a file for it.
        $event = new \Xibo\Event\XmdsConnectorFileEvent($widget);
        $container->get('dispatcher')->dispatch($event, \Xibo\Event\XmdsConnectorFileEvent::$NAME);

        // What now?
        $emitter = new \Slim\ResponseEmitter();
        $emitter->emit($event->getResponse());
    } catch (\Xibo\Support\Exception\GeneralException $e) {
        header('HTTP/1.0 500 Internal Server Error');
        echo $e->getMessage();
    } catch (Exception $e) {
        $container->get('logService')->error('Unknown Error: ' . $e->getMessage());
        $container->get('logService')->debug($e->getTraceAsString());
        header('HTTP/1.0 500 Internal Server Error');
    }
    exit;
}

// Town down all logging
$container->get('logService')->setLevel(\Xibo\Service\LogService::resolveLogLevel('error'));

try {
    $wsdl = PROJECT_ROOT . '/lib/Xmds/service_v' . $version . '.wsdl';

    if (!file_exists($wsdl))
        throw new InvalidArgumentException(__('Your client is not the correct version to communicate with this CMS.'));

    // logProcessor
    $logProcessor = new \Xibo\Xmds\LogProcessor($container->get('logger'), $uidProcessor->getUid());
    $container->get('logger')->pushProcessor($logProcessor);

    // Create a SoapServer
    // explicitly define caching.
    if (\Xibo\Helper\Environment::isDevMode()) {
        // No cache - our WSDL might change in development
        $soap = new SoapServer($wsdl, ['cache_wsdl' => WSDL_CACHE_NONE]);
    } else {
        $soap = new SoapServer($wsdl, ['cache_wsdl' => WSDL_CACHE_MEMORY]);
    }
    $soap->setClass(
        '\Xibo\Xmds\Soap' . $version,
        $logProcessor,
        $container->get('pool'),
        $container->get('store'),
        $container->get('timeSeriesStore'),
        $container->get('logService'),
        $container->get('sanitizerService'),
        $container->get('configService'),
        $container->get('requiredFileFactory'),
        $container->get('moduleFactory'),
        $container->get('layoutFactory'),
        $container->get('dataSetFactory'),
        $container->get('displayFactory'),
        $container->get('userGroupFactory'),
        $container->get('bandwidthFactory'),
        $container->get('mediaFactory'),
        $container->get('widgetFactory'),
        $container->get('regionFactory'),
        $container->get('notificationFactory'),
        $container->get('displayEventFactory'),
        $container->get('scheduleFactory'),
        $container->get('dayPartFactory'),
        $container->get('playerVersionFactory'),
        $container->get('dispatcher'),
        $container->get('campaignFactory')
    );
    // Add manual raw post data parsing, as HTTP_RAW_POST_DATA is deprecated.
    $soap->handle(file_get_contents('php://input'));

    // Get the stats for this connection
    $stats = $container->get('store')->stats();

    $stats['length'] = microtime(true) - $startTime;

    $container->get('logService')->info('PDO stats: %s.', json_encode($stats, JSON_PRETTY_PRINT));

    if ($container->get('store')->getConnection()->inTransaction()) {
        $container->get('store')->getConnection()->commit();
    }

    // Finish any XMR work that has been logged during the request
    \Xibo\Middleware\Xmr::finish($app);

} catch (Exception $e) {
    $container->get('logService')->error($e->getMessage());

    if ($container->get('store')->getConnection()->inTransaction()) {
        $container->get('store')->getConnection()->rollBack();
    }

    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain');
    die ('There has been an unknown error with XMDS, it has been logged. Please contact your administrator.');
}
