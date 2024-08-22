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

namespace Xibo\Connector;

use InvalidArgumentException;

/**
 * Interface for handling the DataConnectorSourceRequestEvent.
 *
 * Registers connectors that provide data connector JavaScript (JS).
 */
interface DataConnectorSourceProviderInterface
{
    /**
     * Adds/Registers a connector, that would provide a data connector JS, to the event.
     * Implementations should use $this->getSourceName() as the $id and $this->getTitle() as the $name.
     *
     * @param string $id
     * @param string $name
     * @throws InvalidArgumentException if a duplicate ID or name is found.
     */
    public function addDataConnectorSource(string $id, string $name): void;
}
