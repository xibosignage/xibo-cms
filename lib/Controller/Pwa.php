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

namespace Xibo\Controller;

use Psr\Container\ContainerInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\DisplayFactory;
use Xibo\Helper\HttpsDetect;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Xmds\Soap7;

/**
 * PWA
 *  routes for a PWA to download resources which live in an iframe
 */
class Pwa extends Base
{
    public function __construct(
        private readonly DisplayFactory $displayFactory,
        private readonly ContainerInterface $container
    ) {
    }

    /**
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Xibo\Support\Exception\NotFoundException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Xibo\Support\Exception\AccessDeniedException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function getResource(Request $request, Response $response): Response
    {
        // Create a Soap client and call it.
        $params = $this->getSanitizer($request->getParams());

        try {
            // Which version are we?
            $version = $params->getInt('v', [
                'default' => 7,
                'throw' => function () {
                    throw new InvalidArgumentException(__('Missing Version'), 'v');
                }
            ]);

            if ($version < 7) {
                throw new InvalidArgumentException(__('PWA supported from XMDS schema 7 onward.'), 'v');
            }

            // Validate that this display should call this service.
            $hardwareKey = $params->getString('hardwareKey');
            $display = $this->displayFactory->getByLicence($hardwareKey);
            if (!$display->isPwa()) {
                throw new AccessDeniedException(__('Please use XMDS API'), 'hardwareKey');
            }

            // Check it is still authorised.
            if ($display->licensed == 0) {
                throw new AccessDeniedException(__('Display unauthorised'));
            }

            /** @var Soap7 $soap */
            $soap = $this->getSoap($version);

            $this->getLog()->debug('getResource: passing to Soap class');

            $body = $soap->GetResource(
                $params->getString('serverKey'),
                $params->getString('hardwareKey'),
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
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Xibo\Support\Exception\NotFoundException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function getData(Request $request, Response $response): Response
    {
        $params = $this->getSanitizer($request->getParams());

        try {
            $version = $params->getInt('v', [
                'default' => 7,
                'throw' => function () {
                    throw new InvalidArgumentException(__('Missing Version'), 'v');
                }
            ]);

            if ($version < 7) {
                throw new InvalidArgumentException(__('PWA supported from XMDS schema 7 onward.'), 'v');
            }

            // Validate that this display should call this service.
            $hardwareKey = $params->getString('hardwareKey');
            $display = $this->displayFactory->getByLicence($hardwareKey);
            if (!$display->isPwa()) {
                throw new AccessDeniedException(__('Please use XMDS API'), 'hardwareKey');
            }

            // Check it is still authorised.
            if ($display->licensed == 0) {
                throw new AccessDeniedException(__('Display unauthorised'));
            }

            /** @var Soap7 $soap */
            $soap = $this->getSoap($version);
            $body = $soap->GetData(
                $params->getString('serverKey'),
                $params->getString('hardwareKey'),
                $params->getInt('widgetId'),
            );

            $response->getBody()->write($body);

            return $response
                ->withoutHeader('Content-Security-Policy');
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
    }

    /**
     * The player's web app manifest, built on every request from the copy bundled with the
     * player: a white-labelled CMS gets its own name and id, and every CMS its own address as
     * the start URL, including a sub-folder (CMS_ALIAS).
     *
     * @throws \Xibo\Support\Exception\NotFoundException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function manifest(Request $request, Response $response): Response
    {
        $template = PROJECT_ROOT . '/web/pwa/manifest.json';
        if (!is_file($template)) {
            throw new NotFoundException(__('The ChromeOS player is not installed'));
        }

        $manifest = json_decode(file_get_contents($template), true);
        if (!is_array($manifest)) {
            throw new GeneralException(__('The ChromeOS player manifest is not valid'));
        }

        // The PWA entry point doesn't load the theme up front, as the web UI does, so
        // load it here: white-label branding comes from library/brand/config.json.
        $config = $this->getConfig();
        $config->loadTheme();
        if ($config->getThemeConfig('app_name', 'Xibo') !== 'Xibo') {
            // The product name: the brand config has no theme_name, so that would stay Xibo's.
            $manifest['name'] = $config->getThemeConfig('theme_title');
            $manifest['description'] = $config->getThemeConfig('theme_title');
            $manifest['short_name'] = $config->getThemeConfig('app_name') . '-chromeos';
        }

        // Marked as a PWA request so the CMS's own address comes back without /pwa. Passing
        // config means WHITELIST_HOSTS (if set) stops the Host header from choosing it.
        $baseUrl = (new HttpsDetect($config))->getBaseUrl($request->withAttribute('_entryPoint', 'pwa'));

        // The app's identity. Earlier manifests set an id on another origin (xibosignage.com,
        // or the brand's URL), which browsers ignore, so installed players are identified by
        // their old start_url, <cms>/pwa. Using that as the id keeps them the same app.
        $manifest['id'] = $baseUrl . '/pwa';

        // /pwa/, not /pwa: the service worker's scope is /pwa/, so the app can only start
        // offline from inside it, and the player's files are referenced relative to it.
        $manifest['start_url'] = $baseUrl . '/pwa/';

        foreach ($manifest['icons'] ?? [] as $i => $icon) {
            $manifest['icons'][$i]['src'] = ($icon['sizes'] ?? '') === '512x512'
                ? '/brand/512x512.png'
                : '/brand/192x192.png';
        }

        $response->getBody()->write(json_encode($manifest, JSON_UNESCAPED_SLASHES));

        return $response
            ->withHeader('Content-Type', 'application/manifest+json')
            ->withHeader('Cache-Control', 'no-cache');
    }

    /**
     * @throws \Xibo\Support\Exception\InvalidArgumentException
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
            $this->container->get('playerFaultFactory'),
            $this->container->get('libraryStorageService'),
            $this->container->get('libraryStorageRegistry')
        );
    }
}
