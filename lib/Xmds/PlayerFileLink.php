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

namespace Xibo\Xmds;

use Xibo\Entity\Display;
use Xibo\Helper\LinkSigner;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;

/**
 * Makes the download link a player is given for a file: direct when the CMS has no local files, else to the CMS,
 * which a PWA, Tizen or webOS player always uses.
 *
 * Links to the CMS are on $baseUrl when given, except for a PWA, Tizen, webOS or Linux player before v400, which keep
 * their own address.
 */
class PlayerFileLink
{
    /** The storage holding each dependency file type; the key of each file is its saveAs name. */
    private const DEPENDENCY_ASSETS = [
        'font' => LibraryAsset::FONT,
        'fontCss' => LibraryAsset::FONT,
        'playersoftware' => LibraryAsset::PLAYER_SOFTWARE,
        'data_connector' => LibraryAsset::DATA_CONNECTOR,
    ];

    public function __construct(
        private readonly ConfigServiceInterface $config,
        private readonly LibraryStorageRegistryInterface $storageRegistry,
        private readonly ?string $baseUrl = null
    ) {
    }

    /**
     * The link for a file and whether it is direct, so it doesn't reach the CMS.
     * @param string $type the required file type: M, L or P
     * @param string $saveAs the name the player saves the file as
     * @param string|null $storageEngine the media's storageEngine, for a file still waiting to be copied
     * @return array{0: string, 1: bool}
     * @throws \Xibo\Storage\DownloadUrlUnavailableException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function get(
        Display $display,
        string $type,
        $itemId,
        string $saveAs,
        ?string $fileType = null,
        bool $isRequestFromPwa = false,
        ?string $storageEngine = null
    ): array {
        $storage = $this->getStorageFor($type, $fileType, $storageEngine);
        $url = $this->isLinkedThroughTheCms($display)
            ? null
            : $storage?->getDirectDownloadUrl($saveAs, LinkSigner::getExpiry($display));
        if ($url !== null) {
            return [$url, true];
        }

        // The CDN fetches from the CMS, so it serves the files the CMS holds locally.
        $isLocal = ($storage ?? $this->storageRegistry->get(LibraryAsset::MEDIA))->hasLocalFiles();

        return [
            LinkSigner::generateSignedLink(
                $display,
                $this->config->getApiKeyDetails()['encryptionKey'],
                $isLocal ? $this->config->getSetting('CDN_URL') : null,
                $type,
                $itemId,
                $saveAs,
                $fileType,
                $isRequestFromPwa,
                ($isRequestFromPwa || $this->isLinkedThroughTheCms($display)) ? null : $this->baseUrl,
            ),
            false,
        ];
    }

    /**
     * Identifies the kind of links this CMS hands out, so a document of links made another way is made again.
     */
    public function getFingerprint(bool $httpDownloads, ?Display $display = null): string
    {
        $hasLocalFiles = $this->hasLocalFiles();
        $mayHaveLocalFiles = $hasLocalFiles || $this->storageRegistry->hasFilesToMove();

        return 'http=' . ($httpDownloads ? 1 : 0)
            . ';local=' . ($hasLocalFiles ? 1 : 0)
            . ';cdn=' . ($mayHaveLocalFiles && !empty($this->config->getSetting('CDN_URL')) ? 1 : 0)
            . ';cms=' . ($display !== null && $this->isLinkedThroughTheCms($display) ? 1 : 0);
    }

    /**
     * Whether $display is only given xmds.php links, never direct storage links.
     */
    private function isLinkedThroughTheCms(Display $display): bool
    {
        // A PWA caches each file under its xmds.php link, which is how its renderer and widgets ask for it.
        // A Linux player before v400 isn't known to follow a link to another host.
        // Tizen (sssp) can't play files from storage links
        return $display->isPwa()
            || in_array($display->clientType, ['sssp', 'lg'], true)
            || ($display->clientType === 'linux' && (int)$display->clientCode < 400);
    }

    /**
     * The storage a file is in, or null when it isn't a library file.
     */
    private function getStorageFor(
        string $type,
        ?string $fileType,
        ?string $storageEngine
    ): ?LibraryStorageServiceInterface {
        $asset = match ($type) {
            'M', 'L' => LibraryAsset::MEDIA,
            'P' => self::DEPENDENCY_ASSETS[$fileType] ?? null,
            default => null,
        };

        // Only media files wait to be copied; a layout's XLF is always in the configured store.
        return $asset === null
            ? null
            : $this->storageRegistry->get($asset, $type === 'M' ? $storageEngine : null);
    }

    private function hasLocalFiles(): bool
    {
        return $this->storageRegistry->get(LibraryAsset::MEDIA)->hasLocalFiles();
    }
}
