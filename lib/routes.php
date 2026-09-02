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

global $app;

use Slim\Routing\RouteCollectorProxy;
use Xibo\Middleware\FeatureAuth;
use Xibo\Middleware\LayoutLock;
use Xibo\Middleware\SuperAdminAuth;

defined('XIBO') or die('Sorry, you are not allowed to directly access this page.');

/**
 * Misc
 */
$app->get('/about', ['\Xibo\Controller\Login', 'about'])->setName('about');
$app->get('/about/config', ['\Xibo\Controller\Login', 'aboutConfig']);
$app->get('/clock', ['\Xibo\Controller\Clock', 'clock'])->setName('clock');
$app->post('/tfa', ['\Xibo\Controller\Login' , 'twoFactorAuthValidate'])->setName('tfa.auth.validate');

/**
 * Schedule
 */
$app->get('/schedule', ['\Xibo\Controller\Schedule','grid'])->setName('schedule.search');

$app->get('/schedule/{id:[0-9]+}/events', ['\Xibo\Controller\Schedule','eventList'])->setName('schedule.events');
$app->get('/schedule/{id:[0-9]+}', ['\Xibo\Controller\Schedule','searchById'])
    ->add(new FeatureAuth($app->getContainer(), ['schedule.view']))
    ->setName('schedule.search.id');

$app->post('/schedule', ['\Xibo\Controller\Schedule','add'])
    ->add(new FeatureAuth($app->getContainer(), ['schedule.add']))
    ->setName('schedule.add');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/schedule/{id:[0-9]+}', ['\Xibo\Controller\Schedule','edit'])
        ->setName('schedule.edit');

    $group->delete('/schedule/{id:[0-9]+}', ['\Xibo\Controller\Schedule','delete'])
        ->setName('schedule.delete');

    $group->delete('/schedulerecurrence/{id:[0-9]+}', ['\Xibo\Controller\Schedule','deleteRecurrence'])
        ->setName('schedule.recurrence.delete');

    $group->post('/schedule/copy/{id:[0-9]+}', ['\Xibo\Controller\Schedule','copy'])
        ->setName('schedule.copy');
})->add(new FeatureAuth($app->getContainer(), ['schedule.modify']));

/**
 * Notification
 */
$app->get('/notification', ['\Xibo\Controller\Notification','grid'])->setName('notification.search');
$app->get('/notification/{id:[0-9]+}', ['\Xibo\Controller\Notification', 'searchById'])->setName('notification.search.id');

$app->post('/notification', ['\Xibo\Controller\Notification','add'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['notification.add']))
    ->setName('notification.add');

$app->post('/notification/attachment', ['\Xibo\Controller\Notification', 'addAttachment'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['notification.add', 'notification.modify']))
    ->setName('notification.addattachment');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/notification/{id:[0-9]+}', ['\Xibo\Controller\Notification', 'edit'])->setName('notification.edit');
    $group->delete('/notification/{id:[0-9]+}', ['\Xibo\Controller\Notification', 'delete'])->setName('notification.delete');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['notification.modify']));

/**
 * Layouts
 */
$app->get('/layout', ['\Xibo\Controller\Layout','grid'])->setName('layout.search');
$app->get('/layout/codes', ['\Xibo\Controller\Layout', 'getLayoutCodes'])->setName('layout.code.search');
$app->put('/layout/lock/release/{id:[0-9]+}', ['\Xibo\Controller\Layout', 'releaseLock'])->setName('layout.lock.release');

