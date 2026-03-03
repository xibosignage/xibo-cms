<?php
/*
 * Copyright (C) 2026 Pau Aliagas <linuxnow@gmail.com>
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

namespace Xibo\Custom\PlayerApiV2\Controller;

use Psr\Container\ContainerInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Routing\RouteContext;
use Xibo\Controller\Base;
use Xibo\Custom\PlayerApiV2\Middleware\PlayerAuthMiddleware;
use Xibo\Custom\PlayerApiV2\Service\ScheduleJsonService;
use Xibo\Factory\DisplayFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InstanceSuspendedException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Player API v2 Controller
 *
 * RESTful endpoints for player communication. Replaces the RPC-style /pwa/*
 * endpoints with resource-oriented URLs, JWT auth, and native JSON responses
 * (no XML parsing required client-side).
 *
 * Public endpoints:
 *   GET  /health     - API availability check
 *   POST /auth       - Authenticate and get JWT token
 *
 * Protected endpoints (JWT required, via PlayerAuthMiddleware):
 *   POST /displays                   - Register new display
 *   GET  /displays/{id}              - Get display settings
 *   PUT  /displays/{id}              - Update display info
 *   GET  /displays/{id}/media        - Get required media files (JSON)
 *   GET  /displays/{id}/schedule     - Get schedule (JSON, not XML)
 *   GET  /datasets/{id}/data         - Get dataset widget data
 *   GET  /widgets/{id}/resource      - Get widget HTML resource (query params)
 *   GET  /widgets/{L}/{R}/{M}        - Get widget HTML resource (path-based)
 *   GET  /media/{id}                 - Download media file (by ID)
 *   GET  /media/file/{storedAs}      - Download media file (by storedAs filename)
 *   GET  /data/{id}.json             - Get dataset data (bare-filename route)
 *   PUT  /displays/{id}/status       - Report display status
 *   POST /displays/{id}/logs         - Submit log entries
 *   POST /displays/{id}/stats        - Submit proof-of-play
 *   POST /displays/{id}/screenshot   - Upload screenshot
 *   PUT  /displays/{id}/inventory    - Update media inventory
 *   POST /displays/{id}/faults       - Report player faults
 *   GET  /displays/{id}/weather      - Get weather data
 *   GET  /dependencies/{filename}     - Download player dependency (by filename)
 */
class PlayerApiV2 extends Base
{
    /** Default base path for player API URLs. */
    private const DEFAULT_API_BASE = '/api/v2/player';

    public function __construct(
        private readonly DisplayFactory $displayFactory,
        private readonly ContainerInterface $container
    ) {
    }

    /**
     * Get the Player API base path.
     * Reads PLAYER_API_BASE from CMS settings; falls back to '/api/v2/player'.
     */
    private function getApiBase(): string
    {
        return $this->getConfig()->getSetting('PLAYER_API_BASE') ?: self::DEFAULT_API_BASE;
    }

    // ─── Public endpoints ───────────────────────────────────────────

