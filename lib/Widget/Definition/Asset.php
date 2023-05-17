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

namespace Xibo\Widget\Definition;

use GuzzleHttp\Psr7\Stream;
use Illuminate\Support\Str;
use Intervention\Image\ImageManagerStatic as Img;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Xmds\Entity\Dependency;

/**
 * An asset
 */
class Asset implements \JsonSerializable
{
    public $id;
    public $type;
    public $path;
    public $mimeType;

    /** @var bool */
    public $cmsOnly;

    public $assetNo;

    /** @inheritDoc */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'path' => $this->path,
            'mimeType' => $this->mimeType,
            'cmsOnly' => $this->cmsOnly,
        ];
    }

    /**
     * Should this asset be sent to the player?
     * @return bool
     */
    public function isSendToPlayer(): bool
    {
        return !($this->cmsOnly ?? false);
    }

    /**
     * Get this asset as a dependency.
     * @return \Xibo\Xmds\Entity\Dependency
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getDependency(): Dependency
    {
        // Check that this asset is valid.
        if (!file_exists(PROJECT_ROOT . $this->path)) {
            throw new NotFoundException(__('Asset not found'));
        }

        // Get the file size and md5 of the asset.
        // TODO: cache this?
        $md5 = md5_file(PROJECT_ROOT . $this->path);
        $size = filesize(PROJECT_ROOT . $this->path);

        // Return a dependency
        return new Dependency(
            'asset',
            $this->id,
            $this->getLegacyId(),
            $this->path,
            $size,
            $md5,
            false
        );
    }

    public function getFilename(): string
    {
        return basename($this->path);
    }

    /**
     * Generate a PSR response for this asset.
     * We cannot use sendfile because the asset isn't in the library folder.
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function psrResponse(ServerRequest $request, Response $response): ResponseInterface
    {
        // Make sure this asset exists
        if (!file_exists(PROJECT_ROOT . $this->path)) {
            throw new NotFoundException(__('Asset file does not exist'));
        }

        if (Str::startsWith('image', $this->mimeType)) {
            return Img::make(PROJECT_ROOT . '/' . $this->path)->psrResponse();
        } else {
            // Set the right content type.
            $response = $response->withHeader('Content-Type', $this->mimeType);
            return $response->withBody(new Stream(fopen(PROJECT_ROOT . $this->path, 'r')));
        }
    }

    /**
     * Get Legacy ID for this asset on older players
     *  there is a risk that this ID will change as modules/templates with assets are added/removed in the system
     *  however, we have mitigated by ensuring that only one instance of any required file is added to rf return
     * @return int
     */
    private function getLegacyId(): int
    {
        return (Dependency::LEGACY_ID_OFFSET_ASSET + $this->assetNo) * -1;
    }
}
