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

namespace Xibo\Xmds;

use Xibo\Entity\Bandwidth;
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
                $widgetId
            );

            $widget = $this->widgetFactory->loadByWidgetId($widgetId);

            $module = $this->moduleFactory->getByType($widget->type);

            // We just want the data.
            $dataModule = $this->moduleFactory->getByType($widget->type);
            if ($dataModule->isDataProviderExpected() || $dataModule->isWidgetProviderAvailable()) {
                // We only ever return cache.
                $dataProvider = $module->createDataProvider($widget);

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

                    // Get media references
                    $media = [];
                    $sql = '
                        SELECT `media`.`mediaId`, `media`.`storedAs`
                          FROM `media`
                            INNER JOIN `display_media`
                            ON `display_media`.mediaid = `media`.mediaId
                         WHERE `display_media`.displayId = :displayId
                    ';

                    // There isn't any point using a prepared statement because the widgetIds are substituted at runtime
                    foreach ($this->getStore()->select($sql, [
                        'displayId' => $this->display->displayId
                    ]) as $row) {
                        $media[$row['mediaId']] = $row['storedAs'];
                    }

                    $this->getLog()->debug('Get Data');
                    $this->getLog()->debug(json_encode($dataProvider->getData()));

                    $resource = json_encode([
                        'data' => $widgetDataProviderCache->decorateForPlayer($dataProvider->getData(), $media),
                        'meta' => $dataProvider->getMeta(),
                    ]);
                } catch (GeneralException $exception) {
                    // We ignore this.
                    $this->getLog()->debug('Failed to get data cache for widgetId ' . $widget->widgetId);
                    $resource = '{"data":[], "meta": {}}';
                }
            } else {
                $resource = '{"data":[], "meta": {}}';
            }

            // Log bandwidth
            $requiredFile->bytesRequested = $requiredFile->bytesRequested + strlen($resource);
            $requiredFile->save();
        } catch (NotFoundException $notEx) {
            $this->getLog()->error('Unknown error during getResource. E = ' . $notEx->getMessage());
            $this->getLog()->debug($notEx->getTraceAsString());
            throw new \SoapFault('Receiver', 'Requested an invalid file.');
        } catch (\Exception $e) {
            $this->getLog()->error('Unknown error during getData. E = ' . $e->getMessage());
            $this->getLog()->debug($e->getTraceAsString());
            throw new \SoapFault('Receiver', 'Unable to get the media resource');
        }

        // Log Bandwidth
        $this->logBandwidth($this->display->displayId, Bandwidth::$GET_DATA, strlen($resource));

        return $resource;
    }
}
