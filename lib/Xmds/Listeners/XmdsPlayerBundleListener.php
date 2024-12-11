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

namespace Xibo\Xmds\Listeners;

use Xibo\Event\XmdsDependencyListEvent;
use Xibo\Event\XmdsDependencyRequestEvent;
use Xibo\Listener\ListenerCacheTrait;
use Xibo\Listener\ListenerConfigTrait;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Support\Exception\GeneralException;

/**
 * XMDS player bundle listener
 *  responsible for adding the player bundle to the list of required files, and for returning the player bundle
 *  when requested.
 */
class XmdsPlayerBundleListener
{
    use ListenerLoggerTrait;
    use ListenerConfigTrait;

    public function onDependencyList(XmdsDependencyListEvent $event)
    {
        $this->getLogger()->debug('onDependencyList: XmdsPlayerBundleListener');

        // Output the player bundle
        $forceUpdate = false;
        $bundlePath = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'assets/player.bundle.min.js';
        if (!file_exists($bundlePath)) {
            $result = @copy(PROJECT_ROOT . '/modules/player.bundle.min.js', $bundlePath);
            if (!$result) {
                throw new GeneralException('Unable to copy asset');
            }
            $forceUpdate = true;
        }

        // Get the bundle MD5
        $bundleMd5CachePath = $bundlePath . '.md5';
        if (!file_exists($bundleMd5CachePath) || $forceUpdate) {
            $bundleMd5 = md5_file($bundlePath);
            file_put_contents($bundleMd5CachePath, $bundleMd5);
        } else {
            $bundleMd5 = file_get_contents($bundlePath . '.md5');
        }

        $event->addDependency(
            'bundle',
            1,
            'assets/player.bundle.min.js',
            filesize($bundlePath),
            $bundleMd5,
            true,
            -1
        );
    }

    public function onDependencyRequest(XmdsDependencyRequestEvent $event)
    {
        // Can we return this type of file?
        if ($event->getFileType() === 'bundle' && $event->getRealId() == 1) {
            // Set the path
            $event->setRelativePathToLibrary('assets/player.bundle.min.js');

            // No need to carry on, we've found it.
            $event->stopPropagation();
        }
    }
}
