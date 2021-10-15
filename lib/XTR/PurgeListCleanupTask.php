<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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

namespace Xibo\XTR;

use Carbon\Carbon;
use Xibo\Helper\DateFormatHelper;

class PurgeListCleanupTask implements TaskInterface
{
    use TaskTrait;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->sanitizerService = $container->get('sanitizerService');
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
