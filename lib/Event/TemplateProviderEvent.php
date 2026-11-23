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

use Xibo\Entity\SearchResult;
use Xibo\Entity\SearchResults;

/**
 * TemplateProviderEvent
 */
class TemplateProviderEvent extends Event
{
    protected static $NAME = 'connector.provider.template';

    /** @var \Xibo\Entity\SearchResults */
    private $results;

    /**
     * @var int
     */
    private $start;

    /**
     * @var int
     */
    private $length;

    /** @var string|null */
    private $search;

    /** @var string|null */
    private $orientation;

    /**
     * @param \Xibo\Entity\SearchResults $results
     * @param int $start
     * @param int $length
     */
    public function __construct(SearchResults $results, int $start, int $length, ?string $search, ?string $orientation)
    {
        $this->results = $results;
        $this->start = $start;
        $this->length = $length;
        $this->search = $search;
        $this->orientation = $orientation;
    }

    /**
     * @param SearchResult $result
     * @return $this
     */
    public function addResult(SearchResult $result): TemplateProviderEvent
    {
        $this->results->data[] = $result;
        return $this;
    }

    /**
     * @return SearchResults
     */
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
     * @return string|null
     */
    public function getSearch(): ?string
    {
        return $this->search;
    }

    /**
     * @return string|null
     */
    public function getOrientation(): ?string
    {
        return $this->orientation;
    }
}
