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

namespace Xibo\XTR;

use Carbon\Carbon;
use Xibo\Helper\DateFormatHelper;

class PurgeListCleanupTask implements TaskInterface
{
    use TaskTrait;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->store = $container->get('store');
        $this->config = $container->get('configService');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $this->tidyPurgeList();
    }

    public function tidyPurgeList()
    {
        $this->runMessage = '# ' . __('Purge List Cleanup Start') . PHP_EOL . PHP_EOL;

        $count = $this->store->update('DELETE FROM `purge_list` WHERE expiryDate < :expiryDate', [
            'expiryDate' => Carbon::now()->format(DateFormatHelper::getSystemFormat())
        ]);

        if ($count <= 0) {
            $this->appendRunMessage('# ' . __('Nothing to remove') . PHP_EOL . PHP_EOL);
        } else {
            $this->appendRunMessage('# ' . sprintf(__('Removed %d rows'), $count) . PHP_EOL . PHP_EOL);
        }
    }
}