$app->get('/layout/status/{id:[0-9]+}', ['\Xibo\Controller\Layout','status'])
    ->setName('layout.status')
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.view', 'template.view']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/layout', ['\Xibo\Controller\Layout', 'add'])->setName('layout.add');
    $group->post('/layout/fullscreen', ['\Xibo\Controller\Layout', 'createFullScreenLayout'])->setName('layout.add.full.screen.schedule');
    $group->post('/layout/copy/{id:[0-9]+}', ['\Xibo\Controller\Layout','copy'])->setName('layout.copy');

    // TODO: why commented out? Layout Import
    //$group->map(['HEAD'],'/layout/import', ['\Xibo\Controller\Library','add');
    $group->post('/layout/import', ['\Xibo\Controller\Layout','import'])->setName('layout.import');

})->add(new FeatureAuth($app->getContainer(), ['layout.add']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/layout/{id:[0-9]+}', ['\Xibo\Controller\Layout','edit'])->setName('layout.edit');
    $group->delete('/layout/{id:[0-9]+}', ['\Xibo\Controller\Layout','delete'])->setName('layout.delete');
    $group->put('/layout/applyTemplate/{id:[0-9]+}', ['\Xibo\Controller\Layout', 'applyTemplate'])
        ->setName('layout.apply.template');
    $group->put('/layout/background/{id:[0-9]+}', ['\Xibo\Controller\Layout','editBackground'])->setName('layout.edit.background');
    $group->put('/layout/publish/{id:[0-9]+}', ['\Xibo\Controller\Layout','publish'])->setName('layout.publish');
    $group->put('/layout/discard/{id:[0-9]+}', ['\Xibo\Controller\Layout','discard'])->setName('layout.discard');
    $group->put('/layout/clear/{id:[0-9]+}', ['\Xibo\Controller\Layout','clear'])->setName('layout.clear');
    $group->put('/layout/retire/{id:[0-9]+}', ['\Xibo\Controller\Layout','retire'])->setName('layout.retire');
    $group->put('/layout/unretire/{id:[0-9]+}', ['\Xibo\Controller\Layout','unretire'])->setName('layout.unretire');
    $group->post('/layout/thumbnail/{id:[0-9]+}', ['\Xibo\Controller\Layout','addThumbnail'])->setName('layout.thumbnail.add');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.modify']))
    ->addMiddleware(new LayoutLock($app));

$app->group('', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/layout/thumbnail/{id:[0-9]+}', ['\Xibo\Controller\Layout', 'downloadThumbnail'])
        ->setName('layout.download.thumbnail');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.view', 'template.view']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/layout/checkout/{id:[0-9]+}', ['\Xibo\Controller\Layout', 'checkout'])->setName('layout.checkout');
    $group->put('/layout/setenablestat/{id:[0-9]+}',['\Xibo\Controller\Layout', 'setEnableStat'])->setName('layout.setenablestat');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.modify']));

$app->group('', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->post('/layout/export/{id:[0-9]+}', ['\Xibo\Controller\Layout', 'export'])->setName('layout.export');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.export']));

// Tagging
$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/layout/{id:[0-9]+}/tag', ['\Xibo\Controller\Layout', 'tag'])->setName('layout.tag');
    $group->post('/layout/{id:[0-9]+}/untag', ['\Xibo\Controller\Layout', 'untag'])->setName('layout.untag');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['tag.tagging']));

/**
 * Region
 */
$app->group('/region', function (RouteCollectorProxy $group) {
    $group->post('/{id:[0-9]+}', ['\Xibo\Controller\Region','add'])->setName('region.add');
    $group->put('/{id:[0-9]+}', ['\Xibo\Controller\Region','edit'])->setName('region.edit');
    $group->delete('/{id:[0-9]+}', ['\Xibo\Controller\Region','delete'])->setName('region.delete');
    $group->put('/position/all/{id:[0-9]+}', ['\Xibo\Controller\Region','positionAll'])->setName('region.position.all');
    $group->post('/drawer/{id:[0-9]+}', ['\Xibo\Controller\Region','addDrawer'])->setName('region.add.drawer');
    $group->put('/drawer/{id:[0-9]+}', ['\Xibo\Controller\Region','saveDrawer'])->setName('region.save.drawer');
})
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.modify']))
    ->addMiddleware(new LayoutLock($app));

/**
 * playlist
 */
$app->get('/playlist', ['\Xibo\Controller\Playlist','grid'])->setName('playlist.search');
$app->get('/playlist/{id:[0-9]+}', ['\Xibo\Controller\Playlist','searchById'])->setName('playlist.search.id');

$app->post('/playlist', ['\Xibo\Controller\Playlist','add'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['playlist.add']))
    ->setName('playlist.add');

$app->group('', function (RouteCollectorProxy $group) use ($app) {
    $group->put('/playlist/{id:[0-9]+}', ['\Xibo\Controller\Playlist','edit'])->setName('playlist.edit');
    $group->delete('/playlist/{id:[0-9]+}', ['\Xibo\Controller\Playlist','delete'])->setName('playlist.delete');
    $group->post('/playlist/copy/{id:[0-9]+}', ['\Xibo\Controller\Playlist','copy'])->setName('playlist.copy');
    $group->put(
        '/playlist/setenablestat/{id:[0-9]+}',
        ['\Xibo\Controller\Playlist','setEnableStat']
    )->setName('playlist.setenablestat');
    $group->put(
        '/playlist/{id:[0-9]+}/selectfolder',
        ['\Xibo\Controller\Playlist','selectFolder']
    )->setName('playlist.selectfolder');
    $group->post(
        '/playlist/{id:[0-9]+}/convert',
        ['\Xibo\Controller\Playlist','convert']
    )->setName('playlist.convert');

})->addMiddleware(new FeatureAuth($app->getContainer(), ['playlist.modify']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/playlist/order/{id:[0-9]+}', ['\Xibo\Controller\Playlist','order'])->setName('playlist.order');
    $group->post('/playlist/library/assign/{id:[0-9]+}', ['\Xibo\Controller\Playlist','libraryAssign'])->setName('playlist.library.assign');
})
    ->addMiddleware(new LayoutLock($app))
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.modify', 'playlist.modify']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/playlist/usage/{id:[0-9]+}', ['\Xibo\Controller\Playlist','usage'])->setName('playlist.usage');
    $group->get('/playlist/usage/layouts/{id:[0-9]+}', ['\Xibo\Controller\Playlist','usageLayouts'])->setName('playlist.usage.layouts');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['schedule.view', 'layout.view']));

// Widget
$app->get('/widget/{id}/edit/options', ['\Xibo\Controller\Widget', 'additionalWidgetEditOptions'])->setName('widget.edit.options');
$app->group('/playlist/widget', function (RouteCollectorProxy $group) {
    $group->post('/{type}/{id:[0-9]+}', ['\Xibo\Controller\Widget','addWidget'])->setName('module.widget.add');
    $group->put('/{id}', ['\Xibo\Controller\Widget','editWidget'])->setName('module.widget.edit');
    $group->delete('/{id}', ['\Xibo\Controller\Widget','deleteWidget'])->setName('module.widget.delete');
    $group->put('/transition/{type}/{id}', ['\Xibo\Controller\Widget','editWidgetTransition'])
        ->setName('module.widget.transition.edit');
    $group->put('/{id}/audio', ['\Xibo\Controller\Widget','widgetAudio'])->setName('module.widget.audio');
    $group->delete('/{id}/audio', ['\Xibo\Controller\Widget','widgetAudioDelete']);
    $group->put('/{id}/expiry', ['\Xibo\Controller\Widget','widgetExpiry'])->setName('module.widget.expiry');
    $group->put('/{id}/elements', ['\Xibo\Controller\Widget','saveElements'])->setName('module.widget.elements');
    $group->get('/{id:[0-9]+}/dataType', ['\Xibo\Controller\Widget','getDataType'])->setName('module.widget.dataType');

    // Drawer widgets Region
    $group->put('/{id}/target', ['\Xibo\Controller\Widget','widgetSetRegion'])->setName('module.widget.set.region');

    // Widget Fallback Data APIs
    $group->get('/fallback/data/{id:[0-9]+}', ['\Xibo\Controller\WidgetData','get'])
        ->setName('module.widget.data.get');
    $group->post('/fallback/data/{id:[0-9]+}', ['\Xibo\Controller\WidgetData','add'])
        ->setName('module.widget.data.add');
    $group->put('/fallback/data/{id:[0-9]+}/{dataId:[0-9]+}', ['\Xibo\Controller\WidgetData','edit'])
        ->setName('module.widget.data.edit');
    $group->delete('/fallback/data/{id:[0-9]+}/{dataId:[0-9]+}', ['\Xibo\Controller\WidgetData','delete'])
        ->setName('module.widget.data.delete');
    $group->post('/fallback/data/{id:[0-9]+}/order', ['\Xibo\Controller\WidgetData','setOrder'])
        ->setName('module.widget.data.set.order');
})
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.modify', 'playlist.modify']))
    ->addMiddleware(new LayoutLock($app));

/**
 * Campaign
 */
$app->get('/campaign', ['\Xibo\Controller\Campaign','grid'])->setName('campaign.search');
$app->get('/campaign/{id:[0-9]+}', ['\Xibo\Controller\Campaign', 'searchById'])->setName('campaign.search.id');
$app->post('/campaign', ['\Xibo\Controller\Campaign','add'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['campaign.add']))
    ->setName('campaign.add');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/campaign/{id:[0-9]+}', ['\Xibo\Controller\Campaign','edit'])->setName('campaign.edit');
    $group->delete('/campaign/{id:[0-9]+}', ['\Xibo\Controller\Campaign','delete'])->setName('campaign.delete');
    $group->post('/campaign/{id:[0-9]+}/copy', ['\Xibo\Controller\Campaign','copy'])->setName('campaign.copy');
    $group->put('/campaign/{id:[0-9]+}/selectfolder', ['\Xibo\Controller\Campaign','selectFolder'])->setName('campaign.selectfolder');
    $group->post('/campaign/layout/assign/{id:[0-9]+}', ['\Xibo\Controller\Campaign','assignLayout'])
        ->setName('campaign.assign.layout');
    $group->delete('/campaign/layout/remove/{id:[0-9]+}', ['\Xibo\Controller\Campaign','removeLayout'])
        ->setName('campaign.remove.layout');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['campaign.modify']));

