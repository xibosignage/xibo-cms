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

use Xibo\Xmds\Entity\Dependency;

/**
 * A dependency list event
 */
class XmdsDependencyListEvent extends Event
{
    private static $NAME = 'xmds.dependency.list';

    private $dependencies = [];
    /**
     * @var int
     */
    private $playerVersionId = null;

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
     * @return $this
     */
    public function addDependency(
        string $fileType,
        int $id,
        string $path,
        int $size,
        string $md5,
        bool $isAvailableOverHttp
    ): XmdsDependencyListEvent {
        $this->dependencies[] = new Dependency($fileType, $id, $path, $size, $md5, $isAvailableOverHttp);
        return $this;
    }

    public function setPlayerVersion(int $playerVersionId)
    {
        $this->playerVersionId = $playerVersionId;
    }

    public function getPlayerVersion()
    {
        return $this->playerVersionId;
    }
}
