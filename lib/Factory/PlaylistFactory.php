<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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

use Xibo\Entity\Playlist;
use Xibo\Entity\User;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class PlaylistFactory
 * @package Xibo\Factory
 */
class PlaylistFactory extends BaseFactory
{
    /**
     * @var DateServiceInterface
     */
    public $dateService;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var WidgetFactory
     */
    private $widgetFactory;

    /** @var TagFactory */
    private $tagFactory;

    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param SanitizerServiceInterface $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     * @param DateServiceInterface $date
     * @param PermissionFactory $permissionFactory
     * @param WidgetFactory $widgetFactory
     * @param TagFactory $tagFactory
     */
    public function __construct($store, $log, $config, $sanitizerService, $user, $userFactory, $date, $permissionFactory, $widgetFactory, $tagFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);

        $this->config = $config;
        $this->dateService = $date;
        $this->permissionFactory = $permissionFactory;
        $this->widgetFactory = $widgetFactory;
        $this->tagFactory = $tagFactory;
    }

    /**
     * @return Playlist
     */
    public function createEmpty()
    {
        return new Playlist(
            $this->getStore(),
            $this->getLog(),
            $this->config,
            $this->dateService,
            $this->permissionFactory,
            $this,
            $this->widgetFactory,
            $this->tagFactory
        );
    }

    /**
     * Load Playlists by
     * @param $regionId
     * @return Playlist
     * @throws NotFoundException
     */
    public function getByRegionId($regionId)
    {
        $playlists = $this->query(null, array('disableUserCheck' => 1, 'regionId' => $regionId));

        if (count($playlists) <= 0)
            throw new NotFoundException(__('Cannot find playlist'));

        return $playlists[0];
    }

    /**
     * Get by Id
     * @param int $playlistId
     * @return Playlist
     * @throws NotFoundException
     */
    public function getById($playlistId)
    {
        $playlists = $this->query(null, array('disableUserCheck' => 1, 'playlistId' => $playlistId));

        if (count($playlists) <= 0)
            throw new NotFoundException(__('Cannot find playlist'));

        return $playlists[0];
    }

    /**
     * Get by OwnerId
     * @param int $ownerId
     * @return Playlist[]
     * @throws NotFoundException
     */
    public function getByOwnerId($ownerId)
    {
        return $this->query(null, ['userId' => $ownerId, 'regionSpecific' => 0]);
    }

    /**
     * Create a Playlist
     * @param string $name
     * @param int $ownerId
     * @param int|null $regionId
     * @return Playlist
     */
    public function create($name, $ownerId, $regionId = null)
    {
        $playlist = $this->createEmpty();
        $playlist->name = $name;
        $playlist->ownerId = $ownerId;
        $playlist->regionId = $regionId;
        $playlist->isDynamic = 0;
        $playlist->requiresDurationUpdate = 1;

        return $playlist;
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return Playlist[]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $parsedFilter = $this->getSanitizer($filterBy);
        $entries = [];

        $params = [];
        $select = '
            SELECT `playlist`.playlistId,
                `playlist`.ownerId,
                `playlist`.name,
                `user`.UserName AS owner, 
                `playlist`.regionId,
                `playlist`.createdDt,
                `playlist`.modifiedDt,
                `playlist`.duration,
                `playlist`.isDynamic,
                `playlist`.filterMediaName,
                `playlist`.filterMediaTags,
                `playlist`.requiresDurationUpdate,
                `playlist`.enableStat,
                (
                SELECT GROUP_CONCAT(DISTINCT tag) 
                  FROM tag 
                    INNER JOIN lktagplaylist 
                    ON lktagplaylist.tagId = tag.tagId 
                 WHERE lktagplaylist.playlistId = playlist.playlistId 
                GROUP BY lktagplaylist.playlistId
                ) AS tags,
                
                (
                SELECT GROUP_CONCAT(IFNULL(value, \'NULL\')) 
                  FROM tag 
                    INNER JOIN lktagplaylist 
                    ON lktagplaylist.tagId = tag.tagId 
                 WHERE lktagplaylist.playlistId = playlist.playlistId 
                GROUP BY lktagplaylist.playlistId
                ) AS tagValues,
                
                (
                SELECT GROUP_CONCAT(DISTINCT `group`.group)
                  FROM `permission`
                    INNER JOIN `permissionentity`
                    ON `permissionentity`.entityId = permission.entityId
                    INNER JOIN `group`
                    ON `group`.groupId = `permission`.groupId
                 WHERE entity = :permissionEntityForGroup
                    AND objectId = playlist.playlistId
                    AND view = 1
                ) AS groupsWithPermissions
        ';

        $params['permissionEntityForGroup'] = 'Xibo\\Entity\\Playlist';

        $body = '  
              FROM `playlist` 
                INNER JOIN `user` 
                ON `user`.userId = `playlist`.ownerId
             WHERE 1 = 1 
        ';

        if ($parsedFilter->getInt('playlistId') !== null) {
            $body .= ' AND `playlist`.playlistId = :playlistId ';
            $params['playlistId'] = $parsedFilter->getInt('playlistId');
        }

        if ($parsedFilter->getInt('notPlaylistId') !== null) {
            $body .= ' AND `playlist`.playlistId <> :notPlaylistId ';
            $params['notPlaylistId'] = $parsedFilter->getInt('notPlaylistId');
        }

        if ($parsedFilter->getInt('userId') !== null) {
            $body .= ' AND `playlist`.ownerId = :ownerId ';
            $params['ownerId'] = $parsedFilter->getInt('userId');
        }

        // User Group filter
        if ($parsedFilter->getInt('ownerUserGroupId',['default' => 0]) != 0) {
            $body .= ' AND `playlist`.ownerId IN (SELECT DISTINCT userId FROM `lkusergroup` WHERE groupId =  :ownerUserGroupId) ';
            $params['ownerUserGroupId'] = $parsedFilter->getInt('ownerUserGroupId',['default' => 0]);
        }

        if ($parsedFilter->getInt('regionId') !== null) {
            $body .= ' AND `playlist`.regionId = :regionId ';
            $params['regionId'] = $parsedFilter->getInt('regionId');
        }

        if ($parsedFilter->getInt('requiresDurationUpdate') !== null) {
            // Either 1, or 0
            if ($parsedFilter->getInt('requiresDurationUpdate') == 1) {
                // Not 0 and behind now.
                $body .= ' AND `playlist`.requiresDurationUpdate <= :requiresDurationUpdate ';
                $body .= ' AND `playlist`.requiresDurationUpdate <> 0 ';
                $params['requiresDurationUpdate'] = time();
            } else {
                // Ahead of now means we don't need to update yet, or we are set to 0 and we never update
                $body .= ' AND (`playlist`.requiresDurationUpdate > :requiresDurationUpdate OR `playlist`.requiresDurationUpdate = 0)';
                $params['requiresDurationUpdate'] = time();
            }
        }

        if ($parsedFilter->getInt('isDynamic') !== null) {
            $body .= ' AND `playlist`.isDynamic = :isDynamic ';
            $params['isDynamic'] = $parsedFilter->getInt('isDynamic');
        }

        if ($parsedFilter->getInt('childId') !== null) {
            $body .= ' 
                AND `playlist`.playlistId IN (
                    SELECT parentId 
                      FROM `lkplaylistplaylist` 
                     WHERE childId = :childId
            ';

            if ($parsedFilter->getInt('depth') !== null) {
                $body .= ' AND depth = :depth ';
                $params['depth'] = $parsedFilter->getInt('depth');
            }

            $body .= '
                ) 
            ';
            $params['childId'] = $parsedFilter->getInt('childId');
        }

        if ($parsedFilter->getInt('regionSpecific') !== null) {
            if ($parsedFilter->getInt('regionSpecific') === 1)
                $body .= ' AND `playlist`.regionId IS NOT NULL ';
            else
                $body .= ' AND `playlist`.regionId IS NULL ';
        }

        // Logged in user view permissions
        $this->viewPermissionSql('Xibo\Entity\Playlist', $body, $params, 'playlist.playlistId', 'playlist.ownerId', $filterBy);

        // Playlist Like
        if ($parsedFilter->getString('name') != '') {
            $terms = explode(',', $parsedFilter->getString('name'));
            $this->nameFilter('playlist', 'name', $terms, $body, $params);
        }

        // Playlist exact name
        if ($parsedFilter->getString('playlistExact') != '') {
            $body.= " AND playlist.name = :exact ";
            $params['exact'] = $parsedFilter->getString('playlistExact');
        }

        // Not PlaylistId
        if ($parsedFilter->getInt('notPlaylistId', ['default' => 0]) != 0) {
            $body .= " AND playlist.playlistId <> :notPlaylistId ";
            $params['notPlaylistId'] = $parsedFilter->getInt('notPlaylistId',['default' => 0]);
        }

        // Tags
        if ($parsedFilter->getString('tags') != '') {

            $tagFilter = $parsedFilter->getString('tags', $filterBy);

            if (trim($tagFilter) === '--no-tag') {
                $body .= ' AND `playlist`.playlistID NOT IN (
                    SELECT `lktagplaylist`.playlistId
                     FROM `tag`
                        INNER JOIN `lktagplaylist`
                        ON `lktagplaylist`.tagId = `tag`.tagId
                    )
                ';
            } else {
                $operator = $parsedFilter->getCheckbox('exactTags') == 1 ? '=' : 'LIKE';

                $body .= " AND `playlist`.playlistID IN (
                SELECT lktagplaylist.playlistId
                  FROM tag
                    INNER JOIN lktagplaylist
                    ON lktagplaylist.tagId = tag.tagId
                ";

                $tags = explode(',', $tagFilter);
                $this->tagFilter($tags, $operator, $body, $params);
            }
        }

        // MediaID
        if ($parsedFilter->getInt('mediaId') !== null) {
            // TODO: sub-playlists
            $body .= ' AND `playlist`.playlistId IN (
                SELECT DISTINCT `widget`.playlistId
                  FROM `lkwidgetmedia`
                    INNER JOIN `widget`
                    ON `widget`.widgetId = `lkwidgetmedia`.widgetId
                 WHERE `lkwidgetmedia`.mediaId = :mediaId
                )
            ';

            $params['mediaId'] = $parsedFilter->getInt('mediaId',['default' => 0]);
        }

        // Media Like
        if (!empty($parsedFilter->getString('mediaLike'))) {
            // TODO: sub-playlists
            $body .= ' AND `playlist`.playlistId IN (
                SELECT DISTINCT `widget`.playlistId
                  FROM `lkwidgetmedia`
                    INNER JOIN `widget`
                    ON `widget`.widgetId = `lkwidgetmedia`.widgetId
                    INNER JOIN `media` 
                    ON `lkwidgetmedia`.mediaId = `media`.mediaId
                 WHERE `media`.name LIKE :mediaLike
                )
            ';

            $params['mediaLike'] = '%' . $parsedFilter->getString('mediaLike') . '%';
        }

        $user = $this->getUser();

        if ( ($user->userTypeId == 1 && $user->showContentFrom == 2) || $user->userTypeId == 4 ) {
            $body .= ' AND user.userTypeId = 4 ';
        } else {
            $body .= ' AND user.userTypeId <> 4 ';
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder)) {
            $order .= 'ORDER BY ' . implode(',', $sortOrder);
        } else {
            $order .= 'ORDER BY `playlist`.name ';
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $parsedFilter->getInt('start') !== null && $parsedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . intval($parsedFilter->getInt('start'), 0) . ', ' . $parsedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $playlist = $this->createEmpty()->hydrate($row, ['intProperties' => ['requiresDurationUpdate', 'isDynamic']]);
            $playlist->excludeProperty('requiresDurationUpdate');
            $entries[] = $playlist;
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            unset($params['permissionEntityForGroup']);
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}