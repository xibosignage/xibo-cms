<?php


namespace Xibo\Event;


use Xibo\Entity\Campaign;

class CampaignLoadEvent extends Event
{
    public static $NAME = 'campaign.load.event';
    /**
     * @var Campaign
     */
    private $campaign;

    public function __construct(Campaign $campaign)
    {
        $this->campaign = $campaign;
    }

    public function getCampaign(): Campaign
    {
        return $this->campaign;
    }
}