    /**
     * Health check / version probe.
     *
     * The SDK uses this to detect v2 API availability before attempting
     * to use v2 endpoints. Returns immediately with no auth required.
     */
    public function health(Request $request, Response $response): Response
    {
        $response->getBody()->write(json_encode([
            'version' => 2,
            'status' => 'ok',
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Authenticate a display and return a JWT token.
     *
     * Validates serverKey + hardwareKey, checks the display is licensed,
     * then issues a short-lived JWT.
     *
     * @param Request $request
     * @param Response $response
     * @return Response JSON with token, expiresIn, expiresAt, displayId
     * @throws InvalidArgumentException
     * @throws AccessDeniedException
     * @throws GeneralException
     */
    public function auth(Request $request, Response $response): Response
    {
        $params = $this->getSanitizer($request->getParsedBody());

        $serverKey = $params->getString('serverKey');
        $hardwareKey = $params->getString('hardwareKey');

        if (empty($serverKey) || empty($hardwareKey)) {
            throw new InvalidArgumentException(
                __('serverKey and hardwareKey are required'),
                'auth'
            );
        }

        // Validate serverKey
        $configServerKey = $this->getConfig()->getSetting('SERVER_KEY');
        if ($serverKey !== $configServerKey) {
            throw new AccessDeniedException(__('Invalid server key'), 'serverKey');
        }

        // Look up display
        try {
            $display = $this->displayFactory->getByLicence($hardwareKey);
        } catch (NotFoundException $e) {
            throw new AccessDeniedException(__('Display not found'), 'hardwareKey');
        }

        if ($display->licensed != 1) {
            throw new AccessDeniedException(__('Display is not licensed'), 'hardwareKey');
        }

        // Generate JWT
        $tokenData = PlayerAuthMiddleware::generateToken(
            $display->displayId,
            $hardwareKey,
            $configServerKey
        );

        $result = array_merge($tokenData, [
            'displayId' => $display->displayId,
        ]);

        $response->getBody()->write(json_encode($result, JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // ─── Display management ─────────────────────────────────────────

    /**
     * Register a new display.
     *
     * Creates or re-registers a display. The JWT provides the auth context,
     * but register also accepts display metadata (name, client info, etc).
     */
    public function registerDisplay(Request $request, Response $response): Response
    {
        $params = $this->getSanitizer($request->getParsedBody());
        $hardwareKey = $request->getAttribute('hardwareKey');

        try {
            $soap = $this->getSoap(7);
            $serverKey = $this->getConfig()->getSetting('SERVER_KEY');

            $xmlResult = $soap->RegisterDisplay(
                $serverKey,
                $hardwareKey,
                $params->getString('displayName', ['default' => '']),
                $params->getString('clientType', ['default' => '']),
                $params->getString('clientVersion', ['default' => '']),
                $params->getInt('clientCode', ['default' => 0]),
                $params->getString('operatingSystem', ['default' => '']),
                $params->getString('macAddress', ['default' => '']),
                $params->getString('xmrChannel'),
                $params->getString('xmrPubKey'),
            );

            $data = json_decode($this->xmlToJson($xmlResult), true);
            $data = $this->flattenDisplayResponse($data);

            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Get display settings.
     *
     * Returns the display configuration without re-registering.
     * Effectively a read-only version of register.
     */
    public function getDisplay(Request $request, Response $response): Response
    {
        $display = $this->resolveDisplay($request);

        try {
            $soap = $this->getSoap(7);
            $serverKey = $this->getConfig()->getSetting('SERVER_KEY');

            $xmlResult = $soap->RegisterDisplay(
                $serverKey,
                $display->license,
                $display->display,
                $display->clientType,
                $display->clientVersion ?? '',
                $display->clientCode ?? 0,
                '', '', '', '',
            );

            $data = json_decode($this->xmlToJson($xmlResult), true);
            $data = $this->flattenDisplayResponse($data);

            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Update display information.
     *
     * Updates display metadata (name, client version, etc) via re-registration.
     */
    public function updateDisplay(Request $request, Response $response): Response
    {
        $display = $this->resolveDisplay($request);
        $params = $this->getSanitizer($request->getParsedBody());

        try {
            $soap = $this->getSoap(7);
            $serverKey = $this->getConfig()->getSetting('SERVER_KEY');

            $xmlResult = $soap->RegisterDisplay(
                $serverKey,
                $display->license,
                $params->getString('displayName', ['default' => $display->display]),
                $params->getString('clientType', ['default' => $display->clientType]),
                $params->getString('clientVersion', ['default' => $display->clientVersion ?? '']),
                $params->getInt('clientCode', ['default' => $display->clientCode ?? 0]),
                $params->getString('operatingSystem', ['default' => '']),
                $params->getString('macAddress', ['default' => '']),
                $params->getString('xmrChannel', ['default' => '']),
                $params->getString('xmrPubKey', ['default' => '']),
            );

            $data = json_decode($this->xmlToJson($xmlResult), true);
            $data = $this->flattenDisplayResponse($data);

            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    // ─── Media & schedule ───────────────────────────────────────────

    /**
     * Get required media files.
     *
     * Returns categorized JSON instead of a flat XML list:
     * { media: [...], layouts: [...], widgets: [...] }
     */
    public function getRequiredMedia(Request $request, Response $response): Response
    {
        $display = $this->resolveDisplay($request);

        try {
            $soap = $this->getSoap(7);
            $serverKey = $this->getConfig()->getSetting('SERVER_KEY');

            $xmlResult = $soap->RequiredFiles($serverKey, $display->license);

            // Parse XML and categorize into media/layouts/widgets
            $categorized = $this->categorizeRequiredFiles($xmlResult, $serverKey, $display->license);

            return $this->cachedJsonResponse($request, $response, $categorized);
        } catch (\Throwable $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Get schedule as structured JSON.
     *
     * Server-side XML→JSON parsing replaces the SDK's schedule-parser.js.
     */
    public function getSchedule(Request $request, Response $response): Response
    {
        $display = $this->resolveDisplay($request);

        try {
            $soap = $this->getSoap(7);
            $serverKey = $this->getConfig()->getSetting('SERVER_KEY');

            $xmlResult = $soap->Schedule($serverKey, $display->license);

            $scheduleData = ScheduleJsonService::parse($xmlResult);

            return $this->cachedJsonResponse($request, $response, $scheduleData);
        } catch (\Throwable $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    // ─── Data & resources ───────────────────────────────────────────

    /**
     * Get dataset data for a widget.
     */
    public function getDatasetData(Request $request, Response $response): Response
    {
        $hardwareKey = $request->getAttribute('hardwareKey');

        try {
            $soap = $this->getSoap(7);
            $serverKey = $this->getConfig()->getSetting('SERVER_KEY');

            $body = $soap->GetData($serverKey, $hardwareKey, (int)$this->getRouteId($request));

            $response->getBody()->write($body);
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withoutHeader('Content-Security-Policy');
        } catch (\SoapFault $e) {
            if (str_contains($e->getMessage(), 'Cache not ready')) {
                return $response
                    ->withStatus(503)
                    ->withHeader('Retry-After', '30')
                    ->withHeader('Content-Type', 'application/json');
            }
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Get widget HTML resource (query-param style, kept for backward compat).
     */
    public function getWidgetResource(Request $request, Response $response): Response
    {
        $hardwareKey = $request->getAttribute('hardwareKey');
        $params = $this->getSanitizer($request->getQueryParams());

        try {
            $soap = $this->getSoap(7);
            $serverKey = $this->getConfig()->getSetting('SERVER_KEY');

            $body = $soap->GetResource(
                $serverKey,
                $hardwareKey,
                $params->getInt('layoutId'),
                $params->getString('regionId'),
                (string)$this->getRouteId($request),
            );

            $response->getBody()->write($body);
            return $response->withoutHeader('Content-Security-Policy');
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Get widget HTML resource (path-based — all IDs in URL, no query params).
     *
     * GET /widgets/{layoutId}/{regionId}/{mediaId}
     */
    public function getWidgetResourceByPath(Request $request, Response $response): Response
    {
        $hardwareKey = $request->getAttribute('hardwareKey');
        $route = RouteContext::fromRequest($request)->getRoute();

        try {
            $soap = $this->getSoap(7);
            $serverKey = $this->getConfig()->getSetting('SERVER_KEY');

            $body = $soap->GetResource(
                $serverKey,
                $hardwareKey,
                (int)$route->getArgument('layoutId'),
                $route->getArgument('regionId'),
                $route->getArgument('mediaId'),
            );

            $response->getBody()->write($body);
            return $response->withoutHeader('Content-Security-Policy');
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Download a media file.
     *
     * JWT-authenticated — display identity comes from the token, media ID from the URL.
     * Resolves the file via RequiredFileFactory and serves it using the configured
     * sendfile mode (Apache X-Sendfile or Nginx X-Accel-Redirect).
     *
     * Supports Range requests for chunked downloads.
     */
    public function downloadMedia(Request $request, Response $response): Response
    {
        $sendFileMode = $this->getConfig()->getSetting('SENDFILE_MODE');
        if ($sendFileMode === 'Off') {
            return $response->withStatus(404);
        }

        try {
            $displayId = $request->getAttribute('displayId');
            $route = RouteContext::fromRequest($request)->getRoute();
            $mediaId = $route ? $route->getArgument('id') : null;

            if (empty($mediaId)) {
                return $response->withStatus(400);
            }

            /** @var \Xibo\Factory\RequiredFileFactory $requiredFileFactory */
            $requiredFileFactory = $this->container->get('requiredFileFactory');

            // Determine file type from query param (default: media)
            $type = $request->getQueryParams()['type'] ?? 'M';

            if ($type === 'L') {
                $requiredFile = $requiredFileFactory->getByDisplayAndLayout($displayId, (int)$mediaId);
            } elseif ($type === 'P') {
                $fileType = $request->getQueryParams()['fileType'] ?? '';
                try {
                    $requiredFile = $requiredFileFactory->getByDisplayAndDependency(
                        $displayId, $fileType, $mediaId
                    );
                } catch (\Exception $e) {
                    return $response->withStatus(404);
                }
                // Resolve dependency path via event
                $event = new \Xibo\Event\XmdsDependencyRequestEvent($requiredFile);
                $this->container->get('dispatcher')->dispatch(
                    $event, \Xibo\Event\XmdsDependencyRequestEvent::$NAME
                );
                $requiredFile->path = $event->getRelativePath();
                if (empty($requiredFile->path)) {
                    return $response->withStatus(404);
                }
            } else {
                $requiredFile = $requiredFileFactory->getByDisplayAndMedia($displayId, (int)$mediaId);
            }

            // Check display is licensed
            $display = $this->displayFactory->getById($displayId);
            if ($display->licensed == 0) {
                return $response->withStatus(403);
            }

            // Check bandwidth limits
            /** @var \Xibo\Factory\BandwidthFactory $bandwidthFactory */
            $bandwidthFactory = $this->container->get('bandwidthFactory');
            if ($bandwidthFactory->isBandwidthExceeded(
                $this->getConfig()->getSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB')
            )) {
                return $response->withStatus(429);
            }

            // Track bandwidth
            $requiredFile->bytesRequested = $requiredFile->bytesRequested + $requiredFile->size;
            $requiredFile->save(['useTransaction' => false]);

            $bandwidthFactory->createAndSave(
                \Xibo\Entity\Bandwidth::$GETFILE,
                $displayId,
                $requiredFile->size,
            );

            $libraryLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION');

            // Determine content type
            $contentType = 'application/octet-stream';
            if ($requiredFile->type === 'L') {
                $contentType = 'text/xml';
            } elseif (\Illuminate\Support\Str::endsWith($requiredFile->path, '.js')) {
                $contentType = 'application/javascript';
            } elseif (\Illuminate\Support\Str::endsWith($requiredFile->path, '.css')) {
                $contentType = 'text/css';
            } else {
                $detected = mime_content_type($libraryLocation . $requiredFile->path);
                if ($detected !== false) {
                    $contentType = $detected;
                }
            }

            $response = $response->withHeader('Content-Type', $contentType);

            if ($sendFileMode === 'Apache') {
                return $response->withHeader('X-Sendfile', $libraryLocation . $requiredFile->path);
            } elseif ($sendFileMode === 'Nginx') {
                return $response->withHeader('X-Accel-Redirect', '/download/' . $requiredFile->path);
            }

            return $response->withStatus(500);
        } catch (NotFoundException $e) {
            $this->getLog()->error('downloadMedia NotFound: ' . $e->getMessage());
            return $response->withStatus(404);
        } catch (InstanceSuspendedException $e) {
            return $response->withStatus(403);
        } catch (\Exception $e) {
            $this->getLog()->error('downloadMedia: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            $response->getBody()->write(json_encode([
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Download a media file by its storedAs filename.
     *
     * The CMS puts bare `storedAs` filenames (e.g. `1234_abcd.png`) in widget HTML
     * for linux clientType displays. The SDK service worker intercepts these from
     * widget iframes and routes them here.
     *
     * Looks up the file via the `requiredfile` table's `path` column (which stores
     * the `storedAs` value) using a raw query to avoid modifying upstream factories.
     *
     * GET /media/file/{storedAs}
     */
    public function downloadMediaByFilename(Request $request, Response $response): Response
    {
        $sendFileMode = $this->getConfig()->getSetting('SENDFILE_MODE');
        if ($sendFileMode === 'Off') {
            return $response->withStatus(404);
        }

        try {
            $displayId = $request->getAttribute('displayId');
            $route = RouteContext::fromRequest($request)->getRoute();
            $storedAs = $route ? $route->getArgument('storedAs') : null;

            if (empty($storedAs)) {
                return $response->withStatus(400);
            }

            // Look up by storedAs filename in requiredfile table (raw query — no upstream changes)
            /** @var \Xibo\Storage\StorageServiceInterface $store */
            $store = $this->container->get('store');
            $rows = $store->select(
                'SELECT rfId, size, `path`, type, realId FROM `requiredfile`
                 WHERE displayId = :displayId AND type = :type AND `path` = :path',
                ['displayId' => $displayId, 'type' => 'M', 'path' => $storedAs]
            );

            if (empty($rows)) {
                $this->getLog()->error('downloadMediaByFilename: not found for storedAs=' . $storedAs
                    . ', displayId=' . $displayId);
                return $response->withStatus(404);
            }

            $row = $rows[0];

            // Check display is licensed
            $display = $this->displayFactory->getById($displayId);
            if ($display->licensed == 0) {
                return $response->withStatus(403);
            }

            // Check bandwidth limits
            /** @var \Xibo\Factory\BandwidthFactory $bandwidthFactory */
            $bandwidthFactory = $this->container->get('bandwidthFactory');
            if ($bandwidthFactory->isBandwidthExceeded(
                $this->getConfig()->getSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB')
            )) {
                return $response->withStatus(429);
            }

            // Track bandwidth
            $fileSize = (int)$row['size'];
            $store->update(
                'UPDATE `requiredfile` SET bytesRequested = bytesRequested + :size WHERE rfId = :rfId',
                ['size' => $fileSize, 'rfId' => $row['rfId']]
            );

            $bandwidthFactory->createAndSave(
                \Xibo\Entity\Bandwidth::$GETFILE,
                $displayId,
                $fileSize,
            );

            $libraryLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION');

            // Determine content type
            $contentType = mime_content_type($libraryLocation . $row['path']);
            if ($contentType === false) {
                $contentType = 'application/octet-stream';
            }

            $response = $response->withHeader('Content-Type', $contentType);

            if ($sendFileMode === 'Apache') {
                return $response->withHeader('X-Sendfile', $libraryLocation . $row['path']);
            } elseif ($sendFileMode === 'Nginx') {
                return $response->withHeader('X-Accel-Redirect', '/download/' . $row['path']);
            }

            return $response->withStatus(500);
        } catch (\Exception $e) {
            $this->getLog()->error('downloadMediaByFilename: ' . $e->getMessage());
            return $response->withStatus(500);
        }
    }

    /**
     * Get weather data for a display.
     *
     * Returns weather data based on the display's configured coordinates.
     * Delegates to Soap7::GetWeather which dispatches a weather event
     * to the configured weather provider (e.g. OpenWeatherMap connector).
     */
    public function getWeather(Request $request, Response $response): Response
    {
        $display = $this->resolveDisplay($request);

        try {
            $soap = $this->getSoap(7);
            $serverKey = $this->getConfig()->getSetting('SERVER_KEY');

            $weatherData = $soap->GetWeather($serverKey, $display->license);

            $response->getBody()->write($weatherData);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Download a layout XLF file.
     *
     * Delegates to downloadMedia() with type=L so RequiredFileFactory
     * resolves via getByDisplayAndLayout().
     */
    public function downloadLayout(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $params['type'] = 'L';
        $request = $request->withQueryParams($params);

        return $this->downloadMedia($request, $response);
    }

    /**
     * Download a player dependency (font, JS library, etc).
     *
     * Wraps downloadMedia() with type=P so RequiredFileFactory resolves
     * via getByDisplayAndDependency() instead of getByDisplayAndMedia().
     */
    public function downloadDependency(Request $request, Response $response): Response
    {
        $sendFileMode = $this->getConfig()->getSetting('SENDFILE_MODE');
        if ($sendFileMode === 'Off') {
            return $response->withStatus(404);
        }

        try {
            $displayId = $request->getAttribute('displayId');
            $route = RouteContext::fromRequest($request)->getRoute();
            $filename = $route ? $route->getArgument('filename') : null;

            if (empty($filename)) {
                return $response->withStatus(400);
            }

            /** @var \Xibo\Factory\RequiredFileFactory $requiredFileFactory */
            $requiredFileFactory = $this->container->get('requiredFileFactory');

            try {
                $requiredFile = $requiredFileFactory->getByDisplayAndDependencyPath($displayId, $filename);
            } catch (\Exception $e) {
                // Fallback: numeric IDs may be font realIds from CSS url() references
                if (ctype_digit($filename)) {
                    try {
                        $requiredFile = $requiredFileFactory->getByDisplayAndDependencyRealId($displayId, (int)$filename);
                    } catch (\Exception $e2) {
                        return $response->withStatus(404);
                    }
                } else {
                    return $response->withStatus(404);
                }
            }

            // Resolve dependency path via event
            $event = new \Xibo\Event\XmdsDependencyRequestEvent($requiredFile);
            $this->container->get('dispatcher')->dispatch(
                $event, \Xibo\Event\XmdsDependencyRequestEvent::$NAME
            );
            $requiredFile->path = $event->getRelativePath();
            if (empty($requiredFile->path)) {
                return $response->withStatus(404);
            }

            // Check display is licensed
            $display = $this->displayFactory->getById($displayId);
            if ($display->licensed == 0) {
                return $response->withStatus(403);
            }

            // Check bandwidth limits
            /** @var \Xibo\Factory\BandwidthFactory $bandwidthFactory */
            $bandwidthFactory = $this->container->get('bandwidthFactory');
            if ($bandwidthFactory->isBandwidthExceeded(
                $this->getConfig()->getSetting('MONTHLY_XMDS_TRANSFER_LIMIT_KB')
            )) {
                return $response->withStatus(429);
            }

            // Track bandwidth
            $requiredFile->bytesRequested = $requiredFile->bytesRequested + $requiredFile->size;
            $requiredFile->save(['useTransaction' => false]);

            $bandwidthFactory->createAndSave(
                \Xibo\Entity\Bandwidth::$GETFILE,
                $displayId,
                $requiredFile->size,
            );

            $libraryLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION');

            // Infer content type from file extension
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $contentType = match ($ext) {
                'css' => 'text/css',
                'js' => 'application/javascript',
                'otf' => 'font/otf',
                'ttf' => 'font/ttf',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'eot' => 'application/vnd.ms-fontobject',
                'svg' => 'image/svg+xml',
                default => mime_content_type($libraryLocation . $requiredFile->path) ?: 'application/octet-stream',
            };

            $response = $response->withHeader('Content-Type', $contentType);

            if ($sendFileMode === 'Apache') {
                return $response->withHeader('X-Sendfile', $libraryLocation . $requiredFile->path);
            } elseif ($sendFileMode === 'Nginx') {
                return $response->withHeader('X-Accel-Redirect', '/download/' . $requiredFile->path);
            }

            return $response->withStatus(500);
        } catch (NotFoundException $e) {
            $this->getLog()->error('downloadDependency NotFound: ' . $e->getMessage());
            return $response->withStatus(404);
        } catch (InstanceSuspendedException $e) {
            return $response->withStatus(403);
        } catch (\Exception $e) {
            $this->getLog()->error('downloadDependency: ' . $e->getMessage());
            return $response->withStatus(500);
        }
    }

    // ─── Reporting ──────────────────────────────────────────────────

    /**
     * Update display status.
     */
    public function updateStatus(Request $request, Response $response): Response
    {
        $display = $this->resolveDisplay($request);
        $params = $this->getSanitizer($request->getParsedBody());

        try {
            $soap = $this->getSoap(7);
            $serverKey = $this->getConfig()->getSetting('SERVER_KEY');

            // Accept JSON status directly — NotifyStatus internally calls json_decode()
            $status = $params->getString('status');
            if (empty($status)) {
                $statusData = $params->getArray('statusData', ['default' => []]);
                $status = json_encode($statusData);
            }

            $soap->NotifyStatus($serverKey, $display->license, $status);

            return $this->jsonSuccess($response);
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Submit log entries.
     */
    public function submitLogs(Request $request, Response $response): Response
    {
        $display = $this->resolveDisplay($request);
        $params = $this->getSanitizer($request->getParsedBody());

        try {
            $soap = $this->getSoap(7);
            $serverKey = $this->getConfig()->getSetting('SERVER_KEY');

            $logXml = $params->getString('logXml');
            if (empty($logXml)) {
                $logs = $params->getArray('logs', ['default' => []]);
                $logXml = $this->arrayToXml('logs', 'log', $logs);
            }

            $soap->SubmitLog($serverKey, $display->license, $logXml);

            return $this->jsonSuccess($response);
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Submit proof-of-play statistics.
     */
    public function submitStats(Request $request, Response $response): Response
    {
        $display = $this->resolveDisplay($request);
        $params = $this->getSanitizer($request->getParsedBody());

        try {
            $soap = $this->getSoap(7);
            $serverKey = $this->getConfig()->getSetting('SERVER_KEY');

            $statXml = $params->getString('statXml');
            if (empty($statXml)) {
                $stats = $params->getArray('stats', ['default' => []]);
                $statXml = $this->arrayToXml('stats', 'stat', $stats);
            }

            $soap->SubmitStats($serverKey, $display->license, $statXml);

            return $this->jsonSuccess($response);
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Submit a display screenshot.
     */
    public function submitScreenshot(Request $request, Response $response): Response
    {
        $display = $this->resolveDisplay($request);
        $params = $this->getSanitizer($request->getParsedBody());

        try {
            $soap = $this->getSoap(7);
            $serverKey = $this->getConfig()->getSetting('SERVER_KEY');

            $screenshot = base64_decode($params->getString('screenshot'));
            $soap->SubmitScreenShot($serverKey, $display->license, $screenshot);

            return $this->jsonSuccess($response);
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Report player faults to CMS for dashboard alerts.
     *
     * Delegates to Soap6::reportFaults which decodes the JSON fault array,
     * manages display alerts, and populates the player_faults table.
     */
    public function reportFaults(Request $request, Response $response): Response
    {
        $display = $this->resolveDisplay($request);
        $params = $this->getSanitizer($request->getParsedBody());

        try {
            $soap = $this->getSoap(7);
            $serverKey = $this->getConfig()->getSetting('SERVER_KEY');

            $soap->reportFaults(
                $serverKey,
                $display->license,
                $params->getString('fault'),
            );

            return $this->jsonSuccess($response);
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Update media inventory.
     */
    public function updateInventory(Request $request, Response $response): Response
    {
        $display = $this->resolveDisplay($request);
        $params = $this->getSanitizer($request->getParsedBody());

        try {
            $soap = $this->getSoap(7);
            $serverKey = $this->getConfig()->getSetting('SERVER_KEY');

            $inventory = $params->getString('inventory', ['default' => '']);
            if (empty($inventory)) {
                $items = $params->getArray('inventoryItems', ['default' => []]);
                $inventory = $this->arrayToXml('files', 'file', $items);
            }

            $soap->MediaInventory($serverKey, $display->license, $inventory);

            return $this->jsonSuccess($response);
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    // ─── Private helpers ────────────────────────────────────────────

    /**
     * Get the {id} route parameter from the Slim RouteContext.
     *
     * PHP-DI Bridge invokes controller methods via autowiring, which doesn't
     * pass the $args array. Route parameters must be read from RouteContext.
     */
    private function getRouteId(Request $request): string
    {
        $route = RouteContext::fromRequest($request)->getRoute();
        return $route ? $route->getArgument('id', '') : '';
    }

    /**
     * Resolve a display from route args and JWT claims.
     *
     * Validates that the display ID in the URL matches the JWT token's display.
     *
     * @throws AccessDeniedException If URL display ID doesn't match JWT
     * @throws NotFoundException If display not found
     */
    private function resolveDisplay(Request $request): \Xibo\Entity\Display
    {
        $tokenDisplayId = $request->getAttribute('displayId');
        $route = RouteContext::fromRequest($request)->getRoute();
        $urlDisplayId = (int)($route ? $route->getArgument('id', '0') : 0);

        if ($urlDisplayId !== 0 && $urlDisplayId !== $tokenDisplayId) {
            throw new AccessDeniedException(
                __('Token does not match requested display'),
                'displayId'
            );
        }

        $hardwareKey = $request->getAttribute('hardwareKey');
        return $this->displayFactory->getByLicence($hardwareKey);
    }

    /**
     * Categorize RequiredFiles XML into typed groups.
     *
     * Converts the flat XML file list into:
     * {
     *   "media": [{ id, type, filename, size, md5, url }],
     *   "layouts": [{ id, md5, url }],
     *   "widgets": [{ id, updateInterval, url }]
     * }
     */
    private function categorizeRequiredFiles(string $xml, string $serverKey, string $hardwareKey): array
    {
        $apiBase = $this->getApiBase();
        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        $result = [
            'media' => [],
            'layouts' => [],
            'widgets' => [],
            'dependencies' => [],
        ];

        $files = $doc->getElementsByTagName('file');
        foreach ($files as $file) {
            $type = $file->getAttribute('type');
            $id = $file->getAttribute('id');

            $baseAttrs = [
                'id' => is_numeric($id) ? (int)$id : $id,
                'md5' => $file->getAttribute('md5') ?: null,
            ];

            switch ($type) {
                case 'media':
                    $result['media'][] = array_merge($baseAttrs, [
                        'type' => $type,
                        'fileSize' => (int)($file->getAttribute('size') ?: 0),
                        'saveAs' => $file->getAttribute('saveAs') ?: null,
                        'url' => $apiBase . '/media/' . urlencode($id),
                    ]);
                    break;

                case 'font':
                case 'css':
                case 'dependency':
                    // Dependencies use saveAs (filename) as their identifier
                    $saveAs = $file->getAttribute('saveAs') ?: $id;
                    $result['dependencies'][] = array_merge($baseAttrs, [
                        'id' => $saveAs,
                        'type' => $type,
                        'fileSize' => (int)($file->getAttribute('size') ?: 0),
                        'url' => $apiBase . '/dependencies/' . urlencode($saveAs),
                    ]);
                    break;

                case 'resource':
                    $layoutId = $file->getAttribute('layoutid');
                    $regionId = $file->getAttribute('regionid');
                    $result['media'][] = array_merge($baseAttrs, [
                        'type' => 'resource',
                        'fileSize' => 0,
                        'saveAs' => null,
                        'url' => $apiBase . '/widgets/' . urlencode($layoutId)
                            . '/' . urlencode($regionId)
                            . '/' . urlencode($id),
                    ]);
                    break;

                case 'layout':
                    $result['layouts'][] = array_merge($baseAttrs, [
                        'fileSize' => (int)($file->getAttribute('size') ?: 0),
                        'url' => $apiBase . '/layouts/' . urlencode($id),
                    ]);
                    break;

                case 'widget':
                    $result['widgets'][] = array_merge($baseAttrs, [
                        'updateInterval' => (int)($file->getAttribute('updateInterval') ?: 0),
                        'url' => $apiBase . '/datasets/' . urlencode($id) . '/data',
                    ]);
                    break;

                default:
                    $result['media'][] = array_merge($baseAttrs, [
                        'type' => $type ?: 'unknown',
                        'fileSize' => (int)($file->getAttribute('size') ?: 0),
                        'saveAs' => $file->getAttribute('saveAs') ?: null,
                        'url' => $apiBase . '/media/' . urlencode($id),
                    ]);
                    break;
            }
        }

        return $result;
    }

    /**
     * Flatten the register display response and normalize tags.
     */
    private function flattenDisplayResponse(array $data): array
    {
        if (isset($data['display'])) {
            $display = &$data['display'];
        } else {
            $display = &$data;
        }

        // Flatten tags from {tag: [{tagName, tagValue}]} to ["tagName|tagValue"]
        if (isset($display['tags']['tag'])) {
            $rawTags = $display['tags']['tag'];
            if (isset($rawTags['tagName'])) {
                $rawTags = [$rawTags];
            }
            $flat = [];
            foreach ($rawTags as $t) {
                $name = $t['tagName'] ?? '';
                $value = $t['tagValue'] ?? null;
                if ($name !== '') {
                    $flat[] = ($value !== null && $value !== '') ? "$name|$value" : $name;
                }
            }
            $display['tags'] = $flat;
        }

        return $data;
    }

    /**
     * Return a JSON response with ETag caching.
     */
    private function cachedJsonResponse(Request $request, Response $response, array $data): Response
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $etag = '"' . md5($json) . '"';

        $ifNoneMatch = $request->getHeaderLine('If-None-Match');
        if ($ifNoneMatch === $etag) {
            return $response->withStatus(304);
        }

        $response->getBody()->write($json);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('ETag', $etag)
            ->withHeader('Cache-Control', 'private, must-revalidate');
    }

    /**
     * Return a JSON success response.
     */
    private function jsonSuccess(Response $response): Response
    {
        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Convert an array of items to XML with attributes.
     *
     * Generalized version of logsToXml/statsToXml/inventoryToXml.
     */
    private function arrayToXml(string $root, string $item, array $items): string
    {
        $xml = '<' . $root . '>';
        foreach ($items as $entry) {
            $attrs = '';
            foreach ($entry as $key => $value) {
                $attrs .= ' ' . $key . '="' . htmlspecialchars((string)$value) . '"';
            }
            $xml .= '<' . $item . $attrs . ' />';
        }
        $xml .= '</' . $root . '>';
        return $xml;
    }

    /**
     * Convert XML string to JSON (same as Pwa::xmlToJson).
     */
    private function xmlToJson(string $xml): string
    {
        $element = new \SimpleXMLElement($xml);
        $data = json_decode(json_encode($element, JSON_UNESCAPED_SLASHES), true);

        $arrayFields = [
            ['path' => ['display', 'commands', 'command']],
            ['path' => ['files', 'file']],
        ];

        foreach ($arrayFields as $field) {
            $ref = &$data;
            foreach ($field['path'] as $i => $key) {
                if ($i === count($field['path']) - 1) {
                    if (isset($ref[$key]) && !empty($ref[$key])) {
                        if (!isset($ref[$key][0])) {
                            $ref[$key] = [$ref[$key]];
                        }
                    }
                } elseif (isset($ref[$key])) {
                    $ref = &$ref[$key];
                } else {
                    break;
                }
            }
            unset($ref);
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Instantiate the appropriate Soap class with all dependencies.
     */
    private function getSoap(int $version): mixed
    {
        $class = '\Xibo\Xmds\Soap' . $version;
        if (!class_exists($class)) {
            throw new InvalidArgumentException(__('Unknown version'), 'version');
        }

        $uidProcessor = new \Monolog\Processor\UidProcessor(7);
        $logProcessor = new \Xibo\Xmds\LogProcessor(
            $this->container->get('logger'),
            $uidProcessor->getUid()
        );
        $this->container->get('logger')->pushProcessor($logProcessor);

        return new $class(
            $logProcessor,
            $this->container->get('pool'),
            $this->container->get('store'),
            $this->container->get('timeSeriesStore'),
            $this->container->get('logService'),
            $this->container->get('sanitizerService'),
            $this->container->get('configService'),
            $this->container->get('requiredFileFactory'),
            $this->container->get('moduleFactory'),
            $this->container->get('layoutFactory'),
            $this->container->get('dataSetFactory'),
            $this->displayFactory,
            $this->container->get('userGroupFactory'),
            $this->container->get('bandwidthFactory'),
            $this->container->get('mediaFactory'),
            $this->container->get('widgetFactory'),
            $this->container->get('regionFactory'),
            $this->container->get('notificationFactory'),
            $this->container->get('displayEventFactory'),
            $this->container->get('scheduleFactory'),
            $this->container->get('dayPartFactory'),
            $this->container->get('playerVersionFactory'),
            $this->container->get('dispatcher'),
            $this->container->get('campaignFactory'),
            $this->container->get('syncGroupFactory'),
            $this->container->get('playerFaultFactory')
        );
    }
}
