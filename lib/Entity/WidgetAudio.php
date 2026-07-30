<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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


namespace Xibo\Entity;

use OpenApi\Attributes as OA;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class WidgetAudio
 * @package Xibo\Entity
 */
#[OA\Schema]
class WidgetAudio implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Widget Id')]
    public $widgetId;

    /**
     * @var int
     */
    #[OA\Property(description: 'The Media Id')]
    public $mediaId;

    /**
     * @var int
     */
    #[OA\Property(description: 'The percentage volume')]
    public $volume;

    /**
     * @var int
     */
    #[OA\Property(description: 'Flag indicating whether to loop')]
    public $loop;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct($store, $log, $dispatcher)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
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