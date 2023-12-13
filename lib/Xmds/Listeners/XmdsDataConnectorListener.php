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

namespace Xibo\Xmds\Listeners;

use Xibo\Event\XmdsDependencyRequestEvent;
use Xibo\Listener\ListenerConfigTrait;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Xmds\Entity\Dependency;

/**
 * Listener to handle dependency requests for data connectors.
 */
class XmdsDataConnectorListener
{
    use ListenerLoggerTrait;
    use ListenerConfigTrait;

    public function onDependencyRequest(XmdsDependencyRequestEvent $event)
    {
        // Can we return this type of file?
        if ($event->getFileType() === 'data_connector') {
            // Set the path
            $event->setRelativePathToLibrary('data_connectors/dataSet_' . $event->getRealId() . '.js');

            // No need to carry on, we've found it.
            $event->stopPropagation();
        }
    }

    /**
     * @throws NotFoundException
     */
    public static function getDataConnectorDependency(string $libraryLocation, int $dataSetId): Dependency
    {
        // Check that this asset is valid.
        $path = $libraryLocation
            . 'data_connectors' . DIRECTORY_SEPARATOR
            . 'dataSet_' . $dataSetId . '.js';

        if (!file_exists($path)) {
            throw new NotFoundException(sprintf(__('Data Connector %s not found'), $path));
        }

        // Return a dependency
        return new Dependency(
            'data_connector',
            $dataSetId,
            (Dependency::LEGACY_ID_OFFSET_DATA_CONNECTOR + $dataSetId) * -1,
            $path,
            filesize($path),
            file_get_contents($path . '.md5'),
            true
        );
    }
}
