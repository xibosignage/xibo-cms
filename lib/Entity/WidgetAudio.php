<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (WidgetAudio.php)
 */


namespace Xibo\Entity;

use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class WidgetAudio
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class WidgetAudio implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The Widget Id")
     * @var int
     */
    public $widgetId;

    /**
     * @SWG\Property(description="The Media Id")
     * @var int
     */
    public $mediaId;

    /**
     * @SWG\Property(description="The percentage volume")
     * @var int
     */
    public $volume;

    /**
     * @SWG\Property(description="Flag indicating whether to loop")
     * @var int
     */
    public $loop;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
    }

    /**
     * Get Id
     * @return int
     */
    public function getId()
    {
        return $this->mediaId;
    }

    /**
     * Save this widget audio
     */
    public function save()
    {
        $sql = '
          INSERT INTO `lkwidgetaudio` (widgetId, mediaId, `volume`, `loop`)
            VALUES (:widgetId, :mediaId, :volume, :loop)
          ON DUPLICATE KEY UPDATE volume = :volume, `loop` = :loop
        ';

        $this->getStore()->insert($sql, array(
            'widgetId' => $this->widgetId,
            'mediaId' => $this->mediaId,
            'volume' => $this->volume,
            'loop' => $this->loop
        ));
    }

    /**
     * Delete this widget audio
     */
    public function delete()
    {
        $this->getStore()->update('
            DELETE FROM `lkwidgetaudio`
              WHERE widgetId = :widgetId AND mediaId = :mediaId
        ', [
            'widgetId' => $this->widgetId,
            'mediaId' => $this->mediaId
        ]);
    }
}