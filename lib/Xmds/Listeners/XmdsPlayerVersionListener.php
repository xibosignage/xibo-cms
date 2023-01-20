<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

namespace Xibo\Xmds\Listeners;

use Xibo\Event\XmdsDependencyListEvent;
use Xibo\Event\XmdsDependencyRequestEvent;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Support\Exception\NotFoundException;

/**
 * A listener to supply player version files to players
 */
class XmdsPlayerVersionListener
{
    use ListenerLoggerTrait;

    /**
     * @var PlayerVersionFactory
     */
    private $playerVersionFactory;

    public function __construct(PlayerVersionFactory $playerVersionFactory)
    {
        $this->playerVersionFactory = $playerVersionFactory;
    }

    public function onDependencyList(XmdsDependencyListEvent $event)
    {
        $this->getLogger()->debug('onDependencyList: XmdsPlayerVersionListener');

        // We do not supply a dependency to SSSP players.
        if ($event->getDisplay()->clientType === 'sssp') {
            return;
        }

        try {
            $playerVersionMediaId = $event->getDisplay()
                ->getSetting('versionMediaId', null, ['displayOverride' => true]);

            // If it isn't set, then we have nothing to do.
            if (empty($playerVersionMediaId)) {
                return;
            }

            $version = $this->playerVersionFactory->getById($playerVersionMediaId);
            $event->addDependency(
                'playersoftware',
                $version->versionId,
                'playersoftware/' . $version->fileName,
                $version->size,
                $version->md5,
                true,
                $this->getLegacyId($playerVersionMediaId)
            );
        } catch (NotFoundException $notFoundException) {
            // Ignore this
            $this->getLogger()->error('onDependencyList: player version not found for displayId '
                . $event->getDisplay()->displayId);
        }
    }

    public function onDependencyRequest(XmdsDependencyRequestEvent $event)
    {
        $this->getLogger()->debug('onDependencyRequest: XmdsPlayerVersionListener');

        if ($event->getFileType() === 'playersoftware') {
            $version = $this->playerVersionFactory->getById($event->getId());
            $event->setRelativePathToLibrary('/playersoftware/' . $version->fileName);
        }
    }

    private function getLegacyId(int $id): int
    {
        return ($id + 200000000) * -1;
    }
}
