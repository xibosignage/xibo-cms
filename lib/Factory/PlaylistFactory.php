<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

use Carbon\Carbon;
use Xibo\Entity\Playlist;
use Xibo\Entity\User;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class PlaylistFactory
 * @package Xibo\Factory
 */
class PlaylistFactory extends BaseFactory
{
    use TagTrait;

    private PermissionFactory $permissionFactory;

    private WidgetFactory $widgetFactory;

    private ConfigServiceInterface $config;

    /**
     * Construct a factory
     * @param ConfigServiceInterface $config
     * @param User $user
     * @param UserFactory $userFactory
     * @param PermissionFactory $permissionFactory
     * @param WidgetFactory $widgetFactory
     */
    public function __construct(
        ConfigServiceInterface $config,
        User $user,
        UserFactory $userFactory,
        PermissionFactory $permissionFactory,
        WidgetFactory $widgetFactory
    ) {
        $this->setAclDependencies($user, $userFactory);

        $this->config = $config;
        $this->permissionFactory = $permissionFactory;
        $this->widgetFactory = $widgetFactory;
    }

    /**
     * @return Playlist
     */
    public function createEmpty(): Playlist
    {
        return new Playlist(
            $this->getStore(),
            $this->getLog(),
            $this->getDispatcher(),
            $this->config,
            $this->permissionFactory,
            $this,
            $this->widgetFactory
        );
    }

    /**
     * Load Playlists by
     * @param $regionId
     * @return Playlist
     * @throws NotFoundException
     */
    public function getByRegionId($regionId): Playlist
    {
        $playlists = $this->query(null, array('disableUserCheck' => 1, 'regionId' => $regionId));

        if (count($playlists) <= 0) {
            $this->getLog()->error(
                'Region ' . $regionId . ' does not have a Playlist associated,
                 please try to set a new owner in Permissions.'
            );
            throw new NotFoundException(
                __('One of the Regions on this Layout does not have a Playlist,
                 please contact your administrator.')
            );
        }

        return $playlists[0];
    }

    /**
     * @param $campaignId
     * @return Playlist[]
     * @throws NotFoundException
     */
    public function getByCampaignId($campaignId): array
    {
        return $this->query(null, ['disableUserCheck' => 1, 'campaignId' => $campaignId]);
    }

    /**
     * Get by Id
     * @param int $playlistId
     * @param bool $disableUserCheck
     * @return Playlist
     * @throws NotFoundException
     */
    public function getById(int $playlistId, bool $disableUserCheck = true): Playlist
    {
        $playlists = $this->query(null, [
            'disableUserCheck' => $disableUserCheck ? 1 : 0,
            'playlistId' => $playlistId
        ]);

        if (count($playlists) <= 0) {
            throw new NotFoundException(__('Cannot find playlist'));
        }

        return $playlists[0];
    }

    /**
     * Get by OwnerId
     * @param int $ownerId
     * @return Playlist[]
     * @throws NotFoundException
     */
    public function getByOwnerId($ownerId): array
    {
        return $this->query(null, ['userId' => $ownerId, 'regionSpecific' => 0]);
    }

    /**
     * @param $folderId
     * @return Playlist[]
     * @throws NotFoundException
     */
    public function getByFolderId($folderId): array
    {
        return $this->query(null, ['disableUserCheck' => 1, 'folderId' => $folderId]);
    }

    /**
     * Create a Playlist
     * @param string $name
     * @param int $ownerId
     * @param int|null $regionId
     * @return Playlist
     */
    public function create(string $name, int $ownerId, ?int $regionId = null): Playlist
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
     * @param ?array $sortOrder
     * @param array $filterBy
     * @return Playlist[]
     * @throws NotFoundException
     */
    public function query(?array $sortOrder = null, array $filterBy = []): array
    {
        $parsedFilter = $this->getSanitizer($filterBy);
        $allowedColumns = [
            'playlistId', 'name', 'duration', 'owner', 'isDynamic', 'enableStat', 'createdDt', 'modifiedDt'
        ];

        $sortOrder = $this->buildSortQuery(
            $sortOrder,
            $allowedColumns,
            defaultSort: ['name ASC']
        );

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
                `playlist`.filterMediaNameLogicalOperator,
                `playlist`.filterMediaTags,
                `playlist`.filterExactTags,
                `playlist`.filterMediaTagsLogicalOperator,
                `playlist`.filterFolderId,
                `playlist`.maxNumberOfItems,
                `playlist`.requiresDurationUpdate,
                `playlist`.enableStat,
                `playlist`.folderId,
                `playlist`.permissionsFolderId,
                `folder`.folderName,
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
                LEFT OUTER JOIN `user` 
                ON `user`.userId = `playlist`.ownerId
                LEFT OUTER JOIN `folder`
                ON `playlist`.folderId = `folder`.folderId
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
        if ($parsedFilter->getInt('ownerUserGroupId', ['default' => 0]) != 0) {
            $body .= ' AND `playlist`.ownerId IN (
                        SELECT DISTINCT userId FROM `lkusergroup` WHERE groupId =  :ownerUserGroupId
                    ) ';
            $params['ownerUserGroupId'] = $parsedFilter->getInt('ownerUserGroupId', ['default' => 0]);
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
                $params['requiresDurationUpdate'] = Carbon::now()->format('U');
            } else {
                // Ahead of now means we don't need to update yet, or we are set to 0 and we never update
                $body .= ' AND (`playlist`.requiresDurationUpdate > :requiresDurationUpdate
                 OR `playlist`.requiresDurationUpdate = 0)';
                $params['requiresDurationUpdate'] = Carbon::now()->format('U');
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
            if ($parsedFilter->getInt('regionSpecific') === 1) {
                $body .= ' AND `playlist`.regionId IS NOT NULL ';
            } else {
                $body .= ' AND `playlist`.regionId IS NULL ';
            }
        }

