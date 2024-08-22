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

namespace Xibo\Xmds;

use Xibo\Entity\Bandwidth;
use Xibo\Helper\LinkSigner;
use Xibo\Event\XmdsWeatherRequestEvent;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Soap7
 * @package Xibo\Xmds
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class Soap7 extends Soap6
{
    /**
     * @inheritDoc
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function RequiredFiles($serverKey, $hardwareKey)
    {
        $httpDownloads = ($this->getConfig()->getSetting('SENDFILE_MODE') != 'Off');
        return $this->doRequiredFiles($serverKey, $hardwareKey, $httpDownloads, true, true);
    }

    /**
     * @inheritDoc
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function GetResource($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId)
    {
        return $this->doGetResource($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId, true);
    }

    /**
     * Get player dependencies.
     * @param $serverKey
     * @param $hardwareKey
     * @param $fileType
     * @param $id
     * @param $chunkOffset
     * @param $chunkSize
     * @return string
     * @throws NotFoundException
     * @throws \SoapFault
     */
    public function GetDependency($serverKey, $hardwareKey, $fileType, $id, $chunkOffset, $chunkSize)
    {
        return $this->GetFile($serverKey, $hardwareKey, $id, $fileType, $chunkOffset, $chunkSize, true);
    }

    /**
     * Get Data for a widget
     * @param $serverKey
     * @param $hardwareKey
     * @param $widgetId
     * @return false|string
     * @throws NotFoundException
     * @throws \SoapFault
     */
    public function GetData($serverKey, $hardwareKey, $widgetId)
    {
        $this->logProcessor->setRoute('GetData');
        $sanitizer = $this->getSanitizer([
            'serverKey' => $serverKey,
            'hardwareKey' => $hardwareKey,
            'widgetId' => $widgetId,
        ]);

        // Sanitize
        $serverKey = $sanitizer->getString('serverKey');
        $hardwareKey = $sanitizer->getString('hardwareKey');
        $widgetId = $sanitizer->getInt('widgetId');

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->getSetting('SERVER_KEY')) {
            throw new \SoapFault(
                'Sender',
                'The Server key you entered does not match with the server key at this address'
            );
        }

        // Auth this request...
        if (!$this->authDisplay($hardwareKey)) {
            throw new \SoapFault('Receiver', 'This Display is not authorised.');
        }

        // Now that we authenticated the Display, make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth($this->display->displayId)) {
            throw new \SoapFault('Receiver', 'Bandwidth Limit exceeded');
        }

        // The MediaId is actually the widgetId
        try {
            $requiredFile = $this->requiredFileFactory->getByDisplayAndWidget(
                $this->display->displayId,
                $widgetId,
                'D',
            );

            $widget = $this->widgetFactory->loadByWidgetId($widgetId);

            $module = $this->moduleFactory->getByType($widget->type);

            // We just want the data.
            $dataModule = $this->moduleFactory->getByType($widget->type);
            if ($dataModule->isDataProviderExpected()) {
                // We only ever return cache.
                $dataProvider = $module->createDataProvider($widget);
                $dataProvider->setDisplayProperties(
                    $this->display->latitude ?: $this->getConfig()->getSetting('DEFAULT_LAT'),
                    $this->display->longitude ?: $this->getConfig()->getSetting('DEFAULT_LONG'),
                    $this->display->displayId
                );

                // We only __ever__ return cache from XMDS.
                try {
                    $cacheKey = $this->moduleFactory->determineCacheKey(
                        $module,
                        $widget,
                        $this->display->displayId,
                        $dataProvider,
                        $module->getWidgetProviderOrNull()
                    );

                    $widgetDataProviderCache = $this->moduleFactory->createWidgetDataProviderCache();

                    // We do not pass a modifiedDt in here because we always expect to be cached.
                    if (!$widgetDataProviderCache->decorateWithCache($dataProvider, $cacheKey, null, false)) {
                        throw new NotFoundException('Cache not ready');
                    }

                    $this->getLog()->debug('Cache ready and populated');

                    // Get media references
                    $media = [];
                    $requiredFiles = [];
                    $mediaIds = $widgetDataProviderCache->getCachedMediaIds();

                    if (count($mediaIds) > 0) {
                        $this->getLog()->debug('Processing media links');

                        $sql = '
                            SELECT `media`.`mediaId`,
                                   `media`.`storedAs`,
                                   `media`.`fileSize`,
                                   `media`.`released`,
                                   `media`.`md5`,
                                   `display_media`.`mediaId` AS displayMediaId
                              FROM `media`
                                LEFT OUTER JOIN `display_media`
                                ON `display_media`.`mediaId` = `media`.`mediaId`
                                    AND `display_media`.`displayId` = :displayId
                             WHERE `media`.`mediaId` IN ( ' . implode(',', $mediaIds) . ')
                        ';

                        // There isn't any point using a prepared statement because the widgetIds are substituted
                        // at runtime
                        foreach ($this->getStore()->select($sql, [
                            'displayId' => $this->display->displayId
                        ]) as $row) {
                            // Media to use for decorating the JSON file.
                            $media[$row['mediaId']] = $row['storedAs'];

                            // Only media we're interested in.
                            if (!in_array($row['displayMediaId'], $mediaIds)) {
                                continue;
                            }

                            // Output required file nodes for any media used in get data.
                            // these will appear in required files as well, and may already be downloaded.
                            $released = intval($row['released']);
                            $this->requiredFileFactory
                                ->createForMedia(
                                    $this->display->displayId,
                                    $row['mediaId'],
                                    $row['fileSize'],
                                    $row['storedAs'],
                                    $released
                                )
                                ->save();

                            // skip media which has released == 0 or 2
                            if ($released == 0 || $released == 2) {
                                continue;
                            }

                            // Add the file node
                            $requiredFiles[] = [
                                'id' => intval($row['mediaId']),
                                'size' => intval($row['fileSize']),
                                'md5' => $row['md5'],
                                'saveAs' => $row['storedAs'],
                                'path' => LinkSigner::generateSignedLink(
                                    $this->display,
                                    $this->configService->getApiKeyDetails()['encryptionKey'],
                                    null,
                                    'M',
                                    intval($row['mediaId']),
                                    $row['storedAs'],
                                ),
                            ];
                        }
                    } else {
                        $this->getLog()->debug('No media links');
                    }

                    $resource = json_encode([
                        'data' => $widgetDataProviderCache->decorateForPlayer(
                            $this->display,
                            $this->configService->getApiKeyDetails()['encryptionKey'],
                            $dataProvider->getData(),
                            $media,
                        ),
                        'meta' => $dataProvider->getMeta(),
                        'files' => $requiredFiles,
                    ]);
                } catch (GeneralException $exception) {
                    $this->getLog()->error('getData: Failed to get data cache for widgetId '
                        . $widget->widgetId . ', e = ' . $exception->getMessage());
                    throw new \SoapFault('Receiver', 'Cache not ready');
                }
            } else {
                // No data cached yet, exception
                throw new \SoapFault('Receiver', 'Cache not ready');
            }

            // Log bandwidth
            $requiredFile->bytesRequested = $requiredFile->bytesRequested + strlen($resource);
            $requiredFile->save();
        } catch (NotFoundException) {
            throw new \SoapFault('Receiver', 'Requested an invalid file.');
        } catch (\Exception $e) {
            if ($e instanceof \SoapFault) {
                return $e;
            }

            $this->getLog()->error('Unknown error during getData. E = ' . $e->getMessage());
            $this->getLog()->debug($e->getTraceAsString());
            throw new \SoapFault('Receiver', 'Unable to get the media resource');
        }

        // Log Bandwidth
        $this->logBandwidth($this->display->displayId, Bandwidth::$GET_DATA, strlen($resource));

        return $resource;
    }

    /**
     * Get Weather data for Display
     * @param $serverKey
     * @param $hardwareKey
     * @return string
     * @throws \SoapFault
     */
    public function GetWeather($serverKey, $hardwareKey): string
    {
        $this->logProcessor->setRoute('GetWeather');
        $sanitizer = $this->getSanitizer([
            'serverKey' => $serverKey,
            'hardwareKey' => $hardwareKey,
        ]);

        // Sanitize
        $serverKey = $sanitizer->getString('serverKey');
        $hardwareKey = $sanitizer->getString('hardwareKey');

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->getSetting('SERVER_KEY')) {
            throw new \SoapFault(
                'Sender',
                'The Server key you entered does not match with the server key at this address'
            );
        }

        // Auth this request...
        if (!$this->authDisplay($hardwareKey)) {
            throw new \SoapFault('Receiver', 'This Display is not authorised.');
        }

        $latitude = $this->display->latitude;
        $longitude = $this->display->longitude;

        // check for coordinates if present
        if ($latitude && $longitude) {
            // Dispatch an event to initialize weather data.
            $event = new XmdsWeatherRequestEvent($latitude, $longitude);
            $this->getDispatcher()->dispatch($event, XmdsWeatherRequestEvent::$NAME);
        } else {
            throw new \SoapFault(
                'Receiver',
                'Display coordinates is not configured'
            );
        }

        // return weather data
        return $event->getWeatherData();
    }
}
