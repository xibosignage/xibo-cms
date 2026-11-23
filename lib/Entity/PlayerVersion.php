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

use Carbon\Carbon;
use OpenApi\Attributes as OA;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Helper\DateFormatHelper;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\LibraryFileJournal;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\DuplicateEntityException;

/**
 * Class PlayerVersion
 * @package Xibo\Entity
*/
#[OA\Schema]
class PlayerVersion implements \JsonSerializable
{
    use EntityTrait;

    #[OA\Property(description: 'Version ID')]
    public ?int $versionId = null;

    #[OA\Property(description: 'Player type')]
    public ?string $type = null;

    #[OA\Property(description: 'Version number')]
    public ?string $version = null;

    #[OA\Property(description: 'Code number')]
    public ?int $code = null;

    #[OA\Property(description: 'Player version to show')]
    public ?string $playerShowVersion = null;

    #[OA\Property(description: 'The Player Version created date')]
    public ?string $createdAt = null;

    #[OA\Property(description: 'The Player Version modified date')]
    public ?string $modifiedAt = null;

    #[OA\Property(description: 'The name of the user that modified this Player Version last')]
    public ?string $modifiedBy = null;

    #[OA\Property(description: 'The Player Version file name')]
    public ?string $fileName = null;

    #[OA\Property(description: 'The Player Version file size in bytes')]
    public ?int $size = null;

    #[OA\Property(description: 'A MD5 checksum of the stored Player Version file')]
    public ?string $md5 = null;

    public function __construct(
        StorageServiceInterface $store,
        LogServiceInterface $log,
        EventDispatcherInterface $dispatcher,
        private readonly ConfigServiceInterface $config,
        private readonly LibraryStorageRegistryInterface $libraryStorageRegistry,
    ) {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    private function add(): void
    {
        $this->versionId = $this->getStore()->insert('
            INSERT INTO `player_software`
                (`player_type`, `player_version`, `player_code`, `playerShowVersion`,
                 `createdAt`, `modifiedAt`, `modifiedBy`, `fileName`, `size`, `md5`)
            VALUES
                (:type, :version, :code, :playerShowVersion,
                 :createdAt, :modifiedAt, :modifiedBy, :fileName, :size, :md5)
        ', [
            'type' => $this->type,
            'version' => $this->version,
            'code' => $this->code,
            'playerShowVersion' => $this->playerShowVersion,
            'createdAt' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            'modifiedAt' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            'modifiedBy' => $this->modifiedBy,
            'fileName' => $this->fileName,
            'size' => $this->size,
            'md5' => $this->md5
        ]);
    }

    private function edit(): void
    {
        $sql = '
          UPDATE `player_software`
            SET `player_version` = :version,
                `player_code` = :code,
                `playerShowVersion` = :playerShowVersion,
                `modifiedAt` = :modifiedAt,
                `modifiedBy` = :modifiedBy
           WHERE versionId = :versionId
        ';

        $params = [
            'version' => $this->version,
            'code' => $this->code,
            'playerShowVersion' => $this->playerShowVersion,
            'modifiedAt' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            'modifiedBy' => $this->modifiedBy,
            'versionId' => $this->versionId
        ];

        $this->getStore()->update($sql, $params);
    }


    public function delete(): void
    {
        $this->load();

        // delete record
        $this->getStore()->update('DELETE FROM `player_software` WHERE `versionId` = :versionId', [
            'versionId' => $this->versionId
        ]);

        LibraryFileJournal::deleteFileAfterCommit(
            $this->getStore(),
            $this->libraryStorageRegistry,
            LibraryAsset::PLAYER_SOFTWARE,
            (string)$this->fileName,
            $this->getLog()->getLoggerInterface()
        );
    }

    public function load(): void
    {
        if ($this->loaded || $this->versionId == null) {
            return;
        }

        $this->loaded = true;
    }

    public function save(array $options = []): void
    {
        $options = array_merge([
            'validate' => true
        ], $options);

        if ($options['validate']) {
            $this->validate();
        }

        if ($this->versionId == null || $this->versionId == 0) {
            $this->add();
        } else {
            $this->edit();
        }
    }

    public function validate(): void
    {
        // do we already have a file with the same exact name?
        $params = [];
        $checkSQL = 'SELECT `fileName` FROM `player_software` WHERE `fileName` = :fileName';

        if ($this->versionId != null) {
            $checkSQL .= ' AND `versionId` <> :versionId ';
            $params['versionId'] = $this->versionId;
        }

        $params['fileName'] = $this->fileName;

        $result = $this->getStore()->select($checkSQL, $params);

        if (count($result) > 0) {
            throw new DuplicateEntityException(__('You already own Player Version file with this name.'));
        }
    }

    /**
     * @return $this
     */
    public function decorateRecord(): static
    {
        $version = '';
        $code = null;
        $type = '';
        $explode = explode('_', $this->fileName);
        $explodeExt = explode('.', $this->fileName);
        $playerShowVersion = $explodeExt[0];

        // standard releases
        if (count($explode) === 5) {
            if (str_contains($explode[4], '.')) {
                $explodeExtension = explode('.', $explode[4]);
                $explode[4] = $explodeExtension[0];
            }

            if (str_contains($explode[3], 'v')) {
                $version = strtolower(substr(strrchr($explode[3], 'v'), 1, 3)) ;
            }
            if (str_contains($explode[4], 'R')) {
                $code = strtolower(substr(strrchr($explode[4], 'R'), 1, 3)) ;
            }
            $playerShowVersion = $version . ' Revision ' . $code;
            // for DSDevices specific apk
        } elseif (count($explode) === 6) {
            if (str_contains($explode[5], '.')) {
                $explodeExtension = explode('.', $explode[5]);
                $explode[5] = $explodeExtension[0];
            }
            if (str_contains($explode[3], 'v')) {
                $version = strtolower(substr(strrchr($explode[3], 'v'), 1, 3)) ;
            }
            if (str_contains($explode[4], 'R')) {
                $code = strtolower(substr(strrchr($explode[4], 'R'), 1, 3)) ;
            }
            $playerShowVersion = $version . ' Revision ' . $code . ' ' . $explode[5];
            // for white labels
        } elseif (count($explode) === 3) {
            if (str_contains($explode[2], '.')) {
                $explodeExtension = explode('.', $explode[2]);
                $explode[2] = $explodeExtension[0];
            }
            if (str_contains($explode[1], 'v')) {
                $version = strtolower(substr(strrchr($explode[1], 'v'), 1, 3)) ;
            }
            if (str_contains($explode[2], 'R')) {
                $code = strtolower(substr(strrchr($explode[2], 'R'), 1, 3)) ;
            }
            $playerShowVersion = $version . ' Revision ' . $code . ' ' . $explode[0];
        }

        $extension = strtolower(substr(strrchr($this->fileName, '.'), 1));

        if ($extension == 'apk') {
            $type = 'android';
        } else if ($extension == 'ipk') {
            $type = 'lg';
        } else if ($extension == 'wgt') {
            $type = 'sssp';
        }

        $this->version = $version;
        $this->code = $code;
        $this->playerShowVersion = $playerShowVersion;
        $this->type = $type;

        return $this;
    }
}
