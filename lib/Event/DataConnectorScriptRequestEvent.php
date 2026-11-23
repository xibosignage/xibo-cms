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

use Xibo\Connector\DataConnectorScriptProviderInterface;
use Xibo\Entity\DataSet;

/**
 * Event triggered to retrieve the Data Connector JavaScript from a connector.
 */
class DataConnectorScriptRequestEvent extends Event implements DataConnectorScriptProviderInterface
{
    public static $NAME = 'data.connector.script.request';

    /**
     * @var DataSet
     */
    private $dataSet;

    /**
     * @param DataSet $dataSet
     */
    public function __construct(DataSet $dataSet)
    {
        $this->dataSet = $dataSet;
    }

    /**
     * @inheritDoc
     */
    public function getConnectorId(): string
    {
        return $this->dataSet->dataConnectorSource;
    }

    /**
     * @inheritDoc
     */
    public function setScript(string $script): void
    {
        if ($this->dataSet->isRealTime == 0) {
            return;
        }

        // Save the script.
        $this->dataSet->saveScript($script, true);
    }
}