/**
 * Templates
 */
$app->get('/template', ['\Xibo\Controller\Template', 'grid'])->setName('template.search');
$app->get('/template/{id:[0-9]+}', ['\Xibo\Controller\Template', 'searchById'])->setName('template.search.id');
$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/template', ['\Xibo\Controller\Template', 'add'])->setName('template.add');
    $group->post('/template/{id:[0-9]+}', ['\Xibo\Controller\Template', 'addFromLayout'])->setName('template.add.from.layout');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['template.add']));

/**
 * Resolutions
 */
$app->get('/resolution', ['\Xibo\Controller\Resolution','grid'])->setName('resolution.search');
$app->get('/resolution/{id:[0-9]+}', ['\Xibo\Controller\Resolution','searchById'])->setName('resolution.search.id');
$app->post('/resolution', ['\Xibo\Controller\Resolution','add'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['resolution.add']))
    ->setName('resolution.add');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/resolution/{id:[0-9]+}', ['\Xibo\Controller\Resolution','edit'])->setName('resolution.edit');
    $group->delete('/resolution/{id:[0-9]+}', ['\Xibo\Controller\Resolution','delete'])->setName('resolution.delete');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['resolution.modify']));

/**
 * Library
 */
$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/library', ['\Xibo\Controller\Library','grid'])->setName('library.search');
    $group->get('/library/{id:[0-9]+}', ['\Xibo\Controller\Library','searchById'])->setName('library.search.id');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['library.view']));

$app->get('/library/{id}/isused', ['\Xibo\Controller\Library','isUsed'])->setName('library.isused');

$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/library/usage/{id}', ['\Xibo\Controller\Library','usage'])->setName('library.usage');
    $group->get('/library/usage/layouts/{id}', ['\Xibo\Controller\Library','usageLayouts'])
        ->setName('library.usage.layouts');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['library.view']));

$app->get('/library/download/{id}', ['\Xibo\Controller\Library', 'download'])->setName('library.download');
$app->get('/library/thumbnail/{id}', ['\Xibo\Controller\Library', 'thumbnail'])->setName('library.thumbnail');

$app->post('/library', ['\Xibo\Controller\Library','add'])->setName('library.add')
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['library.add', 'dashboard.playlist']));

$app->group('', function (RouteCollectorProxy $group) {
    //$group->map(['HEAD'],'/library', ['\Xibo\Controller\Library','  addgroup
    $group->post('/library/uploadUrl', ['\Xibo\Controller\Library','uploadFromUrl'])->setName('library.uploadFromUrl');
    $group->post('/library/thumbnail', ['\Xibo\Controller\Library','addThumbnail'])->setName('library.thumbnail.add');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['library.add']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/library/{id:[0-9]+}', ['\Xibo\Controller\Library','edit'])->setName('library.edit');
    $group->put('/library/setenablestat/{id}', ['\Xibo\Controller\Library','setEnableStat'])
        ->setName('library.setenablestat');
    $group->delete('/library/tidy', ['\Xibo\Controller\Library','tidy'])->setName('library.tidy');
    $group->delete('/library/{id:[0-9]+}', ['\Xibo\Controller\Library','delete'])->setName('library.delete');
    $group->post('/library/copy/{id}', ['\Xibo\Controller\Library','copy'])->setName('library.copy');
    $group->put('/library/{id}/selectfolder', ['\Xibo\Controller\Library','selectFolder'])
        ->setName('library.selectfolder');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['library.modify']));

// Tagging
$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/library/{id}/tag', ['\Xibo\Controller\Library','tag'])->setName('library.tag');
    $group->post('/library/{id}/untag', ['\Xibo\Controller\Library','untag'])->setName('library.untag');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['tag.tagging']));

/**
 * Displays
 */
$app->get('/display', ['\Xibo\Controller\Display', 'grid'])->setName('display.search');
$app->get('/display/locales', ['\Xibo\Controller\Display','getLocaleLanguages'])->setName('display.locales');
$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/display/requestscreenshot/{id:[0-9]+}', ['\Xibo\Controller\Display','requestScreenShot'])
        ->setName('display.requestscreenshot');
    $group->put('/display/licenceCheck/{id:[0-9]+}', ['\Xibo\Controller\Display','checkLicence'])
        ->setName('display.licencecheck');
    $group->put('/display/purgeAll/{id:[0-9]+}', ['\Xibo\Controller\Display','purgeAll'])
        ->setName('display.purge.all');
    $group->get('/display/screenshot/{id:[0-9]+}', ['\Xibo\Controller\Display','screenShot'])
        ->setName('display.screenShot');
    $group->get('/display/status/{id:[0-9]+}', ['\Xibo\Controller\Display','statusWindow'])
        ->setName('display.statusWindow');
    $group->get('/display/faults[/{displayId}]', ['\Xibo\Controller\PlayerFault','grid'])
        ->setName('display.faults.search');
    $group->get('/display/{id:[0-9]+}', ['\Xibo\Controller\Display', 'searchById'])->setName('display.search.id');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['displays.view']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/display/authorise/{id:[0-9]+}', ['\Xibo\Controller\Display','toggleAuthorise'])
        ->setName('display.authorise');
    $group->post('/display/addViaCode', ['\Xibo\Controller\Display','addViaCode'])->setName('display.addViaCode');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['displays.add']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/display/{id:[0-9]+}', ['\Xibo\Controller\Display','edit'])
        ->setName('display.edit');
    $group->delete('/display/{id:[0-9]+}', ['\Xibo\Controller\Display','delete'])
        ->setName('display.delete');
    $group->post('/display/wol/{id:[0-9]+}', ['\Xibo\Controller\Display','wakeOnLan'])
        ->setName('display.wol');
    $group->put('/display/setBandwidthLimit/multi', ['\Xibo\Controller\Display','setBandwidthLimitMultiple'])
        ->setName('display.setBandwidthLimitMultiple');
    $group->put('/display/defaultlayout/{id:[0-9]+}', ['\Xibo\Controller\Display','setDefaultLayout'])
        ->setName('display.defaultlayout');
    $group->post('/display/{id:[0-9]+}/displaygroup/assign', ['\Xibo\Controller\Display','assignDisplayGroup'])
        ->setName('display.assign.displayGroup');
    $group->put('/display/{id:[0-9]+}/moveCms', ['\Xibo\Controller\Display','moveCms'])
        ->setName('display.moveCms');
    $group->delete('/display/{id:[0-9]+}/moveCms', ['\Xibo\Controller\Display','moveCmsCancel'])
        ->setName('display.moveCmsCancel');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['displays.modify']));

