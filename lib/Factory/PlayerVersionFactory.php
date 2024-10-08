<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

namespace Xibo\Factory;

use Xibo\Entity\PlayerVersion;
use Xibo\Entity\User;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Support\Exception\NotFoundException;

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
     * Construct a factory
     * @param User $user
     * @param UserFactory $userFactory
     * @param ConfigServiceInterface $config
     */
    public function __construct($user, $userFactory, $config)
    {
        $this->setAclDependencies($user, $userFactory);

        $this->config = $config;

    }

    /**
     * Create Empty
     * @return PlayerVersion
     */
    public function createEmpty()
    {
        return new PlayerVersion(
            $this->getStore(),
            $this->getLog(),
            $this->getDispatcher(),
            $this->config,
            $this
        );
    }

    /**
     * Populate Player Version table
     * @param string $type
     * @param int $version
     * @param int $code
     * @param string $playerShowVersion
     * @param string $modifiedBy
     * @param string $fileName
     * @param int $size
     * @param string $md5
     * @return PlayerVersion
     */
    public function create(
        $type,
        $version,
        $code,
        $playerShowVersion,
        $modifiedBy,
        $fileName,
        $size,
        $md5
    )
    {
        $playerVersion = $this->createEmpty();
        $playerVersion->type = $type;
        $playerVersion->version = $version;
        $playerVersion->code = $code;
        $playerVersion->playerShowVersion = $playerShowVersion;
        $playerVersion->modifiedBy = $modifiedBy;
        $playerVersion->fileName = $fileName;
        $playerVersion->size = $size;
        $playerVersion->md5 = $md5;
        $playerVersion->save();

        return $playerVersion;
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
    public function getByType(string $type): PlayerVersion
    {
        $versions = $this->query(null, array('disableUserCheck' => 1, 'playerType' => $type));

        if (count($versions) <= 0) {
            throw new NotFoundException(__('Cannot find Player Version'));
        }

        return $versions[0];
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return PlayerVersion[]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder === null) {
            $sortOrder = ['code DESC'];
        }

        $sanitizedFilter = $this->getSanitizer($filterBy);

        $params = [];
        $entries = [];

        $select = '
            SELECT  `player_software`.versionId,
               `player_software`.player_type AS type,
               `player_software`.player_version AS version,
               `player_software`.player_code AS code,
               `player_software`.playerShowVersion,
               `player_software`.createdAt,
               `player_software`.modifiedAt,
               `player_software`.modifiedBy,
               `player_software`.fileName,
               `player_software`.size,
               `player_software`.md5
            ';

        $body = ' FROM player_software 
                  WHERE 1 = 1 
            ';

        if ($sanitizedFilter->getInt('versionId', ['default' => -1]) != -1) {
            $body .= " AND player_software.versionId = :versionId ";
            $params['versionId'] = $sanitizedFilter->getInt('versionId');
        }

        if ($sanitizedFilter->getString('playerType') != '') {
            $body .= " AND player_software.player_type = :playerType ";
            $params['playerType'] = $sanitizedFilter->getString('playerType');
        }

        if ($sanitizedFilter->getString('playerVersion') != '') {
            $body .= " AND player_software.player_version = :playerVersion ";
            $params['playerVersion'] = $sanitizedFilter->getString('playerVersion');
        }

        if ($sanitizedFilter->getInt('playerCode') != '') {
            $body .= " AND player_software.player_code = :playerCode ";
            $params['playerCode'] = $sanitizedFilter->getInt('playerCode');
        }

        if ($sanitizedFilter->getString('playerShowVersion') !== null) {
            $terms = explode(',', $sanitizedFilter->getString('playerShowVersion'));
            $this->nameFilter('player_software', 'playerShowVersion', $terms, $body, $params, ($sanitizedFilter->getCheckbox('useRegexForName') == 1));
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder)) {
            $order .= 'ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0]) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, [
                'intProperties' => [
                    'versionId', 'code', 'size'
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
            $entry = $this->createEmpty()->hydrate($row);
            if ($entry->type === 'sssp') {
                $entry->setUnmatchedProperty('typeShow', 'Tizen');
            } else if ($entry->type === 'lg') {
                $entry->setUnmatchedProperty('typeShow', 'webOS');
            } else {
                $entry->setUnmatchedProperty('typeShow', ucfirst($row['type']));
            }

            $entries[] = $entry;
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
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }

    public function getSizeAndCount()
    {
        return $this->getStore()->select('SELECT IFNULL(SUM(size), 0) AS SumSize, COUNT(*) AS totalCount FROM `player_software`', [])[0];
    }
}
