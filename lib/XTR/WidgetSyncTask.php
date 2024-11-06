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

namespace Xibo\XTR;

use Carbon\Carbon;
use Xibo\Entity\Display;
use Xibo\Entity\Module;
use Xibo\Entity\Widget;
use Xibo\Event\WidgetDataRequestEvent;
use Xibo\Factory\WidgetDataFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Widget\Provider\WidgetProviderInterface;

/**
 * Class WidgetSyncTask
 * Keep all widgets which have data up to date
 * @package Xibo\XTR
 */
class WidgetSyncTask implements TaskInterface
{
    use TaskTrait;

    /** @var \Xibo\Factory\ModuleFactory */
    private $moduleFactory;

    /** @var \Xibo\Factory\WidgetFactory */
    private $widgetFactory;

    /** @var \Xibo\Factory\WidgetDataFactory */
    private WidgetDataFactory $widgetDataFactory;

    /** @var \Xibo\Factory\MediaFactory */
    private $mediaFactory;

    /** @var \Xibo\Factory\DisplayFactory */
    private $displayFactory;

    /** @var \Symfony\Component\EventDispatcher\EventDispatcher */
    private $eventDispatcher;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->moduleFactory = $container->get('moduleFactory');
        $this->widgetFactory = $container->get('widgetFactory');
        $this->widgetDataFactory = $container->get('widgetDataFactory');
        $this->mediaFactory = $container->get('mediaFactory');
        $this->displayFactory = $container->get('displayFactory');
        $this->eventDispatcher = $container->get('dispatcher');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        // Track the total time we've spent caching
        $timeCaching = 0.0;
        $countWidgets = 0;
        $cutOff = Carbon::now()->subHours(2);

        // Update for widgets which are active on displays, or for displays which have been active recently.
        $sql = '
          SELECT DISTINCT `requiredfile`.itemId, `requiredfile`.complete 
            FROM `requiredfile` 
              INNER JOIN `display`
              ON `display`.displayId = `requiredfile`.displayId
           WHERE `requiredfile`.type = \'D\' 
              AND (`display`.loggedIn = 1 OR `display`.lastAccessed > :lastAccessed)
          ORDER BY `requiredfile`.complete DESC, `requiredfile`.itemId
        ';

        $smt = $this->store->getConnection()->prepare($sql);
        $smt->execute(['lastAccessed' => $cutOff->unix()]);

        $row = true;
        while ($row) {
            $row = $smt->fetch(\PDO::FETCH_ASSOC);
            try {
                if ($row !== false) {
                    $widgetId = (int)$row['itemId'];

                    $this->getLogger()->debug('widgetSyncTask: processing itemId ' . $widgetId);

                    // What type of widget do we have here.
                    $widget = $this->widgetFactory->getById($widgetId)->load();

                    // Get the module
                    $module = $this->moduleFactory->getByType($widget->type);

                    // If this widget's module expects data to be provided (i.e. has a datatype) then make sure that
                    // data is cached ahead of time here.
                    // This also refreshes any library or external images referenced by the data so that they aren't
                    // considered for removal.
                    if ($module->isDataProviderExpected()) {
                        $this->getLogger()->debug('widgetSyncTask: data provider expected.');

                        // Record start time
                        $countWidgets++;
                        $startTime = microtime(true);

                        // Grab a widget interface, if there is one
                        $widgetInterface = $module->getWidgetProviderOrNull();

                        // Is the cache key display specific?
                        $cacheKey = $widgetInterface?->getDataCacheKey($module->createDataProvider($widget));
                        if ($cacheKey === null) {
                            $cacheKey = $module->dataCacheKey;
                        }

                        // Refresh the cache if needed.
                        $isDisplaySpecific = str_contains($cacheKey, '%displayId%')
                            || str_contains($cacheKey, '%useDisplayLocation%');

                        // We're either assigning all media to all displays, or we're assigning then one by one
                        if ($isDisplaySpecific) {
                            $this->getLogger()->debug('widgetSyncTask: cache is display specific');

                            // We need to run the cache for every display this widget is assigned to.
                            foreach ($this->getDisplays($widget) as $display) {
                                $mediaIds = $this->cache(
                                    $module,
                                    $widget,
                                    $widgetInterface,
                                    $display,
                                );
                                $this->linkDisplays($widget->widgetId, [$display], $mediaIds);
                            }
                        } else {
                            $this->getLogger()->debug('widgetSyncTask: cache is not display specific');

                            // Just a single run will do it.
                            $mediaIds = $this->cache($module, $widget, $widgetInterface, null);
                            $this->linkDisplays($widget->widgetId, $this->getDisplays($widget), $mediaIds);
                        }

                        // Record end time and aggregate for final total
                        $duration = (microtime(true) - $startTime);
                        $timeCaching = $timeCaching + $duration;
                        $this->log->debug('widgetSyncTask: Took ' . $duration
                            . ' seconds to check and/or cache widgetId ' . $widget->widgetId);

                        // Commit so that any images we've downloaded have their cache times updated for the
                        // next request, this makes sense because we've got a file cache that is already written
                        // out.
                        $this->store->commitIfNecessary();
                    }
                }
            } catch (GeneralException $xiboException) {
                $this->log->debug($xiboException->getTraceAsString());
                $this->log->error('widgetSyncTask: Cannot process widget ' . $widgetId
                    . ', E = ' . $xiboException->getMessage());
            }
        }

