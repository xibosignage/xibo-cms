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
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Xmds\Entity\Dependency;

/**
 * An asset
 */
class Asset implements \JsonSerializable
{
    public $id;
    public $type;
    public $alias;
    public $path;
    public $mimeType;

    /** @var bool */
    public $autoInclude;

    /** @var bool */
    public $cmsOnly;

    public $assetNo;

    private $fileSize;
    private $md5;

    /** @inheritDoc */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'alias' => $this->alias,
            'type' => $this->type,
            'path' => $this->path,
            'mimeType' => $this->mimeType,
            'cmsOnly' => $this->cmsOnly,
            'autoInclude' => $this->autoInclude,
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
     * Should this asset be auto included in the HTML sent to the player
     * @return bool
     */
    public function isAutoInclude(): bool
    {
        return $this->autoInclude && $this->isSendToPlayer();
    }

    /**
     * @param string $libraryLocation
     * @param bool $forceUpdate
     * @return $this
     * @throws GeneralException
     */
    public function updateAssetCache(string $libraryLocation, bool $forceUpdate = false): Asset
    {
        // Verify the asset is cached and update its path.
        $assetPath = $libraryLocation . 'assets/' . $this->getFilename();
        if (!file_exists($assetPath) || $forceUpdate) {
            $result = @copy(PROJECT_ROOT . $this->path, $assetPath);
            if (!$result) {
                throw new GeneralException('Unable to copy asset');
            }
            $forceUpdate = true;
        }

        // Get the bundle MD5
        $assetMd5CachePath = $assetPath . '.md5';
        if (!file_exists($assetMd5CachePath) || $forceUpdate) {
            $assetMd5 = md5_file($assetPath);
            file_put_contents($assetMd5CachePath, $assetMd5);
        } else {
            $assetMd5 = file_get_contents($assetPath . '.md5');
        }

        $this->path = $assetPath;
        $this->md5 = $assetMd5;
        $this->fileSize = filesize($assetPath);

        return $this;
    }

    /**
     * Get this asset as a dependency.
     * @return \Xibo\Xmds\Entity\Dependency
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getDependency(): Dependency
    {
        // Check that this asset is valid.
        if (!file_exists($this->path)) {
            throw new NotFoundException(sprintf(__('Asset %s not found'), $this->path));
        }

        // Return a dependency
        return new Dependency(
            'asset',
            $this->id,
            $this->getLegacyId(),
            $this->path,
            $this->fileSize,
            $this->md5,
            true
        );
    }

    /**
     * Get the file name for this asset
     * @return string
     */
    public function getFilename(): string
    {
        return basename($this->path);
    }

    /**
     * Generate a PSR response for this asset.
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function psrResponse(ServerRequest $request, Response $response, string $sendFileMode): ResponseInterface
    {
        // Make sure this asset exists
        if (!file_exists($this->path)) {
            throw new NotFoundException(__('Asset file does not exist'));
        }

        $response = $response->withHeader('Content-Length', $this->fileSize);
        $response = $response->withHeader('Content-Type', $this->mimeType);

        // Output the file
        if ($sendFileMode === 'Apache') {
            // Send via Apache X-Sendfile header?
            $response = $response->withHeader('X-Sendfile', $this->path);
        } else if ($sendFileMode === 'Nginx') {
            // Send via Nginx X-Accel-Redirect?
            $response = $response->withHeader('X-Accel-Redirect', '/download/assets/' . $this->getFilename());
        } else if (Str::startsWith('image', $this->mimeType)) {
            $response = Img::make('/' . $this->path)->psrResponse();
        } else {
            // Set the right content type.
            $response = $response->withBody(new Stream(fopen($this->path, 'r')));
        }
        return $response;
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
