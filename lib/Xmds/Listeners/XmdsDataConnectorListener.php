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

namespace Xibo\Xmds\Listeners;

use Stash\Interfaces\PoolInterface;
use Xibo\Entity\DataSet;
use Xibo\Event\XmdsDependencyRequestEvent;
use Xibo\Helper\StoredFileMetadata;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Xmds\Entity\Dependency;

/**
 * Listener to handle dependency requests for data connectors.
 */
class XmdsDataConnectorListener
{
    use ListenerLoggerTrait;

    public function __construct(private readonly LibraryStorageRegistryInterface $libraryStorageRegistry)
    {
    }

    public function onDependencyRequest(XmdsDependencyRequestEvent $event)
    {
        // Can we return this type of file?
        if ($event->getFileType() === 'data_connector') {
            $key = DataSet::getScriptKey((int)$event->getRealId());
            $event->setRelativePathToLibrary('data_connectors/' . $key);
            $event->setStorage($this->libraryStorageRegistry->get(LibraryAsset::DATA_CONNECTOR), $key);

            // No need to carry on, we've found it.
            $event->stopPropagation();
        }
    }

    /**
     * The dependency for a real-time DataSet's script, from cached metadata.
     * @param \Closure(): LibraryStorageServiceInterface $storageFactory the script storage, fetched only on a miss
     * @throws NotFoundException if the script isn't stored
     * @throws GeneralException if storage cannot be reached
     */
    public static function getDataConnectorDependency(
        PoolInterface $pool,
        \Closure $storageFactory,
        int $dataSetId
    ): Dependency {
        $key = DataSet::getScriptKey($dataSetId);

        try {
            $metadata = StoredFileMetadata::get(
                $pool,
                DataSet::getScriptMetadataCacheKey($dataSetId),
                $storageFactory,
                $key
            );
        } catch (NotFoundException) {
            throw new NotFoundException(sprintf(__('Data Connector %s not found'), $key));
        }

        return new Dependency(
            'data_connector',
            $dataSetId,
            (Dependency::LEGACY_ID_OFFSET_DATA_CONNECTOR + $dataSetId) * -1,
            'data_connectors/' . $key,
            $metadata['size'],
            $metadata['md5'],
            true
        );
    }
}
