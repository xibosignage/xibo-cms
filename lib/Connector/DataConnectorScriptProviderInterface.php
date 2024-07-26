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

/**
 * Interface for handling the DataConnectorScriptRequestEvent.
 *
 * Provides methods for connectors to supply their data connector JS code.
 *
 * These methods should be used together:
 * - Use getConnectorId() to retrieve the unique identifier of the connector provided in the event.
 * - Check if the connector's ID matches the ID provided in the event.
 * - If the IDs match, use setScript() to provide the JavaScript code for the data connector.
 *
 * This ensures that the correct script is supplied by the appropriate connector.
 */
interface DataConnectorScriptProviderInterface
{
    /**
     * Get the unique identifier of the connector that is selected as the data source for the dataset.
     *
     * @return string
     */
    public function getConnectorId(): string;

    /**
     * Set the data connector JavaScript code provided by the connector. Requires real time.
     *
     * @param string $script JavaScript code
     * @return void
     */
    public function setScript(string $script): void;
}
