<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
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