/**
 * Display Groups
 */
$app->get('/displayvenue', ['\Xibo\Controller\Display','displayVenue'])->setName('display.venue.search');
$app->get('/displaygroup', ['\Xibo\Controller\DisplayGroup','grid'])->setName('displayGroup.search');
$app->get('/displaygroup/{id:[0-9]+}', ['\Xibo\Controller\DisplayGroup','searchById'])->setName('displayGroup.search.id');

$app->post('/displaygroup', ['\Xibo\Controller\DisplayGroup','add'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['displaygroup.add']))
    ->setName('displayGroup.add');
$app->post('/displaygroup/criteria/{displayGroupId:[0-9]+}', ['\Xibo\Controller\DisplayGroup','pushCriteriaUpdate'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['displaygroup.modify', 'displays.modify']))
    ->setName('displayGroup.criteria.push');

$app->post('/displaygroup/{id:[0-9]+}/action/collectNow', ['\Xibo\Controller\DisplayGroup','collectNow'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['displaygroup.view']))
    ->setName('displayGroup.action.collectNow');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/displaygroup/{id:[0-9]+}', ['\Xibo\Controller\DisplayGroup','edit'])->setName('displayGroup.edit');
    $group->delete('/displaygroup/{id:[0-9]+}', ['\Xibo\Controller\DisplayGroup','delete'])->setName('displayGroup.delete');
    // displays
    $group->get('/displaygroup/{id:[0-9]+}/displays', ['\Xibo\Controller\DisplayGroup','getDisplaysAssigned'])->setName('displayGroup.display.list');
    $group->post('/displaygroup/{id:[0-9]+}/display/assign', ['\Xibo\Controller\DisplayGroup','assignDisplay'])->setName('displayGroup.assign.display');
    $group->post('/displaygroup/{id:[0-9]+}/display/unassign', ['\Xibo\Controller\DisplayGroup','unassignDisplay'])->setName('displayGroup.unassign.display');
    // display groups
    $group->get('/displaygroup/{id:[0-9]+}/displayGroups', ['\Xibo\Controller\DisplayGroup','getDisplayGroupAssigned'])->setName('displayGroup.displayGroup.list');
    $group->post('/displaygroup/{id:[0-9]+}/displayGroup/assign', ['\Xibo\Controller\DisplayGroup','assignDisplayGroup'])->setName('displayGroup.assign.displayGroup');
    $group->post('/displaygroup/{id:[0-9]+}/displayGroup/unassign', ['\Xibo\Controller\DisplayGroup','unassignDisplayGroup'])->setName('displayGroup.unassign.displayGroup');
    // relationship tree
    $group->get('/displaygroup/{id:[0-9]+}/relationshiptree', ['\Xibo\Controller\DisplayGroup','getDisplayGroupRelationShipTree'])->setName('displayGroup.relationshipTree');
    // media
    $group->post('/displaygroup/{id:[0-9]+}/media/assign', ['\Xibo\Controller\DisplayGroup','assignMedia'])->setName('displayGroup.assign.media');
    $group->post('/displaygroup/{id:[0-9]+}/media/unassign', ['\Xibo\Controller\DisplayGroup','unassignMedia'])->setName('displayGroup.unassign.media');
    // layouts
    $group->get('/displaygroup/{id:[0-9]+}/layout', ['\Xibo\Controller\DisplayGroup','getDisplayGroupLayout'])->setName('displayGroup.layout.list');
    $group->post('/displaygroup/{id:[0-9]+}/layout/assign', ['\Xibo\Controller\DisplayGroup','assignLayouts'])->setName('displayGroup.assign.layout');
    $group->post('/displaygroup/{id:[0-9]+}/layout/unassign', ['\Xibo\Controller\DisplayGroup','unassignLayouts'])->setName('displayGroup.unassign.layout');
    // actions
    $group->post('/displaygroup/{id:[0-9]+}/action/changeLayout', ['\Xibo\Controller\DisplayGroup','changeLayout'])->setName('displayGroup.action.changeLayout');
    $group->post('/displaygroup/{id:[0-9]+}/action/overlayLayout', ['\Xibo\Controller\DisplayGroup','overlayLayout'])->setName('displayGroup.action.overlayLayout');
    $group->post('/displaygroup/{id:[0-9]+}/action/revertToSchedule', ['\Xibo\Controller\DisplayGroup','revertToSchedule'])->setName('displayGroup.action.revertToSchedule');
    $group->post('/displaygroup/{id:[0-9]+}/action/clearStatsAndLogs', ['\Xibo\Controller\DisplayGroup','clearStatsAndLogs'])->setName('displayGroup.action.clearStatsAndLogs');
    $group->post('/displaygroup/{id:[0-9]+}/action/triggerWebhook', ['\Xibo\Controller\DisplayGroup','triggerWebhook'])->setName('displayGroup.action.trigger.webhook');

    $group->post('/displaygroup/{id:[0-9]+}/copy', ['\Xibo\Controller\DisplayGroup','copy'])->setName('displayGroup.copy');
    $group->put('/displaygroup/{id:[0-9]+}/selectfolder', ['\Xibo\Controller\DisplayGroup','selectFolder'])->setName('displayGroup.selectfolder');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['displaygroup.modify']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/displaygroup/{id:[0-9]+}/action/command', ['\Xibo\Controller\DisplayGroup','command'])->setName('displayGroup.action.command');
    $group->get('/displaygroup/{id:[0-9]+}/action/command', ['\Xibo\Controller\DisplayGroup','getDisplayGroupCommands'])->setName('displayGroup.action.command.list');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['displaygroup.modify', 'command.view']));

/**
 * Display Profile
 */
$app->get('/displayprofile', ['\Xibo\Controller\DisplayProfile','grid'])->setName('displayProfile.search');
$app->get('/displayprofile/types', ['\Xibo\Controller\DisplayProfile','getDisplayProfileTypes'])
    ->setName('displayProfile.types');
$app->get('/displayprofile/{id:[0-9]+}', ['\Xibo\Controller\DisplayProfile','searchById'])
    ->setName('displayProfile.search.id');

