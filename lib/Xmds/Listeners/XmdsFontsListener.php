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
use Xibo\Factory\FontFactory;
use Xibo\Listener\ListenerLoggerTrait;

/**
 * A listener to supply fonts as dependencies to players.
 */
class XmdsFontsListener
{
    use ListenerLoggerTrait;

    /**
     * @var FontFactory
     */
    private $fontFactory;

    public function __construct(FontFactory $fontFactory)
    {
        $this->fontFactory = $fontFactory;
    }

    public function onDependencyList(XmdsDependencyListEvent $event)
    {
        $this->getLogger()->debug('onDependencyList: XmdsFontsListener');

        foreach ($this->fontFactory->query() as $font) {
            $event->addDependency(
                'font',
                $font->id,
                'fonts/'.$font->fileName,
                $font->size,
                $font->md5,
                true,
                $this->getLegacyId($font->id)
            );
        }
        $fontsCssPath = PROJECT_ROOT . '/library/fonts/fonts.css';

        $event->addDependency(
            'fontCss',
            1,
            'fonts/fonts.css',
            filesize($fontsCssPath),
            md5($fontsCssPath),
            true,
            $this->getLegacyId(1)
        );
    }

    public function onDependencyRequest(XmdsDependencyRequestEvent $event)
    {
        $this->getLogger()->debug('onDependencyRequest: XmdsFontsListener');

        if ($event->getFileType() === 'font') {
            $font = $this->fontFactory->getById($event->getId());
            $event->setRelativePathToLibrary('/fonts/' . $font->fileName);
        } else if ($event->getFileType() === 'fontCss') {
            $event->setRelativePathToLibrary('/fonts/fonts.css');
        }
    }

    private function getLegacyId(int $id): int
    {
        return ($id + 100000000) * -1;
    }
}
