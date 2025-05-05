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

global $app;

use Slim\Routing\RouteCollectorProxy;
use Xibo\Middleware\FeatureAuth;
use Xibo\Middleware\LayoutLock;
use Xibo\Middleware\SuperAdminAuth;

defined('XIBO') or die('Sorry, you are not allowed to directly access this page.');

if (file_exists(PROJECT_ROOT . '/lib/routes-cypress.php')) {
    include(PROJECT_ROOT . '/lib/routes-cypress.php');
}

/**
 * @SWG\Swagger(
 *  basePath="/api",
 *  produces={"application/json"},
 *  schemes={"http"},
 *  security={
 *      {"auth": {"write:all", "read:all"}}
 *  },
 *  @SWG\ExternalDocumentation(
 *      description="Manual",
 *      url="https://xibosignage.com/manual"
 *  )
 * )
 *
 * @SWG\Info(
 *  title="Xibo API",
 *  description="Xibo CMS API.
       Using HTTP formData requests.
       All PUT requests require Content-Type:application/x-www-form-urlencoded header.",
 *  version="4.0",
 *  termsOfService="https://xibosignage.com/legal",
 *  @SWG\License(
 *      name="AGPLv3 or later",
 *      url="http://www.gnu.org/licenses/"
 *  ),
 *  @SWG\Contact(
 *      email="info@xibo.org.uk"
 *  )
 * )
 *
 * @SWG\SecurityScheme(
 *   securityDefinition="auth",
 *   type="oauth2",
 *   flow="accessCode",
 *   authorizationUrl="/api/authorize",
 *   tokenUrl="/api/authorize/access_token",
 *   scopes={
 *      "read:all": "read access",
 *      "write:all": "write access"
 *   }
 * )
 */

/**
 * Misc
 * @SWG\Tag(
 *  name="misc",
 *  description="Miscellaneous"
 * )
 */
$app->get('/about', ['\Xibo\Controller\Login', 'About'])->setName('about');
$app->get('/clock', ['\Xibo\Controller\Clock', 'clock'])->setName('clock');
$app->post('/tfa', ['\Xibo\Controller\Login' , 'twoFactorAuthValidate'])->setName('tfa.auth.validate');

/**
 * Schedule
 * @SWG\Tag(
 *  name="schedule",
 *  description="Schedule"
 * )
 */
$app->get('/schedule', ['\Xibo\Controller\Schedule','grid'])->setName('schedule.search');
$app->get('/schedule/data/events', ['\Xibo\Controller\Schedule','eventData'])->setName('schedule.calendar.data');
$app->get('/schedule/{id}/events', ['\Xibo\Controller\Schedule','eventList'])->setName('schedule.events');

$app->post('/schedule', ['\Xibo\Controller\Schedule','add'])
    ->add(new FeatureAuth($app->getContainer(), ['schedule.add']))
    ->setName('schedule.add');

$app->group('', function(RouteCollectorProxy $group) {
    $group->put('/schedule/{id}', ['\Xibo\Controller\Schedule','edit'])
        ->setName('schedule.edit');

    $group->delete('/schedule/{id}', ['\Xibo\Controller\Schedule','delete'])
        ->setName('schedule.delete');

    $group->delete('/schedulerecurrence/{id}', ['\Xibo\Controller\Schedule','deleteRecurrence'])
        ->setName('schedule.recurrence.delete');
})->add(new FeatureAuth($app->getContainer(), ['schedule.modify']));

/**
 * Notification
 * @SWG\Tag(
 *  name="notification",
 *  description="Notifications"
 * )
 */
$app->get('/notification', ['\Xibo\Controller\Notification','grid'])->setName('notification.search');

$app->post('/notification', ['\Xibo\Controller\Notification','add'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['notification.add']))
    ->setName('notification.add');

$app->group('', function(RouteCollectorProxy $group) {
    //$app->map(['HEAD'], '/notification/attachment', ['\Xibo\Controller\Notification','addAttachment']);
    $group->post('/notification/attachment', ['\Xibo\Controller\Notification', 'addAttachment'])
        ->setName('notification.addattachment');

    $group->put('/notification/{id}', ['\Xibo\Controller\Notification', 'edit'])->setName('notification.edit');
    $group->delete('/notification/{id}', ['\Xibo\Controller\Notification', 'delete'])->setName('notification.delete');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['notification.modify']));

/**
 * Layouts
 * @SWG\Tag(
 *  name="layout",
 *  description="Layouts"
 * )
 */
$app->get('/layout', ['\Xibo\Controller\Layout','grid'])->setName('layout.search');
$app->get('/layout/status/{id}', ['\Xibo\Controller\Layout','status'])->setName('layout.status');
$app->put('/layout/lock/release/{id}', ['\Xibo\Controller\Layout', 'releaseLock'])->setName('layout.lock.release');

