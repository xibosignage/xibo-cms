<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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
namespace Xibo\Widget;


use Xibo\Helper\Sanitize;

class Video extends ModuleWidget
{
    /**
     * Edit Media in the Database
     */
    public function edit()
    {
        // Set the properties specific to this module
        if (Sanitize::getCheckbox('playUntilEnd', 0) == 1)
            $this->setDuration(0);
        else
            $this->setDuration(Sanitize::getInt('duration', $this->getDuration()));

        $this->setOption('name', Sanitize::getString('name', $this->getOption('name')));
        $this->setOption('mute', Sanitize::getCheckbox('mute'));

        // Only loop if the duration is > 0
        if ($this->getDuration() == 0)
            $this->setOption('loop', 0);
        else
            $this->setOption('loop', Sanitize::getCheckbox('loop'));

        $this->saveWidget();
    }

    /**
     * Get Resource
     * @param int $displayId
     * @return mixed
     */
    public function getResource($displayId = 0)
    {
        $this->download();
    }

    /**
     * Is this module valid
     * @return int
     */
    public function isValid()
    {
        // Yes
        return 1;
    }
}
