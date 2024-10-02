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
 * Helper class to add factories to DI.
 */
class Factories
{
    /**
     * Register Factories with DI
     */
    public static function registerFactoriesWithDi()
    {
        return [
            'actionFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\ActionFactory(
                    $c->get('user'),
                    $c->get('userFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'apiRequestsFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\ApplicationRequestsFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'applicationFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\ApplicationFactory(
                    $c->get('user'),
                    $c->get('applicationRedirectUriFactory'),
                    $c->get('applicationScopeFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'applicationRedirectUriFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\ApplicationRedirectUriFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'applicationScopeFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\ApplicationScopeFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'auditLogFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\AuditLogFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'bandwidthFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\BandwidthFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'campaignFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\CampaignFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('permissionFactory'),
                    $c->get('scheduleFactory'),
                    $c->get('displayNotifyService')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'commandFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\CommandFactory(
                    $c->get('user'),
                    $c->get('userFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'connectorFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\ConnectorFactory(
                    $c->get('pool'),
                    $c->get('configService'),
                    $c->get('jwtService'),
                    $c->get('playerActionService'),
                    $c
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'dataSetColumnFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\DataSetColumnFactory(
                    $c->get('dataTypeFactory'),
                    $c->get('dataSetColumnTypeFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'dataSetColumnTypeFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\DataSetColumnTypeFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'dataSetFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\DataSetFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService'),
                    $c->get('pool'),
                    $c->get('dataSetColumnFactory'),
                    $c->get('permissionFactory'),
                    $c->get('displayNotifyService')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'dataSetRssFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\DataSetRssFactory(
                    $c->get('user'),
                    $c->get('userFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'dataTypeFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\DataTypeFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'dayPartFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\DayPartFactory(
                    $c->get('user'),
                    $c->get('userFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'displayFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\DisplayFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('displayNotifyService'),
                    $c->get('configService'),
                    $c->get('displayGroupFactory'),
                    $c->get('displayProfileFactory'),
                    $c->get('folderFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'displayEventFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\DisplayEventFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'displayGroupFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\DisplayGroupFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('permissionFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'displayTypeFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\DisplayTypeFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'displayProfileFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\DisplayProfileFactory(
                    $c->get('configService'),
                    $c->get('commandFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'folderFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\FolderFactory(
                    $c->get('permissionFactory'),
                    $c->get('user'),
                    $c->get('userFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'fontFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\FontFactory(
                    $c->get('configService')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'layoutFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\LayoutFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService'),
                    $c->get('permissionFactory'),
                    $c->get('regionFactory'),
                    $c->get('tagFactory'),
                    $c->get('campaignFactory'),
                    $c->get('mediaFactory'),
                    $c->get('moduleFactory'),
                    $c->get('moduleTemplateFactory'),
                    $c->get('resolutionFactory'),
                    $c->get('widgetFactory'),
                    $c->get('widgetOptionFactory'),
                    $c->get('playlistFactory'),
                    $c->get('widgetAudioFactory'),
                    $c->get('actionFactory'),
                    $c->get('folderFactory'),
                    $c->get('fontFactory'),
                    $c->get('widgetDataFactory'),
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));

                if ($c->has('pool')) {
                    $repository->usePool($c->get('pool'));
                }

                return $repository;
            },
            'logFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\LogFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'mediaFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\MediaFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService'),
                    $c->get('permissionFactory'),
                    $c->get('playlistFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'menuBoardCategoryFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\MenuBoardCategoryFactory(
                    $c->get('menuBoardProductOptionFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'menuBoardProductOptionFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\MenuBoardProductOptionFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'menuBoardFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\MenuBoardFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService'),
                    $c->get('pool'),
                    $c->get('permissionFactory'),
                    $c->get('menuBoardCategoryFactory'),
                    $c->get('displayNotifyService')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'moduleFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\ModuleFactory(
                    $c->get('configService')->getSetting('LIBRARY_LOCATION') . 'widget',
                    $c->get('pool'),
                    $c->get('view'),
                    $c->get('configService')
                );
                $repository
                    ->setAclDependencies(
                        $c->get('user'),
                        $c->get('userFactory')
                    )
                    ->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'moduleTemplateFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\ModuleTemplateFactory(
                    $c->get('pool'),
                    $c->get('view'),
                );
                $repository
                    ->setAclDependencies(
                        $c->get('user'),
                        $c->get('userFactory')
                    )
                    ->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'notificationFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\NotificationFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('userGroupFactory'),
                    $c->get('displayGroupFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'permissionFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\PermissionFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'playerFaultFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\PlayerFaultFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'playerVersionFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\PlayerVersionFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'playlistFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\PlaylistFactory(
                    $c->get('configService'),
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('permissionFactory'),
                    $c->get('widgetFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'regionFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\RegionFactory(
                    $c->get('permissionFactory'),
                    $c->get('regionOptionFactory'),
                    $c->get('playlistFactory'),
                    $c->get('actionFactory'),
                    $c->get('campaignFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'regionOptionFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\RegionOptionFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'requiredFileFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\RequiredFileFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'reportScheduleFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\ReportScheduleFactory(
                    $c->get('user'),
                    $c->get('userFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'resolutionFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\ResolutionFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'savedReportFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\SavedReportFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService'),
                    $c->get('mediaFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'scheduleFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\ScheduleFactory(
                    $c->get('configService'),
                    $c->get('pool'),
                    $c->get('displayGroupFactory'),
                    $c->get('dayPartFactory'),
                    $c->get('userFactory'),
                    $c->get('scheduleReminderFactory'),
                    $c->get('scheduleExclusionFactory'),
                    $c->get('user'),
                    $c->get('scheduleCriteriaFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'scheduleReminderFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\ScheduleReminderFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('configService')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'scheduleExclusionFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\ScheduleExclusionFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'scheduleCriteriaFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\ScheduleCriteriaFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'sessionFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\SessionFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'syncGroupFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\SyncGroupFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('permissionFactory'),
                    $c->get('displayFactory'),
                    $c->get('scheduleFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'tagFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\TagFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'taskFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\TaskFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'transitionFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\TransitionFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'userFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\UserFactory(
                    $c->get('configService'),
                    $c->get('permissionFactory'),
                    $c->get('userOptionFactory'),
                    $c->get('applicationScopeFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'userGroupFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\UserGroupFactory(
                    $c->get('user'),
                    $c->get('userFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'userNotificationFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\UserNotificationFactory(
                    $c->get('user'),
                    $c->get('userFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'userOptionFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\UserOptionFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'userTypeFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\UserTypeFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'widgetFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\WidgetFactory(
                    $c->get('user'),
                    $c->get('userFactory'),
                    $c->get('widgetOptionFactory'),
                    $c->get('widgetMediaFactory'),
                    $c->get('widgetAudioFactory'),
                    $c->get('permissionFactory'),
                    $c->get('displayNotifyService'),
                    $c->get('actionFactory'),
                    $c->get('moduleTemplateFactory')
                );
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'widgetMediaFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\WidgetMediaFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'widgetAudioFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\WidgetAudioFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'widgetOptionFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\WidgetOptionFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
            'widgetDataFactory' => function (ContainerInterface $c) {
                $repository = new \Xibo\Factory\WidgetDataFactory();
                $repository->useBaseDependenciesService($c->get('RepositoryBaseDependenciesService'));
                return $repository;
            },
        ];
    }
}