$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/displayprofile', ['\Xibo\Controller\DisplayProfile','add'])->setName('displayProfile.add');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['displayprofile.add']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/displayprofile/{id:[0-9]+}', ['\Xibo\Controller\DisplayProfile','edit'])->setName('displayProfile.edit');
    $group->delete('/displayprofile/{id:[0-9]+}', ['\Xibo\Controller\DisplayProfile','delete'])->setName('displayProfile.delete');
    $group->post('/displayprofile/{id:[0-9]+}/copy', ['\Xibo\Controller\DisplayProfile','copy'])->setName('displayProfile.copy');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['displayprofile.modify']));

/**
 * DataSet
 */
$app->get('/dataset', ['\Xibo\Controller\DataSet','grid'])->setName('dataSet.search');
$app->get('/dataset/{id:[0-9]+}', ['\Xibo\Controller\DataSet','searchById'])->setName('dataSet.search.id');
$app->post('/dataset', ['\Xibo\Controller\DataSet','add'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['dataset.add']))
    ->setName('dataSet.add');
$app->get('/rss/{psk}', ['\Xibo\Controller\DataSetRss','feed'])->setName('dataSet.rss.feed');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/dataset/{id:[0-9]+}', ['\Xibo\Controller\DataSet','edit'])->setName('dataSet.edit');
    $group->delete('/dataset/{id:[0-9]+}', ['\Xibo\Controller\DataSet','delete'])->setName('dataSet.delete');
    $group->put('/dataset/{id:[0-9]+}/selectfolder', ['\Xibo\Controller\DataSet', 'selectFolder'])->setName('dataSet.selectfolder');

    $group->post('/dataset/copy/{id:[0-9]+}', ['\Xibo\Controller\DataSet','copy'])->setName('dataSet.copy');
    $group->post('/dataset/clearcache/{id:[0-9]+}', ['\Xibo\Controller\DataSet','clearCache'])
        ->setName('dataSet.clearcache');
    //$group->map(['HEAD'],'/dataset/import/{id}', ['\Xibo\Controller\DataSet','import');
    $group->post('/dataset/import/{id:[0-9]+}', ['\Xibo\Controller\DataSet','import'])->setName('dataSet.import');
    $group->post('/dataset/importjson/{id:[0-9]+}', ['\Xibo\Controller\DataSet','importJson'])->setName('dataSet.import.json');
    $group->post('/dataset/remote/test', ['\Xibo\Controller\DataSet','testRemoteRequest'])->setName('dataSet.test.remote');
    $group->get('/dataset/dataConnector/{id:[0-9]+}/script', ['\Xibo\Controller\DataSet', 'getDataConnectorScript'])
        ->setName('dataSet.dataConnector.script.get');
    $group->put('/dataset/dataConnector/{id:[0-9]+}', ['\Xibo\Controller\DataSet','updateDataConnector'])->setName('dataSet.dataConnector.update');
    $group->get('/dataset/export/csv/{id:[0-9]+}', ['\Xibo\Controller\DataSet', 'exportToCsv'])->setName('dataSet.export.csv');

    // Columns
    $group->get('/dataset/column/types', ['\Xibo\Controller\DataSetColumn','getDataSetColumnTypes'])
        ->setName('dataSet.column.types');
    $group->get('/dataset/column/datatypes', ['\Xibo\Controller\DataSetColumn','getDataTypes'])
        ->setName('dataSet.column.datatypes');
    $group->get('/dataset/column/{colId:[0-9]+}', ['\Xibo\Controller\DataSetColumn','searchById'])
        ->setName('dataSet.column.search.id');
    $group->get('/dataset/{id:[0-9]+}/column', ['\Xibo\Controller\DataSetColumn','grid'])->setName('dataSet.column.search');
    $group->post('/dataset/{id:[0-9]+}/column', ['\Xibo\Controller\DataSetColumn','add'])->setName('dataSet.column.add');
    $group->put('/dataset/{id:[0-9]+}/column/{colId:[0-9]+}', ['\Xibo\Controller\DataSetColumn','edit'])->setName('dataSet.column.edit');
    $group->delete('/dataset/{id:[0-9]+}/column/{colId:[0-9]+}', ['\Xibo\Controller\DataSetColumn','delete'])->setName('dataSet.column.delete');

    // RSS
    $group->get('/dataset/{id:[0-9]+}/rss/{rssId:[0-9]+}', ['\Xibo\Controller\DataSetRss','searchById'])
        ->setName('dataSet.rss.search.id');
    $group->get('/dataset/{id:[0-9]+}/rss', ['\Xibo\Controller\DataSetRss','grid'])->setName('dataSet.rss.search');
    $group->post('/dataset/{id:[0-9]+}/rss', ['\Xibo\Controller\DataSetRss','add'])->setName('dataSet.rss.add');
    $group->put('/dataset/{id:[0-9]+}/rss/{rssId:[0-9]+}', ['\Xibo\Controller\DataSetRss','edit'])
        ->setName('dataSet.rss.edit');
    $group->delete('/dataset/{id:[0-9]+}/rss/{rssId:[0-9]+}', ['\Xibo\Controller\DataSetRss','delete'])
        ->setName('dataSet.rss.delete');

    // Data connector sources
    $group->get('/dataset/dataconnector/source', ['\Xibo\Controller\DataSet', 'dataConnectorSource'])
        ->setName('dataSet.dataconnector.source');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['dataset.modify']));

// Data
$app->get('/dataset/data/{id:[0-9]+}', ['\Xibo\Controller\DataSetData','grid'])->setName('dataSet.data.search');
$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/dataset/data/{id:[0-9]+}', ['\Xibo\Controller\DataSetData','add'])->setName('dataSet.data.add');
    $group->put('/dataset/data/{id:[0-9]+}/{rowId:[0-9]+}', ['\Xibo\Controller\DataSetData','edit'])->setName('dataSet.data.edit');
    $group->delete('/dataset/data/{id:[0-9]+}/{rowId:[0-9]+}', ['\Xibo\Controller\DataSetData','delete'])->setName('dataSet.data.delete');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['dataset.data']));

/**
 * Folders
 */
$app->get('/folders[/{folderId}]', ['\Xibo\Controller\Folder', 'grid'])->setName('folders.search');
$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/folders/contextButtons/{folderId}', ['\Xibo\Controller\Folder', 'getContextMenuButtons'])
        ->setName('folders.context.buttons');
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
 */
$app->get('/stats', ['\Xibo\Controller\Stats','grid'])->setName('stats.search');

$app->get('/stats/timeDisconnected', ['\Xibo\Controller\Stats', 'gridTimeDisconnected'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['displays.reporting']))
    ->setName('stats.timeDisconnected.search');

$app->get('/stats/export', ['\Xibo\Controller\Stats','export'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['proof-of-play']))
    ->setName('stats.export');

