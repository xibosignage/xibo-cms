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

namespace Xibo\Xmds;

use Xibo\Entity\Bandwidth;
use Xibo\Entity\RequiredFile;
use Xibo\Factory\BandwidthFactory;

/**
 * Counts the bandwidth of files a player downloads directly, as those downloads never reach the CMS.
 */
class DirectDownloadBandwidth
{
    private int $claimedBytes = 0;

    public function __construct(
        private readonly BandwidthFactory $bandwidthFactory,
        private readonly int $displayId
    ) {
    }

    /**
     * Records whether the player has $file, counting a direct download the first time it is reported complete.
     */
    public function setComplete(RequiredFile $file, int $complete): void
    {
        if ($complete === 1 && (int)$file->isDirect === 1 && (int)$file->complete === 0) {
            if ($file->claimComplete()) {
                $this->claimedBytes += (int)$file->size;
            }
            return;
        }

        $file->complete = $complete;
        $file->save();
    }

    /**
     * Records the counted files as one GetFile record.
     */
    public function save(): void
    {
        if ($this->claimedBytes > 0) {
            $this->bandwidthFactory->createAndSave(Bandwidth::$GETFILE, $this->displayId, $this->claimedBytes);
        }
    }
}