$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/layout', ['\Xibo\Controller\Layout', 'add'])->setName('layout.add');
    $group->post('/layout/fullscreen', ['\Xibo\Controller\Layout', 'createFullScreenLayout'])->setName('layout.add.full.screen.schedule');
    $group->post('/layout/copy/{id}', ['\Xibo\Controller\Layout','copy'])->setName('layout.copy');

    // TODO: why commented out? Layout Import
    //$group->map(['HEAD'],'/layout/import', ['\Xibo\Controller\Library','add');
    $group->post('/layout/import', ['\Xibo\Controller\Layout','import'])->setName('layout.import');

})->add(new FeatureAuth($app->getContainer(), ['layout.add']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/layout/{id}', ['\Xibo\Controller\Layout','edit'])->setName('layout.edit');
    $group->delete('/layout/{id}', ['\Xibo\Controller\Layout','delete'])->setName('layout.delete');
    $group->put('/layout/applyTemplate/{id}', ['\Xibo\Controller\Layout', 'applyTemplate'])
        ->setName('layout.apply.template');
    $group->put('/layout/background/{id}', ['\Xibo\Controller\Layout','editBackground'])->setName('layout.edit.background');
    $group->put('/layout/publish/{id}', ['\Xibo\Controller\Layout','publish'])->setName('layout.publish');
    $group->put('/layout/discard/{id}', ['\Xibo\Controller\Layout','discard'])->setName('layout.discard');
    $group->put('/layout/clear/{id}', ['\Xibo\Controller\Layout','clear'])->setName('layout.clear');
    $group->put('/layout/retire/{id}', ['\Xibo\Controller\Layout','retire'])->setName('layout.retire');
    $group->put('/layout/unretire/{id}', ['\Xibo\Controller\Layout','unretire'])->setName('layout.unretire');
    $group->post('/layout/thumbnail/{id}', ['\Xibo\Controller\Layout','addThumbnail'])->setName('layout.thumbnail.add');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.modify']))
    ->addMiddleware(new LayoutLock($app));

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/layout/checkout/{id}', ['\Xibo\Controller\Layout', 'checkout'])->setName('layout.checkout');
    $group->put('/layout/setenablestat/{id}',['\Xibo\Controller\Layout', 'setEnableStat'])->setName('layout.setenablestat');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.modify']));

// Tagging
$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/layout/{id}/tag', ['\Xibo\Controller\Layout', 'tag'])->setName('layout.tag');
    $group->post('/layout/{id}/untag', ['\Xibo\Controller\Layout', 'untag'])->setName('layout.untag');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['tag.tagging']));

/**
 * Region
 */
$app->group('/region', function (RouteCollectorProxy $group) {
    $group->post('/{id}', ['\Xibo\Controller\Region','add'])->setName('region.add');
    $group->put('/{id}', ['\Xibo\Controller\Region','edit'])->setName('region.edit');
    $group->delete('/{id}', ['\Xibo\Controller\Region','delete'])->setName('region.delete');
    $group->put('/position/all/{id}', ['\Xibo\Controller\Region','positionAll'])->setName('region.position.all');
    $group->post('/drawer/{id}', ['\Xibo\Controller\Region','addDrawer'])->setName('region.add.drawer');
    $group->put('/drawer/{id}', ['\Xibo\Controller\Region','saveDrawer'])->setName('region.save.drawer');
})
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.modify']))
    ->addMiddleware(new LayoutLock($app));

/**
 * playlist
 * @SWG\Tag(
 *  name="playlist",
 *  description="Playlists"
 * )
 */
$app->get('/playlist', ['\Xibo\Controller\Playlist','grid'])->setName('playlist.search');

$app->post('/playlist', ['\Xibo\Controller\Playlist','add'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['playlist.add']))
    ->setName('playlist.add');

$app->group('', function (RouteCollectorProxy $group) use ($app) {
    $group->put('/playlist/{id}', ['\Xibo\Controller\Playlist','edit'])->setName('playlist.edit');
    $group->delete('/playlist/{id}', ['\Xibo\Controller\Playlist','delete'])->setName('playlist.delete');
    $group->post('/playlist/copy/{id}', ['\Xibo\Controller\Playlist','copy'])->setName('playlist.copy');
    $group->put(
        '/playlist/setenablestat/{id}',
        ['\Xibo\Controller\Playlist','setEnableStat']
    )->setName('playlist.setenablestat');
    $group->put(
        '/playlist/{id}/selectfolder',
        ['\Xibo\Controller\Playlist','selectFolder']
    )->setName('playlist.selectfolder');
    $group->post(
        '/playlist/{id}/convert',
        ['\Xibo\Controller\Playlist','convert']
    )->setName('playlist.convert');

})->addMiddleware(new FeatureAuth($app->getContainer(), ['playlist.modify']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/playlist/order/{id}', ['\Xibo\Controller\Playlist','order'])->setName('playlist.order');
    $group->post('/playlist/library/assign/{id}', ['\Xibo\Controller\Playlist','libraryAssign'])->setName('playlist.library.assign');
})
    ->addMiddleware(new LayoutLock($app))
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.modify', 'playlist.modify']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/playlist/usage/{id}', ['\Xibo\Controller\Playlist','usage'])->setName('playlist.usage');
    $group->get('/playlist/usage/layouts/{id}', ['\Xibo\Controller\Playlist','usageLayouts'])->setName('playlist.usage.layouts');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['schedule.view', 'layout.view']));

/**
 * @SWG\Tag(
 *  name="widget",
 *  description="Widgets"
 * )
 */
$app->get('/widget/{id}/edit/options', ['\Xibo\Controller\Widget', 'additionalWidgetEditOptions'])->setName('widget.edit.options');
$app->group('/playlist/widget', function (RouteCollectorProxy $group) {
    $group->post('/{type}/{id}', ['\Xibo\Controller\Widget','addWidget'])->setName('module.widget.add');
    $group->put('/{id}', ['\Xibo\Controller\Widget','editWidget'])->setName('module.widget.edit');
    $group->delete('/{id}', ['\Xibo\Controller\Widget','deleteWidget'])->setName('module.widget.delete');
    $group->put('/transition/{type}/{id}', ['\Xibo\Controller\Widget','editWidgetTransition'])
        ->setName('module.widget.transition.edit');
    $group->put('/{id}/audio', ['\Xibo\Controller\Widget','widgetAudio'])->setName('module.widget.audio');
    $group->delete('/{id}/audio', ['\Xibo\Controller\Widget','widgetAudioDelete']);
    $group->put('/{id}/expiry', ['\Xibo\Controller\Widget','widgetExpiry'])->setName('module.widget.expiry');
    $group->put('/{id}/elements', ['\Xibo\Controller\Widget','saveElements'])->setName('module.widget.elements');
    $group->get('/{id}/dataType', ['\Xibo\Controller\Widget','getDataType'])->setName('module.widget.dataType');

    // Drawer widgets Region
    $group->put('/{id}/target', ['\Xibo\Controller\Widget','widgetSetRegion'])->setName('module.widget.set.region');

    // Widget Fallback Data APIs
    $group->get('/fallback/data/{id}', ['\Xibo\Controller\WidgetData','get'])
        ->setName('module.widget.data.get');
    $group->post('/fallback/data/{id}', ['\Xibo\Controller\WidgetData','add'])
        ->setName('module.widget.data.add');
    $group->put('/fallback/data/{id}/{dataId}', ['\Xibo\Controller\WidgetData','edit'])
        ->setName('module.widget.data.edit');
    $group->delete('/fallback/data/{id}/{dataId}', ['\Xibo\Controller\WidgetData','delete'])
        ->setName('module.widget.data.delete');
    $group->post('/fallback/data/{id}/order', ['\Xibo\Controller\WidgetData','setOrder'])
        ->setName('module.widget.data.set.order');
})
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.modify', 'playlist.modify']))
    ->addMiddleware(new LayoutLock($app));

