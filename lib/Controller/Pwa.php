<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

namespace Xibo\Controller;

use Psr\Container\ContainerInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\DisplayFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * PWA Controller
 *
 * Provides all endpoints needed by PWA/ChromeOS players:
 *  - Original iframe resource endpoints (getResource, getData)
 *  - Complete PWA REST endpoints (register, requiredFiles, schedule,
 *    notifyStatus, submitLog, submitStats, submitScreenshot, mediaInventory)
 *
 * Each REST method delegates to the existing Soap classes, converting
 * XML responses to JSON where appropriate. Authentication uses
 * serverKey + hardwareKey per-request (same as XMDS).
 *
 * Benefits over SOAP:
 *  - Standard HTTP methods and status codes
 *  - JSON responses (smaller payload, native parsing)
 *  - HTTP caching via ETag/If-None-Match headers
 *  - Standard tooling (curl, Postman, browser DevTools)
 */
class Pwa extends Base
{
    public function __construct(
        private readonly DisplayFactory $displayFactory,
        private readonly ContainerInterface $container
    ) {
    }

    // ─── Original PWA iframe endpoints ──────────────────────────────

    /**
     * Get a rendered widget resource (HTML) for iframe embedding.
     *
     * @throws InvalidArgumentException
     * @throws GeneralException
     */
    public function getResource(Request $request, Response $response): Response
    {
        $params = $this->getSanitizer($request->getParams());

        try {
            $version = $this->getVersion($request);
            $display = $this->authenticateDisplay($request);

            $soap = $this->getSoap($version);

            $this->getLog()->debug('getResource: passing to Soap class');

            $body = $soap->GetResource(
                $params->getString('serverKey'),
                $display->license,
                $params->getInt('layoutId'),
                $params->getInt('regionId') . '',
                $params->getInt('mediaId') . '',
            );

            $response->getBody()->write($body);

            return $response
                ->withoutHeader('Content-Security-Policy');
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Get data for a data-aware widget.
     *
     * @throws InvalidArgumentException
     * @throws GeneralException
     */
    public function getData(Request $request, Response $response): Response
    {
        $params = $this->getSanitizer($request->getParams());

        try {
            $version = $this->getVersion($request);
            $display = $this->authenticateDisplay($request);

            $soap = $this->getSoap($version);
            $body = $soap->GetData(
                $params->getString('serverKey'),
                $display->license,
                $params->getInt('widgetId'),
            );

            $response->getBody()->write($body);

            return $response
                ->withoutHeader('Content-Security-Policy');
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    // ─── Player REST protocol endpoints ─────────────────────────────

    /**
     * Register or re-register a display.
     *
     * Replaces: XMDS RegisterDisplay
     *
     * Note: No isPwa check here — the display may not exist yet
     * (first registration creates it).
     *
     * @param Request $request
     * @param Response $response
     * @return Response JSON with display settings and authorization status
     * @throws InvalidArgumentException
     * @throws GeneralException
     */
    public function register(Request $request, Response $response): Response
    {
        $params = $this->getSanitizer($request->getParsedBody());

        try {
            $soap = $this->getSoap($this->getVersion($request));

            $xmlResult = $soap->RegisterDisplay(
                $params->getString('serverKey'),
                $params->getString('hardwareKey'),
                $params->getString('displayName', ['default' => '']),
                $params->getString('clientType', ['default' => '']),
                $params->getString('clientVersion', ['default' => '']),
                $params->getInt('clientCode', ['default' => 0]),
                $params->getString('operatingSystem', ['default' => '']),
                $params->getString('macAddress', ['default' => '']),
                $params->getString('xmrChannel'),
                $params->getString('xmrPubKey'),
            );

            return $this->jsonResponseFromXml($response, $xmlResult);
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Get the list of required files (media, layouts, resources).
     *
     * Replaces: XMDS RequiredFiles
     *
     * Supports HTTP caching:
     *  - Returns ETag header based on content hash
     *  - Honors If-None-Match -> 304 Not Modified
     *
     * @param Request $request
     * @param Response $response
     * @return Response JSON file manifest with hashes and download paths
     * @throws InvalidArgumentException
     * @throws GeneralException
     */
    public function requiredFiles(Request $request, Response $response): Response
    {
        $params = $this->getSanitizer($request->getQueryParams());

        try {
            $display = $this->authenticateDisplay($request);
            $soap = $this->getSoap($this->getVersion($request));

            $xmlResult = $soap->RequiredFiles(
                $params->getString('serverKey'),
                $display->license,
            );

            return $this->cachedJsonResponseFromXml($request, $response, $xmlResult);
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Get the current schedule.
     *
     * Replaces: XMDS Schedule
     *
     * Returns XML (not JSON) because the schedule structure is deeply nested
     * and players already have XML parsers for it.
     *
     * Supports HTTP caching:
     *  - Returns ETag header based on content hash
     *  - Honors If-None-Match -> 304 Not Modified
     *
     * @param Request $request
     * @param Response $response
     * @return Response XML schedule
     * @throws InvalidArgumentException
     * @throws GeneralException
     */
    public function schedule(Request $request, Response $response): Response
    {
        $params = $this->getSanitizer($request->getQueryParams());

        try {
            $display = $this->authenticateDisplay($request);
            $soap = $this->getSoap($this->getVersion($request));

            $xmlResult = $soap->Schedule(
                $params->getString('serverKey'),
                $display->license,
            );

            return $this->cachedXmlResponse($request, $response, $xmlResult);
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Submit display status (current layout, storage, etc).
     *
     * Replaces: XMDS NotifyStatus
     *
     * Accepts either:
     *  - `status`: raw XML string (XMDS-compatible)
     *  - `statusData`: JSON object -> auto-converted to XML
     *
     * @param Request $request
     * @param Response $response
     * @return Response JSON success acknowledgement
     * @throws InvalidArgumentException
     * @throws GeneralException
     */
    public function notifyStatus(Request $request, Response $response): Response
    {
        $params = $this->getSanitizer($request->getParsedBody());

        try {
            $display = $this->authenticateDisplay($request);
            $soap = $this->getSoap($this->getVersion($request));

            // Accept JSON string or structured statusData
            // NotifyStatus internally calls json_decode(), so we must pass JSON
            $status = $params->getString('status');
            if (empty($status)) {
                $statusData = $params->getArray('statusData', ['default' => []]);
                $status = json_encode($statusData);
            }

            $soap->NotifyStatus(
                $params->getString('serverKey'),
                $display->license,
                $status,
            );

            return $this->jsonSuccess($response);
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Submit player log entries.
     *
     * Replaces: XMDS SubmitLog
     *
     * Accepts either:
     *  - `logXml`: raw XML string (XMDS-compatible)
     *  - `logs`: JSON array of {date, category, type, message, method, thread}
     *
     * @param Request $request
     * @param Response $response
     * @return Response JSON success acknowledgement
     * @throws InvalidArgumentException
     * @throws GeneralException
     */
    public function submitLog(Request $request, Response $response): Response
    {
        $params = $this->getSanitizer($request->getParsedBody());

        try {
            $display = $this->authenticateDisplay($request);
            $soap = $this->getSoap($this->getVersion($request));

            // Accept XML or JSON log format
            $logXml = $params->getString('logXml');
            if (empty($logXml)) {
                $logs = $params->getArray('logs', ['default' => []]);
                $logXml = $this->logsToXml($logs);
            }

            $soap->SubmitLog(
                $params->getString('serverKey'),
                $display->license,
                $logXml,
            );

            return $this->jsonSuccess($response);
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Submit proof-of-play statistics.
     *
     * Replaces: XMDS SubmitStats
     *
     * Accepts either:
     *  - `statXml`: raw XML string (XMDS-compatible)
     *  - `stats`: JSON array of stat records
     *
     * @param Request $request
     * @param Response $response
     * @return Response JSON success acknowledgement
     * @throws InvalidArgumentException
     * @throws GeneralException
     */
    public function submitStats(Request $request, Response $response): Response
    {
        $params = $this->getSanitizer($request->getParsedBody());

        try {
            $display = $this->authenticateDisplay($request);
            $soap = $this->getSoap($this->getVersion($request));

            // Accept XML or JSON stats format
            $statXml = $params->getString('statXml');
            if (empty($statXml)) {
                $stats = $params->getArray('stats', ['default' => []]);
                $statXml = $this->statsToXml($stats);
            }

            $soap->SubmitStats(
                $params->getString('serverKey'),
                $display->license,
                $statXml,
            );

            return $this->jsonSuccess($response);
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Submit a display screenshot.
     *
     * Replaces: XMDS SubmitScreenShot
     *
     * @param Request $request
     * @param Response $response
     * @return Response JSON success acknowledgement
     * @throws InvalidArgumentException
     * @throws GeneralException
     */
    public function submitScreenshot(Request $request, Response $response): Response
    {
        $params = $this->getSanitizer($request->getParsedBody());

        try {
            $display = $this->authenticateDisplay($request);
            $soap = $this->getSoap($this->getVersion($request));

            $soap->SubmitScreenShot(
                $params->getString('serverKey'),
                $display->license,
                $params->getString('screenshot'),
            );

            return $this->jsonSuccess($response);
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * Report the display's media inventory (cached files).
     *
     * Replaces: XMDS MediaInventory
     *
     * Accepts either:
     *  - `inventory`: raw XML string (XMDS-compatible)
     *  - `inventoryItems`: JSON array of {id, complete, md5, lastChecked}
     *
     * @param Request $request
     * @param Response $response
     * @return Response JSON success acknowledgement
     * @throws InvalidArgumentException
     * @throws GeneralException
     */
    public function mediaInventory(Request $request, Response $response): Response
    {
        $params = $this->getSanitizer($request->getParsedBody());

        try {
            $display = $this->authenticateDisplay($request);
            $soap = $this->getSoap($this->getVersion($request));

            // Accept XML string or JSON array of inventory items
            $inventory = $params->getString('inventory', ['default' => '']);
            if (empty($inventory)) {
                $items = $params->getArray('inventoryItems', ['default' => []]);
                $inventory = $this->inventoryToXml($items);
            }

            $soap->MediaInventory(
                $params->getString('serverKey'),
                $display->license,
                $inventory,
            );

            return $this->jsonSuccess($response);
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    // ─── Private helpers ────────────────────────────────────────────

    /**
     * Get the XMDS version from query param or header, defaulting to 7.
     */
    private function getVersion(Request $request): int
    {
        $version = (int)($request->getQueryParams()['v']
            ?? $request->getHeaderLine('X-Xmds-Version')
            ?: 7);

        if ($version < 4 || $version > 7) {
            throw new InvalidArgumentException(
                __('Unsupported XMDS version. Supported: 4-7.'),
                'v'
            );
        }

        return $version;
    }

    /**
     * Authenticate a display by hardwareKey from query params or parsed body.
     *
     * Validates:
     *  - Display exists (by hardwareKey/licence)
     *  - Display is a PWA type (isPwa())
     *
     * Note: licensed/authorised check is deliberately omitted here — the
     * underlying Soap classes handle it internally, returning appropriate
     * error responses for unauthorised displays.
     *
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    private function authenticateDisplay(Request $request): \Xibo\Entity\Display
    {
        // Try query params first (GET requests), then parsed body (POST/PUT)
        $hardwareKey = $request->getQueryParams()['hardwareKey']
            ?? null;

        if (empty($hardwareKey)) {
            $body = $request->getParsedBody();
            $hardwareKey = $body['hardwareKey'] ?? '';
        }

        if (empty($hardwareKey)) {
            throw new InvalidArgumentException(__('Missing hardwareKey'), 'hardwareKey');
        }

        $display = $this->displayFactory->getByLicence($hardwareKey);

        if (!$display->isPwa()) {
            throw new AccessDeniedException(__('Please use XMDS API'), 'hardwareKey');
        }

        return $display;
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
     * Parse XML result to JSON and return as response.
     */
    private function jsonResponseFromXml(Response $response, string $xml): Response
    {
        $json = $this->xmlToJson($xml);
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Parse XML to JSON with HTTP caching (ETag + If-None-Match).
     */
    private function cachedJsonResponseFromXml(
        Request $request,
        Response $response,
        string $xml
    ): Response {
        $json = $this->xmlToJson($xml);
        $etag = '"' . md5($json) . '"';

        // Check If-None-Match for 304
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
     * Return raw XML with HTTP caching (ETag + If-None-Match).
     */
    private function cachedXmlResponse(
        Request $request,
        Response $response,
        string $xml
    ): Response {
        $etag = '"' . md5($xml) . '"';

        $ifNoneMatch = $request->getHeaderLine('If-None-Match');
        if ($ifNoneMatch === $etag) {
            return $response->withStatus(304);
        }

        $response->getBody()->write($xml);
        return $response
            ->withHeader('Content-Type', 'application/xml')
            ->withHeader('ETag', $etag)
            ->withHeader('Cache-Control', 'private, must-revalidate');
    }

    /**
     * Convert XML string to JSON.
     */
    private function xmlToJson(string $xml): string
    {
        $element = new \SimpleXMLElement($xml);
        return json_encode($element, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Convert JSON status data to XML expected by NotifyStatus.
     *
     * Input: { "currentLayoutId": 1, "availableSpace": 1024, ... }
     * Output: <status><currentLayoutId>1</currentLayoutId>...</status>
     */
    private function statusToXml(array $data): string
    {
        $xml = '<status>';
        foreach ($data as $key => $value) {
            $xml .= '<' . $key . '>' . htmlspecialchars((string)$value) . '</' . $key . '>';
        }
        $xml .= '</status>';
        return $xml;
    }

    /**
     * Convert JSON log entries to XML expected by SubmitLog.
     *
     * Input: [{ "date": "...", "category": "...", "type": "...", "message": "..." }]
     * Output: <logs><log date="..." category="..." type="..." message="..." /></logs>
     */
    private function logsToXml(array $logs): string
    {
        $xml = '<logs>';
        foreach ($logs as $log) {
            $attrs = '';
            foreach ($log as $key => $value) {
                $attrs .= ' ' . $key . '="' . htmlspecialchars((string)$value) . '"';
            }
            $xml .= '<log' . $attrs . ' />';
        }
        $xml .= '</logs>';
        return $xml;
    }

    /**
     * Convert JSON stats to XML expected by SubmitStats.
     *
     * Input: [{ "type": "...", "fromDt": "...", "toDt": "...", ... }]
     * Output: <stats><stat type="..." fromDt="..." toDt="..." ... /></stats>
     */
    private function statsToXml(array $stats): string
    {
        $xml = '<stats>';
        foreach ($stats as $stat) {
            $attrs = '';
            foreach ($stat as $key => $value) {
                $attrs .= ' ' . $key . '="' . htmlspecialchars((string)$value) . '"';
            }
            $xml .= '<stat' . $attrs . ' />';
        }
        $xml .= '</stats>';
        return $xml;
    }

    /**
     * Convert JSON inventory items to XML expected by MediaInventory.
     *
     * Input: [{ "id": "1", "complete": "1", "md5": "abc...", "lastChecked": "..." }]
     * Output: <files><file id="1" complete="1" md5="abc..." lastChecked="..." /></files>
     */
    private function inventoryToXml(array $items): string
    {
        $xml = '<files>';
        foreach ($items as $item) {
            $attrs = '';
            foreach ($item as $key => $value) {
                $attrs .= ' ' . $key . '="' . htmlspecialchars((string)$value) . '"';
            }
            $xml .= '<file' . $attrs . ' />';
        }
        $xml .= '</files>';
        return $xml;
    }

    /**
     * Instantiate the appropriate Soap class with all dependencies.
     *
     * @throws InvalidArgumentException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function getSoap(int $version): mixed
    {
        $class = '\Xibo\Xmds\Soap' . $version;
        if (!class_exists($class)) {
            throw new InvalidArgumentException(__('Unknown version'), 'version');
        }

        // Overwrite the logger
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
