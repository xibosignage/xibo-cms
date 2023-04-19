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

namespace Xibo\XTR;

use Carbon\Carbon;
use Xibo\Entity\Module;
use Xibo\Entity\Widget;
use Xibo\Event\WidgetDataRequestEvent;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Exception\GeneralException;
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

        // Update for widgets which are active on displays
        // TODO: decide if this is soon enough to do this work (none of these widgets will have any data until
        //  this runs).
        $sql = '
          SELECT DISTINCT `requiredfile`.itemId, `requiredfile`.complete 
            FROM `requiredfile` 
              INNER JOIN `widget`
              ON `widget`.widgetId = `requiredfile`.itemId
              INNER JOIN `display`
              ON `display`.displayId = `requiredfile`.displayId
           WHERE `requiredfile`.type = \'W\' 
              AND `display`.loggedIn = 1
          ORDER BY `requiredfile`.complete DESC, `requiredfile`.itemId
        ';

        $smt = $this->store->getConnection()->prepare($sql);
        $smt->execute();

        $row = true;
        while ($row) {
            $row = $smt->fetch(\PDO::FETCH_ASSOC);
            try {
                if ($row !== false) {
                    $widgetId = (int)$row['itemId'];

                    $this->getLogger()->debug('widgetSyncTask: processing widgetId ' . $widgetId);

                    $widget = $this->widgetFactory->getById($widgetId);
                    $widget->load();

                    $module = $this->moduleFactory->getByType($widget->type);

                    // If this widget's module expects data to be provided (i.e. has a datatype) then make sure that
                    // data is cached ahead of time here.
                    // This also refreshes any library or external images referenced by the data so that they aren't
                    // considered for removal.
                    if ($module->isDataProviderExpected() || $module->isWidgetProviderAvailable()) {
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
                        $isDisplaySpecific = str_contains($cacheKey, '%displayId%');

                        // We're either assigning all media to all displays, or we're assigning then one by one
                        if ($isDisplaySpecific) {
                            $this->getLogger()->debug('widgetSyncTask: cache is display specific');

                            // We need to run the cache for every display this widget is assigned to.
                            foreach ($this->getDisplays($widget) as $display) {
                                $mediaIds = $this->cache(
                                    $module,
                                    $widget,
                                    $widgetInterface,
                                    intval($display['displayId'])
                                );
                                $this->linkDisplays([$display], $mediaIds);
                            }
                        } else {
                            $this->getLogger()->debug('widgetSyncTask: cache is not display specific');

                            // Just a single run will do it.
                            $mediaIds = $this->cache($module, $widget, $widgetInterface, null);
                            $this->linkDisplays($this->getDisplays($widget), $mediaIds);
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
                // Log and skip to the next layout
                $this->log->debug($xiboException->getTraceAsString());
                $this->log->error('widgetSyncTask: Cannot process widget ' . $widgetId
                    . ', E = ' . $xiboException->getMessage());
            }
        }

        // Remove display_media records which have not been touched for a defined period of time.
        $this->removeOldDisplayLinks();

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
        ?int $displayId
    ): array {
        $mediaIds = [];

        // Each time we call this we use a new provider
        $dataProvider = $module->createDataProvider($widget);
        $dataProvider->setMediaFactory($this->mediaFactory);
        $widgetDataProviderCache = $this->moduleFactory->createWidgetDataProviderCache();

        // Get the cache key
        $cacheKey = $this->moduleFactory->determineCacheKey(
            $module,
            $widget,
            $displayId ?? 0,
            $dataProvider,
            $widgetInterface
        );

        // Set our provider up for the displays
        if ($displayId !== null) {
            $display = $this->displayFactory->getById($displayId);
            $dataProvider->setDisplayProperties($display->latitude, $display->longitude, $displayId);
        } else {
            $dataProvider->setDisplayProperties(
                $this->getConfig()->getSetting('DEFAULT_LAT'),
                $this->getConfig()->getSetting('DEFAULT_LONG')
            );
        }

        // Get the data modified date
        $dataModifiedDt = null;
        if ($widgetInterface !== null) {
            $dataModifiedDt = $widgetInterface->getDataModifiedDt($dataProvider);

            if ($dataModifiedDt !== null) {
                $this->getLogger()->debug('cache: data modifiedDt is ' . $dataModifiedDt->toAtomString());
            }
        }

        if (!$widgetDataProviderCache->decorateWithCache($dataProvider, $cacheKey, $dataModifiedDt)) {
            $this->getLogger()->debug('Cache expired, pulling fresh: key: ' . $cacheKey);

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

                // Do we have images?
                $media = $dataProvider->getImages();
                if (count($media) > 0) {
                    // Process the downloads.
                    $this->mediaFactory->processDownloads(function ($media) use ($widget, &$mediaIds) {
                        /** @var \Xibo\Entity\Media $media */
                        // Success
                        // We don't need to do anything else, references to mediaId will be built when we decorate
                        // the HTML.
                        $this->getLogger()->debug('Successfully downloaded ' . $media->mediaId);

                        if (!in_array($media->mediaId, $mediaIds)) {
                            $mediaIds[] = $media->mediaId;
                        }
                    });
                }

                // Save to cache
                // TODO: we should implement a "has been processed" flag instead as it might be valid to cache no data
                if (count($dataProvider->getData()) > 0) {
                    $widgetDataProviderCache->saveToCache($dataProvider);
                }
            } finally {
                $widgetDataProviderCache->finaliseCache();
            }
        } else {
            $this->getLogger()->debug('Cache still valid, key: ' . $cacheKey);

            // Get the existing mediaIds so that we can maintain the links to displays.
            $mediaIds = $widgetDataProviderCache->getCachedMediaIds();
        }

        return $mediaIds;
    }

    private function getDisplays(Widget $widget): array
    {
        $sql = '
            SELECT DISTINCT displayId
              FROM `requiredfile`
             WHERE itemId = :widgetId
                AND type = \'W\'
        ';

        return $this->store->select($sql, ['widgetId' => $widget->widgetId]);
    }

    /**
     * Link an array of displays with an array of media
     * @param array $displays
     * @param array $mediaIds
     * @return void
     */
    private function linkDisplays(array $displays, array $mediaIds): void
    {
        $this->getLogger()->debug('linkDisplays: ' . count($displays) . ' displays, ' . count($mediaIds) . ' media');

        $sql = '
            INSERT INTO `display_media` (`displayId`, `mediaId`, `modifiedAt`) 
                VALUES (:displayId, :mediaId, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE `modifiedAt` = CURRENT_TIMESTAMP
        ';

        // TODO: there will be a much more efficient way to do this!
        foreach ($displays as $display) {
            $displayId = intval($display['displayId']);
            $this->displayFactory->getDisplayNotifyService()->collectLater()->notifyByDisplayId($displayId);

            foreach ($mediaIds as $mediaId) {
                $this->store->update($sql, [
                    'displayId' => $displayId,
                    'mediaId' => $mediaId
                ]);
            }
        }
    }

    /**
     * Remove any display/media links which are older than $days days
     * @param int $days
     * @return void
     */
    private function removeOldDisplayLinks(int $days = 5): void
    {
        $sql = 'DELETE FROM `display_media` WHERE `modifiedAt` < :modifiedAt';
        $this->store->update($sql, [
            'modifiedAt' => Carbon::now()->subDays($days)->format(DateFormatHelper::getSystemFormat()),
        ]);
    }
}
