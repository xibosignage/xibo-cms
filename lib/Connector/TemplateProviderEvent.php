<?php
/*
 * Copyright (C) 2021 Xibo Signage Ltd
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

use Xibo\Entity\SearchResult;
use Xibo\Entity\SearchResults;
use Xibo\Event\Event;

/**
 * TemplateProviderEvent
 */
class TemplateProviderEvent extends Event
{
    protected static $NAME = 'connector.provider.template';

    /** @var \Xibo\Entity\SearchResults */
    private $results;

    /**
     * @param \Xibo\Entity\SearchResults $results
     */
    public function __construct(SearchResults $results)
    {
        $this->results = $results;
    }

    public function addResult(SearchResult $result): TemplateProviderEvent
    {
        $this->results->data[] = $result;
        return $this;
    }

    public function getResults(): SearchResults
    {
        return $this->results;
    }
}