/**
 * Campaign
 * @SWG\Tag(
 *  name="campaign",
 *  description="Campaigns"
 * )
 */
$app->get('/campaign', ['\Xibo\Controller\Campaign','grid'])->setName('campaign.search');
$app->post('/campaign', ['\Xibo\Controller\Campaign','add'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['campaign.add']))
    ->setName('campaign.add');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/campaign/{id}', ['\Xibo\Controller\Campaign','edit'])->setName('campaign.edit');
    $group->delete('/campaign/{id}', ['\Xibo\Controller\Campaign','delete'])->setName('campaign.delete');
    $group->post('/campaign/{id}/copy', ['\Xibo\Controller\Campaign','copy'])->setName('campaign.copy');
    $group->put('/campaign/{id}/selectfolder', ['\Xibo\Controller\Campaign','selectFolder'])->setName('campaign.selectfolder');
    $group->post('/campaign/layout/assign/{id}', ['\Xibo\Controller\Campaign','assignLayout'])
        ->setName('campaign.assign.layout');
    $group->delete('/campaign/layout/remove/{id}', ['\Xibo\Controller\Campaign','removeLayout'])
        ->setName('campaign.remove.layout');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['campaign.modify']));

/**
 * Templates
 * @SWG\Tag(
 *  name="template",
 *  description="Templates"
 * )
 */
$app->get('/template', ['\Xibo\Controller\Template', 'grid'])->setName('template.search');
$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/template', ['\Xibo\Controller\Template', 'add'])->setName('template.add');
    $group->post('/template/{id}', ['\Xibo\Controller\Template', 'addFromLayout'])->setName('template.add.from.layout');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['template.add']));

/**
 * Resolutions
 * @SWG\Tag(
 *  name="resolution",
 *  description="Resolutions"
 * )
 */
$app->get('/resolution', ['\Xibo\Controller\Resolution','grid'])->setName('resolution.search');
$app->post('/resolution', ['\Xibo\Controller\Resolution','add'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['resolution.add']))
    ->setName('resolution.add');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/resolution/{id}', ['\Xibo\Controller\Resolution','edit'])->setName('resolution.edit');
    $group->delete('/resolution/{id}', ['\Xibo\Controller\Resolution','delete'])->setName('resolution.delete');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['resolution.modify']));

/**
 * Library
 * @SWG\Tag(
 *  name="library",
 *  description="Library"
 * )
 */
$app->get('/library', ['\Xibo\Controller\Library','grid'])->setName('library.search');
$app->get('/library/{id}/isused', ['\Xibo\Controller\Library','isUsed'])->setName('library.isused');

$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/library/usage/{id}', ['\Xibo\Controller\Library','usage'])->setName('library.usage');
    $group->get('/library/usage/layouts/{id}', ['\Xibo\Controller\Library','usageLayouts'])->setName('library.usage.layouts');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['library.view']));

$app->get('/library/download/{id}', [
    '\Xibo\Controller\Library', 'download'
])->setName('library.download');
$app->get('/library/thumbnail/{id}', [
    '\Xibo\Controller\Library', 'thumbnail'
])->setName('library.thumbnail');

$app->post('/library', ['\Xibo\Controller\Library','add'])->setName('library.add')
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['library.add', 'dashboard.playlist']));

$app->group('', function (RouteCollectorProxy $group) {
    //$group->map(['HEAD'],'/library', ['\Xibo\Controller\Library','  addgroup
    $group->post('/library/uploadUrl', ['\Xibo\Controller\Library','uploadFromUrl'])->setName('library.uploadFromUrl');
    $group->post('/library/thumbnail', ['\Xibo\Controller\Library','addThumbnail'])->setName('library.thumbnail.add');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['library.add']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/library/{id}', ['\Xibo\Controller\Library','edit'])->setName('library.edit');
    $group->put('/library/setenablestat/{id}', ['\Xibo\Controller\Library','setEnableStat'])->setName('library.setenablestat');
    $group->delete('/library/tidy', ['\Xibo\Controller\Library','tidy'])->setName('library.tidy');
    $group->delete('/library/{id}', ['\Xibo\Controller\Library','delete'])->setName('library.delete');
    $group->post('/library/copy/{id}', ['\Xibo\Controller\Library','copy'])->setName('library.copy');
    $group->put('/library/{id}/selectfolder', ['\Xibo\Controller\Library','selectFolder'])->setName('library.selectfolder');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['library.modify']));

// Tagging
$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/library/{id}/tag', ['\Xibo\Controller\Library','tag'])->setName('library.tag');
    $group->post('/library/{id}/untag', ['\Xibo\Controller\Library','untag'])->setName('library.untag');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['tag.tagging']));

/**
 * Displays
 * @SWG\Tag(
 *  name="display",
 *  description="Displays"
 * )
 */
