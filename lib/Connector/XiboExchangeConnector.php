<?php
/*
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

namespace Xibo\Connector;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\SearchResult;
use Xibo\Event\TemplateProviderEvent;

/**
 * XiboExchangeConnector
 */
class XiboExchangeConnector implements ConnectorInterface
{
    use ConnectorTrait;

    /**
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @return \Xibo\Connector\ConnectorInterface
     */
    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): ConnectorInterface
    {
        $dispatcher->addListener('connector.provider.template', [$this, 'onTemplateProvider']);
        return $this;
    }

    /**
     * @param \Xibo\Event\TemplateProviderEvent $event
     */
    public function onTemplateProvider(TemplateProviderEvent $event)
    {
        $this->getLogger()->debug('onTemplateProvider');

        // Mimic some network
        sleep(2);

        // Add some random events.
        for ($i = 0; $i < 10; $i++) {
            $searchResult = new SearchResult();
            $searchResult->id = $i;
            $searchResult->source = $this->getSourceName();
            $searchResult->title = $i;
            $searchResult->description = $i . 'desc';
            $event->addResult($searchResult);
        }
    }

    public function getSourceName(): string
    {
        return 'xibo-exchange';
    }
}
