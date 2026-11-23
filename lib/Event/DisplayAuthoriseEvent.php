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
 * Raised when a display is being authorised, so a listener can refuse it. One event object follows the
 * authorisation through two steps, each dispatched under its own name:
 *
 * - REQUEST, before the display is saved: a listener may refuse, with a message for the user.
 * - SAVED, after the display is saved, whether it was authorised or refused (a display added by a player
 *   is saved unauthorised when refused).
 *
 * Saving can still fail, or the request roll back, after REQUEST: work outside the database should be undone
 * when the transaction rolls back (StorageServiceInterface::afterRollback()).
 *
 * Without a listener every display is authorised.
 */
class DisplayAuthoriseEvent extends Event
{
    public static $NAME = 'display.authorise.request';
    public static $NAME_SAVED = 'display.authorise.saved';

    /** Whether the display was not yet saved when it was authorised (a new display added by a player). */
    private bool $isNewDisplay;

    private ?string $refusal = null;

    /** Anything a listener needs to carry from REQUEST to SAVED. */
    private mixed $listenerData = null;

    public function __construct(private readonly Display $display)
    {
        $this->isNewDisplay = empty($display->displayId);
    }

    public function getDisplay(): Display
    {
        return $this->display;
    }

    public function isNewDisplay(): bool
    {
        return $this->isNewDisplay;
    }

    /**
     * Refuses the authorisation, with the message to show the user.
     */
    public function refuse(string $message): void
    {
        $this->refusal = $message;
    }

    public function isRefused(): bool
    {
        return $this->refusal !== null;
    }

    /**
     * The message to show the user, or null when the authorisation was not refused.
     */
    public function getRefusal(): ?string
    {
        return $this->refusal;
    }

    public function setListenerData(mixed $data): void
    {
        $this->listenerData = $data;
    }

    public function getListenerData(): mixed
    {
        return $this->listenerData;
    }
}
