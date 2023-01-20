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

    /** @inheritDoc */
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'path' => $this->path,
            'mimeType' => $this->mimeType,
        ];
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
            $response = $response->withAddedHeader('Content-Type', $this->mimeType);
            return $response->withBody(new Stream(fopen(PROJECT_ROOT . $this->path, 'r')));
        }
    }

    private function getLegacyId(): int
    {
        return (random_int(300000000, 400000000)) * -1;
    }
}