        if ($parsedFilter->getInt('layoutId', $filterBy) !== null) {
            $body .= '
                AND playlist.playlistId IN (
                       SELECT lkplaylistplaylist.childId
                        FROM region
                        INNER JOIN playlist
                            ON playlist.regionId = region.regionId
                        INNER JOIN lkplaylistplaylist
                            ON lkplaylistplaylist.parentId = playlist.playlistId
                        WHERE region.layoutId = :layoutId
                )';
            $params['layoutId'] = $parsedFilter->getInt('layoutId', $filterBy);
        }

        if ($parsedFilter->getInt('campaignId') !== null) {
            $body .= '
                AND `playlist`.playlistId IN (
                    SELECT `lkplaylistplaylist`.childId
                    FROM region
                    INNER JOIN playlist
                        ON `playlist`.regionId = `region`.regionId
                    INNER JOIN lkplaylistplaylist
                        ON `lkplaylistplaylist`.parentId = `playlist`.playlistId
                    INNER JOIN widget
                        ON `widget`.playlistId = `lkplaylistplaylist`.childId
                    INNER JOIN lkwidgetmedia
                        ON `widget`.widgetId = `lkwidgetmedia`.widgetId        
                    INNER JOIN `lkcampaignlayout` lkcl
                            ON lkcl.layoutid = region.layoutid
                            AND lkcl.CampaignID = :campaignId
            )';
            $params['campaignId'] = $parsedFilter->getInt('campaignId');
        }

        // Playlist Like
        if ($parsedFilter->getString('name') != '') {
            $terms = explode(',', $parsedFilter->getString('name'));
            $logicalOperator = $parsedFilter->getString('logicalOperatorName', ['default' => 'OR']);
            $this->nameFilter(
                'playlist',
                'name',
                $terms,
                $body,
                $params,
                ($parsedFilter->getCheckbox('useRegexForName') == 1),
                $logicalOperator
            );
        }

