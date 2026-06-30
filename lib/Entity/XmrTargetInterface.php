<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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
namespace Xibo\Entity;

/**
 * Minimal contract required to dispatch a PlayerAction via XMR.
 *
 * Implemented by Display and by DisplayXmrTarget (a lightweight DTO used when
 * only a subset of Display data is available, e.g. inside DisplayNotifyService).
 *
 * Implementors must expose these public properties:
 *   public int     $displayId
 *   public ?string $xmrChannel  (null when display has not yet registered)
 *   public ?string $xmrPubKey   (null when display has not yet registered)
 *   public string  $display
 *   public string  $clientType
 *   public int     $clientCode
 */
interface XmrTargetInterface
{
    public function isWebSocketXmrSupported(): bool;
}
