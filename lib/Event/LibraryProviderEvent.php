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

namespace Xibo\Event;

use Xibo\Entity\SearchResult;
use Xibo\Entity\SearchResults;

/**
 * LibraryProviderEvent
 */
class LibraryProviderEvent extends Event
{
    protected static $NAME = 'connector.provider.library';

    /** @var \Xibo\Entity\SearchResults */
    private $results;

    /** @var int Record count to start from */
    private $start;

    /** @var int Number of records to return */
    private $length;

    /** @var string */
    private $search;

    /** @var array */
    private $types;

    /** @var string landspace|portrait or empty */
    private $orientation;
    /** @var string provider name */
    private $provider;

    /**
     * @param \Xibo\Entity\SearchResults $results
     * @param $start
     * @param $length
     * @param $search
     * @param $types
     * @param $orientation
     * @param $provider
     */
    public function __construct(SearchResults $results, $start, $length, $search, $types, $orientation, $provider)
    {
        $this->results = $results;
        $this->start = $start;
        $this->length = $length;
        $this->search = $search;
        $this->types = $types;
        $this->orientation = $orientation;
        $this->provider = $provider;
    }

    public function addResult(SearchResult $result): LibraryProviderEvent
    {
        $this->results->data[] = $result;
        return $this;
    }

    public function getResults(): SearchResults
    {
        return $this->results;
    }

    /**
     * Get starting record
     * @return int
     */
    public function getStart(): int
    {
        return $this->start;
    }

    /**
     * Get number of records to return
     * @return int
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * @return string
     */
    public function getSearch()
    {
        return $this->search;
    }

    /**
     * @return array
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * @return string
     */
    public function getOrientation()
    {
        return $this->orientation;
    }

    public function getProviderName()
    {
        return $this->provider;
    }
}
