<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
     * @throws \DOMException
     */
    public function RequiredFiles($serverKey, $hardwareKey)
    {
        $httpDownloads = ($this->getConfig()->getSetting('SENDFILE_MODE') != 'Off');
        return $this->doRequiredFiles($serverKey, $hardwareKey, $httpDownloads, true);
    }

    /**
     * @inheritDoc
     */
    public function GetFile($serverKey, $hardwareKey, $fileId, $fileType, $chunkOffset, $chunkSize)
    {
        // TODO: we're going to need to have a slightly different format because we need the regionId to
        //  get the correct HTML cache.
        //  Maybe this should even be a different call altogether.
        if ($fileType === 'widget') {
            return '';
        } else {
            return parent::GetFile($serverKey, $hardwareKey, $fileId, $fileType, $chunkOffset, $chunkSize);
        }
    }

    /**
     * @inheritDoc
     */
    public function GetResource($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId)
    {
        $this->logProcessor->setRoute('GetResource');
        $sanitizer = $this->getSanitizer([
            'serverKey' => $serverKey,
            'hardwareKey' => $hardwareKey,
            'layoutId' => $layoutId,
            'regionId' => $regionId,
            'mediaId' => $mediaId
        ]);

        // Sanitize
        $serverKey = $sanitizer->getString('serverKey');
        $hardwareKey = $sanitizer->getString('hardwareKey');
        $mediaId = $sanitizer->getString('mediaId');

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
                $mediaId
            );

            $widget = $this->widgetFactory->loadByWidgetId($mediaId);

            $module = $this->moduleFactory->getByType($widget->type);

            // We just want the data.
            $dataModule = $this->moduleFactory->getByType($widget->type);
            if ($dataModule->isDataProviderExpected()) {
                // We only ever return cache.
                $dataProvider = $module->createDataProvider($widget, $this->display->displayId);

                // Use the cache if we can.
                try {
                    $widgetDataProviderCache = $this->moduleFactory->createWidgetDataProviderCache();
                    $widgetDataProviderCache->decorateWithCache($module, $widget, $dataProvider);
                } catch (GeneralException $exception) {
                    // We ignore this.
                    $this->getLog()->debug('Failed to get data cache for widgetId ' . $widget->widgetId);
                }

                $resource = json_encode($dataProvider->getData());
            } else {
                $resource = '{}';
            }

            // Log bandwidth
            $requiredFile->bytesRequested = $requiredFile->bytesRequested + strlen($resource);
            $requiredFile->save();
        } catch (NotFoundException $notEx) {
            throw new \SoapFault('Receiver', 'Requested an invalid file.');
        } catch (\Exception $e) {
            $this->getLog()->error('Unknown error during getResource. E = ' . $e->getMessage());
            $this->getLog()->debug($e->getTraceAsString());
            throw new \SoapFault('Receiver', 'Unable to get the media resource');
        }

        // Log Bandwidth
        $this->logBandwidth($this->display->displayId, Bandwidth::$GETRESOURCE, strlen($resource));

        return $resource;
    }
}
