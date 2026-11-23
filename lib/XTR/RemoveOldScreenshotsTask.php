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
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Support\Exception\GeneralException;

class RemoveOldScreenshotsTask implements TaskInterface
{
    use TaskTrait;

    /** @var LibraryStorageRegistryInterface */
    private $libraryStorageRegistry;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->libraryStorageRegistry = $container->get('libraryStorageRegistry');

        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $this->runMessage = '# ' . __('Remove Old Screenshots') . PHP_EOL . PHP_EOL;

        $screenshotTTL = $this->config->getSetting('DISPLAY_SCREENSHOT_TTL');
        $count = 0;

        if ($screenshotTTL > 0) {
            $now = Carbon::now();
            $storage = $this->libraryStorageRegistry->get(LibraryAsset::SCREENSHOT);

            foreach ($storage->listKeys() as $item) {
                // Keep a screenshot whose age is unknown rather than guess.
                if ($item['lastModified'] === null) {
                    continue;
                }

                $lastModified = DateFormatHelper::createFromTimestamp($item['lastModified']);
                $diff = $now->diffInDays($lastModified, true);

                if ($diff > $screenshotTTL) {
                    try {
                        $storage->delete($item['key']);
                    } catch (GeneralException $exception) {
                        $this->log->error('Unable to remove old Display screenshot ' . $item['key'] . ': '
                            . $exception->getMessage());
                        continue;
                    }
                    $count++;

                    $this->log->debug('Removed old Display screenshot:' . $item['key']);
                }
            }
            $this->appendRunMessage(sprintf(__('Removed %d old Display screenshots'), $count));
        } else {
            $this->appendRunMessage(__('Display Screenshot Time to keep set to 0, nothing to remove.'));
        }
    }
}
