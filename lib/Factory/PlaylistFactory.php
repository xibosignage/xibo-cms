<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (PlaylistFactory.php) is part of Xibo.
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
use Xibo\Exception\NotFoundException;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

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
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     * @param DateServiceInterface $date
     * @param PermissionFactory $permissionFactory
     * @param WidgetFactory $widgetFactory
     * @param TagFactory $tagFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $date, $permissionFactory, $widgetFactory, $tagFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);

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

        return $playlist;
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return Playlist[]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = array();

        $params = array();
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
                (
                SELECT GROUP_CONCAT(DISTINCT tag) 
                  FROM tag 
                    INNER JOIN lktagplaylist 
                    ON lktagplaylist.tagId = tag.tagId 
                 WHERE lktagplaylist.playlistId = playlist.playlistId 
                GROUP BY lktagplaylist.playlistId
                ) AS tags,
                
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

        if ($this->getSanitizer()->getInt('playlistId', $filterBy) !== null) {
            $body .= ' AND `playlist`.playlistId = :playlistId ';
            $params['playlistId'] = $this->getSanitizer()->getInt('playlistId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('notPlaylistId', $filterBy) !== null) {
            $body .= ' AND `playlist`.playlistId <> :notPlaylistId ';
            $params['notPlaylistId'] = $this->getSanitizer()->getInt('notPlaylistId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('userId', $filterBy) !== null) {
            $body .= ' AND `playlist`.ownerId = :ownerId ';
            $params['ownerId'] = $this->getSanitizer()->getInt('userId', $filterBy);
        }

        // User Group filter
        if ($this->getSanitizer()->getInt('ownerUserGroupId', 0, $filterBy) != 0) {
            $body .= ' AND `playlist`.ownerId IN (SELECT DISTINCT userId FROM `lkusergroup` WHERE groupId =  :ownerUserGroupId) ';
            $params['ownerUserGroupId'] = $this->getSanitizer()->getInt('ownerUserGroupId', 0, $filterBy);
        }

        if ($this->getSanitizer()->getInt('regionId', $filterBy) !== null) {
            $body .= ' AND `playlist`.regionId = :regionId ';
            $params['regionId'] = $this->getSanitizer()->getInt('regionId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('requiresDurationUpdate', $filterBy) !== null) {
            $body .= ' AND `playlist`.requiresDurationUpdate = :requiresDurationUpdate ';
            $params['requiresDurationUpdate'] = $this->getSanitizer()->getInt('requiresDurationUpdate', $filterBy);
        }

        if ($this->getSanitizer()->getInt('isDynamic', $filterBy) !== null) {
            $body .= ' AND `playlist`.isDynamic = :isDynamic ';
            $params['isDynamic'] = $this->getSanitizer()->getInt('isDynamic', $filterBy);
        }

        if ($this->getSanitizer()->getInt('childId', $filterBy) !== null) {
            $body .= ' 
                AND `playlist`.playlistId IN (
                    SELECT parentId 
                      FROM `lkplaylistplaylist` 
                     WHERE childId = :childId
            ';

            if ($this->getSanitizer()->getInt('depth', $filterBy) !== null) {
                $body .= ' AND depth = :depth ';
                $params['depth'] = $this->getSanitizer()->getInt('depth', $filterBy);
            }

            $body .= '
                ) 
            ';
            $params['childId'] = $this->getSanitizer()->getInt('childId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('regionSpecific', $filterBy) !== null) {
            if ($this->getSanitizer()->getInt('regionSpecific', $filterBy) === 1)
                $body .= ' AND `playlist`.regionId IS NOT NULL ';
            else
                $body .= ' AND `playlist`.regionId IS NULL ';
        }

        // Logged in user view permissions
        $this->viewPermissionSql('Xibo\Entity\Playlist', $body, $params, 'playlist.playlistId', 'playlist.ownerId', $filterBy);

        // Layout Like
        if ($this->getSanitizer()->getString('name', $filterBy) != '') {
            // convert into a space delimited array
            $names = explode(' ', $this->getSanitizer()->getString('name', $filterBy));

            $i = 0;
            foreach($names as $searchName)
            {
                $i++;

                // Ignore if the word is empty
                if($searchName == '')
                    continue;

                // Not like, or like?
                if (substr($searchName, 0, 1) == '-') {
                    $body.= " AND  `playlist`.name NOT LIKE (:search$i) ";
                    $params['search' . $i] = '%' . ltrim($searchName) . '%';
                }
                else {
                    $body.= " AND  `playlist`.name LIKE (:search$i) ";
                    $params['search' . $i] = '%' . $searchName . '%';
                }
            }
        }

        // Tags
        if ($this->getSanitizer()->getString('tags', $filterBy) != '') {

            $tagFilter = $this->getSanitizer()->getString('tags', $filterBy);

            if (trim($tagFilter) === '--no-tag') {
                $body .= ' AND `playlist`.playlistID NOT IN (
                    SELECT `lktagplaylist`.playlistId
                     FROM `tag`
                        INNER JOIN `lktagplaylist`
                        ON `lktagplaylist`.tagId = `tag`.tagId
                    )
                ';
            } else {
                $operator = $this->getSanitizer()->getCheckbox('exactTags') == 1 ? '=' : 'LIKE';

                $body .= " AND `playlist`.playlistID IN (
                SELECT lktagplaylist.playlistId
                  FROM tag
                    INNER JOIN lktagplaylist
                    ON lktagplaylist.tagId = tag.tagId
                ";
                $i = 0;
                foreach (explode(',', $tagFilter) as $tag) {
                    $i++;

                    if ($i == 1)
                        $body .= ' WHERE `tag` ' . $operator . ' :tags' . $i;
                    else
                        $body .= ' OR `tag` ' . $operator . ' :tags' . $i;

                    if ($operator === '=')
                        $params['tags' . $i] = $tag;
                    else
                        $params['tags' . $i] = '%' . $tag . '%';
                }

                $body .= " ) ";
            }
        }

        // MediaID
        if ($this->getSanitizer()->getInt('mediaId', $filterBy) !== null) {
            // TODO: sub-playlists
            $body .= ' AND `playlist`.playlistId IN (
                SELECT DISTINCT `widget`.playlistId
                  FROM `lkwidgetmedia`
                    INNER JOIN `widget`
                    ON `widget`.widgetId = `lkwidgetmedia`.widgetId
                 WHERE `lkwidgetmedia`.mediaId = :mediaId
                )
            ';

            $params['mediaId'] = $this->getSanitizer()->getInt('mediaId', 0, $filterBy);
        }

        // Media Like
        if ($this->getSanitizer()->getString('mediaLike', $filterBy) !== null) {
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

            $params['mediaLike'] = '%' . $this->getSanitizer()->getString('mediaLike', $filterBy) . '%';
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