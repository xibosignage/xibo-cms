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

namespace Xibo\Helper;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\Display;
use Xibo\Event\DisplayGroupLoadEvent;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Exception\GeneralException;

/**
 * Assigns a newly added display to the display group chosen on the Add Display form.
 *
 * Shared by RegisterDisplay, which applies activation-code settings, and the Add Display endpoint
 * that applies Manual Configure settings once the Player has connected.
 */
class DisplayClaimGroup
{
    /**
     * Assign the display to the group. A failure is logged rather than thrown, so the rest of the
     * settings still stand.
     *
     * @return bool false if a group was chosen but the display could not be added to it
     */
    public static function assign(
        DisplayGroupFactory $displayGroupFactory,
        EventDispatcherInterface $dispatcher,
        LogServiceInterface $log,
        Display $display,
        int $displayGroupId
    ): bool {
        if ($displayGroupId <= 0) {
            return true;
        }

        try {
            $displayGroup = $displayGroupFactory->getById($displayGroupId);
            $displayGroup->load();
            $dispatcher->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);

            $displayGroup->assignDisplay($display);
            $displayGroup->save(['validate' => false]);
        } catch (GeneralException $e) {
            $log->error('assignClaimDisplayGroup: cannot assign displayId '
                . $display->displayId . ' to displayGroupId ' . $displayGroupId
                . ', e = ' . $e->getMessage());

            return false;
        }

        return true;
    }
}
