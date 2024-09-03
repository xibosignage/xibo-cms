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

namespace Xibo\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;
use Xibo\Event\CommandDeleteEvent;
use Xibo\Event\DependencyFileSizeEvent;
use Xibo\Event\DisplayGroupLoadEvent;
use Xibo\Event\FolderMovingEvent;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Event\MediaFullLoadEvent;
use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Event\PlaylistMaxNumberChangedEvent;
use Xibo\Event\SystemUserChangedEvent;
use Xibo\Event\UserDeleteEvent;
use Xibo\Listener\CampaignListener;
use Xibo\Listener\DataSetDataProviderListener;
use Xibo\Listener\DisplayGroupListener;
use Xibo\Listener\LayoutListener;
use Xibo\Listener\MediaListener;
use Xibo\Listener\MenuBoardProviderListener;
use Xibo\Listener\ModuleTemplateListener;
use Xibo\Listener\NotificationDataProviderListener;
use Xibo\Listener\PlaylistListener;
use Xibo\Listener\SyncGroupListener;
use Xibo\Listener\TaskListener;
use Xibo\Listener\WidgetListener;
use Xibo\Xmds\Listeners\XmdsAssetsListener;
use Xibo\Xmds\Listeners\XmdsDataConnectorListener;
use Xibo\Xmds\Listeners\XmdsFontsListener;
use Xibo\Xmds\Listeners\XmdsPlayerBundleListener;
use Xibo\Xmds\Listeners\XmdsPlayerVersionListener;

/**
 * This middleware is used to register listeners against the dispatcher
 */
class ListenersMiddleware implements MiddlewareInterface
{
    /* @var App $app */
    private $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $app = $this->app;

        // Set connectors
        self::setListeners($app);