$app->get('/display', ['\Xibo\Controller\Display', 'grid'])->setName('display.search');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/display/requestscreenshot/{id}', ['\Xibo\Controller\Display','requestScreenShot'])->setName('display.requestscreenshot');
    $group->put('/display/licenceCheck/{id}', ['\Xibo\Controller\Display','checkLicence'])->setName('display.licencecheck');
    $group->put('/display/purgeAll/{id}', ['\Xibo\Controller\Display','purgeAll'])->setName('display.purge.all');
    $group->get('/display/screenshot/{id}', ['\Xibo\Controller\Display','screenShot'])->setName('display.screenShot');
    $group->get('/display/status/{id}', ['\Xibo\Controller\Display','statusWindow'])->setName('display.statusWindow');
    $group->get('/display/faults[/{displayId}]', ['\Xibo\Controller\PlayerFault','grid'])->setName('display.faults.search');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['displays.view']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/display/authorise/{id}', ['\Xibo\Controller\Display','toggleAuthorise'])->setName('display.authorise');
    $group->post('/display/addViaCode', ['\Xibo\Controller\Display','addViaCode'])->setName('display.addViaCode');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['displays.add']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/display/{id}', ['\Xibo\Controller\Display','edit'])->setName('display.edit');
    $group->delete('/display/{id}', ['\Xibo\Controller\Display','delete'])->setName('display.delete');
    $group->post('/display/wol/{id}', ['\Xibo\Controller\Display','wakeOnLan'])->setName('display.wol');
    $group->put('/display/setBandwidthLimit/multi', ['\Xibo\Controller\Display','setBandwidthLimitMultiple'])->setName('display.setBandwidthLimitMultiple');
    $group->put('/display/defaultlayout/{id}', ['\Xibo\Controller\Display','setDefaultLayout'])->setName('display.defaultlayout');
    $group->post('/display/{id}/displaygroup/assign', ['\Xibo\Controller\Display','assignDisplayGroup'])->setName('display.assign.displayGroup');
    $group->put('/display/{id}/moveCms', ['\Xibo\Controller\Display','moveCms'])->setName('display.moveCms');
    $group->delete('/display/{id}/moveCms', ['\Xibo\Controller\Display','moveCmsCancel'])->setName('display.moveCmsCancel');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['displays.modify']));

/**
 * Display Groups
 * @SWG\Tag(
 *  name="displayGroup",
 *  description="Display Groups"
 * )
 */
$app->get('/displayvenue', ['\Xibo\Controller\Display','displayVenue'])->setName('display.venue.search');
$app->get('/displaygroup', ['\Xibo\Controller\DisplayGroup','grid'])->setName('displayGroup.search');

$app->post('/displaygroup', ['\Xibo\Controller\DisplayGroup','add'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['displaygroup.add']))
    ->setName('displayGroup.add');
$app->post('/displaygroup/criteria/{displayGroupId}', ['\Xibo\Controller\DisplayGroup','pushCriteriaUpdate'])->setName('displayGroup.criteria.push');

$app->post('/displaygroup/{id}/action/collectNow', ['\Xibo\Controller\DisplayGroup','collectNow'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['displaygroup.view']))
    ->setName('displayGroup.action.collectNow');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/displaygroup/{id}', ['\Xibo\Controller\DisplayGroup','edit'])->setName('displayGroup.edit');
    $group->delete('/displaygroup/{id}', ['\Xibo\Controller\DisplayGroup','delete'])->setName('displayGroup.delete');

    $group->post('/displaygroup/{id}/display/assign', ['\Xibo\Controller\DisplayGroup','assignDisplay'])->setName('displayGroup.assign.display');
    $group->post('/displaygroup/{id}/display/unassign', ['\Xibo\Controller\DisplayGroup','unassignDisplay'])->setName('displayGroup.unassign.display');
    $group->post('/displaygroup/{id}/displayGroup/assign', ['\Xibo\Controller\DisplayGroup','assignDisplayGroup'])->setName('displayGroup.assign.displayGroup');
    $group->post('/displaygroup/{id}/displayGroup/unassign', ['\Xibo\Controller\DisplayGroup','unassignDisplayGroup'])->setName('displayGroup.unassign.displayGroup');
    $group->post('/displaygroup/{id}/media/assign', ['\Xibo\Controller\DisplayGroup','assignMedia'])->setName('displayGroup.assign.media');
    $group->post('/displaygroup/{id}/media/unassign', ['\Xibo\Controller\DisplayGroup','unassignMedia'])->setName('displayGroup.unassign.media');
    $group->post('/displaygroup/{id}/layout/assign', ['\Xibo\Controller\DisplayGroup','assignLayouts'])->setName('displayGroup.assign.layout');
    $group->post('/displaygroup/{id}/layout/unassign', ['\Xibo\Controller\DisplayGroup','unassignLayouts'])->setName('displayGroup.unassign.layout');
    $group->post('/displaygroup/{id}/action/changeLayout', ['\Xibo\Controller\DisplayGroup','changeLayout'])->setName('displayGroup.action.changeLayout');
    $group->post('/displaygroup/{id}/action/overlayLayout', ['\Xibo\Controller\DisplayGroup','overlayLayout'])->setName('displayGroup.action.overlayLayout');
    $group->post('/displaygroup/{id}/action/revertToSchedule', ['\Xibo\Controller\DisplayGroup','revertToSchedule'])->setName('displayGroup.action.revertToSchedule');
    $group->post('/displaygroup/{id}/copy', ['\Xibo\Controller\DisplayGroup','copy'])->setName('displayGroup.copy');
    $group->post('/displaygroup/{id}/action/clearStatsAndLogs', ['\Xibo\Controller\DisplayGroup','clearStatsAndLogs'])->setName('displayGroup.action.clearStatsAndLogs');
    $group->post('/displaygroup/{id}/action/triggerWebhook', ['\Xibo\Controller\DisplayGroup','triggerWebhook'])->setName('displayGroup.action.trigger.webhook');
    $group->put('/displaygroup/{id}/selectfolder', ['\Xibo\Controller\DisplayGroup','selectFolder'])->setName('displayGroup.selectfolder');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['displaygroup.modify']));

