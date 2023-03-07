<?php
/*
 * Copyright (c) 2023  Xibo Signage Ltd
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
 *
 */
namespace Xibo\Event;

use Xibo\Widget\Provider\DataProviderInterface;

/**
 * Event raised when a widget requests snippets.
 */
class WidgetSnippetsRequestEvent extends Event
{
    public static $NAME = 'widget.snippets.request.event';

    /** @var string The Data Provider */
    private $dataProvider;

    /** @var string[] */
    private $snippets = [];

    public function __construct(DataProviderInterface $dataProvider)
    {
        $this->dataProvider = $dataProvider;
    }

    /**
     * Get the dataType this event has been raised for
     * @return string
     */
    public function getDataType(): string
    {
        return $this->dataProvider->getDataType();
    }

    public function addSnippets(array $snippets): WidgetSnippetsRequestEvent
    {
        $this->snippets = array_merge($this->snippets, $snippets);
        return $this;
    }

    /**
     * Return the array of snippets
     * @return array
     */
    public function getSnippets(): array
    {
        return $this->snippets;
    }
}
