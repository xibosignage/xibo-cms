<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
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

    /** @var string */
    private $baseUrl;
    
    /** @var int */
    private $displayId;

    /** @var boolean should we use the event? */
    private $isUseEvent = false;

    /** @var array the data */
    private $data = [];

    /** @var \Xibo\Entity\Media[] */
    private $media = [];

    /** @var int the cache ttl in seconds - default to 7 days */
    private $cacheTtl = 86400 * 7;

    /**
     * Constructor
     * @param \Xibo\Entity\Module $module
     * @param \Xibo\Entity\Widget $widget
     * @param int $displayId Provide 0 for preview
     */
    public function __construct(Module $module, Widget $widget, int $displayId)
    {
        $this->module = $module;
        $this->widget = $widget;
        $this->displayId = $displayId;
    }

    /**
     * @param \Xibo\Factory\MediaFactory $mediaFactory
     * @param string|null $baseUrl The base url for any preview images.
     * @return \Xibo\Widget\Provider\DataProviderInterface
     */
    public function setMediaFactory(MediaFactory $mediaFactory, ?string $baseUrl = null): DataProviderInterface
    {
        $this->mediaFactory = $mediaFactory;
        $this->baseUrl = $baseUrl;
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
    public function isPreview(): bool
    {
        return $this->displayId == 0;
    }

    /**
     * @inheritDoc
     */
    public function getDisplayId(): int
    {
        return $this->displayId;
    }

    /**
     * @inheritDoc
     */
    public function getProperty(string $property, $default = null)
    {
        $this->widget->getOptionValue($property, $default);
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
    public function addItem(array $item): DataProviderInterface
    {
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
    public function addImage(string $id, string $url, int $expiresAt): string
    {
        $media = $this->mediaFactory->queueDownload($id, $url, $expiresAt);
        $this->media[] = $media;

        if ($this->isPreview()) {
            return str_replace(':id', $media->mediaId, $this->baseUrl);
        } else {
            return $media->storedAs;
        }
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
}
