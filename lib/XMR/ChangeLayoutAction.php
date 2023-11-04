<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

namespace Xibo\XMR;

class ChangeLayoutAction extends PlayerAction
{
    public $layoutId;
    public $duration;
    public $downloadRequired;
    public $changeMode;

    public function __construct()
    {
        $this->setQos(10);
    }

    /**
     * Set details for this layout
     * @param int $layoutId the layoutId to change to
     * @param int $duration the duration this layout should be shown
     * @param bool|false $downloadRequired flag indicating whether a download is required before changing to the layout
     * @param string $changeMode whether to queue or replace
     * @return $this
     */
    public function setLayoutDetails($layoutId, $duration = 0, $downloadRequired = false, $changeMode = 'queue')
    {
        if ($duration === null) {
            $duration = 0;
        }

        $this->layoutId = $layoutId;
        $this->duration = $duration;
        $this->downloadRequired = $downloadRequired;
        $this->changeMode = $changeMode;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): string
    {
        $this->action = 'changeLayout';

        if ($this->layoutId == 0) {
            throw new PlayerActionException('Layout Details not provided');
        }

        return $this->serializeToJson(['layoutId', 'duration', 'downloadRequired', 'changeMode']);
    }
}