// Log (no APIs)
// -------------
$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/log', ['\Xibo\Controller\Logging', 'grid'])->setName('log.search');
    $group->get('/log/{id:[0-9]+}', ['\Xibo\Controller\Logging', 'searchById'])->setName('log.search.id');
    $group->delete('/log', ['\Xibo\Controller\Logging', 'truncate'])->setName('log.truncate');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['log.view']));

/**
 * User
 */
$app->get('/user/pref', ['\Xibo\Controller\User' , 'pref'])->setName('user.pref');
$app->post('/user/pref', ['\Xibo\Controller\User' ,'prefEdit']);
$app->put('/user/pref', ['\Xibo\Controller\User' ,'prefEditFromForm']);
$app->get('/user/me', ['\Xibo\Controller\User','myDetails'])->setName('user.me');
$app->get('/help/page-links', ['\Xibo\Controller\Help','pageLinks'])->setName('help.pageLinks');
$app->get('/user/types', ['\Xibo\Controller\User','getUserTypes'])->setName('user.types');
$app->get('/user', ['\Xibo\Controller\User','grid'])->setName('user.search');
$app->get('/user/{id:[0-9]+}/applications', ['\Xibo\Controller\User', 'applicationsGrid'])->setName('user.applications');
$app->get('/user/{id:[0-9]+}', ['\Xibo\Controller\User','searchById'])->setName('user.search.id');
$app->put('/user/profile/edit', ['\Xibo\Controller\User','editProfile'])->setName('user.edit.profile');
$app->get('/user/profile/setup', ['\Xibo\Controller\User','tfaSetup'])->setName('user.setup.profile');
$app->post('/user/profile/validate', ['\Xibo\Controller\User','tfaValidate'])->setName('user.validate.profile');
$app->get('/user/profile/recoveryGenerate', ['\Xibo\Controller\User','tfaRecoveryGenerate'])
    ->setName('user.recovery.generate.profile');
$app->get('/user/profile/recoveryShow', ['\Xibo\Controller\User','tfaRecoveryShow'])
    ->setName('user.recovery.show.profile');
$app->put('/user/password/forceChange', ['\Xibo\Controller\User','forceChangePassword'])
    ->setName('user.force.change.password');

// permissions
$app->get('/user/permissions/{entity}/{id:[0-9]+}', ['\Xibo\Controller\User','permissionsGrid'])
    ->setName('user.permissions');
$app->get('/user/permissions/{entity}', ['\Xibo\Controller\User','permissionsMultiGrid'])
    ->setName('user.permissions.multi');
$app->post('/user/permissions/{entity}/{id:[0-9]+}', ['\Xibo\Controller\User','permissions'])
    ->setName('user.set.permissions');
$app->post('/user/permissions/{entity}', ['\Xibo\Controller\User','permissionsMulti'])
    ->setName('user.set.permissions.multi');

$app->post('/user', ['\Xibo\Controller\User','add'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['users.add']))
    ->setName('user.add');

// Welcome wizard state
$app->put('/user/welcome', ['\Xibo\Controller\User','userWelcomeSetSeen'])->setName('welcome.wizard.seen');
$app->post('/user/welcome', ['\Xibo\Controller\User','userWelcomeSetUnseen'])->setName('welcome.wizard.unseen');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/user/{id:[0-9]+}', ['\Xibo\Controller\User','edit'])->setName('user.edit');
    $group->delete('/user/{id:[0-9]+}', ['\Xibo\Controller\User','delete'])->setName('user.delete');
    $group->post('/user/{id:[0-9]+}/usergroup/assign', ['\Xibo\Controller\User','assignUserGroup'])
        ->setName('user.assign.userGroup');
    $group->post('/user/{id:[0-9]+}/setHomeFolder', ['\Xibo\Controller\User', 'setHomeFolder'])
        ->addMiddleware(new FeatureAuth($group->getContainer(), ['folder.userHome']))
        ->setName('user.homeFolder');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['users.modify']));

/**
 * User Group
 */
$app->get('/group', ['\Xibo\Controller\UserGroup','grid'])->setName('group.search');
$app->get('/group/{id:[0-9]+}', ['\Xibo\Controller\UserGroup','searchById'])->setName('group.search.id');
$app->post('/group', ['\Xibo\Controller\UserGroup','add'])->setName('group.add');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/group/{id:[0-9]+}', ['\Xibo\Controller\UserGroup','edit'])->setName('group.edit');
    $group->delete('/group/{id:[0-9]+}', ['\Xibo\Controller\UserGroup','delete'])->setName('group.delete');
    $group->post('/group/{id:[0-9]+}/copy', ['\Xibo\Controller\UserGroup','copy'])->setName('group.copy');

    $group->post(
        '/group/members/assign/{id:[0-9]+}',
        ['\Xibo\Controller\UserGroup','assignUser']
    )->setName('group.members.assign');
    $group->post(
        '/group/members/unassign/{id:[0-9]+}',
        ['\Xibo\Controller\UserGroup','unassignUser']
    )->setName('group.members.unassign');

    $group->post('/group/acl/{id:[0-9]+}', ['\Xibo\Controller\UserGroup','acl'])->setName('group.acl');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['usergroup.modify']));

//
// Applications
//
$app->delete('/application/revoke/{id}/{userId}', ['\Xibo\Controller\Applications', 'revokeAccess'])
    ->setName('application.revoke');

/**
 * Modules
 */
$app->get('/module', ['\Xibo\Controller\Module','grid'])->setName('module.search');
$app->get('/module/library', ['\Xibo\Controller\Module','getLibraryModules'])->setName('module.library.list');
$app->get('/module/{id}', ['\Xibo\Controller\Module','searchById'])->setName('module.search.id');

$app->get('/module/templates/{dataType}', [
    '\Xibo\Controller\Module', 'templateGrid'
])->setName('module.template.search');

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
$app->get('/transition/{id:[0-9]+}', ['\Xibo\Controller\Transition','searchById'])->setName('transition.search.id');
$app->put('/transition/{id:[0-9]+}', ['\Xibo\Controller\Transition','edit'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['transition.view']))
    ->setName('transition.edit');

//
// Sessions
//
$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/sessions', ['\Xibo\Controller\Sessions','grid'])->setName('sessions.search');
    $group->delete('/sessions/logout/{id:[0-9]+}', ['\Xibo\Controller\Sessions','logout'])->setName('sessions.confirm.logout');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['session.view']));

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
    $group->get('/audit/{id:[0-9]+}', ['\Xibo\Controller\AuditLog','searchById'])->setName('auditLog.searchById');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['auditlog.view']));

/**
 * Commands
 */