        // Remove display_media records which have not been touched for a defined period of time.
        $this->removeOldDisplayLinks($cutOff);

        $this->log->info('Total time spent caching is ' . $timeCaching . ', synced ' . $countWidgets . ' widgets');

        $this->appendRunMessage('Synced ' . $countWidgets . ' widgets');
    }

    /**
     * @return int[] mediaIds
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function cache(
        Module $module,
        Widget $widget,
        ?WidgetProviderInterface $widgetInterface,
        ?Display $display
    ): array {
        $this->getLogger()->debug('cache: ' . $widget->widgetId . ' for display: ' . ($display?->displayId ?? 0));

        // Each time we call this we use a new provider
        $dataProvider = $module->createDataProvider($widget);
        $dataProvider->setMediaFactory($this->mediaFactory);

        // Set our provider up for the display
        $dataProvider->setDisplayProperties(
            $display?->latitude ?: $this->getConfig()->getSetting('DEFAULT_LAT'),
            $display?->longitude ?: $this->getConfig()->getSetting('DEFAULT_LONG'),
            $display?->displayId ?? 0
        );

        $widgetDataProviderCache = $this->moduleFactory->createWidgetDataProviderCache();

        // Get the cache key
        $cacheKey = $this->moduleFactory->determineCacheKey(
            $module,
            $widget,
            $display?->displayId ?? 0,
            $dataProvider,
            $widgetInterface
        );

        // Get the data modified date
        $dataModifiedDt = null;
        if ($widgetInterface !== null) {
            $dataModifiedDt = $widgetInterface->getDataModifiedDt($dataProvider);

            if ($dataModifiedDt !== null) {
                $this->getLogger()->debug('cache: data modifiedDt is ' . $dataModifiedDt->toAtomString());
            }
        }

        // Will we use fallback data if available?
        $showFallback = $widget->getOptionValue('showFallback', 'never');
        if ($showFallback !== 'never') {
            // What data type are we dealing with?
            try {
                $dataTypeFields = [];
                foreach ($this->moduleFactory->getDataTypeById($module->dataType)->fields as $field) {
                    $dataTypeFields[$field->id] = $field->type;
                }

                // Potentially we will, so get the modifiedDt of this fallback data.
                $fallbackModifiedDt = $this->widgetDataFactory->getModifiedDtForWidget($widget->widgetId);

                if ($fallbackModifiedDt !== null) {
                    $this->getLogger()->debug('cache: fallback modifiedDt is ' . $fallbackModifiedDt->toAtomString());

                    $dataModifiedDt = max($dataModifiedDt, $fallbackModifiedDt);
                }
            } catch (NotFoundException) {
                $this->getLogger()->info('cache: widget will fallback set where the module does not support it');
                $dataTypeFields = null;
            }
        } else {
            $dataTypeFields = null;
        }

        if (!$widgetDataProviderCache->decorateWithCache($dataProvider, $cacheKey, $dataModifiedDt)
            || $widgetDataProviderCache->isCacheMissOrOld()
        ) {
            $this->getLogger()->debug('cache: Cache expired, pulling fresh: key: ' . $cacheKey);

            $dataProvider->clearData();
            $dataProvider->clearMeta();
            $dataProvider->addOrUpdateMeta('showFallback', $showFallback);

            try {
                if ($widgetInterface !== null) {
                    $widgetInterface->fetchData($dataProvider);
                } else {
                    $dataProvider->setIsUseEvent();
                }

                if ($dataProvider->isUseEvent()) {
                    $this->getDispatcher()->dispatch(
                        new WidgetDataRequestEvent($dataProvider),
                        WidgetDataRequestEvent::$NAME
                    );
                }

                // Before caching images, check to see if the data provider is handled
                $isFallback = false;
                if ($showFallback !== 'never'
                    && $dataTypeFields !== null
                    && (
                        count($dataProvider->getErrors()) > 0
                        || count($dataProvider->getData()) <= 0
                        || $showFallback === 'always'
                    )
                ) {
                    // Error or no data.
                    // Pull in the fallback data
                    foreach ($this->widgetDataFactory->getByWidgetId($dataProvider->getWidgetId()) as $item) {
                        // Handle any special data types in the fallback data
                        foreach ($item->data as $itemId => $itemData) {
                            if (!empty($itemData)
                                && array_key_exists($itemId, $dataTypeFields)
                                && $dataTypeFields[$itemId] === 'image'
                            ) {
                                $item->data[$itemId] = $dataProvider->addLibraryFile($itemData);
                            }
                        }

                        $dataProvider->addItem($item->data);

                        // Indicate we've been handled by fallback data
                        $isFallback = true;
                    }

                    if ($isFallback) {
                        $dataProvider->addOrUpdateMeta('includesFallback', true);
                    }
                }

                // Remove fallback data from the cache if no-longer needed
                if (!$isFallback) {
                    $dataProvider->addOrUpdateMeta('includesFallback', false);
                }

                // Do we have images?
                // They could be library images (i.e. they already exist) or downloads
                $mediaIds = $dataProvider->getImageIds();
                if (count($mediaIds) > 0) {
                    // Process the downloads.
                    $this->mediaFactory->processDownloads(function ($media) {
                        /** @var \Xibo\Entity\Media $media */
                        // Success
                        // We don't need to do anything else, references to mediaId will be built when we decorate
                        // the HTML.
                        $this->getLogger()->debug('cache: Successfully downloaded ' . $media->mediaId);
                    }, function ($media) use (&$mediaIds) {
                        /** @var \Xibo\Entity\Media $media */
                        // Error
                        // Remove it
                        unset($mediaIds[$media->mediaId]);
                    });
                }

                // Save to cache
                if ($dataProvider->isHandled() || $isFallback) {
                    $widgetDataProviderCache->saveToCache($dataProvider);
                }
            } finally {
                $widgetDataProviderCache->finaliseCache();
            }
        } else {
            $this->getLogger()->debug('cache: Cache still valid, key: ' . $cacheKey);

            // Get the existing mediaIds so that we can maintain the links to displays.
            $mediaIds = $widgetDataProviderCache->getCachedMediaIds();
        }

        return $mediaIds;
    }

    /**
     * @param \Xibo\Entity\Widget $widget
     * @return Display[]
     */
    private function getDisplays(Widget $widget): array
    {
        $sql = '
            SELECT DISTINCT `requiredfile`.`displayId`
              FROM `requiredfile`
             WHERE itemId = :widgetId
                AND type = \'D\'
        ';

        $displayIds = [];
        foreach ($this->store->select($sql, ['widgetId' => $widget->widgetId]) as $record) {
            $displayId = intval($record['displayId']);
            try {
                $displayIds[] = $this->displayFactory->getById($displayId);
            } catch (NotFoundException) {
                $this->getLogger()->error('getDisplayIds: unknown displayId: ' . $displayId);
            }
        }

        return $displayIds;
    }

    /**
     * Link an array of displays with an array of media
     * @param int $widgetId
     * @param Display[] $displays
     * @param int[] $mediaIds
     * @return void
     */
    private function linkDisplays(int $widgetId, array $displays, array $mediaIds): void
    {
        $this->getLogger()->debug('linkDisplays: ' . count($displays) . ' displays, ' . count($mediaIds) . ' media');

        $sql = '
            INSERT INTO `display_media` (`displayId`, `mediaId`, `modifiedAt`) 
                VALUES (:displayId, :mediaId, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE `modifiedAt` = CURRENT_TIMESTAMP
        ';

        // Run invididual updates so that we can see if we've made a change.
        // With ON DUPLICATE KEY UPDATE, the affected-rows value per row is
        // 1 if the row is inserted as a new row,
        // 2 if an existing row is updated and
        // 0 if the existing row is set to its current values.
        foreach ($displays as $display) {
            $shouldNotify = false;
            foreach ($mediaIds as $mediaId) {
                try {
                    $affected = $this->store->update($sql, [
                        'displayId' => $display->displayId,
                        'mediaId' => $mediaId
                    ]);

                    if ($affected == 1) {
                        $shouldNotify = true;
                    }
                } catch (\PDOException) {
                    // We link what we can, and log any failures.
                    $this->getLogger()->error('linkDisplays: unable to link displayId: ' . $display->displayId
                        . ' to mediaId: ' . $mediaId . ', most likely the media has since gone');
                }
            }

            // When should we notify?
            // ----------------------
            // Newer displays (>= v4) should clear their cache only if linked media has changed
            // Older displays (< v4) should check in immediately on change
            if ($display->clientCode >= 400) {
                if ($shouldNotify) {
                    $this->displayFactory->getDisplayNotifyService()->collectLater()
                        ->notifyByDisplayId($display->displayId);
                }
                $this->displayFactory->getDisplayNotifyService()
                    ->notifyDataUpdate($display, $widgetId);
            } else {
                $this->displayFactory->getDisplayNotifyService()->collectNow()
                    ->notifyByDisplayId($display->displayId);
            }
        }
    }

    /**
     * Remove any display/media links which have expired.
     * @param Carbon $cutOff
     * @return void
     */
    private function removeOldDisplayLinks(Carbon $cutOff): void
    {
        $sql = '
            DELETE 
                FROM `display_media`
             WHERE `modifiedAt` < :modifiedAt
                AND `display_media`.`displayId` IN (
                    SELECT `displayId` 
                      FROM `display`
                     WHERE `display`.`loggedIn` = 1
                        OR `display`.`lastAccessed` > :lastAccessed
                )
        ';

        $this->store->update($sql, [
            'modifiedAt' => Carbon::now()->subDay()->format(DateFormatHelper::getSystemFormat()),
            'lastAccessed' => $cutOff->unix(),
        ]);
    }
}
