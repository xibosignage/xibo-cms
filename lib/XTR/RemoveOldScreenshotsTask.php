<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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
use Xibo\Factory\MediaFactory;

class RemoveOldScreenshotsTask implements TaskInterface
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
        $this->runMessage = '# ' . __('Remove Old Screenshots') . PHP_EOL . PHP_EOL;

        $screenshotLocation = $this->config->getSetting('LIBRARY_LOCATION') . 'screenshots/';
        $screenshotTTL = $this->config->getSetting('DISPLAY_SCREENSHOT_TTL');
        $count = 0;

        if ($screenshotTTL > 0) {
            foreach (array_diff(scandir($screenshotLocation), ['..', '.']) as $file) {
                $fileLocation = $screenshotLocation . $file;

                $lastModified = Carbon::createFromTimestamp(filemtime($fileLocation));
                $now = Carbon::now();
                $diff = $now->diffInDays($lastModified);

                if ($diff > $screenshotTTL) {
                    unlink($fileLocation);
                    $count++;

                    $this->log->debug('Removed old Display screenshot:' . $file);
                }
            }
            $this->appendRunMessage(sprintf(__('Removed %d old Display screenshots'), $count));
        } else {
            $this->appendRunMessage(__('Display Screenshot Time to keep set to 0, nothing to remove.'));
        }
    }
}