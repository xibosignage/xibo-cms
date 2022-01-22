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

/**
 * Event raised when one or more provider search results have been chosen for importing on a layout
 */
class TemplateProviderImportEvent extends Event
{
    protected static $NAME = 'connector.provider.template.import';
    /**
     * @var string
     */
    private $downloadUrl;
    /** @var string */
    private $libraryLocation;
    /**
     * @var string
     */
    private $fileName;
    /** @var string */
    private $tempFile;

    public function __construct(
        string $uri,
        string $fileName,
        string $libraryLocation
    ) {
        $this->downloadUrl = $uri;
        $this->fileName = $fileName;
        $this->libraryLocation = $libraryLocation;
    }

    public function getDownloadUrl(): string
    {
        return $this->downloadUrl;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getLibraryLocation(): string
    {
        return $this->libraryLocation;
    }

    public function setFilePath($tempFile)
    {
        $this->tempFile = $tempFile;
    }

    public function getFilePath()
    {
        return $this->tempFile;
    }
}
