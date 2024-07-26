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
        $this->dataSet->saveScript($script);
    }
}
