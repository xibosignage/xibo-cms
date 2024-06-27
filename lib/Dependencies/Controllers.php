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

namespace Xibo\Dependencies;

use Psr\Container\ContainerInterface;

/**
 * Helper class to add controllers to DI
 */
class Controllers
{
    /**
     * Register controllers with DI
     */
    public static function registerControllersWithDi()
    {
        return [
            '\Xibo\Controller\Action' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Action(
                    $c->get('actionFactory'),
                    $c->get('layoutFactory'),
                    $c->get('regionFactory'),
                    $c->get('widgetFactory'),
                    $c->get('moduleFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Applications' => function (ContainerInterface $c) {
                $controller =  new \Xibo\Controller\Applications(
                    $c->get('session'),
                    $c->get('applicationFactory'),
                    $c->get('applicationRedirectUriFactory'),
                    $c->get('applicationScopeFactory'),
                    $c->get('userFactory'),
                    $c->get('pool'),
                    $c->get('connectorFactory')
                );

                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\AuditLog' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\AuditLog(
                    $c->get('auditLogFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Campaign' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Campaign(
                    $c->get('campaignFactory'),
                    $c->get('layoutFactory'),
                    $c->get('tagFactory'),
                    $c->get('folderFactory'),
                    $c->get('displayGroupFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Connector' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Connector(
                    $c->get('connectorFactory'),
                    $c->get('widgetFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Clock' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Clock(
                    $c->get('session')
                );

                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Command' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Command(
                    $c->get('commandFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\DataSet' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\DataSet(
                    $c->get('dataSetFactory'),
                    $c->get('dataSetColumnFactory'),
                    $c->get('userFactory'),
                    $c->get('folderFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\DataSetColumn' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\DataSetColumn(
                    $c->get('dataSetFactory'),
                    $c->get('dataSetColumnFactory'),
                    $c->get('dataSetColumnTypeFactory'),
                    $c->get('dataTypeFactory'),
                    $c->get('pool')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\DataSetData' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\DataSetData(
                    $c->get('dataSetFactory'),
                    $c->get('mediaFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\DataSetRss' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\DataSetRss(
                    $c->get('dataSetRssFactory'),
                    $c->get('dataSetFactory'),
                    $c->get('dataSetColumnFactory'),
                    $c->get('pool'),
                    $c->get('store')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\DayPart' => function (ContainerInterface $c) {
                $controller =  new \Xibo\Controller\DayPart(
                    $c->get('dayPartFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('displayNotifyService')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Developer' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Developer(
                    $c->get('moduleFactory'),
                    $c->get('moduleTemplateFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Display' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Display(
                    $c->get('store'),
                    $c->get('pool'),
                    $c->get('playerActionService'),
                    $c->get('displayFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('displayTypeFactory'),
                    $c->get('layoutFactory'),
                    $c->get('displayProfileFactory'),
                    $c->get('displayEventFactory'),
                    $c->get('requiredFileFactory'),
                    $c->get('tagFactory'),
                    $c->get('notificationFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('playerVersionFactory'),
                    $c->get('dayPartFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\DisplayGroup' => function (ContainerInterface $c) {
                $controller =  new \Xibo\Controller\DisplayGroup(
                    $c->get('playerActionService'),
                    $c->get('displayFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('layoutFactory'),
                    $c->get('moduleFactory'),
                    $c->get('mediaFactory'),
                    $c->get('commandFactory'),
                    $c->get('tagFactory'),
                    $c->get('campaignFactory'),
                    $c->get('folderFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\DisplayProfile' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\DisplayProfile(
                    $c->get('pool'),
                    $c->get('displayProfileFactory'),
                    $c->get('commandFactory'),
                    $c->get('playerVersionFactory'),
                    $c->get('dayPartFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Fault' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Fault(
                    $c->get('store'),
                    $c->get('logFactory'),
                    $c->get('displayFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Folder' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Folder(
                    $c->get('folderFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Font' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Font(
                    $c->get('fontFactory')
                );
                $controller->useMediaService($c->get('mediaService'));
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\IconDashboard' => function (ContainerInterface $c) {
                $controller =  new \Xibo\Controller\IconDashboard();
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Layout' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Layout(
                    $c->get('session'),
                    $c->get('userFactory'),
                    $c->get('resolutionFactory'),
                    $c->get('layoutFactory'),
                    $c->get('moduleFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('tagFactory'),
                    $c->get('mediaFactory'),
                    $c->get('dataSetFactory'),
                    $c->get('campaignFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('pool'),
                    $c->get('mediaService'),
                    $c->get('widgetFactory'),
                    $c->get('widgetDataFactory'),
                    $c->get('playlistFactory'),
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Library' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Library(
                    $c->get('userFactory'),
                    $c->get('moduleFactory'),
                    $c->get('tagFactory'),
                    $c->get('mediaFactory'),
                    $c->get('widgetFactory'),
                    $c->get('permissionFactory'),
                    $c->get('layoutFactory'),
                    $c->get('playlistFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('displayFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('folderFactory')
                );
                $controller->useMediaService($c->get('mediaService'));
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Logging' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Logging(
                    $c->get('store'),
                    $c->get('logFactory'),
                    $c->get('userFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Login' => function (ContainerInterface $c) {
                $controller =  new \Xibo\Controller\Login(
                    $c->get('session'),
                    $c->get('userFactory'),
                    $c->get('pool')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                if ($c->has('flash')) {
                    $controller->setFlash($c->get('flash'));
                }
                return $controller;
            },
            '\Xibo\Controller\Maintenance' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Maintenance(
                    $c->get('store'),
                    $c->get('mediaFactory'),
                    $c->get('mediaService')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\MediaManager' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\MediaManager(
                    $c->get('store'),
                    $c->get('moduleFactory'),
                    $c->get('mediaFactory'),
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\MenuBoard' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\MenuBoard(
                    $c->get('menuBoardFactory'),
                    $c->get('folderFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\MenuBoardCategory' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\MenuBoardCategory(
                    $c->get('menuBoardFactory'),
                    $c->get('menuBoardCategoryFactory'),
                    $c->get('mediaFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\MenuBoardProduct' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\MenuBoardProduct(
                    $c->get('menuBoardFactory'),
                    $c->get('menuBoardCategoryFactory'),
                    $c->get('menuBoardProductOptionFactory'),
                    $c->get('mediaFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\PlaylistDashboard' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\PlaylistDashboard(
                    $c->get('playlistFactory'),
                    $c->get('moduleFactory'),
                    $c->get('widgetFactory'),
                    $c->get('mediaFactory'),
                    $c
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Module' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Module(
                    $c->get('moduleFactory'),
                    $c->get('moduleTemplateFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Notification' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Notification(
                    $c->get('notificationFactory'),
                    $c->get('userNotificationFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('displayNotifyService')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\PlayerFault' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\PlayerFault(
                    $c->get('playerFaultFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\PlayerSoftware' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\PlayerSoftware(
                    $c->get('pool'),
                    $c->get('playerVersionFactory'),
                    $c->get('displayProfileFactory'),
                    $c->get('displayFactory')
                );
                $controller->useMediaService($c->get('mediaService'));
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Playlist' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Playlist(
                    $c->get('playlistFactory'),
                    $c->get('mediaFactory'),
                    $c->get('widgetFactory'),
                    $c->get('moduleFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('userFactory'),
                    $c->get('tagFactory'),
                    $c->get('layoutFactory'),
                    $c->get('displayFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('folderFactory'),
                    $c->get('regionFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Preview' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Preview(
                    $c->get('layoutFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Region' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Region(
                    $c->get('regionFactory'),
                    $c->get('widgetFactory'),
                    $c->get('transitionFactory'),
                    $c->get('moduleFactory'),
                    $c->get('layoutFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Report' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Report(
                    $c->get('reportService')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\SavedReport' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\SavedReport(
                    $c->get('reportService'),
                    $c->get('reportScheduleFactory'),
                    $c->get('savedReportFactory'),
                    $c->get('mediaFactory'),
                    $c->get('userFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\ScheduleReport' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\ScheduleReport(
                    $c->get('reportService'),
                    $c->get('reportScheduleFactory'),
                    $c->get('savedReportFactory'),
                    $c->get('mediaFactory'),
                    $c->get('userFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\SyncGroup' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\SyncGroup(
                    $c->get('syncGroupFactory'),
                    $c->get('folderFactory')
                );

                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Resolution' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Resolution(
                    $c->get('resolutionFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Schedule' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Schedule(
                    $c->get('session'),
                    $c->get('scheduleFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('campaignFactory'),
                    $c->get('commandFactory'),
                    $c->get('displayFactory'),
                    $c->get('layoutFactory'),
                    $c->get('dayPartFactory'),
                    $c->get('scheduleReminderFactory'),
                    $c->get('scheduleExclusionFactory'),
                    $c->get('syncGroupFactory'),
                    $c->get('scheduleCriteriaFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\CypressTest' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\CypressTest(
                    $c->get('store'),
                    $c->get('session'),
                    $c->get('scheduleFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('campaignFactory'),
                    $c->get('displayFactory'),
                    $c->get('layoutFactory'),
                    $c->get('dayPartFactory'),
                    $c->get('folderFactory'),
                    $c->get('commandFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Sessions' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Sessions(
                    $c->get('store'),
                    $c->get('sessionFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Settings' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Settings(
                    $c->get('layoutFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('transitionFactory'),
                    $c->get('userFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Stats' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Stats(
                    $c->get('store'),
                    $c->get('timeSeriesStore'),
                    $c->get('reportService'),
                    $c->get('displayFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\StatusDashboard' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\StatusDashboard(
                    $c->get('store'),
                    $c->get('pool'),
                    $c->get('userFactory'),
                    $c->get('displayFactory'),
                    $c->get('displayGroupFactory'),
                    $c->get('mediaFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Task' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Task(
                    $c->get('store'),
                    $c->get('timeSeriesStore'),
                    $c->get('pool'),
                    $c->get('taskFactory'),
                    $c
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Tag' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Tag(
                    $c->get('displayGroupFactory'),
                    $c->get('layoutFactory'),
                    $c->get('tagFactory'),
                    $c->get('userFactory'),
                    $c->get('displayFactory'),
                    $c->get('mediaFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('campaignFactory'),
                    $c->get('playlistFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Template' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Template(
                    $c->get('layoutFactory'),
                    $c->get('tagFactory'),
                    $c->get('resolutionFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Transition' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Transition(
                    $c->get('transitionFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\User' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\User(
                    $c->get('userFactory'),
                    $c->get('userTypeFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('permissionFactory'),
                    $c->get('applicationFactory'),
                    $c->get('sessionFactory'),
                    $c->get('mediaService')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\UserGroup' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\UserGroup(
                    $c->get('userGroupFactory'),
                    $c->get('permissionFactory'),
                    $c->get('userFactory')
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\Widget' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\Widget(
                    $c->get('moduleFactory'),
                    $c->get('moduleTemplateFactory'),
                    $c->get('playlistFactory'),
                    $c->get('mediaFactory'),
                    $c->get('permissionFactory'),
                    $c->get('widgetFactory'),
                    $c->get('transitionFactory'),
                    $c->get('regionFactory'),
                    $c->get('widgetAudioFactory'),
                    $c->get('widgetDataFactory'),
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
            '\Xibo\Controller\WidgetData' => function (ContainerInterface $c) {
                $controller = new \Xibo\Controller\WidgetData(
                    $c->get('widgetDataFactory'),
                    $c->get('widgetFactory'),
                    $c->get('moduleFactory'),
                );
                $controller->useBaseDependenciesService($c->get('ControllerBaseDependenciesService'));
                return $controller;
            },
        ];
    }
}
