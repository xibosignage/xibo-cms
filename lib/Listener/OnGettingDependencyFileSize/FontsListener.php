<?php

namespace Xibo\Listener\OnGettingDependencyFileSize;

use Xibo\Event\DependencyFileSizeEvent;
use Xibo\Factory\FontFactory;

class FontsListener
{
    /**
     * @var FontFactory
     */
    private $fontFactory;

    public function __construct(FontFactory $fontFactory)
    {
        $this->fontFactory = $fontFactory;
    }

    public function __invoke(DependencyFileSizeEvent $event)
    {
        $fontsSize = $this->fontFactory->getFontsSizeAndCount();
        $event->addResult([
            'SumSize' => $fontsSize['SumSize'],
            'type' => 'font',
            'count' => $fontsSize['totalCount']
        ]);
    }
}
