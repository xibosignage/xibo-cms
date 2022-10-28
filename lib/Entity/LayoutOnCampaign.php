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

namespace Xibo\Entity;

/**
 * @SWG\Definition("Layout linked to a Campaign")
 * @property bool $locked Is this layout locked
 */
class LayoutOnCampaign implements \JsonSerializable
{
    use EntityTrait;

    public $lkCampaignLayoutId;
    public $campaignId;
    public $layoutId;
    public $displayOrder;

    public $dayPartId;
    public $daysOfWeek;
    public $geoFence;

    /** @var string The Layout name (readonly) */
    public $layout;

    /** @var int The owner id (readonly) */
    public $ownerId;

    /** @var int The duration (readonly) */
    public $duration;

    /** @var string The dayPart (readonly) */
    public $dayPart;
}
