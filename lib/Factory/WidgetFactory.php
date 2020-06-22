<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (WidgetFactory.php) is part of Xibo.
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


use Xibo\Entity\User;
use Xibo\Entity\Widget;
use Xibo\Exception\NotFoundException;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class WidgetFactory
 * @package Xibo\Factory
 */
class WidgetFactory extends BaseFactory
{

    /** @var  DateServiceInterface */
    private $dateService;

    /**
     * @var WidgetOptionFactory
     */
    private $widgetOptionFactory;

    /**
     * @var WidgetMediaFactory
     */
    private $widgetMediaFactory;

    /** @var  WidgetAudioFactory */
    private $widgetAudioFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     * @param DateServiceInterface $date
     * @param WidgetOptionFactory $widgetOptionFactory
     * @param WidgetMediaFactory $widgetMediaFactory
     * @param WidgetAudioFactory $widgetAudioFactory
     * @param PermissionFactory $permissionFactory
     * @param DisplayFactory $displayFactory
     *
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $date, $widgetOptionFactory, $widgetMediaFactory, $widgetAudioFactory, $permissionFactory, $displayFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);
        $this->dateService = $date;
        $this->widgetOptionFactory = $widgetOptionFactory;
        $this->widgetMediaFactory = $widgetMediaFactory;
        $this->widgetAudioFactory = $widgetAudioFactory;
        $this->permissionFactory = $permissionFactory;
        $this->displayFactory = $displayFactory;
    }

    /**
     * Create Empty
     * @return Widget
     */
    public function createEmpty()
    {
        return new Widget(
            $this->getStore(),
            $this->getLog(),
            $this->dateService,
            $this->widgetOptionFactory,
            $this->widgetMediaFactory,
            $this->widgetAudioFactory,
            $this->permissionFactory,
            $this->displayFactory
        );
    }

    /**
     * Load widgets by Playlist ID
     * @param int $playlistId
     * @return array[Widget]
     */
    public function getByPlaylistId($playlistId)
    {
        return $this->query(null, array('disableUserCheck' => 1, 'playlistId' => $playlistId));
    }

    /**
     * Load widgets by MediaId
     * @param int $mediaId
     * @return array[Widget]
     */
    public function getByMediaId($mediaId)
    {
        return $this->query(null, array('disableUserCheck' => 1, 'mediaId' => $mediaId));
    }

    /**
     * Get Widget by its ID
     *  first check the lkwidgetmedia table for any active links
     *  if that fails, check the widgethistory table
     *  in either case, if we find a widget that isn't a media record, then still throw not found
     * @param int $widgetId
     * @return int|null
     * @throws \Xibo\Exception\NotFoundException
     */
    public function getWidgetForStat($widgetId)
    {
        // Try getting the widget directly
        $row = $this->getStore()->select('
            SELECT `widget`.widgetId, `lkwidgetmedia`.mediaId 
              FROM `widget`
                LEFT OUTER JOIN `lkwidgetmedia`
                ON `lkwidgetmedia`.widgetId = `widget`.widgetId
             WHERE `widget`.widgetId = :widgetId
        ', [
            'widgetId' => $widgetId
        ]);

        // The widget doesn't exist
        if (count($row) <= 0) {
            // Try and get the same from the widget history table
            $row = $this->getStore()->select('
                SELECT widgetId, mediaId 
                  FROM `widgethistory`
                 WHERE widgetId = :widgetId
            ', [
                'widgetId' => $widgetId
            ]);

            // The widget didn't ever exist
            if (count($row) <= 0) {
                throw new NotFoundException();
            }
        }

        return ($row[0]['mediaId'] == null) ? null : intval($row[0]['mediaId']);
    }

    /**
     * Get widget by widget id
     * @param $widgetId
     * @return Widget
     */
    public function getById($widgetId)
    {
        $widgets = $this->query(null, array('disableUserCheck' => 1, 'widgetId' => $widgetId));
        return $widgets[0];
    }

    /**
     * Load widget by widget id
     * @param $widgetId
     * @return Widget
     * @throws NotFoundException
     */
    public function loadByWidgetId($widgetId)
    {
        $widgets = $this->query(null, array('disableUserCheck' => 1, 'widgetId' => $widgetId));

        if (count($widgets) <= 0)
            throw new NotFoundException(__('Widget not found'));

        $widget = $widgets[0];
        /* @var Widget $widget */
        $widget->load();
        return $widget;
    }

    /**
     * Create a new widget
     * @param int $ownerId
     * @param int $playlistId
     * @param string $type
     * @param int $duration
     * @return Widget
     */
    public function create($ownerId, $playlistId, $type, $duration)
    {
        $widget = $this->createEmpty();
        $widget->ownerId = $ownerId;
        $widget->playlistId = $playlistId;
        $widget->type = $type;
        $widget->duration = $duration;
        $widget->displayOrder = 1;

        return $widget;
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return Widget[]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder == null)
            $sortOrder = array('displayOrder');

        $entries = array();

        $params = array();
        $select = '
          SELECT widget.widgetId,
              widget.playlistId,
              widget.ownerId,
              widget.type,
              widget.duration,
              widget.displayOrder,
              `widget`.useDuration,
              `widget`.calculatedDuration,
              `widget`.fromDt,
              `widget`.toDt, 
              `widget`.createdDt, 
              `widget`.modifiedDt,
              `widget`.calculatedDuration,
              `playlist`.name AS playlist
        ';

        if (is_array($sortOrder) && (in_array('`widget`', $sortOrder) || in_array('`widget` DESC', $sortOrder))) {
            // output a pseudo column for the widget name
            $select .= '
                , IFNULL((
                    SELECT `value` AS name
                      FROM `widgetoption`
                     WHERE `widgetoption`.widgetId = `widget`.widgetId
                        AND `widgetoption`.type = \'attrib\'
                        AND `widgetoption`.option = \'name\'
                ), `widget`.type) AS widget
            ';
        }

        $body = '
          FROM `widget`
            INNER JOIN `playlist`
            ON `playlist`.playlistId = `widget`.playlistId
        ';

        if ($this->getSanitizer()->getInt('showWidgetsFrom', $filterBy) === 1) {
            $body .= '
                    INNER JOIN `region`
                        ON `region`.regionId = `playlist`.regionId
                    INNER JOIN `layout`
                        ON `layout`.layoutId = `region`.layoutId
            ';
        }

        if ($this->getSanitizer()->getInt('mediaId', $filterBy) !== null) {
            $body .= '
                INNER JOIN `lkwidgetmedia`
                ON `lkwidgetmedia`.widgetId = widget.widgetId
                    AND `lkwidgetmedia`.mediaId = :mediaId
            ';
            $params['mediaId'] = $this->getSanitizer()->getInt('mediaId', $filterBy);
        }

        $body .= ' WHERE 1 = 1 ';

        if ($this->getSanitizer()->getInt('showWidgetsFrom', $filterBy) === 1) {
            $body .= ' AND layout.parentId IS NOT NULL ';
        }

        if ($this->getSanitizer()->getInt('showWidgetsFrom', $filterBy) === 2) {
            $body .= ' AND playlist.regionId IS NULL ';
        }

        // Permissions
        $this->viewPermissionSql('Xibo\Entity\Widget', $body, $params, 'widget.widgetId', 'widget.ownerId', $filterBy);

        if ($this->getSanitizer()->getInt('playlistId', $filterBy) !== null) {
            $body .= ' AND `widget`.playlistId = :playlistId';
            $params['playlistId'] = $this->getSanitizer()->getInt('playlistId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('widgetId', $filterBy) !== null) {
            $body .= ' AND `widget`.widgetId = :widgetId';
            $params['widgetId'] = $this->getSanitizer()->getInt('widgetId', $filterBy);
        }

        if ($this->getSanitizer()->getString('type', $filterBy) !== null) {
            $body .= ' AND `widget`.type = :type';
            $params['type'] = $this->getSanitizer()->getString('type', $filterBy);
        }

        if ($this->getSanitizer()->getString('layout', $filterBy) !== null) {
            $body .= ' AND widget.widgetId IN (
                SELECT widgetId
                  FROM `widget`
                    INNER JOIN `playlist`
                    ON `widget`.playlistId = `playlist`.playlistId
                    INNER JOIN `region`
                    ON `region`.regionId = `playlist`.regionId
                    INNER JOIN `layout`
                    ON `layout`.layoutId = `region`.layoutId
                 WHERE layout.layout LIKE :layout
            )';
            $params['layout'] = '%' . $this->getSanitizer()->getString('layout', $filterBy) . '%';
        }

        if ($this->getSanitizer()->getString('region', $filterBy) !== null) {
            $body .= ' AND widget.widgetId IN (
                SELECT widgetId
                  FROM `widget`
                    INNER JOIN `playlist`
                    ON `widget`.playlistId = `playlist`.playlistId
                    INNER JOIN `region`
                    ON `region`.regionId = `playlist`.regionId
                 WHERE region.name LIKE :region
            )';
            $params['region'] = '%' . $this->getSanitizer()->getString('region', $filterBy) . '%';
        }

        if ($this->getSanitizer()->getString('media', $filterBy) !== null) {
            $body .= ' AND widget.widgetId IN (
                SELECT widgetId
                  FROM `lkwidgetmedia`
                    INNER JOIN `media`
                    ON `media`.mediaId = `lkwidgetmedia`.mediaId
                 WHERE media.name LIKE :media
            )';
            $params['media'] = '%' . $this->getSanitizer()->getString('media', $filterBy) . '%';
        }

        // Playlist Like
        if ($this->getSanitizer()->getString('playlist', $filterBy) != '') {
            $terms = explode(',', $this->getSanitizer()->getString('playlist', $filterBy));
            $this->nameFilter('playlist', 'name', $terms, $body, $params, ($this->getSanitizer()->getCheckbox('useRegexForName', $filterBy) == 1));
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= ' ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        // The final statements
        $sql = $select . $body . $order . $limit;



        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, ['intProperties' => [
                'duration', 'useDuration', 'calculatedDuration', 'fromDt', 'toDt', 'createdDt', 'modifiedDt']
            ]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}