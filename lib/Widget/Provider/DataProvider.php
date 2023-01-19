<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

namespace Xibo\Widget\Provider;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Xibo\Entity\Module;
use Xibo\Entity\Widget;
use Xibo\Factory\MediaFactory;

/**
 * Xibo default implementation of a Widget Data Provider
 */
class DataProvider implements DataProviderInterface
{
    /** @var \Xibo\Entity\Module */
    private $module;

    /** @var \Xibo\Entity\Widget */
    private $widget;

    /** @var \Xibo\Factory\MediaFactory */
    private $mediaFactory;

    /** @var boolean should we use the event? */
    private $isUseEvent = false;

    /** @var array the data */
    private $data = [];

    /** @var array the metadata */
    private $meta = [];

    /** @var \Xibo\Entity\Media[] */
    private $media = [];

    /** @var int the cache ttl in seconds - default to 7 days */
    private $cacheTtl = 86400 * 7;

    /** @var int the displayId */
    private $displayId = 0;

    /** @var float the display latitude */
    private $latitude;

    /** @var float the display longitude */
    private $longitude;

    /** @var \GuzzleHttp\Client */
    private $client;

    /** @var array Guzzle proxy configuration */
    private $guzzleProxy;

    /** @var null cached property values. */
    private $properties = null;

    /**
     * Constructor
     * @param \Xibo\Entity\Module $module
     * @param \Xibo\Entity\Widget $widget
     * @param array $guzzleProxy
     */
    public function __construct(Module $module, Widget $widget, array $guzzleProxy)
    {
        $this->module = $module;
        $this->widget = $widget;
        $this->guzzleProxy = $guzzleProxy;
    }

    /**
     * Set the latitude and longitude for this data provider.
     *  This is primary used if a widget is display specific
     * @param $latitude
     * @param $longitude
     * @param int $displayId
     * @return \Xibo\Widget\Provider\DataProviderInterface
     */
    public function setDisplayProperties($latitude, $longitude, int $displayId = 0): DataProviderInterface
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->displayId = $displayId;
        return $this;
    }

    /**
     * @param \Xibo\Factory\MediaFactory $mediaFactory
     * @return \Xibo\Widget\Provider\DataProviderInterface
     */
    public function setMediaFactory(MediaFactory $mediaFactory): DataProviderInterface
    {
        $this->mediaFactory = $mediaFactory;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getDataSource(): string
    {
        return $this->module->type;
    }

    /**
     * @inheritDoc
     */
    public function getDataType(): string
    {
        return $this->module->dataType;
    }

    /**
     * @inheritDoc
     */
    public function getDisplayId(): int
    {
        return $this->displayId ?? 0;
    }

    /**
     * @inheritDoc
     */
    public function getDisplayLatitude(): float
    {
        return $this->latitude;
    }

    /**
     * @inheritDoc
     */
    public function getDisplayLongitude(): float
    {
        return $this->longitude;
    }

    /**
     * @inheritDoc
     */
    public function getProperty(string $property, $default = null)
    {
        if ($this->properties === null) {
            $this->properties = $this->module->getPropertyValues(false);
        }

        $value = $this->properties[$property] ?? $default;
        if (is_integer($default)) {
            return intval($value);
        } else if (is_numeric($value)) {
            return doubleval($value);
        }
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function getSetting(string $setting, $default = null)
    {
        return $this->module->settings[$setting] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function setIsUseEvent(): DataProviderInterface
    {
        $this->isUseEvent = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @inheritDoc
     */
    public function getWidgetModifiedDt(): ?Carbon
    {
        return Carbon::createFromTimestamp($this->widget->modifiedDt);
    }

    /**
     * @inheritDoc
     */
    public function addItem($item): DataProviderInterface
    {
        if (!is_array($item) && !is_object($item)) {
            throw new \RuntimeException('Item must be an array or an object');
        }

        if (is_object($item) && !($item instanceof \JsonSerializable)) {
            throw new \RuntimeException('Item must be JSON serilizable');
        }

        $this->data[] = $item;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addItems(array $items): DataProviderInterface
    {
        foreach ($items as $item) {
            $this->addItem($item);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addOrUpdateMeta(string $key, $item): DataProviderInterface
    {
        if (!is_array($item) && (is_object($item) && !$item instanceof \JsonSerializable)) {
            throw new \RuntimeException('Item must be an array or a JSON serializable object');
        }

        $this->meta[$key] = $item;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addImage(string $id, string $url, int $expiresAt): string
    {
        $media = $this->mediaFactory->queueDownload($id, $url, $expiresAt);
        $this->media[] = $media;

        return '[[mediaId=' . $media->mediaId . ']]';
    }

    /**
     * @inheritDoc
     */
    public function addLibraryFile(int $mediaId): string
    {
        $media = $this->mediaFactory->getById($mediaId);
        $this->media[] = $media;

        return '[[mediaId=' . $media->mediaId . ']]';
    }

    /**
     * @return \Xibo\Entity\Media[]
     */
    public function getImages(): array
    {
        return $this->media;
    }

    /**
     * @inheritDoc
     */
    public function clearData(): DataProviderInterface
    {
        $this->data = [];
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function clearMeta(): DataProviderInterface
    {
        $this->meta = [];
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isUseEvent(): bool
    {
        return $this->isUseEvent;
    }

    /**
     * @inheritDoc
     */
    public function setCacheTtl(int $ttlSeconds): DataProviderInterface
    {
        $this->cacheTtl = $ttlSeconds;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }

    /**
     * @inheritDoc
     */
    public function getGuzzleClient(array $requestOptions = []): Client
    {
        if ($this->client === null) {
            $this->client = new Client(array_merge($this->guzzleProxy, $requestOptions));
        }

        return $this->client;
    }
}