$app->get('/command', ['\Xibo\Controller\Command','grid'])->setName('command.search');
$app->get('/command/{id:[0-9]+}', ['\Xibo\Controller\Command','searchById'])->setName('command.search.id');
$app->post('/command', ['\Xibo\Controller\Command','add'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['command.add']))
    ->setName('command.add');
$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/command/{id:[0-9]+}', ['\Xibo\Controller\Command','edit'])->setName('command.edit');
    $group->delete('/command/{id:[0-9]+}', ['\Xibo\Controller\Command','delete'])->setName('command.delete');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['command.modify']));

/**
 * Dayparts
 */
$app->get('/daypart', ['\Xibo\Controller\DayPart','grid'])->setName('daypart.search');
$app->get('/daypart/{id:[0-9]+}', ['\Xibo\Controller\DayPart','searchById'])->setName('daypart.search.id');
$app->post('/daypart', ['\Xibo\Controller\DayPart','add'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['daypart.add']))
    ->setName('daypart.add');
$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/daypart/{id:[0-9]+}', ['\Xibo\Controller\DayPart','edit'])->setName('daypart.edit');
    $group->delete('/daypart/{id:[0-9]+}', ['\Xibo\Controller\DayPart','delete'])->setName('daypart.delete');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['daypart.modify']));

// Tasks (no APIs)
// ----
$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/task', ['\Xibo\Controller\Task', 'grid'])->setName('task.search');
    $group->get('/task/list', ['\Xibo\Controller\Task', 'getTaskList'])->setName('task.list');
    $group->get('/task/{id:[0-9]+}', ['\Xibo\Controller\Task', 'searchById'])->setName('task.search.id');
    $group->post('/task', ['\Xibo\Controller\Task', 'add'])->setName('task.add');
    $group->put('/task/{id:[0-9]+}', ['\Xibo\Controller\Task', 'edit'])->setName('task.edit');
    $group->delete('/task/{id:[0-9]+}', ['\Xibo\Controller\Task', 'delete'])->setName('task.delete');
    $group->post('/task/{id:[0-9]+}/run', ['\Xibo\Controller\Task', 'runNow'])->setName('task.runNow');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['task.view']));

// Report schedule (no APIs)
// -------------------------
// TODO consider moving those to json routes.
$app->get('/report/reportschedule', ['\Xibo\Controller\ScheduleReport','reportScheduleGrid'])
    ->setName('reportschedule.search');
$app->get('/report/reportschedule/{id:[0-9]+}', ['\Xibo\Controller\ScheduleReport','searchById'])
    ->setName('reportschedule.search.id');
$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/report/reportschedule', ['\Xibo\Controller\ScheduleReport','reportScheduleAdd'])
        ->setName('reportschedule.add');
    $group->put('/report/reportschedule/{id:[0-9]+}', ['\Xibo\Controller\ScheduleReport','reportScheduleEdit'])
        ->setName('reportschedule.edit');
    $group->delete('/report/reportschedule/{id:[0-9]+}', ['\Xibo\Controller\ScheduleReport','reportScheduleDelete'])
        ->setName('reportschedule.delete');
    $group->post(
        '/report/reportschedule/{id:[0-9]+}/deletesavedreport',
        ['\Xibo\Controller\ScheduleReport','reportScheduleDeleteAllSavedReport']
    )->setName('reportschedule.deleteall');
    $group->post(
        '/report/reportschedule/{id:[0-9]+}/toggleactive',
        ['\Xibo\Controller\ScheduleReport','reportScheduleToggleActive']
    )->setName('reportschedule.toggleactive');
    $group->post('/report/reportschedule/{id:[0-9]+}/reset', ['\Xibo\Controller\ScheduleReport','reportScheduleReset'])
        ->setName('reportschedule.reset');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['report.scheduling']));

//
// Saved reports
//
$app->get('/report/savedreport', ['\Xibo\Controller\SavedReport','savedReportGrid'])
    ->setName('savedreport.search');
$app->get('/report/savedreport/{id:[0-9]+}', ['\Xibo\Controller\SavedReport','searchById'])
    ->setName('savedreport.search.id');
$app->delete('/report/savedreport/{id:[0-9]+}', ['\Xibo\Controller\SavedReport','savedReportDelete'])
    ->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['report.saving']))
    ->setName('savedreport.delete');

/**
 * Player Versions
 */
$app->get('/playersoftware', ['\Xibo\Controller\PlayerSoftware','grid'])
    ->setName('playersoftware.search');
$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/playersoftware/meta', ['\Xibo\Controller\PlayerSoftware', 'metaData'])
        ->setName('playersoftware.meta');
    $group->get('/playersoftware/download/{id:[0-9]+}', ['\Xibo\Controller\PlayerSoftware', 'download'])
        ->setName('playersoftware.download');
    $group->get('/playersoftware/{id:[0-9]+}', ['\Xibo\Controller\PlayerSoftware', 'searchById'])
        ->setName('playersoftware.search.id');
    $group->post('/playersoftware', ['\Xibo\Controller\PlayerSoftware','add'])
        ->setName('playersoftware.add');
    $group->put('/playersoftware/{id:[0-9]+}', ['\Xibo\Controller\PlayerSoftware','edit'])
        ->setName('playersoftware.edit');
    $group->delete('/playersoftware/{id:[0-9]+}', ['\Xibo\Controller\PlayerSoftware','delete'])
        ->setName('playersoftware.delete');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['playersoftware.view']));

// Install
$app->get('/sssp_config.xml', ['\Xibo\Controller\PlayerSoftware','getSsspInstall'])->setName('playersoftware.sssp.install');
$app->get('/sssp_dl.wgt', ['\Xibo\Controller\PlayerSoftware','getSsspInstallDownload'])->setName('playersoftware.sssp.install.download');
$app->get('/playersoftware/{nonce}/sssp_config.xml', ['\Xibo\Controller\PlayerSoftware','getSssp'])->setName('playersoftware.sssp');
$app->get('/playersoftware/{nonce}/sssp_dl.wgt', ['\Xibo\Controller\PlayerSoftware','getVersionFile'])->setName('playersoftware.version.file');

/**
 * Tags
 */
$app->get('/tag', ['\Xibo\Controller\Tag','grid'])->setName('tag.search');
$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/tag', ['\Xibo\Controller\Tag','add'])->setName('tag.add');
    $group->put('/tag/{id:[0-9]+}', ['\Xibo\Controller\Tag','edit'])->setName('tag.edit');
    $group->delete('/tag/{id:[0-9]+}', ['\Xibo\Controller\Tag','delete'])->setName('tag.delete');
    $group->get('/tag/name', ['\Xibo\Controller\Tag','loadTagOptions'])->setName('tag.getByName');
    $group->put('/tag/{type}/multi', ['\Xibo\Controller\Tag','editMultiple'])->setName('tag.editMultiple');
    $group->get('/tag/usage/{id:[0-9]+}', ['\Xibo\Controller\Tag', 'usage'])->setName('tag.usage');
})->addMiddleware(new \Xibo\Middleware\FeatureAuth($app->getContainer(), ['tag.view']));
$app->get('/tag/{id:[0-9]+}', ['\Xibo\Controller\Tag','searchById'])->setName('tag.search.id');

