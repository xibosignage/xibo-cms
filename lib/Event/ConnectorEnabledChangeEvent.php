<?php

namespace Xibo\Event;

use Xibo\Entity\Connector;
use Xibo\Service\ConfigServiceInterface;

class ConnectorEnabledChangeEvent extends Event
{
    public static $NAME = 'connector.enabled.change.event';

    /** @var \Xibo\Entity\Connector */
    private $connector;

    /** @var ConfigServiceInterface */
    private $configService;

    public function __construct(Connector $connector, ConfigServiceInterface $configService)
    {
        $this->connector = $connector;
        $this->configService = $configService;
    }

    public function getConnector(): Connector
    {
        return $this->connector;
    }

    public function getConfigService(): ConfigServiceInterface
    {
        return $this->configService;
    }
}
