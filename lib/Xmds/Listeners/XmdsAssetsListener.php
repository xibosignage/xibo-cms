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

use Xibo\Event\XmdsDependencyRequestEvent;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\ModuleTemplateFactory;
use Xibo\Listener\ListenerConfigTrait;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Support\Exception\NotFoundException;

/**
 * Listener which handles requests for assets.
 */
class XmdsAssetsListener
{
    use ListenerLoggerTrait;
    use ListenerConfigTrait;

    /** @var \Xibo\Factory\ModuleFactory */
    private $moduleFactory;

    /** @var \Xibo\Factory\ModuleTemplateFactory */
    private $moduleTemplateFactory;

    /**
     * @param \Xibo\Factory\ModuleFactory $moduleFactory
     * @param \Xibo\Factory\ModuleTemplateFactory $moduleTemplateFactory
     */
    public function __construct(ModuleFactory $moduleFactory, ModuleTemplateFactory $moduleTemplateFactory)
    {
        $this->moduleFactory = $moduleFactory;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
    }

    public function onDependencyRequest(XmdsDependencyRequestEvent $event): void
    {
        $this->getLogger()->debug('onDependencyRequest: XmdsAssetsListener');

        if ($event->getFileType() === 'asset') {
            // Get the asset using only the assetId.
            try {
                $asset = $this->moduleFactory
                    ->getAssetsFromAnywhereById($event->getRealId(), $this->moduleTemplateFactory);

                if ($asset->isSendToPlayer()) {
                    // Make sure the asset cache is there
                    $asset->updateAssetCache($this->getConfig()->getSetting('LIBRARY_LOCATION'));

                    // Return the full path to this asset
                    $event->setRelativePathToLibrary('assets/' . $asset->getFilename());
                    $event->stopPropagation();
                } else {
                    $this->getLogger()->debug('onDependencyRequest: asset found but is cms only');
                }
            } catch (NotFoundException $notFoundException) {
                $this->getLogger()->info('onDependencyRequest: No asset found for assetId: '
                    . $event->getRealId());
            }
        }
    }
}
