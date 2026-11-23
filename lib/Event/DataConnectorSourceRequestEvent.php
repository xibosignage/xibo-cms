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

namespace Xibo\Event;

use InvalidArgumentException;
use Xibo\Connector\DataConnectorSourceProviderInterface;

/**
 * Event triggered to retrieve a list of data connector sources.
 *
 * This event collects metadata (names and IDs) of connectors that provides data connector.
 */
class DataConnectorSourceRequestEvent extends Event implements DataConnectorSourceProviderInterface
{
    public static $NAME = 'data.connector.source.request';

    /**
     * @var array
     */
    private $dataConnectorSources = [];

    /**
     * Initializes the dataConnectorSources with default value.
     */
    public function __construct()
    {
        $this->dataConnectorSources[] = [
            'id' => 'user_defined',
            'name' => __('User-Defined JavaScript')
        ];
    }

    /**
     * @inheritDoc
     */
    public function addDataConnectorSource(string $id, string $name): void
    {
        // ensure that there are no duplicate id or name
        foreach ($this->dataConnectorSources as $dataConnectorSource) {
            if ($dataConnectorSource['id'] == $id) {
                throw new InvalidArgumentException('Duplicate Connector ID found.');
            }
            if ($dataConnectorSource['name'] == $name) {
                throw new InvalidArgumentException('Duplicate Connector Name found.');
            }
        }

        $this->dataConnectorSources[] = ['id' => $id, 'name' => $name];
    }

    /**
     * Retrieves the list of data connector sources.
     *
     * @return array
     */
    public function getDataConnectorSources(): array
    {
        return $this->dataConnectorSources;
    }
}
