<?php


namespace Xibo\Listener\OnParsePermissions;

use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Factory\CampaignFactory;

class PermissionsCampaignListener
{
    /**
     * @var CampaignFactory
     */
    private $campaignFactory;

    public function __construct(CampaignFactory $campaignFactory)
    {
        $this->campaignFactory = $campaignFactory;
    }

    /**
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function __invoke(ParsePermissionEntityEvent $event)
    {
        $event->setObject($this->campaignFactory->getById($event->getObjectId()));
    }
}
