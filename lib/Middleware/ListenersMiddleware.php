<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

namespace Xibo\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;
use Xibo\Event\CommandDeleteEvent;
use Xibo\Event\DisplayGroupLoadEvent;
use Xibo\Event\FolderMovingEvent;
use Xibo\Event\LayoutOwnerChangeEvent;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Event\MediaFullLoadEvent;
use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Event\PlaylistMaxNumberChangedEvent;
use Xibo\Event\SystemUserChangedEvent;
use Xibo\Event\UserDeleteEvent;
use Xibo\Listener\CampaignListener;

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

        // Media Delete Events
        $dispatcher->addListener(MediaDeleteEvent::$NAME, (new \Xibo\Listener\OnMediaDelete\MenuBoardListener(
            $c->get('menuBoardCategoryFactory')
        )));

        $dispatcher->addListener(MediaDeleteEvent::$NAME, (new \Xibo\Listener\OnMediaDelete\LayoutListener(
            $c->get('layoutFactory')
        )));

        $dispatcher->addListener(MediaDeleteEvent::$NAME, (new \Xibo\Listener\OnMediaDelete\WidgetListener(
            $c->get('store'),
            $c->get('widgetFactory'),
            $c->get('moduleFactory')
        )));

        $dispatcher->addListener(MediaDeleteEvent::$NAME, (new \Xibo\Listener\OnMediaDelete\DisplayGroupListener(
            $c->get('displayGroupFactory')
        ))->useLogger($c->get('logger')));

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

        $dispatcher->addListener(UserDeleteEvent::$NAME, (new \Xibo\Listener\OnUserDelete\DisplayGroupListener(
            $c->get('store'),
            $c->get('displayGroupFactory')
        ))->useLogger($c->get('logger')));

        $dispatcher->addListener(UserDeleteEvent::$NAME, (new \Xibo\Listener\OnUserDelete\DisplayProfileListener(
            $c->get('store'),
            $c->get('displayProfileFactory')
        ))->useLogger($c->get('logger')));

        $dispatcher->addListener(UserDeleteEvent::$NAME, (new \Xibo\Listener\OnUserDelete\LayoutListener(
            $c->get('layoutFactory')
        ))->useLogger($c->get('logger')));

        $dispatcher->addListener(UserDeleteEvent::$NAME, (new \Xibo\Listener\OnUserDelete\MediaListener(
            $c->get('store'),
            $c->get('mediaFactory')
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

        $dispatcher->addListener(UserDeleteEvent::$NAME, (new \Xibo\Listener\OnUserDelete\PlaylistListener(
            $c->get('playlistFactory'),
            $c->get('moduleFactory')
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
        $dispatcher->addListener(DisplayGroupLoadEvent::$NAME, (new \Xibo\Listener\OnDisplayGroupLoad\DisplayGroupMediaListener(
            $c->get('mediaFactory')
        )));

        $dispatcher->addListener(DisplayGroupLoadEvent::$NAME, (new \Xibo\Listener\OnDisplayGroupLoad\DisplayGroupLayoutListener(
            $c->get('layoutFactory')
        )));

        $dispatcher->addListener(DisplayGroupLoadEvent::$NAME, (new \Xibo\Listener\OnDisplayGroupLoad\DisplayGroupDisplayListener(
            $c->get('displayFactory')
        )));

        $dispatcher->addListener(DisplayGroupLoadEvent::$NAME, (new \Xibo\Listener\OnDisplayGroupLoad\DisplayGroupScheduleListener(
            $c->get('scheduleFactory')
        )));

        // Media full load events
        $dispatcher->addListener(MediaFullLoadEvent::$NAME, (new \Xibo\Listener\OnMediaLoad\DisplayGroupListener(
            $c->get('displayGroupFactory')
        )));

        $dispatcher->addListener(MediaFullLoadEvent::$NAME, (new \Xibo\Listener\OnMediaLoad\LayoutListener(
            $c->get('layoutFactory')
        )));

        $dispatcher->addListener(MediaFullLoadEvent::$NAME, (new \Xibo\Listener\OnMediaLoad\WidgetListener(
            $c->get('widgetFactory')
        )));

        $dispatcher->addListener(LayoutOwnerChangeEvent::$NAME, new \Xibo\Listener\OnLayoutOwnerChange(
            $c->get('layoutFactory')
        ));

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

        $dispatcher->addListener(ParsePermissionEntityEvent::$NAME . 'displayGroup', (new \Xibo\Listener\OnParsePermissions\PermissionsDisplayGroupListener(
            $c->get('displayGroupFactory')
        )));

        $dispatcher->addListener(ParsePermissionEntityEvent::$NAME . 'folder', (new \Xibo\Listener\OnParsePermissions\PermissionsFolderListener(
            $c->get('folderFactory')
        )));

        $dispatcher->addListener(ParsePermissionEntityEvent::$NAME . 'media', (new \Xibo\Listener\OnParsePermissions\PermissionsMediaListener(
            $c->get('mediaFactory')
        )));

        $dispatcher->addListener(ParsePermissionEntityEvent::$NAME . 'menuBoard', (new \Xibo\Listener\OnParsePermissions\PermissionsMenuBoardListener(
            $c->get('menuBoardFactory')
        )));

        $dispatcher->addListener(ParsePermissionEntityEvent::$NAME . 'notification', (new \Xibo\Listener\OnParsePermissions\PermissionsNotificationListener(
            $c->get('notificationFactory')
        )));

        $dispatcher->addListener(ParsePermissionEntityEvent::$NAME . 'playlist', (new \Xibo\Listener\OnParsePermissions\PermissionsPlaylistListener(
            $c->get('playlistFactory')
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

        $dispatcher->addListener(FolderMovingEvent::$NAME, (new \Xibo\Listener\OnFolderMoving\DisplayGroupListener(
            $c->get('displayGroupFactory')
        )));

        $dispatcher->addListener(FolderMovingEvent::$NAME, (new \Xibo\Listener\OnFolderMoving\FolderListener(
            $c->get('folderFactory')
        )), -1);

        $dispatcher->addListener(FolderMovingEvent::$NAME, (new \Xibo\Listener\OnFolderMoving\MediaListener(
            $c->get('mediaFactory')
        )));

        $dispatcher->addListener(FolderMovingEvent::$NAME, (new \Xibo\Listener\OnFolderMoving\MenuBoardListener(
            $c->get('menuBoardFactory')
        )));

        $dispatcher->addListener(FolderMovingEvent::$NAME, (new \Xibo\Listener\OnFolderMoving\PlaylistListener(
            $c->get('playlistFactory')
        )));

        $dispatcher->addListener(FolderMovingEvent::$NAME, (new \Xibo\Listener\OnFolderMoving\UserListener(
            $c->get('userFactory'),
            $c->get('store')
        )));
    }
}
