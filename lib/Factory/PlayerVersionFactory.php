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

namespace Xibo\Factory;


use Xibo\Entity\PlayerVersion;
use Xibo\Entity\User;
use Xibo\Exception\NotFoundException;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class PlayerVersionFactory
 * @package Xibo\Factory
 */
class PlayerVersionFactory extends BaseFactory
{
    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     * @param ConfigServiceInterface $config
     * @param MediaFactory $mediaFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $config, $mediaFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);

        $this->config = $config;
        $this->mediaFactory = $mediaFactory;

    }

    /**
     * Create Empty
     * @return PlayerVersion
     */
    public function createEmpty()
    {
        return new PlayerVersion($this->getStore(), $this->getLog(), $this->config, $this->mediaFactory, $this);
    }

    /**
     * Populate Player Version table
     * @param string $type
     * @param int $version
     * @param int $code
     * @param int $mediaId
     * @param string $playerShowVersion
     * @return PlayerVersion
     */
    public function create($type, $version, $code, $mediaId, $playerShowVersion)
    {
        $playerVersion = $this->createEmpty();
        $playerVersion->type = $type;
        $playerVersion->version = $version;
        $playerVersion->code = $code;
        $playerVersion->mediaId = $mediaId;
        $playerVersion->playerShowVersion = $playerShowVersion;
        $playerVersion->save();

        return $playerVersion;
    }

    /**
     * Get by Media Id
     * @param int $mediaId
     * @return PlayerVersion
     * @throws NotFoundException
     */
    public function getByMediaId($mediaId)
    {
        $versions = $this->query(null, array('disableUserCheck' => 1, 'mediaId' => $mediaId));

        if (count($versions) <= 0)
            throw new NotFoundException(__('Cannot find media'));

        return $versions[0];
    }

    /**
     * Get by Version Id
     * @param int $versionId
     * @return PlayerVersion
     * @throws NotFoundException
     */
    public function getById($versionId)
    {
        $versions = $this->query(null, array('disableUserCheck' => 1, 'versionId' => $versionId));

        if (count($versions) <= 0)
            throw new NotFoundException(__('Cannot find version'));

        return $versions[0];
    }

    /**
     * Get by Type
     * @param string $type
     * @return PlayerVersion
     * @throws NotFoundException
     */
    public function getByType($type)
    {
        $versions = $this->query(null, array('disableUserCheck' => 1, 'playerType' => $type));

        if (count($versions) <= 0)
            throw new NotFoundException(__('Cannot find Player Version'));

        return $versions[0];
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return PlayerVersion[]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder === null)
            $sortOrder = ['code DESC'];

        $params = [];
        $entries = [];

        $select = '
            SELECT  player_software.versionId,
               player_software.player_type AS type,
               player_software.player_version AS version,
               player_software.player_code AS code,
               player_software.playerShowVersion,
               media.mediaId,
               media.originalFileName,
               media.storedAs,
            ';

        $select .= " (SELECT GROUP_CONCAT(DISTINCT `group`.group)
                              FROM `permission`
                                INNER JOIN `permissionentity`
                                ON `permissionentity`.entityId = permission.entityId
                                INNER JOIN `group`
                                ON `group`.groupId = `permission`.groupId
                             WHERE entity = :entity
                                AND objectId = media.mediaId
                                AND view = 1
                            ) AS groupsWithPermissions ";
        $params['entity'] = 'Xibo\\Entity\\Media';

        $body = ' FROM player_software 
                    INNER JOIN media
                    ON  player_software.mediaId = media.mediaId
                  WHERE 1 = 1 
            ';


        // View Permissions
        $this->viewPermissionSql('Xibo\Entity\Media', $body, $params, '`media`.mediaId', '`media`.userId', $filterBy);

        // by media ID
        if ($this->getSanitizer()->getInt('mediaId', -1, $filterBy) != -1) {
            $body .= " AND media.mediaId = :mediaId ";
            $params['mediaId'] = $this->getSanitizer()->getInt('mediaId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('versionId', -1, $filterBy) != -1) {
            $body .= " AND player_software.versionId = :versionId ";
            $params['versionId'] = $this->getSanitizer()->getInt('versionId', $filterBy);
        }

        if ($this->getSanitizer()->getString('playerType', $filterBy) != '') {
            $body .= " AND player_software.player_type = :playerType ";
            $params['playerType'] = $this->getSanitizer()->getString('playerType', $filterBy);
        }

        if ($this->getSanitizer()->getString('playerVersion', $filterBy) != '') {
            $body .= " AND player_software.player_version = :playerVersion ";
            $params['playerVersion'] = $this->getSanitizer()->getString('playerVersion', $filterBy);
        }

        if ($this->getSanitizer()->getInt('playerCode', $filterBy) != '') {
            $body .= " AND player_software.player_code = :playerCode ";
            $params['playerCode'] = $this->getSanitizer()->getInt('playerCode', $filterBy);
        }

        if ($this->getSanitizer()->getString('playerShowVersion', $filterBy) !== null) {
            $terms = explode(',', $this->getSanitizer()->getString('playerShowVersion', $filterBy));
            $this->nameFilter('player_software', 'playerShowVersion', $terms, $body, $params, ($this->getSanitizer()->getCheckbox('useRegexForName', $filterBy) == 1));
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $version = $this->createEmpty()->hydrate($row, [
                'intProperties' => [
                    'mediaId', 'code'
                ]
            ]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            unset($params['entity']);
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;


    }

    public function getDistinctType()
    {
        $params = [];
        $entries = [];
        $sql = '
        SELECT DISTINCT player_software.player_type AS type 
        FROM player_software
        ORDER BY type ASC
        ';

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $version = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }

    public function getDistinctVersion()
    {
        $params = [];
        $entries = [];
        $sql = '
        SELECT DISTINCT player_software.player_version AS version 
        FROM player_software
        ORDER BY version ASC
        ';

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $version = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }
}