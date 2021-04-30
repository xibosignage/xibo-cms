<?php


namespace Xibo\Listener;

use Xibo\Event\CampaignLoadEvent;
use Xibo\Factory\LayoutFactory;

class OnCampaignLoad
{
    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    public function __construct(LayoutFactory $layoutFactory)
    {
        $this->layoutFactory = $layoutFactory;
    }
    
    public function __invoke(CampaignLoadEvent $event)
    {
        $campaign = $event->getCampaign();
        $campaign->layouts = $this->layoutFactory->getByCampaignId($campaign->campaignId, false);
    }
}