$app->post('/displaygroup/{id}/action/command', ['\Xibo\Controller\DisplayGroup','command'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['displaygroup.modify']))
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['command.view']))
    ->setName('displayGroup.action.command');
/**
 * Display Profile
 * @SWG\Tag(
 *  name="displayprofile",
 *  description="Display Settings"
 * )
 */
$app->get('/displayprofile', ['\Xibo\Controller\DisplayProfile','grid'])->setName('displayProfile.search');

$app->post('/displayprofile', ['\Xibo\Controller\DisplayProfile','add'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['displayprofile.add']))
    ->setName('displayProfile.add');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/displayprofile/{id}', ['\Xibo\Controller\DisplayProfile','edit'])->setName('displayProfile.edit');
    $group->delete('/displayprofile/{id}', ['\Xibo\Controller\DisplayProfile','delete'])->setName('displayProfile.delete');
    $group->post('/displayprofile/{id}/copy', ['\Xibo\Controller\DisplayProfile','copy'])->setName('displayProfile.copy');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['displayprofile.modify']));

/**
 * DataSet
 * @SWG\Tag(
 *  name="dataset",
 *  description="DataSets"
 * )
 */
$app->get('/dataset', ['\Xibo\Controller\DataSet','grid'])->setName('dataSet.search');
$app->post('/dataset', ['\Xibo\Controller\DataSet','add'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['dataset.add']))
    ->setName('dataSet.add');
$app->get('/rss/{psk}', ['\Xibo\Controller\DataSetRss','feed'])->setName('dataSet.rss.feed');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/dataset/{id}', ['\Xibo\Controller\DataSet','edit'])->setName('dataSet.edit');
    $group->delete('/dataset/{id}', ['\Xibo\Controller\DataSet','delete'])->setName('dataSet.delete');
    $group->put('/dataset/{id}/selectfolder', ['\Xibo\Controller\DataSet', 'selectFolder'])->setName('dataSet.selectfolder');

    $group->post('/dataset/copy/{id}', ['\Xibo\Controller\DataSet','copy'])->setName('dataSet.copy');
    //$group->map(['HEAD'],'/dataset/import/{id}', ['\Xibo\Controller\DataSet','import');
    $group->post('/dataset/import/{id}', ['\Xibo\Controller\DataSet','import'])->setName('dataSet.import');
    $group->post('/dataset/importjson/{id}', ['\Xibo\Controller\DataSet','importJson'])->setName('dataSet.import.json');
    $group->post('/dataset/remote/test', ['\Xibo\Controller\DataSet','testRemoteRequest'])->setName('dataSet.test.remote');
    $group->put('/dataset/dataConnector/{id}', ['\Xibo\Controller\DataSet','updateDataConnector'])->setName('dataSet.dataConnector.update');
    $group->get('/dataset/export/csv/{id}', ['\Xibo\Controller\DataSet', 'exportToCsv'])->setName('dataSet.export.csv');

    // Columns
    $group->get('/dataset/{id}/column', ['\Xibo\Controller\DataSetColumn','grid'])->setName('dataSet.column.search');
    $group->post('/dataset/{id}/column', ['\Xibo\Controller\DataSetColumn','add'])->setName('dataSet.column.add');
    $group->put('/dataset/{id}/column/{colId}', ['\Xibo\Controller\DataSetColumn','edit'])->setName('dataSet.column.edit');
    $group->delete('/dataset/{id}/column/{colId}', ['\Xibo\Controller\DataSetColumn','delete'])->setName('dataSet.column.delete');

    // RSS
    $group->get('/dataset/{id}/rss', ['\Xibo\Controller\DataSetRss','grid'])->setName('dataSet.rss.search');
    $group->post('/dataset/{id}/rss', ['\Xibo\Controller\DataSetRss','add'])->setName('dataSet.rss.add');
    $group->put('/dataset/{id}/rss/{rssId}', ['\Xibo\Controller\DataSetRss','edit'])
        ->setName('dataSet.rss.edit');
    $group->delete('/dataset/{id}/rss/{rssId}', ['\Xibo\Controller\DataSetRss','delete'])
        ->setName('dataSet.rss.delete');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['dataset.modify']));

// Data
$app->get('/dataset/data/{id}', ['\Xibo\Controller\DataSetData','grid'])->setName('dataSet.data.search');
$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/dataset/data/{id}', ['\Xibo\Controller\DataSetData','add'])->setName('dataSet.data.add');
    $group->put('/dataset/data/{id}/{rowId}', ['\Xibo\Controller\DataSetData','edit'])->setName('dataSet.data.edit');
    $group->delete('/dataset/data/{id}/{rowId}', ['\Xibo\Controller\DataSetData','delete'])->setName('dataSet.data.delete');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['dataset.data']));

/**
 * Folders
 * @SWG\Tag(
 *  name="folder",
 *  description="Folders"
 * )
 */
$app->get('/folders[/{folderId}]', ['\Xibo\Controller\Folder', 'grid'])->setName('folders.search');
$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/folders/contextButtons/{folderId}', ['\Xibo\Controller\Folder', 'getContextMenuButtons'])->setName('folders.context.buttons');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['folder.view']));

$app->post('/folders', ['\Xibo\Controller\Folder', 'add'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['folder.add']))
    ->setName('folders.add');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/folders/{folderId}', ['\Xibo\Controller\Folder', 'edit'])->setName('folders.edit');
    $group->delete('/folders/{folderId}', ['\Xibo\Controller\Folder', 'delete'])->setName('folders.delete');
    $group->put('/folders/{folderId}/move', ['\Xibo\Controller\Folder', 'move'])->setName('folders.move');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['folder.modify']));

/**
 * Statistics
 * @SWG\Tag(
 *  name="statistics",
 *  description="Statistics"
 * )
 */
