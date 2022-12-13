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
use Xibo\Event\ConnectorReportEvent;
use Xibo\Storage\StorageServiceInterface;

/**
 * Connector report events
 */
class ConnectorReportListener
{
    use ListenerLoggerTrait;

    /** @var \Xibo\Storage\StorageServiceInterface  */
    private $storageService;

    public function __construct(
        StorageServiceInterface $storageService
    ) {
        $this->storageService = $storageService;
    }

    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): ConnectorReportListener
    {
        $dispatcher->addListener(ConnectorReportEvent::$NAME, [$this, 'onRequestReport']);
        return $this;
    }

    /**
     * Get reports
     * @param ConnectorReportEvent $event
     * @return void
     */
    public function onRequestReport(ConnectorReportEvent $event)
    {
        $this->getLogger()->debug('onRequestReport');

        $connectorReports = [
            [
                'name'=> 'campaignProofOfPlayReport',
                'description'=> 'Campaign Proof of Play',
                'class'=> '\\Xibo\\Report\\CampaignProofOfPlay',
                'type'=> 'Report',
                'output_type'=> 'table',
                'color'=> 'gray',
                'fa_icon'=> 'fa-th',
                'category'=> 'Connector Reports',
                'feature'=> 'campaign-proof-of-play',
                'adminOnly'=> 0,
            ],
        ];

        $reports = [];
        foreach ($connectorReports as $connectorReport) {

            // Compatibility check
            if (!isset($connectorReport['feature']) || !isset($connectorReport['category'])) {
                continue;
            }

            $reports[$connectorReport['category']][] = (object) $connectorReport;
        }

        $event->setReportObject($reports);
    }
}