// Actions (no APIs)
// -----------------
$app->get('/action', ['\Xibo\Controller\Action', 'grid'])->setName('action.search');
$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/action', ['\Xibo\Controller\Action', 'add'])->setName('action.add');
    $group->put('/action/{id:[0-9]+}', ['\Xibo\Controller\Action', 'edit'])->setName('action.edit');
    $group->delete('/action/{id:[0-9]+}', ['\Xibo\Controller\Action', 'delete'])->setName('action.delete');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['layout.modify', 'playlist.modify']));

/**
 * Menu Boards
 */
$app->get('/menuboards', ['\Xibo\Controller\MenuBoard', 'grid'])->setName('menuBoard.search');
$app->post('/menuboard', ['\Xibo\Controller\MenuBoard', 'add'])
    ->addMiddleware(new FeatureAuth($app->getContainer(), ['menuBoard.add']))
    ->setName('menuBoard.add');

$app->group('', function (RouteCollectorProxy $group) {
    $group->put('/menuboard/{id:[0-9]+}', ['\Xibo\Controller\MenuBoard', 'edit'])->setName('menuBoard.edit');
    $group->delete('/menuboard/{id:[0-9]+}', ['\Xibo\Controller\MenuBoard', 'delete'])->setName('menuBoard.delete');
    $group->put('/menuboard/{id:[0-9]+}/selectfolder', ['\Xibo\Controller\MenuBoard', 'selectFolder'])
        ->setName('menuBoard.selectfolder');
    $group->post('/menuboard/copy/{id:[0-9]+}', ['\Xibo\Controller\MenuBoard', 'copy'])->setName('menuBoard.copy');
    $group->post('/menuboard/category/copy/{id:[0-9]+}', ['\Xibo\Controller\MenuBoardCategory', 'copy'])
        ->setName('menuBoard.category.copy');

    $group->get('/menuboard/{id:[0-9]+}/categories', ['\Xibo\Controller\MenuBoardCategory', 'grid'])
        ->setName('menuBoard.category.search');
    $group->get('/menuboard/category/{id:[0-9]+}', ['\Xibo\Controller\MenuBoardCategory', 'searchById'])
        ->setName('menuBoard.category.search.id');
    $group->post('/menuboard/{id:[0-9]+}/category', ['\Xibo\Controller\MenuBoardCategory', 'add'])
        ->setName('menuBoard.category.add');
    $group->put('/menuboard/{id:[0-9]+}/category', ['\Xibo\Controller\MenuBoardCategory', 'edit'])
        ->setName('menuBoard.category.edit');
    $group->delete('/menuboard/{id:[0-9]+}/category', ['\Xibo\Controller\MenuBoardCategory', 'delete'])
        ->setName('menuBoard.category.delete');

    $group->get('/menuboard/{id:[0-9]+}/products', ['\Xibo\Controller\MenuBoardProduct', 'grid'])
        ->setName('menuBoard.product.search');
    $group->get('/menuboard/product/{id:[0-9]+}', ['\Xibo\Controller\MenuBoardProduct', 'searchById'])
        ->setName('menuBoard.product.search.id');
    $group->get('/menuboard/products', ['\Xibo\Controller\MenuBoardProduct', 'productsForWidget'])
        ->setName('menuBoard.product.search.widget');
    $group->post('/menuboard/{id:[0-9]+}/product', ['\Xibo\Controller\MenuBoardProduct', 'add'])
        ->setName('menuBoard.product.add');
    $group->put('/menuboard/{id:[0-9]+}/product', ['\Xibo\Controller\MenuBoardProduct', 'edit'])
        ->setName('menuBoard.product.edit');
    $group->delete('/menuboard/{id:[0-9]+}/product', ['\Xibo\Controller\MenuBoardProduct', 'delete'])
        ->setName('menuBoard.product.delete');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['menuBoard.modify']));

$app->get('/menuboard/{id:[0-9]+}', ['\Xibo\Controller\MenuBoard', 'searchById'])->setName('menuBoard.search.id');

/**
 * Fonts
 */
$app->get('/fonts', ['\Xibo\Controller\Font', 'grid'])->setName('font.search');
$app->get('/fonts/{id:[0-9]+}', ['\Xibo\Controller\Font', 'searchById'])->setName('font.search.id');

$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/fonts/details/{id:[0-9]+}', ['\Xibo\Controller\Font', 'getFontLibDetails'])->setName('font.details');
    $group->get('/fonts/download/{id:[0-9]+}', ['\Xibo\Controller\Font', 'download'])->setName('font.download');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['font.view']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/fonts', ['\Xibo\Controller\Font','add'])->setName('font.add');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['font.add']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->delete('/fonts/{id}/delete', ['\Xibo\Controller\Font','delete'])->setName('font.delete');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['font.delete']));

$app->get('/syncgroups', ['\Xibo\Controller\SyncGroup', 'grid'])->setName('syncgroup.search');
$app->get('/syncgroup/{id:[0-9]+}', ['\Xibo\Controller\SyncGroup', 'searchById'])->setName('syncgroup.search.id');
$app->group('', function (RouteCollectorProxy $group) {
    $group->get('/syncgroup/{id:[0-9]+}/displays', ['\Xibo\Controller\SyncGroup', 'fetchDisplays'])
        ->setName('syncgroup.fetch.displays');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['display.syncView']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/syncgroup/add', ['\Xibo\Controller\SyncGroup', 'add'])->setName('syncgroup.add');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['display.syncAdd']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/syncgroup/{id:[0-9]+}/members', ['\Xibo\Controller\SyncGroup', 'members'])->setName('syncgroup.members');
    $group->put('/syncgroup/{id:[0-9]+}/edit', ['\Xibo\Controller\SyncGroup', 'edit'])->setName('syncgroup.edit');
    $group->delete('/syncgroup/{id:[0-9]+}/delete', ['\Xibo\Controller\SyncGroup', 'delete'])->setName('syncgroup.delete');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['display.syncModify']));

$app->group('', function (RouteCollectorProxy $group) {
    $group->post('/schedule/sync/add', ['\Xibo\Controller\Schedule', 'syncAdd'])->setName('schedule.add.sync');
})->addMiddleware(new FeatureAuth($app->getContainer(), ['schedule.sync']));
