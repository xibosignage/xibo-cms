<?php

namespace Xibo\Listener\OnGettingDependencyFileSize;

use Xibo\Event\DependencyFileSizeEvent;
use Xibo\Factory\PlayerVersionFactory;

class PlayerVersionListener
{
    /**
     * @var PlayerVersionFactory
     */
    private $playerVersionFactory;

    public function __construct(PlayerVersionFactory $playerVersionFactory)
    {
        $this->playerVersionFactory = $playerVersionFactory;
    }

    public function __invoke(DependencyFileSizeEvent $event)
    {
        $versionSize = $this->playerVersionFactory->getSizeAndCount();
        $event->addResult([
            'SumSize' => $versionSize['SumSize'],
            'type' => 'playersoftware',
            'count' => $versionSize['totalCount']
        ]);
    }
}
