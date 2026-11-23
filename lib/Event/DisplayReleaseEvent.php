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

/**
 * Raised when an authorised display is un-authorised or deleted, so a listener can give up whatever the
 * authorisation took (a licence slot). The display is saved or deleted whatever the listeners do: work outside the
 * database should wait for the transaction to commit.
 */
class DisplayReleaseEvent extends Event
{
    public static $NAME = 'display.release';

    /**
     * @param Display $display
     * @param bool $isDelete whether the display is being deleted, rather than un-authorised
     * @param string|null $hardwareKey the hardware key that held the authorisation, when not the display's own
     *   (the key is being changed in the same save)
     */
    public function __construct(
        private readonly Display $display,
        private readonly bool $isDelete,
        private readonly ?string $hardwareKey = null
    ) {
    }

    public function getDisplay(): Display
    {
        return $this->display;
    }

    /**
     * Whether the display is being deleted, rather than un-authorised.
     */
    public function isDelete(): bool
    {
        return $this->isDelete;
    }

    /**
     * The hardware key that held the authorisation.
     */
    public function getHardwareKey(): string
    {
        return (string)($this->hardwareKey ?? $this->display->license);
    }
}
