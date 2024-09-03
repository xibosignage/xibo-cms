<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

use Xibo\Connector\ProviderDetails;

/**
 * @SWG\Definition()
 */
class SearchResult implements \JsonSerializable
{
    public $title;
    public $description;
    public $thumbnail;
    public $source;
    public $type;
    public $id;
    public $download;
    public $fileSize;
    public $width;
    public $height;
    public $orientation;
    public $duration;
    public $videoThumbnailUrl;
    public $tags = [];
    public $isFeatured = 0;

    /** @var ProviderDetails */
    public $provider;

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'duration' => $this->duration,
            'download' => $this->download,
            'provider' => $this->provider,
            'width' => $this->width,
            'height' => $this->height,
            'orientation' => $this->orientation,
            'fileSize' => $this->fileSize,
            'videoThumbnailUrl' => $this->videoThumbnailUrl,
            'tags' => $this->tags,
            'isFeatured' => $this->isFeatured
        ];
    }
}
