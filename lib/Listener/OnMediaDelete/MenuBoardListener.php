<?php


namespace Xibo\Listener\OnMediaDelete;

use Xibo\Event\MediaDeleteEvent;
use Xibo\Factory\MenuBoardCategoryFactory;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Support\Exception\InvalidArgumentException;

class MenuBoardListener
{
    use ListenerLoggerTrait;

    /** @var MenuBoardCategoryFactory */
    private $menuBoardCategoryFactory;

    public function __construct($menuBoardCategoryFactory)
    {
        $this->menuBoardCategoryFactory = $menuBoardCategoryFactory;
    }

    /**
     * @param MediaDeleteEvent $event
     * @throws InvalidArgumentException
     */
    public function __invoke(MediaDeleteEvent $event)
    {
        $media = $event->getMedia();

        foreach ($this->menuBoardCategoryFactory->query(null, ['mediaId' => $media->mediaId]) as $category) {
            $category->mediaId = null;
            $category->save();
        }

        foreach ($this->menuBoardCategoryFactory->getProductData(null, ['mediaId' => $media->mediaId]) as $product) {
            $product->mediaId = null;
            $product->save();
        }
    }
}
