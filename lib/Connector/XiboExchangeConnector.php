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
use Xibo\Event\TemplateProviderEvent;

/**
 * XiboExchangeConnector
 * ---------------------
 * This connector will consume the Xibo Layout Exchange API and offer pre-built templates for selection when adding
 * a new layout.
 *
 * This is a work in progress and is currently disabled pending work on the Layout Exchange API.
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
        //$dispatcher->addListener('connector.provider.template', [$this, 'onTemplateProvider']);
        return $this;
    }

    /**
     * @param \Xibo\Event\TemplateProviderEvent $event
     */
    public function onTemplateProvider(TemplateProviderEvent $event)
    {
        $this->getLogger()->debug('XiboExchangeConnector: onTemplateProvider');
    }

    public function getSourceName(): string
    {
        return 'xibo-exchange';
    }
}
