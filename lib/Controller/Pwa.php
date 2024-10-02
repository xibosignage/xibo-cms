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
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Xmds\Soap7;

class Pwa extends Base
{
    public function __construct(
        private readonly DisplayFactory $displayFactory,
        private readonly DisplayProfileFactory $displayProfileFactory,
        private readonly PlayerVersionFactory $playerVersionFactory,
        private readonly ContainerInterface $container
    ) {
    }

    /**
     * @throws \Xibo\Support\Exception\NotFoundException
     * @throws \Xibo\Support\Exception\AccessDeniedException
     */
    public function home(Request $request, Response $response): Response
    {
        $params = $this->getSanitizer($request->getParams());

        // See if we have a specific display profile we want to use.
        $displayProfileId = $params->getInt('displayProfileId');
        if (!empty($displayProfileId)) {
            $displayProfile = $this->displayProfileFactory->getById($displayProfileId);

            if ($displayProfile->type !== 'chromeOS') {
                throw new AccessDeniedException(__('This type of display is not allowed to access this API'));
            }
        } else {
            $displayProfile = $this->displayProfileFactory->getDefaultByType('chromeOS');
        }

        // We have the display profile.
        // use that to get the player version
        $versionId = $displayProfile->getSetting('versionMediaId');
        if (!empty($versionId)) {
            $version = $this->playerVersionFactory->getById($versionId);
        } else {
            $version = $this->playerVersionFactory->getByType('chromeOS');
        }

        // Output the index.html file from the relevant bundle.
        $response->getBody()->write('<html></html>');
        return $response;
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
                ->withoutHeader('Content-Security-Policy')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Methods', '*')
                ->withHeader(
                    'Access-Control-Allow-Headers',
                    'append,delete,entries,foreach,get,has,keys,set,values,Origin,Authorization'
                );
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
                ->withoutHeader('Content-Security-Policy')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Methods', '*')
                ->withHeader(
                    'Access-Control-Allow-Headers',
                    'append,delete,entries,foreach,get,has,keys,set,values,Origin,Authorization'
                );
        } catch (\SoapFault $e) {
            throw new GeneralException($e->getMessage());
        }
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
            $this->container->get('playerFaultFactory')
        );
    }
}
