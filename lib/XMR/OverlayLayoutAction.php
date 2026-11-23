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

namespace Xibo\XMR;

/**
 * Class OverlayLayoutAction
 * @package Xibo\XMR
 */
class OverlayLayoutAction extends PlayerAction
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
     * @param int $duration the duration this layout should be overlaid
     * @param bool|false $downloadRequired flag indicating whether a download is required before changing to the layout
     * @return $this
     */
    public function setLayoutDetails($layoutId, $duration, $downloadRequired = false)
    {
        $this->layoutId = $layoutId;
        $this->duration = $duration;
        $this->downloadRequired = $downloadRequired;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getMessage(): string
    {
        $this->action = 'overlayLayout';

        if ($this->layoutId == 0) {
            throw new PlayerActionException(__('Layout Details not provided'));
        }

        if ($this->duration == 0) {
            throw new PlayerActionException(__('Duration not provided'));
        }

        return $this->serializeToJson(['layoutId', 'duration', 'downloadRequired']);
    }
}