        // Playlist exact name
        if ($parsedFilter->getString('playlistExact') != '') {
            $body.= ' AND playlist.name = :exact ';
            $params['exact'] = $parsedFilter->getString('playlistExact');
        }

        if ($parsedFilter->getString('keyword') != null) {
            // Fulltext search
            $body .= $this->buildSearchQuery(
                $parsedFilter->getString('keyword'),
                $params,
                ['playlist.name'],
                ['playlist.playlistId']
            );
        }

        // Not PlaylistId
        if ($parsedFilter->getInt('notPlaylistId', ['default' => 0]) != 0) {
            $body .= ' AND playlist.playlistId <> :notPlaylistId ';
            $params['notPlaylistId'] = $parsedFilter->getInt('notPlaylistId', ['default' => 0]);
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
                $logicalOperator = $parsedFilter->getString('logicalOperator', ['default' => 'OR']);
                $allTags = explode(',', $tagFilter);
                $notTags = [];
                $tags = [];

                foreach ($allTags as $tag) {
                    if (str_starts_with($tag, '-')) {
                        $notTags[] = ltrim(($tag), '-');
                    } else {
                        $tags[] = $tag;
                    }
                }

                if (!empty($notTags)) {
                    $body .=  ' AND `playlist`.playlistID NOT IN (
                        SELECT lktagplaylist.playlistId
                          FROM tag
                            INNER JOIN lktagplaylist
                            ON lktagplaylist.tagId = tag.tagId
                    ';

                    $this->tagFilter(
                        $notTags,
                        'lktagplaylist',
                        'lkTagPlaylistId',
                        'playlistId',
                        $logicalOperator,
                        $operator,
                        true,
                        $body,
                        $params
                    );
                }

                if (!empty($tags)) {
                    $body .=  ' AND `playlist`.playlistID IN (
                        SELECT lktagplaylist.playlistId
                          FROM tag
                            INNER JOIN lktagplaylist
                            ON lktagplaylist.tagId = tag.tagId
                    ';

                    $this->tagFilter(
                        $tags,
                        'lktagplaylist',
                        'lkTagPlaylistId',
                        'playlistId',
                        $logicalOperator,
                        $operator,
                        false,
                        $body,
                        $params
                    );
                }
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

            $params['mediaId'] = $parsedFilter->getInt('mediaId', ['default' => 0]);
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

        if ($parsedFilter->getInt('filterFolderId') !== null) {
            $body .= ' AND `playlist`.filterFolderId = :filterFolderId ';
            $params['filterFolderId'] = $parsedFilter->getInt('filterFolderId');
        }

        if ($parsedFilter->getInt('folderId') !== null) {
            $body .= ' AND `playlist`.folderId = :folderId ';
            $params['folderId'] = $parsedFilter->getInt('folderId');
        }

        // Logged in user view permissions
        $this->viewPermissionSql(
            'Xibo\Entity\Playlist',
            $body,
            $params,
            'playlist.playlistId',
            'playlist.ownerId',
            $filterBy,
            '`playlist`.permissionsFolderId'
        );

        // Sorting?
        $order = !empty($sortOrder) ? ' ORDER BY ' . implode(', ', $sortOrder) : '';

        $limit = '';
        // Paging
        if ($filterBy !== null && $parsedFilter->getInt('start') !== null && $parsedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $parsedFilter->getInt('start', ['default' => 0]) .
                ', ' . $parsedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;
        $playlistIds = [];

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $playlist = $this->createEmpty()->hydrate($row, [
                'intProperties' => [
                    'requiresDurationUpdate',
                    'isDynamic',
                    'maxNumberOfItems',
                    'duration'
                ]
            ]);
            $playlistIds[] = $playlist->playlistId;
            $entries[] = $playlist;
        }

        // decorate with TagLinks
        if (count($entries) > 0) {
            $this->decorateWithTagLinks('lktagplaylist', 'playlistId', $playlistIds, $entries);
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
