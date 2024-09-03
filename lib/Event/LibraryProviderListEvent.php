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

use Xibo\Connector\ProviderDetails;

class LibraryProviderListEvent extends Event
{
    protected static $NAME = 'connector.provider.library.list';
    /**
     * @var array
     */
    private mixed $providers;

    public function __construct($providers = [])
    {
        $this->providers = $providers;
    }

    /**
     * @param ProviderDetails $provider
     * @return LibraryProviderListEvent
     */
    public function addProvider(ProviderDetails $provider): LibraryProviderListEvent
    {
        $this->providers[] = $provider;
        return $this;
    }

    /**
     * @return ProviderDetails[]
     */
    public function getProviders(): array
    {
        return $this->providers;
    }
}
