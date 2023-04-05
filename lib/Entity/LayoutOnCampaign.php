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

namespace Xibo\Entity;

/**
 * @SWG\Definition("Layout linked to a Campaign")
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

    /**
     * @SWG\Property(description="The Layout name (readonly)")
     * @var string
     */
    public $layout;

    /**
     * @SWG\Property(description="The Layout campaignId (readonly)")
     * @var string
     */
    public $layoutCampaignId;

    /**
     * @SWG\Property(description="The owner id (readonly))")
     * @var integer
     */
    public $ownerId;

    /**
     * @SWG\Property(description="The duration (readonly))")
     * @var integer
     */
    public $duration;

    /**
     * @SWG\Property(description="The dayPart (readonly)")
     * @var string
     */
    public $dayPart;
}