        // Next middleware
        return $handler->handle($request);
    }

    /**
     * Set listeners
     * @param \Slim\App $app
     * @return void
     */
    public static function setListeners(App $app)
    {
        $c = $app->getContainer();
        $dispatcher = $c->get('dispatcher');

        // Register listeners
        // ------------------
        // Listen for events that affect campaigns
        (new CampaignListener(
            $c->get('campaignFactory'),
            $c->get('store')
        ))
            ->useLogger($c->get('logger'))
            ->registerWithDispatcher($dispatcher);

        // Listen for events that affect Layouts
        (new LayoutListener(
            $c->get('layoutFactory'),
            $c->get('store'),
            $c->get('permissionFactory'),
        ))
            ->useLogger($c->get('logger'))
            ->registerWithDispatcher($dispatcher);

        // Listen for event that affect Display Groups
        (new DisplayGroupListener(
            $c->get('displayGroupFactory'),
            $c->get('store')
        ))
            ->useLogger($c->get('logger'))
            ->registerWithDispatcher($dispatcher);

        // Listen for event that affect Media
        (new MediaListener(
            $c->get('mediaFactory'),
            $c->get('store')
        ))
            ->useLogger($c->get('logger'))
            ->registerWithDispatcher($dispatcher);

        // Listen for events that affect ModuleTemplates
        (new ModuleTemplateListener(
            $c->get('moduleTemplateFactory'),
        ))
            ->useLogger($c->get('logger'))
            ->registerWithDispatcher($dispatcher);

        // Listen for event that affect Playlist
        (new PlaylistListener(
            $c->get('playlistFactory'),
            $c->get('store')
        ))
            ->useLogger($c->get('logger'))
            ->registerWithDispatcher($dispatcher);

        // Listen for event that affect Sync Group
        (new SyncGroupListener(
            $c->get('syncGroupFactory'),
            $c->get('store')
        ))
            ->useLogger($c->get('logger'))
            ->registerWithDispatcher($dispatcher);

        // Listen for event that affect Task
        (new TaskListener(
            $c->get('taskFactory'),
            $c->get('configService'),
            $c->get('pool')
        ))
            ->useLogger($c->get('logger'))
            ->registerWithDispatcher($dispatcher);

        // Media Delete Events
        $dispatcher->addListener(MediaDeleteEvent::$NAME, (new \Xibo\Listener\OnMediaDelete\MenuBoardListener(
            $c->get('menuBoardCategoryFactory')
        )));

        $dispatcher->addListener(MediaDeleteEvent::$NAME, (new \Xibo\Listener\OnMediaDelete\WidgetListener(
            $c->get('store'),
            $c->get('widgetFactory'),
            $c->get('moduleFactory')
        )));

        $dispatcher->addListener(MediaDeleteEvent::$NAME, (new \Xibo\Listener\OnMediaDelete\PurgeListListener(
            $c->get('store'),
            $c->get('configService')
        )));

        // User Delete Events
        $dispatcher->addListener(UserDeleteEvent::$NAME, (new \Xibo\Listener\OnUserDelete\ActionListener(
            $c->get('store'),
            $c->get('actionFactory')
        ))->useLogger($c->get('logger')));

        $dispatcher->addListener(UserDeleteEvent::$NAME, (new \Xibo\Listener\OnUserDelete\CommandListener(
            $c->get('store'),
            $c->get('commandFactory')
        ))->useLogger($c->get('logger')));

        $dispatcher->addListener(UserDeleteEvent::$NAME, (new \Xibo\Listener\OnUserDelete\DataSetListener(
            $c->get('store'),
            $c->get('dataSetFactory')
        ))->useLogger($c->get('logger')));

        $dispatcher->addListener(UserDeleteEvent::$NAME, (new \Xibo\Listener\OnUserDelete\DayPartListener(
            $c->get('store'),
            $c->get('dayPartFactory'),
            $c->get('scheduleFactory'),
            $c->get('displayNotifyService')
        ))->useLogger($c->get('logger')));

        $dispatcher->addListener(UserDeleteEvent::$NAME, (new \Xibo\Listener\OnUserDelete\DisplayProfileListener(
            $c->get('store'),
            $c->get('displayProfileFactory')
        ))->useLogger($c->get('logger')));

        $dispatcher->addListener(UserDeleteEvent::$NAME, (new \Xibo\Listener\OnUserDelete\MenuBoardListener(
            $c->get('store'),
            $c->get('menuBoardFactory')
        ))->useLogger($c->get('logger')));

        $dispatcher->addListener(UserDeleteEvent::$NAME, (new \Xibo\Listener\OnUserDelete\NotificationListener(
            $c->get('notificationFactory')
        ))->useLogger($c->get('logger')));

        $dispatcher->addListener(UserDeleteEvent::$NAME, (new \Xibo\Listener\OnUserDelete\OnUserDelete(
            $c->get('store')
        ))->useLogger($c->get('logger')));

        $dispatcher->addListener(UserDeleteEvent::$NAME, (new \Xibo\Listener\OnUserDelete\RegionListener(
            $c->get('regionFactory')
        ))->useLogger($c->get('logger')), -1);

        $dispatcher->addListener(UserDeleteEvent::$NAME, (new \Xibo\Listener\OnUserDelete\ReportScheduleListener(
            $c->get('store'),
            $c->get('reportScheduleFactory')
        ))->useLogger($c->get('logger')));

        $dispatcher->addListener(UserDeleteEvent::$NAME, (new \Xibo\Listener\OnUserDelete\ResolutionListener(
            $c->get('store'),
            $c->get('resolutionFactory')
        ))->useLogger($c->get('logger')));

        $dispatcher->addListener(UserDeleteEvent::$NAME, (new \Xibo\Listener\OnUserDelete\SavedReportListener(
            $c->get('store'),
            $c->get('savedReportFactory')
        ))->useLogger($c->get('logger')));

        $dispatcher->addListener(UserDeleteEvent::$NAME, (new \Xibo\Listener\OnUserDelete\ScheduleListener(
            $c->get('store'),
            $c->get('scheduleFactory')
        ))->useLogger($c->get('logger')));

        $dispatcher->addListener(UserDeleteEvent::$NAME, (new \Xibo\Listener\OnUserDelete\WidgetListener(
            $c->get('widgetFactory')
        ))->useLogger($c->get('logger')), -2);

        // Display Group Load events
        $dispatcher->addListener(DisplayGroupLoadEvent::$NAME, (new \Xibo\Listener\OnDisplayGroupLoad\DisplayGroupDisplayListener(
            $c->get('displayFactory')
        )));

        $dispatcher->addListener(DisplayGroupLoadEvent::$NAME, (new \Xibo\Listener\OnDisplayGroupLoad\DisplayGroupScheduleListener(
            $c->get('scheduleFactory')
        )));

        // Media full load events
        $dispatcher->addListener(MediaFullLoadEvent::$NAME, (new \Xibo\Listener\OnMediaLoad\WidgetListener(
            $c->get('widgetFactory')
        )));

        // Parse Permissions Event Listeners
        $dispatcher->addListener(ParsePermissionEntityEvent::$NAME . 'command', (new \Xibo\Listener\OnParsePermissions\PermissionsCommandListener(
            $c->get('commandFactory')
        )));

        $dispatcher->addListener(ParsePermissionEntityEvent::$NAME . 'dataSet', (new \Xibo\Listener\OnParsePermissions\PermissionsDataSetListener(
            $c->get('dataSetFactory')
        )));

        $dispatcher->addListener(ParsePermissionEntityEvent::$NAME . 'dayPart', (new \Xibo\Listener\OnParsePermissions\PermissionsDayPartListener(
            $c->get('dayPartFactory')
        )));

        $dispatcher->addListener(ParsePermissionEntityEvent::$NAME . 'folder', (new \Xibo\Listener\OnParsePermissions\PermissionsFolderListener(
            $c->get('folderFactory')
        )));

        $dispatcher->addListener(ParsePermissionEntityEvent::$NAME . 'menuBoard', (new \Xibo\Listener\OnParsePermissions\PermissionsMenuBoardListener(
            $c->get('menuBoardFactory')
        )));

        $dispatcher->addListener(ParsePermissionEntityEvent::$NAME . 'notification', (new \Xibo\Listener\OnParsePermissions\PermissionsNotificationListener(
            $c->get('notificationFactory')
        )));

        $dispatcher->addListener(ParsePermissionEntityEvent::$NAME . 'region', (new \Xibo\Listener\OnParsePermissions\PermissionsRegionListener(
            $c->get('regionFactory')
        )));

        $dispatcher->addListener(ParsePermissionEntityEvent::$NAME . 'widget', (new \Xibo\Listener\OnParsePermissions\PermissionsWidgetListener(
            $c->get('widgetFactory')
        )));

        // On Command delete event listener
        $dispatcher->addListener(CommandDeleteEvent::$NAME, (new \Xibo\Listener\OnCommandDelete(
            $c->get('displayProfileFactory')
        )));

        // On System User change event listener
        $dispatcher->addListener(SystemUserChangedEvent::$NAME, (new \Xibo\Listener\OnSystemUserChange(
            $c->get('store')
        )));

        // On Playlist Max Number of Items limit change listener
        $dispatcher->addListener(PlaylistMaxNumberChangedEvent::$NAME, (new \Xibo\Listener\OnPlaylistMaxNumberChange(
            $c->get('store')
        )));

        // On Folder moving listeners
        $dispatcher->addListener(FolderMovingEvent::$NAME, (new \Xibo\Listener\OnFolderMoving\DataSetListener(
            $c->get('dataSetFactory')
        )));

        $dispatcher->addListener(FolderMovingEvent::$NAME, (new \Xibo\Listener\OnFolderMoving\FolderListener(
            $c->get('folderFactory')
        )), -1);

        $dispatcher->addListener(FolderMovingEvent::$NAME, (new \Xibo\Listener\OnFolderMoving\MenuBoardListener(
            $c->get('menuBoardFactory')
        )));

        $dispatcher->addListener(FolderMovingEvent::$NAME, (new \Xibo\Listener\OnFolderMoving\UserListener(
            $c->get('userFactory'),
            $c->get('store')
        )));

        // dependencies file size
        $dispatcher->addListener(DependencyFileSizeEvent::$NAME, (new \Xibo\Listener\OnGettingDependencyFileSize\FontsListener(
            $c->get('fontFactory')
        )));

        $dispatcher->addListener(DependencyFileSizeEvent::$NAME, (new \Xibo\Listener\OnGettingDependencyFileSize\PlayerVersionListener(
            $c->get('playerVersionFactory')
        )));

        $dispatcher->addListener(DependencyFileSizeEvent::$NAME, (new \Xibo\Listener\OnGettingDependencyFileSize\SavedReportListener(
            $c->get('savedReportFactory')
        )));

        // Widget related listeners for getting core data
        (new DataSetDataProviderListener(
            $c->get('store'),
            $c->get('configService'),
            $c->get('dataSetFactory'),
            $c->get('displayFactory')
        ))
            ->useLogger($c->get('logger'))
            ->registerWithDispatcher($dispatcher);

        (new NotificationDataProviderListener(
            $c->get('configService'),
            $c->get('notificationFactory'),
            $c->get('user')
        ))
            ->useLogger($c->get('logger'))
            ->registerWithDispatcher($dispatcher);

        (new WidgetListener(
            $c->get('playlistFactory'),
            $c->get('moduleFactory'),
            $c->get('widgetFactory'),
            $c->get('store'),
            $c->get('configService')
        ))
            ->useLogger($c->get('logger'))
            ->registerWithDispatcher($dispatcher);

        (new MenuBoardProviderListener(
            $c->get('menuBoardFactory'),
            $c->get('menuBoardCategoryFactory'),
        ))
            ->useLogger($c->get('logger'))
            ->registerWithDispatcher($dispatcher);
    }

    /**
     * Set XMDS specific listeners
     * @param App $app
     * @return void
     */
    public static function setXmdsListeners(App $app)
    {
        $c = $app->getContainer();
        $dispatcher = $c->get('dispatcher');

        $playerBundleListener = new XmdsPlayerBundleListener();
        $playerBundleListener
            ->useLogger($c->get('logger'))
            ->useConfig($c->get('configService'));

        $fontsListener = new XmdsFontsListener($c->get('fontFactory'));
        $fontsListener
            ->useLogger($c->get('logger'))
            ->useConfig($c->get('configService'));

        $playerVersionListner = new XmdsPlayerVersionListener($c->get('playerVersionFactory'));
        $playerVersionListner->useLogger($c->get('logger'));

        $assetsListener = new XmdsAssetsListener(
            $c->get('moduleFactory'),
            $c->get('moduleTemplateFactory')
        );
        $assetsListener
            ->useLogger($c->get('logger'))
            ->useConfig($c->get('configService'));

        $dataConnectorListener = new XmdsDataConnectorListener();
        $dataConnectorListener
            ->useLogger($c->get('logger'))
            ->useConfig($c->get('configService'));

        $dispatcher->addListener('xmds.dependency.list', [$playerBundleListener, 'onDependencyList']);
        $dispatcher->addListener('xmds.dependency.request', [$playerBundleListener, 'onDependencyRequest']);
        $dispatcher->addListener('xmds.dependency.list', [$fontsListener, 'onDependencyList']);
        $dispatcher->addListener('xmds.dependency.request', [$fontsListener, 'onDependencyRequest']);
        $dispatcher->addListener('xmds.dependency.list', [$playerVersionListner, 'onDependencyList']);
        $dispatcher->addListener('xmds.dependency.request', [$playerVersionListner, 'onDependencyRequest']);
        $dispatcher->addListener('xmds.dependency.request', [$assetsListener, 'onDependencyRequest']);
        $dispatcher->addListener('xmds.dependency.request', [$dataConnectorListener, 'onDependencyRequest']);
    }
}
