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
namespace Xibo\Event;

use Xibo\Widget\Provider\DataProviderInterface;

/**
 * Event raised when a widget requests data.
 */
class WidgetDataRequestEvent extends Event
{
    public static $NAME = 'widget.data.request.event';
    
    /** @var \Xibo\Widget\Provider\DataProviderInterface */
    private $dataProvider;

    public function __construct(DataProviderInterface $dataProvider)
    {
        $this->dataProvider = $dataProvider;
    }

    /**
     * The data provider should be updated with data for its widget.
     * @return \Xibo\Widget\Provider\DataProviderInterface
     */
    public function getDataProvider(): DataProviderInterface
    {
        return $this->dataProvider;
    }
}
