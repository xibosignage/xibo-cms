<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\TriggerTaskEvent;
use Xibo\Factory\TaskFactory;
use Xibo\Service\ConfigServiceInterface;

/**
 * Task events
 */
class TaskListener
{
    use ListenerLoggerTrait;

    /**
     * @var TaskFactory
     */
    private $taskFactory;
    /**
     * @var ConfigServiceInterface
     */
    private $configService;

    public function __construct(
        TaskFactory $taskFactory,
        ConfigServiceInterface $configService
    ) {
        $this->taskFactory = $taskFactory;
        $this->configService = $configService;
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
    public function onTriggerTask(TriggerTaskEvent $event)
    {
        if (!empty($event->getSetting()) && !empty($event->getSettingValue())) {
            $this->configService->changeSetting($event->getSetting(), $event->getSettingValue());
        }

        $task = $this->taskFactory->getByClass($event->getClassName());
        $task->runNow = 1;
        $task->save();
    }
}
