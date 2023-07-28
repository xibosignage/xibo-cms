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


use Carbon\Carbon;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\DuplicateEntityException;

/**
 * Class PlayerVersion
 * @package Xibo\Entity
 *
 * @SWG\Definition()
*/
class PlayerVersion implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="Version ID")
     * @var int
     */
    public $versionId;

    /**
     * @SWG\Property(description="Player type")
     * @var string
     */
    public $type;

    /**
     * @SWG\Property(description="Version number")
     * @var string
     */
    public $version;

    /**
     * @SWG\Property(description="Code number")
     * @var int
     */
    public $code;

    /**
     * @SWG\Property(description="Player version to show")
     * @var string
     */
    public $playerShowVersion;

    /**
     * @SWG\Property(description="The Player Version created date")
     * @var string
     */
    public $createdAt;

    /**
     * @SWG\Property(description="The Player Version modified date")
     * @var string
     */
    public $modifiedAt;

    /**
     * @SWG\Property(description="The name of the user that modified this Player Version last")
     * @var string
     */
    public $modifiedBy;

    /**
     * @SWG\Property(description="The Player Version file name")
     * @var string
     */
    public $fileName;

    /**
     * @SWG\Property(description="The Player Version file size in bytes")
     * @var int
     */
    public $size;

    /**
     * @SWG\Property(description="A MD5 checksum of the stored Player Version file")
     * @var string
     */
    public $md5;

    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * @var PlayerVersionFactory
     */
    private $playerVersionFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param ConfigServiceInterface $config
     * @param MediaFactory $mediaFactory
     * @param PlayerVersionFactory $playerVersionFactory
     */
    public function __construct($store, $log, $dispatcher, $config, $playerVersionFactory)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);

        $this->config = $config;
        $this->playerVersionFactory = $playerVersionFactory;
    }

    /**
     * Add
     */
    private function add()
    {
        $this->versionId = $this->getStore()->insert('
            INSERT INTO `player_software` (`player_type`, `player_version`, `player_code`, `playerShowVersion`,`createdAt`, `modifiedAt`, `modifiedBy`, `fileName`, `size`, `md5`)
              VALUES (:type, :version, :code, :playerShowVersion, :createdAt, :modifiedAt, :modifiedBy, :fileName, :size, :md5)
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

    /**
     * Edit
     */
    private function edit()
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


    /**
     * Delete
     */
    public function delete()
    {
        $this->load();

        // delete record
        $this->getStore()->update('DELETE FROM `player_software` WHERE `versionId` = :versionId', [
            'versionId' => $this->versionId
        ]);

        // Library location
        $libraryLocation = $this->config->getSetting('LIBRARY_LOCATION');

        // delete file
        if (file_exists($libraryLocation . 'playersoftware/'. $this->fileName)) {
            unlink($libraryLocation  . 'playersoftware/'. $this->fileName);
        }
    }

    /**
     * Load
     */
    public function load()
    {
        if ($this->loaded || $this->versionId == null)
            return;

        $this->loaded = true;
    }

    /**
     * Save this media
     * @param array $options
     */
    public function save($options = [])
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

    public function validate() {
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

    public function decorateRecord()
    {
        $version = '';
        $code = null;
        $type = '';
        $explode = explode('_', $this->fileName);
        $explodeExt = explode('.', $this->fileName);
        $playerShowVersion = $explodeExt[0];

        // standard releases
        if (count($explode) === 5) {
            if (strpos($explode[4], '.') !== false) {
                $explodeExtension = explode('.', $explode[4]);
                $explode[4] = $explodeExtension[0];
            }

            if (strpos($explode[3], 'v') !== false) {
                $version = strtolower(substr(strrchr($explode[3], 'v'), 1, 3)) ;
            }
            if (strpos($explode[4], 'R') !== false) {
                $code = strtolower(substr(strrchr($explode[4], 'R'), 1, 3)) ;
            }
            $playerShowVersion = $version . ' Revision ' . $code;
            // for DSDevices specific apk
        } elseif (count($explode) === 6) {
            if (strpos($explode[5], '.') !== false) {
                $explodeExtension = explode('.', $explode[5]);
                $explode[5] = $explodeExtension[0];
            }
            if (strpos($explode[3], 'v') !== false) {
                $version = strtolower(substr(strrchr($explode[3], 'v'), 1, 3)) ;
            }
            if (strpos($explode[4], 'R') !== false) {
                $code = strtolower(substr(strrchr($explode[4], 'R'), 1, 3)) ;
            }
            $playerShowVersion = $version . ' Revision ' . $code . ' ' . $explode[5];
            // for white labels
        } elseif (count($explode) === 3) {
            if (strpos($explode[2], '.') !== false) {
                $explodeExtension = explode('.', $explode[2]);
                $explode[2] = $explodeExtension[0];
            }
            if (strpos($explode[1], 'v') !== false) {
                $version = strtolower(substr(strrchr($explode[1], 'v'), 1, 3)) ;
            }
            if (strpos($explode[2], 'R') !== false) {
                $code = strtolower(substr(strrchr($explode[2], 'R'), 1, 3)) ;
            }
            $playerShowVersion = $version . ' Revision ' . $code . ' ' . $explode[0];
        }

        $extension = strtolower(substr(strrchr($this->fileName, '.'), 1));

        if ($extension == 'apk') {
            $type = 'android';
        } elseif ($extension == 'ipk') {
            $type = 'lg';
        } elseif ($extension == 'wgt') {
            $type = 'sssp';
        }

        $this->version = $version;
        $this->code = $code;
        $this->playerShowVersion = $playerShowVersion;
        $this->type = $type;

        return $this;
    }
}