$app->get('/stats', ['\Xibo\Controller\Stats','grid'])->setName('stats.search');

$app->get('/stats/timeDisconnected', ['\Xibo\Controller\Stats', 'gridTimeDisconnected'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['display.reporting']))
    ->setName('stats.timeDisconnected.search');

$app->get('/stats/export', ['\Xibo\Controller\Stats','export'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['proof-of-play']))
    ->setName('stats.export');

// Log (no APIs)
// -------------
$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/log', ['\Xibo\Controller\Logging', 'grid'])->setName('log.search');
    $group->delete('/log', ['\Xibo\Controller\Logging', 'truncate'])->setName('log.truncate');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['log.view']));

/**
 * User
 * @SWG\Tag(
 *  name="user",
 *  description="Users"
 * )
 */
$app->get('/user/pref', ['\Xibo\Controller\User' , 'pref'])->setName('user.pref');
$app->post('/user/pref', ['\Xibo\Controller\User' ,'prefEdit']);
$app->put('/user/pref', ['\Xibo\Controller\User' ,'prefEditFromForm']);
$app->get('/user/me', ['\Xibo\Controller\User','myDetails'])->setName('user.me');
$app->get('/user', ['\Xibo\Controller\User','grid'])->setName('user.search');
$app->put('/user/profile/edit', ['\Xibo\Controller\User','editProfile'])->setName('user.edit.profile');
$app->get('/user/profile/setup', ['\Xibo\Controller\User','tfaSetup'])->setName('user.setup.profile');
$app->post('/user/profile/validate', ['\Xibo\Controller\User','tfaValidate'])->setName('user.validate.profile');
$app->get('/user/profile/recoveryGenerate', ['\Xibo\Controller\User','tfaRecoveryGenerate'])->setName('user.recovery.generate.profile');
$app->get('/user/profile/recoveryShow', ['\Xibo\Controller\User','tfaRecoveryShow'])->setName('user.recovery.show.profile');
$app->put('/user/password/forceChange', ['\Xibo\Controller\User','forceChangePassword'])->setName('user.force.change.password');

// permissions
$app->get('/user/permissions/{entity}/{id}', ['\Xibo\Controller\User','permissionsGrid'])->setName('user.permissions');
$app->get('/user/permissions/{entity}', ['\Xibo\Controller\User','permissionsMultiGrid'])->setName('user.permissions.multi');
$app->post('/user/permissions/{entity}/{id}', ['\Xibo\Controller\User','permissions'])->setName('user.set.permissions');
$app->post('/user/permissions/{entity}', ['\Xibo\Controller\User','permissionsMulti'])->setName('user.set.permissions.multi');

$app->post('/user', ['\Xibo\Controller\User','add'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['users.add']))
    ->setName('user.add');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/user/{id}', ['\Xibo\Controller\User','edit'])->setName('user.edit');
    $group->delete('/user/{id}', ['\Xibo\Controller\User','delete'])->setName('user.delete');
    $group->post('/user/{id}/usergroup/assign', ['\Xibo\Controller\User','assignUserGroup'])->setName('user.assign.userGroup');
    $group->post('/user/{id}/setHomeFolder', ['\Xibo\Controller\User', 'setHomeFolder'])
        ->addMiddleware(new FeatureAuth($group->getContainer(), ['folder.userHome']))
        ->setName('user.homeFolder');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['users.modify']));

/**
 * User Group
 * @SWG\Tag(
 *  name="usergroup",
 *  description="User Groups"
 * )
 */
$app->get('/group', ['\Xibo\Controller\UserGroup','grid'])->setName('group.search');

$app->post('/group', ['\Xibo\Controller\UserGroup','add'])->setName('group.add');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/group/{id}', ['\Xibo\Controller\UserGroup','edit'])->setName('group.edit');
    $group->delete('/group/{id}', ['\Xibo\Controller\UserGroup','delete'])->setName('group.delete');
    $group->post('/group/{id}/copy', ['\Xibo\Controller\UserGroup','copy'])->setName('group.copy');

    $group->post('/group/members/assign/{id}', ['\Xibo\Controller\UserGroup','assignUser'])->setName('group.members.assign');
    $group->post('/group/members/unassign/{id}', ['\Xibo\Controller\UserGroup','unassignUser'])->setName('group.members.unassign');

    $group->post('/group/acl/{id}', ['\Xibo\Controller\UserGroup','acl'])->setName('group.acl');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['usergroup.modify']));

//
// Applications
//
$app->get('/application', ['\Xibo\Controller\Applications','grid'])->setName('application.search');

$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/application', ['\Xibo\Controller\Applications','add'])->setName('application.add');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['application.add']));
$app->delete('/application/revoke/{id}/{userId}', ['\Xibo\Controller\Applications', 'revokeAccess'])->setName('application.revoke');


/**
 * Modules
 * @SWG\Tag(
 *  name="module",
 *  description="Modules and Widgets"
 * )
 */
$app->get('/module', ['\Xibo\Controller\Module','grid'])->setName('module.search');
$app->get('/module/templates/{dataType}', [
    '\Xibo\Controller\Module', 'templateGrid'
])->setName('module.template.search');

$app->get('/module/asset/{assetId}', [
    '\Xibo\Controller\Module',
    'assetDownload',
])->setName('module.asset.download');

// Properties
$app->get('/module/properties/{id}', ['\Xibo\Controller\Module','getProperties'])
    ->setName('module.get.properties');
$app->get('/module/template/{dataType}/properties/{id}', ['\Xibo\Controller\Module','getTemplateProperties'])
    ->setName('module.template.get.properties');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/module/settings/{id}', ['\Xibo\Controller\Module','settings'])->setName('module.settings');
    $group->put('/module/clear-cache/{id}', ['\Xibo\Controller\Module','clearCache'])->setName('module.clear.cache');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['module.view']));

