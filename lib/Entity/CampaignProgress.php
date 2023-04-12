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
 * Campaign Progress
 */
class CampaignProgress implements \JsonSerializable
{
    /** @var int */
    public $daysIn = 0;

    /** @var int */
    public $daysTotal = 0;

    /** @var float */
    public $targetPerDay = 0.0;

    /** @var float */
    public $progressTime = 0.0;

    /** @var float */
    public $progressTarget = 0.0;

    /** @inheritDoc */
    public function jsonSerialize(): array
    {
        return [
            'daysIn' => $this->daysIn,
            'daysTotal' => $this->daysTotal,
            'targetPerDay' => $this->targetPerDay,
            'progressTime' => $this->progressTime,
            'progressTarget' => $this->progressTarget,
        ];
    }
}
