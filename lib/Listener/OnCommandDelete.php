<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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

namespace Xibo\Listener;

use Xibo\Event\CommandDeleteEvent;
use Xibo\Factory\DisplayProfileFactory;

class OnCommandDelete
{
    /**
     * @var DisplayProfileFactory
     */
    private $displayProfileFactory;

    public function __construct(DisplayProfileFactory $displayProfileFactory)
    {
        $this->displayProfileFactory = $displayProfileFactory;
    }

    /**
     * @throws \Xibo\Support\Exception\NotFoundException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function __invoke(CommandDeleteEvent $event)
    {
        $command = $event->getCommand();

        foreach ($this->displayProfileFactory->getByCommandId($command->commandId) as $displayProfile) {
            $displayProfile->unassignCommand($command);
            $displayProfile->save(['validate' => false]);
        }
    }
}
