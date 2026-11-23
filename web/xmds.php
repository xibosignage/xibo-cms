<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

use Monolog\Logger;
use Nyholm\Psr7\ServerRequest;
use Slim\Http\ServerRequest as Request;
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
$request = $request->withAttribute('_entryPoint', 'xmds');
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

// Check to see if the instance has been suspended
$instanceSuspended = $container->get('configService')->getSetting('INSTANCE_SUSPENDED');
if ($instanceSuspended == 'yes' || $instanceSuspended == 'partial') {
    header('HTTP/1.0 403 Forbidden');
    die('CMS suspended');
}

// Load the theme
$container->get('configService')->loadTheme($container->get('brandService'));

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
    $logger = $container->get('logService');

    // Check send file mode is enabled
    $sendFileMode = $container->get('configService')->getSetting('SENDFILE_MODE');

    if ($sendFileMode == 'Off') {
        $logger->notice('HTTP GetFile: request received but SendFile Mode is Off. Issuing 404');
        header('HTTP/1.0 404 Not Found');
        exit;
    }

    // Check nonce, output appropriate headers, log bandwidth and stop.
    try {
        if (!isset($_REQUEST['displayId']) || !isset($_REQUEST['type']) || !isset($_REQUEST['itemId'])) {
            $logger->error('HTTP GetFile: Missing params');
            throw new NotFoundException(__('Missing params'));
        }

        $displayId = intval($_REQUEST['displayId']);

        // Has the URL expired
        if (time() > $_REQUEST['X-Amz-Expires']) {
            $logger->error('HTTP GetFile: Access denied: Pre-signed S3 URL expired');
            throw new NotFoundException(__('Expired'));
        }

        // Validate the URL.
        $encryptionKey = $container->get('configService')->getApiKeyDetails()['encryptionKey'];
        $signature = $_REQUEST['X-Amz-Signature'];
        $calculatedSignature = \Xibo\Helper\LinkSigner::getSignature(
            parse_url(\Xibo\Xmds\Wsdl::getRoot(), PHP_URL_HOST),
            \Xibo\Helper\LinkSigner::getXmdsResource(
                $_GET['file'],
                $_REQUEST['displayId'],
                $_REQUEST['type'],
                $_REQUEST['itemId'],
                $_REQUEST['fileType'] ?? null,
            ),
            $_REQUEST['X-Amz-Expires'],
            $encryptionKey,
            $_REQUEST['X-Amz-Date'],
            true,
        );

        if ($signature !== $calculatedSignature) {
            $logger->error('HTTP GetFile: Invalid URL');
            throw new NotFoundException(__('Invalid URL'));
        }

        /** @var \Xibo\Factory\RequiredFileFactory $requiredFileFactory */
        $requiredFileFactory = $container->get('requiredFileFactory');
        $file = $requiredFileFactory->resolveRequiredFileFromRequest($_REQUEST);

        // Check that we've not used all of our bandwidth already (if we have an allowance)
        if ($container->get('bandwidthFactory')->isBandwidthExceeded(
            $container->get('configService')->getSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB')
        )) {
            $logger->error('HTTP GetFile: Bandwidth Exceeded');
            throw new \Xibo\Support\Exception\InstanceSuspendedException('Bandwidth Exceeded');
        }

        // Get the display
        /** @var \Xibo\Entity\Display $display */
        $display = $container->get('displayFactory')->getById($displayId);

        // Check it is still authorised.
        if ($display->licensed == 0) {
            $logger->error('HTTP GetFile: Display unauthorised');
            throw new NotFoundException(__('Display unauthorised'));
        }

        // Check the display specific limit next.
        $usage = 0;
        if ($container->get('bandwidthFactory')->isBandwidthExceeded(
            $display->bandwidthLimit,
            $usage,
            $displayId
        )) {
            $logger->error('HTTP GetFile: Bandwidth Exceeded for Display ID: ' . $displayId);
            throw new \Xibo\Support\Exception\InstanceSuspendedException('Bandwidth Exceeded');
        }

        // Bandwidth
        // Add the size to the bytes we have already requested. With cloud storage the CMS streams, so a HEAD adds
        // nothing and a Range request only its range.
        $isStreamed = !$container->get('libraryStorageService')->hasLocalFiles();
        if (!$isStreamed || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            $range = !$isStreamed || isset($_SERVER['HTTP_IF_RANGE'])
                ? null
                : \Xibo\Helper\ByteRange::parse($_SERVER['HTTP_RANGE'] ?? '', (int)$file->size);
            $file->bytesRequested = $file->bytesRequested
                + match (true) {
                    is_array($range) => $range[1] - $range[0] + 1,
                    $range === false => 0,
                    default => (int)$file->size,
                };
            $file->save(['useTransaction' => false]);
        }

        // Send the file, decided by where it is kept and by the player.
        new \Xibo\Xmds\HttpFileSender(
            new \Xibo\Xmds\PhpHttpOutput(),
            $container->get('libraryStorageService'),
            $container->get('layoutFactory'),
            $requiredFileFactory,
            new \Xibo\Xmds\PlayerFileLink($container->get('configService'), $container->get('libraryStorageRegistry')),
            $container->get('bandwidthFactory'),
            $container->get('store'),
            $logger->getLoggerInterface(),
            (string)$sendFileMode,
            (string)$container->get('configService')->getSetting('LIBRARY_LOCATION'),
            $container->get('libraryStorageRegistry')
        )->send($file, $display, $_SERVER);
    } catch (\Xibo\Support\Exception\NotFoundException|\Xibo\Support\Exception\ExpiredException $e) {
        $logger->notice('HTTP GetFile: request received but unable to find XMDS Nonce. Issuing 404. '
            . $e->getMessage());

        // 404
        if (!headers_sent()) {
            header('HTTP/1.0 404 Not Found');
        }
    } catch (\Xibo\Support\Exception\InstanceSuspendedException $e) {
        $logger->debug('HTTP GetFile: Bandwidth exceeded');
        if (!headers_sent()) {
            header('HTTP/1.0 403 Forbidden');
        }
    } catch (\Exception $e) {
        $logger->error('HTTP GetFile: Unknown Error: ' . $e->getMessage());
        $logger->debug($e->getTraceAsString());

        // Issue a 500
        if (!headers_sent()) {
            header('HTTP/1.0 500 Internal Server Error');
        }
    }
    exit;
}