//
// Transition
//
$app->get('/transition', ['\Xibo\Controller\Transition','grid'])->setName('transition.search');
$app->put('/transition/{id}', ['\Xibo\Controller\Transition','edit'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['transition.view']))
    ->setName('transition.edit');

//
// Sessions
//
$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/sessions', ['\Xibo\Controller\Sessions','grid'])->setName('sessions.search');
    $group->delete('/sessions/logout/{id}', ['\Xibo\Controller\Sessions','logout'])->setName('sessions.confirm.logout');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['session.view']));

//
// Settings
//
$app->put('/admin', ['\Xibo\Controller\Settings','update'])
    ->addMiddleware(new SuperAdminAuth($app->getContainer()))
    ->setName('settings.update');

//
// Maintenance
//
$app->post('/maintenance/tidy', ['\Xibo\Controller\Maintenance','tidyLibrary'])
    ->addMiddleware(new SuperAdminAuth($app->getContainer()))
    ->setName('maintenance.tidy');

//
// Audit Log
//
$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/audit', ['\Xibo\Controller\AuditLog','grid'])->setName('auditLog.search');
    $group->get('/audit/export', ['\Xibo\Controller\AuditLog','export'])->setName('auditLog.export');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['auditlog.view']));

//
// Fault
//
$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/fault/debug/on', ['\Xibo\Controller\Fault','debugOn'])->setName('fault.debug.on');
    $group->put('/fault/debug/off', ['\Xibo\Controller\Fault','debugOff'])->setName('fault.debug.off');
    $group->get('/fault/collect', ['\Xibo\Controller\Fault','collect'])->setName('fault.collect');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['fault.view']));

/**
 * Commands
 * @SWG\Tag(
 *  name="command",
 *  description="Commands"
 * )
 */
$app->get('/command', ['\Xibo\Controller\Command','grid'])->setName('command.search');
$app->post('/command', ['\Xibo\Controller\Command','add'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['command.add']))
    ->setName('command.add');
$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/command/{id}', ['\Xibo\Controller\Command','edit'])->setName('command.edit');
    $group->delete('/command/{id}', ['\Xibo\Controller\Command','delete'])->setName('command.delete');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['command.modify']));

/**
 * Dayparts
 * @SWG\Tag(
 *  name="dayPart",
 *  description="Dayparting"
 * )
 */
$app->get('/daypart', ['\Xibo\Controller\DayPart','grid'])->setName('daypart.search');
$app->post('/daypart', ['\Xibo\Controller\DayPart','add'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['daypart.add']))
    ->setName('daypart.add');
$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/daypart/{id}', ['\Xibo\Controller\DayPart','edit'])->setName('daypart.edit');
    $group->delete('/daypart/{id}', ['\Xibo\Controller\DayPart','delete'])->setName('daypart.delete');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['daypart.modify']));

// Tasks (no APIs)
// ----
$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/task', ['\Xibo\Controller\Task', 'grid'])->setName('task.search');
    $group->post('/task', ['\Xibo\Controller\Task', 'add'])->setName('task.add');
    $group->put('/task/{id}', ['\Xibo\Controller\Task', 'edit'])->setName('task.edit');
    $group->delete('/task/{id}', ['\Xibo\Controller\Task', 'delete'])->setName('task.delete');
    $group->post('/task/{id}/run', ['\Xibo\Controller\Task', 'runNow'])->setName('task.runNow');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['task.view']));

// Report schedule (no APIs)
// -------------------------
$app->get('/report/reportschedule', ['\Xibo\Controller\ScheduleReport','reportScheduleGrid'])->setName('reportschedule.search');
$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/report/reportschedule', ['\Xibo\Controller\ScheduleReport','reportScheduleAdd'])->setName('reportschedule.add');
    $group->put('/report/reportschedule/{id}', ['\Xibo\Controller\ScheduleReport','reportScheduleEdit'])->setName('reportschedule.edit');
    $group->delete('/report/reportschedule/{id}', ['\Xibo\Controller\ScheduleReport','reportScheduleDelete'])->setName('reportschedule.delete');
    $group->post('/report/reportschedule/{id}/deletesavedreport', ['\Xibo\Controller\ScheduleReport','reportScheduleDeleteAllSavedReport'])->setName('reportschedule.deleteall');
    $group->post('/report/reportschedule/{id}/toggleactive', ['\Xibo\Controller\ScheduleReport','reportScheduleToggleActive'])->setName('reportschedule.toggleactive');
    $group->post('/report/reportschedule/{id}/reset', ['\Xibo\Controller\ScheduleReport','reportScheduleReset'])->setName('reportschedule.reset');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['report.scheduling']));

//
// Saved reports
//
$app->get('/report/savedreport', ['\Xibo\Controller\SavedReport','savedReportGrid'])
    ->setName('savedreport.search');
$app->delete('/report/savedreport/{id}', ['\Xibo\Controller\SavedReport','savedReportDelete'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['report.saving']))
    ->setName('savedreport.delete');

/**
 * Player Versions
 * @SWG\Tag(
 *  name="Player Software",
 * )
 */
$app->get('/playersoftware', ['\Xibo\Controller\PlayerSoftware','grid'])->setName('playersoftware.search');

$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/playersoftware/download/{id}', ['\Xibo\Controller\PlayerSoftware', 'download'])->setName('playersoftware.download');
    $group->post('/playersoftware', ['\Xibo\Controller\PlayerSoftware','add'])->setName('playersoftware.add');
    $group->put('/playersoftware/{id}', ['\Xibo\Controller\PlayerSoftware','edit'])->setName('playersoftware.edit');
    $group->delete('/playersoftware/{id}', ['\Xibo\Controller\PlayerSoftware','delete'])->setName('playersoftware.delete');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['playersoftware.view']));

