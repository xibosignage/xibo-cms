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
use Xibo\Factory\MediaFactory;
use Xibo\Helper\DateFormatHelper;

/**
 * Class ClearCachedMediaDataTask
 * @package Xibo\XTR
 */
class ClearCachedMediaDataTask implements TaskInterface
{
    use TaskTrait;

    /** @var MediaFactory */
    private $mediaFactory;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->mediaFactory = $container->get('mediaFactory');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $this->runMessage = '# ' . __('Clear Cached Media Data') . PHP_EOL . PHP_EOL;

        // Long running task
        set_time_limit(0);

        $this->runClearCache();
    }

    /**
     * Updates all md5/filesizes to empty for any image/module file created since 2.2.0 release date
     */
    private function runClearCache()
    {

        $cutOffDate = Carbon::createFromFormat('Y-m-d', '2019-11-26')->startOfDay()->format(DateFormatHelper::getSystemFormat());

        // Update the MD5 and fileSize to null
        $this->store->update('UPDATE `media` SET md5 = :md5, fileSize = :fileSize, modifiedDt = :modifiedDt
        WHERE (`media`.type = \'image\' OR (`media`.type = \'module\' AND `media`.moduleSystemFile = 0)) AND createdDt >= :createdDt ', [
            'fileSize' => null,
            'md5' => null,
            'createdDt' => $cutOffDate,
            'modifiedDt' => date(DateFormatHelper::getSystemFormat())

        ]);

        // Disable the task
        $this->appendRunMessage('# Disabling task.');

        $this->getTask()->isActive = 0;
        $this->getTask()->save();

        $this->appendRunMessage(__('Done.'. PHP_EOL));


    }
}