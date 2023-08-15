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

namespace Xibo\Listener;

use Carbon\Carbon;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\User;
use Xibo\Event\NotificationDataRequestEvent;
use Xibo\Factory\NotificationFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Widget\Provider\DataProviderInterface;

/**
 * Listens to request for data from Notification.
 */
class NotificationDataProviderListener
{

    use ListenerLoggerTrait;

    /** @var \Xibo\Service\ConfigServiceInterface */
    private $config;

    /** @var \Xibo\Factory\NotificationFactory */
    private $notificationFactory;

    /**
     * @var User
     */
    private $user;

    public function __construct(
        ConfigServiceInterface $config,
        NotificationFactory $notificationFactory,
        User $user
    ) {
        $this->config = $config;
        $this->notificationFactory = $notificationFactory;
        $this->user = $user;
    }

    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): NotificationDataProviderListener
    {
        $dispatcher->addListener(NotificationDataRequestEvent::$NAME, [$this, 'onDataRequest']);
        return $this;
    }

    public function onDataRequest(NotificationDataRequestEvent $event)
    {
        $this->getData($event->getDataProvider());
    }

    private function getData(DataProviderInterface $dataProvider)
    {
        $age = $dataProvider->getProperty('age', 0);

        $filter = [
            'releaseDt' => ($age === 0) ? null : Carbon::now()->subMinutes($age)->unix(),
            'onlyReleased' => 1,
        ];

        if ($dataProvider->isPreview()) {
            $filter['userId'] = $this->user->getId();
        } else {
            $filter['displayId'] = $dataProvider->getDisplayId();
        }

        $sort = ['releaseDt DESC', 'createDt DESC', 'subject'];

        $notifications = $this->notificationFactory->query($sort, $filter);

        foreach ($notifications as $notification) {
            $item = [];
            $item['subject'] = $notification->subject;
            $item['body'] = strip_tags($notification->body);
            $item['date'] = Carbon::createFromTimestamp($notification->releaseDt)->format('c');
            $item['createdAt'] = Carbon::createFromTimestamp($notification->createDt)->format('c');

            $dataProvider->addItem($item);
        }

        $dataProvider->setIsHandled();
    }
}
