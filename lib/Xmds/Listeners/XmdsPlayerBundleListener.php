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
use Xibo\Listener\ListenerLoggerTrait;

/**
 * XMDS player bundle listener
 *  responsible for adding the player bundle to the list of required files, and for returning the player bundle
 *  when requested.
 */
class XmdsPlayerBundleListener
{
    use ListenerLoggerTrait;

    public function onDependencyList(XmdsDependencyListEvent $event)
    {
        $this->getLogger()->debug('onDependencyList: XmdsPlayerBundleListener');

        // Output the player bundle
        $bundlePath = PROJECT_ROOT . '/modules/bundle.min.js';
        $bundleSize = filesize($bundlePath);

        $event->addDependency(
            'bundle',
            1,
            PROJECT_ROOT . '/modules/bundle.min.js',
            $bundleSize,
            md5_file($bundlePath),
            false,
            -1
        );
    }

    public function onDependencyRequest(XmdsDependencyRequestEvent $event)
    {
        // Can we return this type of file?
        if ($event->getFileType() === 'bundle' && $event->getRealId() == 1) {
            // Yes!
            // we only set a full path as this file not available over HTTP (it can't be because it isn't stored
            // under the library folder).
            $event->setFullPath(PROJECT_ROOT . '/modules/bundle.min.js');

            // No need to carry on, we've found it.
            $event->stopPropagation();
        }
    }
}
