<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

namespace Xibo\Widget\Provider;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Module;
use Xibo\Entity\Widget;
use Xibo\Factory\MediaFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Xibo default implementation of a Widget Data Provider
 */
class DataProvider implements DataProviderInterface
{
    /** @var \Xibo\Factory\MediaFactory */
    private $mediaFactory;

    /** @var boolean should we use the event? */
    private $isUseEvent = false;

    /** @var bool Is this data provider handled? */
    private $isHandled = false;

    /** @var array errors */
    private $errors = [];

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

    /** @var bool Is this data provider in preview mode? */
    private $isPreview = false;

    /** @var \GuzzleHttp\Client */
    private $client;

    /** @var null cached property values. */
    private $properties = null;

    /** @var null cached setting values. */
    private $settings = null;

    /**
     * Constructor
     * @param Module $module
     * @param Widget $widget
     * @param array $guzzleProxy
     * @param SanitizerService $sanitizer
     * @param PoolInterface $pool
     */
    public function __construct(
        private readonly Module $module,
        private readonly Widget $widget,
        private readonly array $guzzleProxy,
        private readonly SanitizerService $sanitizer,
        private readonly PoolInterface $pool
    ) {
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
     * Set whether this data provider is in preview mode
     * @param bool $isPreview
     * @return DataProviderInterface
     */
    public function setIsPreview(bool $isPreview): DataProviderInterface
    {
        $this->isPreview = $isPreview;
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
    public function getDisplayLatitude(): ?float
    {
        return $this->latitude;
    }

    /**
     * @inheritDoc
     */
    public function getDisplayLongitude(): ?float
    {
        return $this->longitude;
    }

    /**
     * @inheritDoc
     */
    public function isPreview(): bool
    {
        return $this->isPreview;
    }

    /**
     * @inheritDoc
     */
    public function getWidgetId(): int
    {
        return $this->widget->widgetId;
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
        if ($this->settings === null) {
            foreach ($this->module->settings as $item) {
                $this->settings[$item->id] = $item->value ?: $item->default;
            }
        }

        return $this->settings[$setting] ?? $default;
    }

    /**
     * Is this data provider handled?
     * @return bool
     */
    public function isHandled(): bool
    {
        return $this->isHandled;
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
    public function setIsHandled(): DataProviderInterface
    {
        $this->isHandled = true;
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
     * Get any errors recorded on this provider
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
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
    public function addError(string $errorMessage): DataProviderInterface
    {
        $this->errors[] = $errorMessage;
        return $this;
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
     * @return int[]
     */
    public function getImageIds(): array
    {
        $mediaIds = [];
        foreach ($this->getImages() as $media) {
            $mediaIds[] = $media->mediaId;
        }
        return $mediaIds;
    }

    /**
     * @inheritDoc
     */
    public function clearData(): DataProviderInterface
    {
        $this->media = [];
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

    /**
     * @inheritDoc
     */
    public function getPool(): PoolInterface
    {
        return $this->pool;
    }

    /**
     * @inheritDoc
     */
    public function getSanitizer(array $params): SanitizerInterface
    {
        return $this->sanitizer->getSanitizer($params);
    }
}