// Are we a CDN bandwidth log
if (isset($_GET['cdn'])) {
    $logger = $container->get('logService');

    try {
        // The CDN only links files the CMS holds locally, which some still are while files are being copied.
        if (!$container->get('libraryStorageService')->hasLocalFiles()
            && !$container->get('libraryStorageRegistry')->hasFilesToMove()
        ) {
            throw new NotFoundException('CDN not used');
        }

        // We expect a PSK CONSTANT to be defined in our configuration
        if (!defined('CDN_PSK')) {
            throw new \Xibo\Support\Exception\ConfigurationException('Missing CDN config');
        }

        if (!array_key_exists('HTTP_X_CDN_PSK', $_SERVER) || $_SERVER['HTTP_X_CDN_PSK'] !== CDN_PSK) {
            throw new NotFoundException('Invalid Token');
        }

        /** @var \Xibo\Factory\RequiredFileFactory $requiredFileFactory */
        $requiredFileFactory = $container->get('requiredFileFactory');
        $file = $requiredFileFactory->resolveRequiredFileFromRequest($_REQUEST);

        $container->get('logService')->info('CDN bandwidth request for ' . $file->path);

        // The usage reported in the request, or the file size if none is reported.
        $usedBandwidth = \Xibo\Helper\CdnBandwidth::fromHeader($_SERVER['HTTP_X_CDN_BW'] ?? null, (int) $file->size);

        // Bandwidth
        // Add the usage to the bytes we have already requested.
        $file->bytesRequested = $file->bytesRequested + $usedBandwidth;
        $file->save(['useTransaction' => false]);

        // Also add to the overall bandwidth used by get file
        $container->get('bandwidthFactory')->createAndSave(
            \Xibo\Entity\Bandwidth::$GETFILE,
            $file->displayId,
            $usedBandwidth
        );
    } catch (Exception $e) {
        $logger->error('Unknown Error: ' . $e->getMessage());
        $logger->debug($e->getTraceAsString());
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

        // Get the display
        /** @var \Xibo\Entity\Display $display */
        $display = $container->get('displayFactory')->getById($tokenEvent->getDisplayId());

        // Check it is still authorised.
        if ($display->licensed == 0) {
            throw new NotFoundException(__('Display unauthorised'));
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

    if (!file_exists($wsdl)) {
        throw new InvalidArgumentException(__('Your client is not the correct version to communicate with this CMS.'));
    }

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
        $container->get('campaignFactory'),
        $container->get('syncGroupFactory'),
        $container->get('playerFaultFactory'),
        $container->get('libraryStorageService'),
        $container->get('libraryStorageRegistry'),
        $container->get('displayGroupFactory')
    );

    // Add manual raw post data parsing, as HTTP_RAW_POST_DATA is deprecated.
    $soap->handle(file_get_contents('php://input'));

    // Get the stats for this connection
    $stats = $container->get('store')->stats();

    $stats['length'] = microtime(true) - $startTime;

    $container->get('logService')->info('PDO stats: %s.', json_encode($stats, JSON_PRETTY_PRINT));

    if ($container->get('store')->getConnection()->inTransaction()) {
        // SoapServer::handle() traps any exception thrown inside a SOAP method, turns it into a
        // SOAP fault (setting the HTTP status to 500) and returns normally - so a faulted request
        // never reaches the catch below. Without this check we would commit the partial work of a
        // faulted request (e.g. a display row inserted without its display-specific display group).
        // Gate the commit on the response status so faults roll back as intended.
        if (http_response_code() >= 500) {
            try {
                $container->get('store')->rollBackIfNecessary();
            } catch (\Throwable $t) {
                // The transaction may already be gone (e.g. after a deadlock the server rolls back
                // for us), in which case there is nothing to do here.
                $container->get('logService')->debug('XMDS rollback no-op: ' . $t->getMessage());
            }
        } else {
            $container->get('store')->commitIfNecessary();
        }
    }

    // Finish any XMR work that has been logged during the request
    \Xibo\Middleware\Xmr::finish($app);
} catch (Exception $e) {
    $container->get('logService')->error($e->getMessage());

    $container->get('store')->rollBackIfNecessary();

    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain');
    die('There has been an unknown error with XMDS, it has been logged. Please contact your administrator.');
}
