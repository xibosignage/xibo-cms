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

use Xibo\Connector\ProviderImport;

/**
 * Event raised when one or more provider search results have been chosen for importing on a layout
 */
class LibraryProviderImportEvent extends Event
{
    protected static $NAME = 'connector.provider.library.import';

    /** @var ProviderImport[] */
    private $ids;

    /**
     * @param ProviderImport[] $ids
     */
    public function __construct(array $ids)
    {
        $this->ids = $ids;
    }

    public function getItems(): array
    {
        return $this->ids;
    }
}
