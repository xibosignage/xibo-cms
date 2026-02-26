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
use Xibo\Connector\ProviderDetails;

#[OA\Schema(schema: 'SearchResult')]
class SearchResult implements \JsonSerializable
{
    #[OA\Property()]
    public $title;
    #[OA\Property()]
    public $description;
    #[OA\Property()]
    public $thumbnail;
    #[OA\Property()]
    public $source;
    #[OA\Property()]
    public $type;
    #[OA\Property()]
    public $id;
    #[OA\Property()]
    public $download;
    #[OA\Property()]
    public $fileSize;
    #[OA\Property()]
    public $width;
    #[OA\Property()]
    public $height;
    #[OA\Property()]
    public $orientation;
    #[OA\Property()]
    public $duration;
    #[OA\Property()]
    public $videoThumbnailUrl;
    #[OA\Property(type: 'array', items: new OA\Items(type: 'string'))]
    public $tags = [];
    #[OA\Property()]
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
