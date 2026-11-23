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

namespace Xibo\Xmds\Listeners;

use Stash\Interfaces\PoolInterface;
use Xibo\Event\XmdsDependencyListEvent;
use Xibo\Event\XmdsDependencyRequestEvent;
use Xibo\Factory\FontFactory;
use Xibo\Helper\StoredFileMetadata;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Xmds\Entity\Dependency;

/**
 * A listener to supply fonts as dependencies to players.
 */
class XmdsFontsListener
{
    use ListenerLoggerTrait;

    use XmdsListenerTrait;

    /**
     * @var FontFactory
     */
    private $fontFactory;

    public function __construct(FontFactory $fontFactory, private readonly PoolInterface $pool)
    {
        $this->fontFactory = $fontFactory;
    }

    public function onDependencyList(XmdsDependencyListEvent $event): void
    {
        $this->getLogger()->debug('onDependencyList: XmdsFontsListener');

        foreach ($this->fontFactory->query() as $font) {
            $event->addDependency(
                'font',
                $font->id,
                'fonts/' . $font->fileName,
                $font->size,
                $font->md5,
                true,
                $this->getLegacyId($font->id, Dependency::LEGACY_ID_OFFSET_FONT)
            );
        }

        // Always add fonts.css to the list.
        // This can have the ID of 1 because it is a different file type and will therefore be unique.
        try {
            $fontsCssMetadata = StoredFileMetadata::get(
                $this->pool,
                FontFactory::FONTS_CSS_METADATA_CACHE_KEY,
                fn () => $this->fontFactory->getStorage(),
                FontFactory::FONTS_CSS_KEY
            );
        } catch (NotFoundException) {
            $this->getLogger()->error('onDependencyList: fonts.css has not been generated yet.');
            return;
        } catch (GeneralException $exception) {
            // A storage outage fails RequiredFiles, so players keep the fonts.css they have; a list of names needs none.
            if ($event->needsFileMetadata()) {
                throw $exception;
            }
            $fontsCssMetadata = ['size' => 0, 'md5' => ''];
        }

        $event->addDependency(
            'fontCss',
            1,
            'fonts/fonts.css',
            $fontsCssMetadata['size'],
            $fontsCssMetadata['md5'],
            true,
            $this->getLegacyId(0, Dependency::LEGACY_ID_OFFSET_FONT)
        );
    }

    public function onDependencyRequest(XmdsDependencyRequestEvent $event): void
    {
        $this->getLogger()->debug('onDependencyRequest: XmdsFontsListener');

        if ($event->getFileType() === 'font') {
            $font = $this->fontFactory->getById($event->getRealId());
            $event->setRelativePathToLibrary('fonts/' . $font->fileName);
            $event->setStorage($this->fontFactory->getStorage(), $font->fileName);
        } else if ($event->getFileType() === 'fontCss') {
            $event->setRelativePathToLibrary('fonts/fonts.css');
            $event->setStorage($this->fontFactory->getStorage(), FontFactory::FONTS_CSS_KEY);
        }
    }
}
