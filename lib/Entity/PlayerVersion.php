<?php
/**
* Copyright (C) 2018 Xibo Signage Ltd
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
namespace Xibo\Entity;


use Xibo\Exception\XiboException;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PlayerVersionFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

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
     * @SWG\Property(description="A comma separated list of groups/users with permissions to this Media")
     * @var string
     */
    public $groupsWithPermissions;

    /**
     * @SWG\Property(description="The Media ID")
     * @var int
     */
    public $mediaId;

    /**
     * @SWG\Property(description="Player version to show")
     * @var string
     */
    public $playerShowVersion;

    /**
     * @SWG\Property(description="Original name of the uploaded installer file")
     * @var string
     */
    public $originalFileName;

    /**
     * @SWG\Property(description="Stored As")
     * @var string
     */
    public $storedAs;

    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var PlayerVersionFactory
     */
    private $playerVersionFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param MediaFactory $mediaFactory
     * @param PlayerVersionFactory $playerVersionFactory
     */
    public function __construct($store, $log, $config, $mediaFactory, $playerVersionFactory)
    {
        $this->setCommonDependencies($store, $log);

        $this->config = $config;
        $this->mediaFactory = $mediaFactory;
        $this->playerVersionFactory = $playerVersionFactory;
    }

    /**
     * Add
     */
    private function add()
    {
        $this->versionId = $this->getStore()->insert('
            INSERT INTO `player_software` (`player_type`, `player_version`, `player_code`, `mediaId`, `playerShowVersion`)
              VALUES (:type, :version, :code, :mediaId, :playerShowVersion)
        ', [
            'type' => $this->type,
            'version' => $this->version,
            'code' => $this->code,
            'mediaId' => $this->mediaId,
            'playerShowVersion' => $this->playerShowVersion
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
                `playerShowVersion` = :playerShowVersion
           WHERE versionId = :versionId
        ';

        $params = [
            'version' => $this->version,
            'code' => $this->code,
            'playerShowVersion' => $this->playerShowVersion,
            'versionId' => $this->versionId
        ];

        $this->getStore()->update($sql, $params);
    }


    /**
     * Delete
     * @throws XiboException
     */
    public function delete()
    {
        $this->load();

        $this->getStore()->update('DELETE FROM `player_software` WHERE `versionId` = :versionId', [
            'versionId' => $this->versionId
        ]);
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

        if ($this->versionId == null || $this->versionId == 0)
            $this->add();
        else
            $this->edit();
    }
}