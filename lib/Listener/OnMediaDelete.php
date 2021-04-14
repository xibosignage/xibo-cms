<?php


namespace Xibo\Listener;


use Xibo\Event\MediaDeleteEvent;
use Xibo\Factory\MenuBoardCategoryFactory;
use Xibo\Support\Exception\InvalidArgumentException;

class OnMediaDelete
{
    use ListenerLoggerTrait;

    /** @var MenuBoardCategoryFactory */
    private $menuBoardCategoryFactory;

    public function __construct($menuBoardCategoryFactory)
    {
        $this->menuBoardCategoryFactory = $menuBoardCategoryFactory;
    }

    /**
     * @param object $event
     * @throws InvalidArgumentException
     */
    public function __invoke(object $event)
    {
        /** @var  MediaDeleteEvent $event */
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