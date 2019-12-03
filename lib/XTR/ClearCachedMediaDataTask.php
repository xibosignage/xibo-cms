<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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
use Xibo\Factory\MediaFactory;
use Xibo\Service\DateServiceInterface;

/**
 * Class ClearCachedMediaDataTask
 * @package Xibo\XTR
 */
class ClearCachedMediaDataTask implements TaskInterface
{
    use TaskTrait;

    /** @var DateServiceInterface */
    private $date;

    /** @var MediaFactory */
    private $mediaFactory;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->date = $container->get('dateService');
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

        $cutOffDate = $this->date->parse('2019-11-26', 'Y-m-d')->startOfDay()->format('Y-m-d H:i:s');

        // Update the MD5 and fileSize to null
        $this->store->update('UPDATE `media` SET md5 = :md5, fileSize = :fileSize, modifiedDt = :modifiedDt
        WHERE (`media`.type = \'image\' OR (`media`.type = \'module\' AND `media`.moduleSystemFile = 0)) AND createdDt >= :createdDt ', [
            'fileSize' => null,
            'md5' => null,
            'createdDt' => $cutOffDate,
            'modifiedDt' => date('Y-m-d H:i:s')

        ]);

        // Disable the task
        $this->appendRunMessage('# Disabling task.');

        $this->getTask()->isActive = 0;
        $this->getTask()->save();

        $this->appendRunMessage(__('Done.'. PHP_EOL));


    }
}