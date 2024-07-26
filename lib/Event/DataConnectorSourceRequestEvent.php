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
