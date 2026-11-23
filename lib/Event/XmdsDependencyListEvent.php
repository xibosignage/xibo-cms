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

use Xibo\Entity\Display;
use Xibo\Xmds\Entity\Dependency;

/**
 * A dependency list event
 */
class XmdsDependencyListEvent extends Event
{
    private static $NAME = 'xmds.dependency.list';

    private $dependencies = [];

    /** @var \Xibo\Entity\Display */
    private $display;

    /**
     * @param bool $needsFileMetadata false when only the dependencies' paths are used, so size and md5 may be zero
     */
    public function __construct(Display $display, private readonly bool $needsFileMetadata = true)
    {
        $this->display = $display;
    }

    public function needsFileMetadata(): bool
    {
        return $this->needsFileMetadata;
    }

    /**
     * @return Dependency[]
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * Add a dependency to the list.
     * @param string $fileType
     * @param int $id
     * @param string $path
     * @param int $size
     * @param string $md5
     * @param bool $isAvailableOverHttp
     * @param int $legacyId
     * @return $this
     */
    public function addDependency(
        string $fileType,
        int $id,
        string $path,
        int $size,
        string $md5,
        bool $isAvailableOverHttp,
        int $legacyId
    ): XmdsDependencyListEvent {
        $this->dependencies[] = new Dependency($fileType, $id, $legacyId, $path, $size, $md5, $isAvailableOverHttp);
        return $this;
    }

    /**
     * Get the display which raised this event
     * @return \Xibo\Entity\Display
     */
    public function getDisplay(): Display
    {
        return $this->display;
    }
}
