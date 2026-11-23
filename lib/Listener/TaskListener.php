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

namespace Xibo\Listener;

use Stash\Interfaces\PoolInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\TriggerTaskEvent;
use Xibo\Factory\TaskFactory;
use Xibo\Service\ConfigServiceInterface;

/**
 * A listener for events related to tasks
 */
class TaskListener
{
    use ListenerLoggerTrait;

    public function __construct(
        private readonly TaskFactory $taskFactory,
        private readonly ConfigServiceInterface $configService,
        private readonly PoolInterface $pool
    ) {
    }

    /**
     * @param EventDispatcherInterface $dispatcher
     * @return $this
     */
    public function registerWithDispatcher(EventDispatcherInterface $dispatcher) : TaskListener
    {
        $dispatcher->addListener(TriggerTaskEvent::$NAME, [$this, 'onTriggerTask']);

        return $this;
    }

    /**
     * @param TriggerTaskEvent $event
     * @return void
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function onTriggerTask(TriggerTaskEvent $event): void
    {
        if (!empty($event->getKey())) {
            // Drop this setting from the cache
            $this->pool->deleteItem($event->getKey());
        }

        // Mark the task to run now
        $task = $this->taskFactory->getByClass($event->getClassName());
        $task->runNow = 1;
        $task->save(['validate' => false]);
    }
}