// Install
$app->get('/sssp_config.xml', ['\Xibo\Controller\PlayerSoftware','getSsspInstall'])->setName('playersoftware.sssp.install');
$app->get('/sssp_dl.wgt', ['\Xibo\Controller\PlayerSoftware','getSsspInstallDownload'])->setName('playersoftware.sssp.install.download');
$app->get('/playersoftware/{nonce}/sssp_config.xml', ['\Xibo\Controller\PlayerSoftware','getSssp'])->setName('playersoftware.sssp');
$app->get('/playersoftware/{nonce}/sssp_dl.wgt', ['\Xibo\Controller\PlayerSoftware','getVersionFile'])->setName('playersoftware.version.file');

/**
 * Tags
 * @SWG\Tag(
 *  name="tags",
 *  description="Tags"
 * )
 */
$app->get('/tag', ['\Xibo\Controller\Tag','grid'])->setName('tag.search');
$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/tag', ['\Xibo\Controller\Tag','add'])->setName('tag.add');
    $group->put('/tag/{id}', ['\Xibo\Controller\Tag','edit'])->setName('tag.edit');
    $group->delete('/tag/{id}', ['\Xibo\Controller\Tag','delete'])->setName('tag.delete');
    $group->get('/tag/name', ['\Xibo\Controller\Tag','loadTagOptions'])->setName('tag.getByName');
    $group->put('/tag/{type}/multi', ['\Xibo\Controller\Tag','editMultiple'])->setName('tag.editMultiple');
    $group->get('/tag/usage/{id}', ['\Xibo\Controller\Tag', 'usage'])->setName('tag.usage');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['tag.view']));

// Actions (no APIs)
// -----------------
$app->get('/action', ['\Xibo\Controller\Action', 'grid'])->setName('action.search');
$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/action', ['\Xibo\Controller\Action', 'add'])->setName('action.add');
    $group->put('/action/{id}', ['\Xibo\Controller\Action', 'edit'])->setName('action.edit');
    $group->delete('/action/{id}', ['\Xibo\Controller\Action', 'delete'])->setName('action.delete');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.modify', 'playlist.modify']));

/**
 * Menu Boards
 * @SWG\Tag(
 *  name="menuBoard",
 *  description="Menu Boards - feature preview, please do not use in production."
 * )
 */
$app->get('/menuboards', ['\Xibo\Controller\MenuBoard', 'grid'])->setName('menuBoard.search');
$app->post('/menuboard', ['\Xibo\Controller\MenuBoard', 'add'])->addMiddleware(new FeatureAuth($app->getContainer(), ['menuBoard.add']))->setName('menuBoard.add');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/menuboard/{id}', ['\Xibo\Controller\MenuBoard', 'edit'])->setName('menuBoard.edit');
    $group->delete('/menuboard/{id}', ['\Xibo\Controller\MenuBoard', 'delete'])->setName('menuBoard.delete');
    $group->put('/menuboard/{id}/selectfolder', ['\Xibo\Controller\MenuBoard', 'selectFolder'])->setName('menuBoard.selectfolder');

    $group->get('/menuboard/{id}/categories', ['\Xibo\Controller\MenuBoardCategory', 'grid'])->setName('menuBoard.category.search');
    $group->post('/menuboard/{id}/category', ['\Xibo\Controller\MenuBoardCategory', 'add'])->setName('menuBoard.category.add');
    $group->put('/menuboard/{id}/category', ['\Xibo\Controller\MenuBoardCategory', 'edit'])->setName('menuBoard.category.edit');
    $group->delete('/menuboard/{id}/category', ['\Xibo\Controller\MenuBoardCategory', 'delete'])->setName('menuBoard.category.delete');

    $group->get('/menuboard/{id}/products', ['\Xibo\Controller\MenuBoardProduct', 'grid'])->setName('menuBoard.product.search');
    $group->get('/menuboard/products', ['\Xibo\Controller\MenuBoardProduct', 'productsForWidget'])->setName('menuBoard.product.search.widget');
    $group->post('/menuboard/{id}/product', ['\Xibo\Controller\MenuBoardProduct', 'add'])->setName('menuBoard.product.add');
    $group->put('/menuboard/{id}/product', ['\Xibo\Controller\MenuBoardProduct', 'edit'])->setName('menuBoard.product.edit');
    $group->delete('/menuboard/{id}/product', ['\Xibo\Controller\MenuBoardProduct', 'delete'])->setName('menuBoard.product.delete');
})
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['menuBoard.modify']));

$app->get('/fonts', ['\Xibo\Controller\Font', 'grid'])->setName('font.search');
$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/fonts/details/{id}', ['\Xibo\Controller\Font', 'getFontLibDetails'])->setName('font.details');
    $group->get('/fonts/download/{id}', ['\Xibo\Controller\Font', 'download'])->setName('font.download');
    $group->post('/fonts', ['\Xibo\Controller\Font','add'])->setName('font.add');
    $group->delete('/fonts/{id}/delete', ['\Xibo\Controller\Font','delete'])->setName('font.delete');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['font.view']));

$app->get('/syncgroups', ['\Xibo\Controller\SyncGroup', 'grid'])->setName('syncgroup.search');
$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/syncgroup/{id}/displays', ['\Xibo\Controller\SyncGroup', 'fetchDisplays'])
        ->setName('syncgroup.fetch.displays');
    $group->post('/syncgroup/add', ['\Xibo\Controller\SyncGroup', 'add'])->setName('syncgroup.add');
    $group->post('/syncgroup/{id}/members', ['\Xibo\Controller\SyncGroup', 'members'])->setName('syncgroup.members');
    $group->put('/syncgroup/{id}/edit', ['\Xibo\Controller\SyncGroup', 'edit'])->setName('syncgroup.edit');
    $group->delete('/syncgroup/{id}/delete', ['\Xibo\Controller\SyncGroup', 'delete'])->setName('syncgroup.delete');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['display.syncView']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/schedule/sync/add', ['\Xibo\Controller\Schedule', 'syncAdd'])->setName('schedule.add.sync');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['schedule.sync']));
