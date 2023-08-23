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


namespace Xibo\Factory;

use Xibo\Entity\Module;
use Xibo\Entity\User;
use Xibo\Entity\Widget;
use Xibo\Service\DisplayNotifyServiceInterface;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class WidgetFactory
 * @package Xibo\Factory
 */
class WidgetFactory extends BaseFactory
{

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

    /** @var DisplayNotifyServiceInterface */
    private $displayNotifyService;

    /** @var ActionFactory */
    private $actionFactory;

    /** @var \Xibo\Factory\ModuleTemplateFactory */
    private $moduleTemplateFactory;

    /**
     * Construct a factory
     * @param User $user
     * @param UserFactory $userFactory
     * @param WidgetOptionFactory $widgetOptionFactory
     * @param WidgetMediaFactory $widgetMediaFactory
     * @param WidgetAudioFactory $widgetAudioFactory
     * @param PermissionFactory $permissionFactory
     * @param DisplayNotifyServiceInterface $displayNotifyService
     * @param ActionFactory $actionFactory
     * @param \Xibo\Factory\ModuleTemplateFactory $moduleTemplateFactory
     */
    public function __construct(
        $user,
        $userFactory,
        $widgetOptionFactory,
        $widgetMediaFactory,
        $widgetAudioFactory,
        $permissionFactory,
        $displayNotifyService,
        $actionFactory,
        $moduleTemplateFactory
    ) {
        $this->setAclDependencies($user, $userFactory);
        $this->widgetOptionFactory = $widgetOptionFactory;
        $this->widgetMediaFactory = $widgetMediaFactory;
        $this->widgetAudioFactory = $widgetAudioFactory;
        $this->permissionFactory = $permissionFactory;
        $this->displayNotifyService = $displayNotifyService;
        $this->actionFactory = $actionFactory;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
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
            $this->getDispatcher(),
            $this->widgetOptionFactory,
            $this->widgetMediaFactory,
            $this->widgetAudioFactory,
            $this->permissionFactory,
            $this->displayNotifyService,
            $this->actionFactory
        );
    }

    /**
     * Load widgets by Playlist ID
     * @param int $playlistId
     * @return array[Widget]
     * @throws NotFoundException
     */
    public function getByPlaylistId($playlistId)
    {
        return $this->query(null, array('disableUserCheck' => 1, 'playlistId' => $playlistId));
    }

    /**
     * Load widgets by MediaId
     * @param int $mediaId
     * @param int|null $isDynamicPlaylist
     * @return Widget[]
     * @throws NotFoundException
     */
    public function getByMediaId($mediaId, $isDynamicPlaylist = null)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'mediaId' => $mediaId, 'isDynamicPlaylist' => $isDynamicPlaylist]);
    }

    /**
     * Get Widget by its ID
     *  first check the lkwidgetmedia table for any active links
     *  if that fails, check the widgethistory table
     *  in either case, if we find a widget that isn't a media record, then still throw not found
     * @param int $widgetId
     * @return int|null
     * @throws NotFoundException
     */
    public function getMediaByWidgetId($widgetId)
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
     * @throws NotFoundException
     */
    public function getById($widgetId): Widget
    {
        $widgets = $this->query(null, array('disableUserCheck' => 1, 'widgetId' => $widgetId));

        if (count($widgets) <= 0) {
            throw new NotFoundException(__('Widget not found'));
        }

        return $widgets[0];
    }

    /**
     * Load widget by widget id
     * @param $widgetId
     * @return Widget
     * @throws NotFoundException
     */
    public function loadByWidgetId($widgetId): Widget
    {
        $widgets = $this->query(null, array('disableUserCheck' => 1, 'widgetId' => $widgetId));

        if (count($widgets) <= 0) {
            throw new NotFoundException(__('Widget not found'));
        }

        $widget = $widgets[0];
        /* @var Widget $widget */
        $widget->load();
        return $widget;
    }

    /**
     * @param $ownerId
     * @return Widget[]
     * @throws NotFoundException
     */
    public function getByOwnerId($ownerId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'userId' => $ownerId]);
    }

    /**
     * Create a new widget
     * @param int $ownerId
     * @param int $playlistId
     * @param string $type
     * @param int $duration
     * @param int $schemaVersion
     * @return Widget
     */
    public function create($ownerId, $playlistId, $type, $duration, $schemaVersion)
    {
        $widget = $this->createEmpty();
        $widget->ownerId = $ownerId;
        $widget->playlistId = $playlistId;
        $widget->type = $type;
        $widget->duration = $duration;
        $widget->schemaVersion = $schemaVersion;
        $widget->displayOrder = 1;
        $widget->useDuration = 0;

        return $widget;
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return Widget[]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder == null) {
            $sortOrder = ['displayOrder'];
        }

        $sanitizedFilter = $this->getSanitizer($filterBy);

        $entries = [];

        $params = [];
        $select = '
          SELECT widget.widgetId,
              widget.playlistId,
              widget.ownerId,
              widget.type,
              widget.duration,
              widget.displayOrder,
              widget.schemaVersion,
              `widget`.useDuration,
              `widget`.calculatedDuration,
              `widget`.fromDt,
              `widget`.toDt, 
              `widget`.createdDt, 
              `widget`.modifiedDt,
              `widget`.calculatedDuration,
              `playlist`.name AS playlist,
              `playlist`.folderId,
              `playlist`.permissionsFolderId,
              `playlist`.isDynamic
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

        if ($sanitizedFilter->getInt('showWidgetsFrom') === 1) {
            $body .= '
                    INNER JOIN `region`
                        ON `region`.regionId = `playlist`.regionId
                    INNER JOIN `layout`
                        ON `layout`.layoutId = `region`.layoutId
            ';
        }

        if ($sanitizedFilter->getInt('mediaId') !== null) {
            $body .= '
                INNER JOIN `lkwidgetmedia`
                ON `lkwidgetmedia`.widgetId = widget.widgetId
                    AND `lkwidgetmedia`.mediaId = :mediaId
            ';
            $params['mediaId'] = $sanitizedFilter->getInt('mediaId');
        }

        $body .= ' WHERE 1 = 1 ';

        if ($sanitizedFilter->getInt('showWidgetsFrom') === 1) {
            $body .= ' AND layout.parentId IS NOT NULL ';
        }

        if ($sanitizedFilter->getInt('showWidgetsFrom') === 2) {
            $body .= ' AND playlist.regionId IS NULL ';
        }

        if ($sanitizedFilter->getInt('playlistId') !== null) {
            $body .= ' AND `widget`.playlistId = :playlistId';
            $params['playlistId'] = $sanitizedFilter->getInt('playlistId');
        }

        if ($sanitizedFilter->getInt('widgetId') !== null) {
            $body .= ' AND `widget`.widgetId = :widgetId';
            $params['widgetId'] = $sanitizedFilter->getInt('widgetId');
        }

        if ($sanitizedFilter->getInt('schemaVersion') !== null) {
            $body .= ' AND `widget`.schemaVersion = :schemaVersion';
            $params['schemaVersion'] = $sanitizedFilter->getInt('schemaVersion');
        }

        if ($sanitizedFilter->getString('type') !== null) {
            $body .= ' AND `widget`.type = :type';
            $params['type'] = $sanitizedFilter->getString('type');
        }

        if ($sanitizedFilter->getString('layout') !== null) {
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
            $params['layout'] = '%' . $sanitizedFilter->getString('layout') . '%';
        }

        if ($sanitizedFilter->getString('region') !== null) {
            $body .= ' AND widget.widgetId IN (
                SELECT widgetId
                  FROM `widget`
                    INNER JOIN `playlist`
                    ON `widget`.playlistId = `playlist`.playlistId
                    INNER JOIN `region`
                    ON `region`.regionId = `playlist`.regionId
                 WHERE region.name LIKE :region
            )';
            $params['region'] = '%' . $sanitizedFilter->getString('region') . '%';
        }

        if ($sanitizedFilter->getString('media') !== null) {
            $body .= ' AND widget.widgetId IN (
                SELECT widgetId
                  FROM `lkwidgetmedia`
                    INNER JOIN `media`
                    ON `media`.mediaId = `lkwidgetmedia`.mediaId
                 WHERE media.name LIKE :media
            )';
            $params['media'] = '%' . $sanitizedFilter->getString('media') . '%';
        }

        if ($sanitizedFilter->getInt('userId') !== null) {
            $body .= ' AND `widget`.ownerId = :userId';
            $params['userId'] = $sanitizedFilter->getInt('userId');
        }

        // Playlist Like
        if ($sanitizedFilter->getString('playlist') != '') {
            $terms = explode(',', $sanitizedFilter->getString('playlist'));
            $this->nameFilter('playlist', 'name', $terms, $body, $params, ($sanitizedFilter->getCheckbox('useRegexForName') == 1));
        }

        if ($sanitizedFilter->getInt('isDynamicPlaylist') !== null) {
            $body .= ' AND `playlist`.isDynamic = :isDynamicPlaylist';
            $params['isDynamicPlaylist'] = $sanitizedFilter->getInt('isDynamicPlaylist');
        }

        // Permissions
        $this->viewPermissionSql('Xibo\Entity\Widget', $body, $params, 'widget.widgetId', 'widget.ownerId', $filterBy, 'playlist.permissionsFolderId');

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= ' ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0]) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        // The final statements
        $sql = $select . $body . $order . $limit;



        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, ['intProperties' => [
                'duration', 'useDuration', 'schemaVersion', 'calculatedDuration', 'fromDt', 'toDt', 'createdDt', 'modifiedDt']
            ]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }

    /**
     * Get all templates for a set of widgets.
     * @param \Xibo\Entity\Module $module The lead module we're rendering for
     * @param Widget[] $widgets
     * @return \Xibo\Entity\ModuleTemplate[]
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getTemplatesForWidgets(Module $module, array $widgets): array
    {
        $this->getLog()->debug('getTemplatesForWidgets: ' . count($widgets) . ' widgets, module: '
            . $module->type . ', dataType: ' . $module->dataType);

        $templates = [];
        foreach ($widgets as $widget) {
            if (!empty($module->dataType)) {
                // Do we have a static one?
                $templateId = $widget->getOptionValue('templateId', null);
                if ($templateId !== null && $templateId !== 'elements') {
                    $templates[] = $this->moduleTemplateFactory->getByDataTypeAndId(
                        $module->dataType,
                        $templateId
                    );
                }
            }

            // Does this widget have elements?
            $widgetElements = $widget->getOptionValue('elements', null);
            if (!empty($widgetElements)) {
                $this->getLog()->debug('getTemplatesForWidgets: there are elements to include');

                // Elements will be JSON
                $widgetElements = json_decode($widgetElements, true);

                // Get all templates used by this widget
                $uniqueElements = [];

                foreach ($widgetElements as $widgetElement) {
                    foreach ($widgetElement['elements'] ?? [] as $element) {
                        if (!array_key_exists($element['id'], $uniqueElements)) {
                            $uniqueElements[$element['id']] = $element;
                        }
                    }

                    foreach ($uniqueElements as $templateId => $element) {
                        try {
                            $template = $this->moduleTemplateFactory->getByTypeAndId(
                                'element',
                                $templateId
                            );

                            // Does this template extend a global template
                            if (!empty($template->extends)) {
                                try {
                                    $templates[] = $this->moduleTemplateFactory->getByDataTypeAndId(
                                        'global',
                                        $template->extends->template
                                    );
                                } catch (\Exception $e) {
                                    $this->getLog()->error('getTemplatesForWidgets: ' . $templateId
                                        . ' extends another template which does not exist.');
                                }
                            }

                            $templates[] = $template;
                        } catch (NotFoundException $notFoundException) {
                            $this->getLog()->error('getTemplatesForWidgets: templateId ' . $templateId
                                . ' not found');
                        }
                    }
                }
            }
        }

        $this->getLog()->debug('getTemplatesForWidgets: ' . count($templates) . ' templates returned.');

        return $templates;
    }
}
