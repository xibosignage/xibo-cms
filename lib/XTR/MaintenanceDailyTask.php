<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\XTR;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Xibo\Helper\Guzzle\SafeClient;
use GuzzleHttp\Exception\GuzzleException;
use Xibo\Controller\Module;
use Xibo\Event\MaintenanceDailyEvent;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\FontFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\ModuleTemplateFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\DatabaseLogHandler;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Random;
use Xibo\Service\DefaultLayoutBuilder;
use Xibo\Service\DefaultsImporter;
use Xibo\Service\MediaService;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\MediaServiceInterface;
use Xibo\Storage\LibraryFileJournal;
use Xibo\Support\Exception\GeneralException;

/**
 * Class MaintenanceDailyTask
 * @package Xibo\XTR
 */
class MaintenanceDailyTask implements TaskInterface
{
    use TaskTrait;
    use NotifiesOncePerDayTrait;

    /** @var LayoutFactory */
    private $layoutFactory;

    /** @var UserFactory */
    private $userFactory;

    /** @var Module */
    private $moduleController;

    /** @var MediaServiceInterface */
    private $mediaService;

    /** @var DataSetFactory */
    private $dataSetFactory;

    /** @var FontFactory */
    private $fontFactory;

    /** @var ModuleFactory */
    private $moduleFactory;

    /** @var ModuleTemplateFactory */
    private $moduleTemplateFactory;

    /** @var LibraryStorageRegistryInterface */
    private $libraryStorageRegistry;

    /** @var NotificationFactory */
    private $notificationFactory;

    private PlaylistFactory $playlistFactory;

    private WidgetFactory $widgetFactory;

    private MediaFactory $mediaFactory;

    /** @var string */
    private $libraryLocation;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->moduleController = $container->get('\Xibo\Controller\Module');
        $this->layoutFactory = $container->get('layoutFactory');
        $this->userFactory = $container->get('userFactory');
        $this->dataSetFactory = $container->get('dataSetFactory');
        $this->mediaService = $container->get('mediaService');
        $this->fontFactory = $container->get('fontFactory');
        $this->moduleFactory = $container->get('moduleFactory');
        $this->moduleTemplateFactory = $container->get('moduleTemplateFactory');
        $this->libraryStorageRegistry = $container->get('libraryStorageRegistry');
        $this->notificationFactory = $container->get('notificationFactory');
        $this->playlistFactory = $container->get('playlistFactory');
        $this->widgetFactory = $container->get('widgetFactory');
        $this->mediaFactory = $container->get('mediaFactory');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $this->runMessage = '# ' . __('Daily Maintenance') . PHP_EOL . PHP_EOL;

        // Long-running task
        set_time_limit(0);

        // Make sure our library structure is as it should be
        try {
            $this->libraryLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION');
            MediaService::ensureLibraryExists($this->libraryLocation);
        } catch (\Exception $exception) {
            $this->getLogger()->error('Library structure invalid, e = ' . $exception->getMessage());
            $this->appendRunMessage(__('Library structure invalid'));
        }

        // Import layouts
        $this->importLayouts();

        $this->repairDefaultFonts();

        // Cycle the XMR Key
        $this->cycleXmrKey();

        try {
            $this->appendRunMessage(__('## Build caches'));

            // TODO: should we remove all bundle/asset cache before we start?
            // Player bundle
            $this->cachePlayerBundle();

            // Cache Assets
            $this->cacheAssets();

            // Fonts
            $this->mediaService->setUser($this->userFactory->getSystemUser())->updateFontsCss();
        } catch (\Exception $exception) {
            $this->getLogger()->error('Failure to build caches, e = ' . $exception->getMessage());
            $this->appendRunMessage(__('Failure to build caches'));
        }

        // Library file deletes and rolled-back uploads still pending
        $this->processLibraryJournal();

        // Tidy logs
        $this->tidyLogs();

        // Tidy Cache
        $this->tidyCache();

        // Dispatch an event so that consumers can hook into daily maintenance.
        $event = new MaintenanceDailyEvent();
        $this->getDispatcher()->dispatch($event, MaintenanceDailyEvent::$NAME);
        foreach ($event->getMessages() as $message) {
            $this->appendRunMessage($message);
        }
    }

    /**
     * Tidy the DB logs
     */
    private function tidyLogs()
    {
        $this->runMessage .= '## ' . __('Tidy Logs') . PHP_EOL;

        $maxage = $this->config->getSetting('MAINTENANCE_LOG_MAXAGE');
        if ($maxage != 0) {
            // Run this in the log handler so that we share the same connection and don't deadlock.
            DatabaseLogHandler::tidyLogs(
                Carbon::now()
                    ->subDays(intval($maxage))
                    ->format(DateFormatHelper::getSystemFormat())
            );

            $this->runMessage .= ' - ' . __('Done') . PHP_EOL . PHP_EOL;
        } else {
            $this->runMessage .= ' - ' . __('Disabled') . PHP_EOL . PHP_EOL;
        }
    }

    /**
     * Carry out journalled library file work that hasn't happened yet, for every asset type, so a failed delete or
     * a rolled-back upload is cleaned up without waiting for a Library Tidy.
     */
    private function processLibraryJournal(): void
    {
        $this->appendRunMessage('## ' . __('Library files'));

        // Commit what earlier steps left open (cycleXmrKey()), so it doesn't hold journal rows or roll back with them.
        $this->store->commitIfNecessary();

        try {
            $result = LibraryFileJournal::processAllPending(
                $this->store,
                $this->libraryStorageRegistry,
                $this->getLogger()
            );
        } catch (\Exception $exception) {
            $this->getLogger()->error('Failure to process the library file journal, e = ' . $exception->getMessage());
            $this->appendRunMessage(__('Failure to process pending library files'));
            return;
        }

        $this->appendRunMessage(sprintf(
            __('Deleted %d library files, %d failed and will be retried.'),
            $result['deleted'],
            $result['failed']
        ));

        if ($result['stuck'] > 0) {
            $this->notifyOncePerDay(
                $this->notificationFactory,
                __('Library files could not be deleted'),
                __('Some library files could not be deleted after several attempts. See the CMS log for details.'),
                'library'
            );
            $this->store->commitIfNecessary();
        }
    }

    /**
     * Tidy Cache
     */
    private function tidyCache()
    {
        $this->runMessage .= '## ' . __('Tidy Cache') . PHP_EOL;
        $this->pool->purge();
        $this->runMessage .= ' - ' . __('Done.') . PHP_EOL . PHP_EOL;
    }

    /**
     * Stores again any unchanged default font missing from storage, such as after a storage change.
     */
    private function repairDefaultFonts(): void
    {
        try {
            $storage = $this->fontFactory->getStorage();
            foreach (FontFactory::getDefaultFonts() as $file => $md5) {
                $isDefault = false;
                foreach ($this->fontFactory->getByFileName($file) as $font) {
                    $isDefault = $isDefault || strtolower((string)$font->md5) === $md5;
                }

                if ($isDefault && !$storage->exists($file)) {
                    $this->getLogger()->info('Default font missing from storage, storing it again: ' . $file);
                    try {
                        $this->getDefaultsImporter()->storeDefaultFont($file);
                    } catch (\Exception $exception) {
                        $this->getLogger()->error('Unable to store default font ' . $file . ': '
                            . $exception->getMessage());
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->getLogger()->error('Unable to check default fonts: ' . $exception->getMessage());
        }
    }

    /**
     * Import Layouts
     */
    private function importLayouts()
    {
        $this->runMessage .= '## ' . __('Import Layouts and Fonts') . PHP_EOL;

        $importer = $this->getDefaultsImporter();
        if ($importer->isRequired()) {
            try {
                $importer->import(rtrim($this->config->getSetting('LIBRARY_LOCATION'), '/') . '/brand', true);
                $this->runMessage .= ' - ' . __('Done.') . PHP_EOL . PHP_EOL;
            } catch (\Exception $exception) {
                // Left to run again tomorrow, without stopping the rest of the maintenance.
                $this->getLogger()->error('Unable to import defaults: ' . $exception->getMessage());
                $this->runMessage .= ' - ' . __('Failed.') . PHP_EOL . PHP_EOL;
            }
        } else {
            $this->runMessage .= ' - ' . __('Not Required.') . PHP_EOL . PHP_EOL;
        }
    }

    private function getDefaultsImporter(): DefaultsImporter
    {
        return new DefaultsImporter(
            $this->config,
            $this->store,
            $this->layoutFactory,
            $this->userFactory,
            $this->dataSetFactory,
            $this->fontFactory,
            $this->mediaService,
            new DefaultLayoutBuilder(
                $this->config,
                $this->layoutFactory,
                $this->playlistFactory,
                $this->widgetFactory,
                $this->moduleFactory,
                $this->mediaFactory,
                $this->getLogger()
            ),
            $this->getLogger()
        );
    }

    /**
     * Refresh the cache of assets
     * @return void
     * @throws GeneralException
     */
    private function cacheAssets(): void
    {
        // Assets
        $failedCount = 0;
        $assets = array_merge($this->moduleFactory->getAllAssets(), $this->moduleTemplateFactory->getAllAssets());
        foreach ($assets as $asset) {
            try {
                $asset->updateAssetCache($this->libraryLocation, true);
            } catch (GeneralException $exception) {
                $failedCount++;
                $this->log->error('Unable to copy asset: ' . $asset->id . ', e: ' . $exception->getMessage());
            }
        }

        $this->appendRunMessage(sprintf(__('Assets cached, %d failed.'), $failedCount));
    }

    /**
     * Cache the player bundle.
     * @return void
     */
    private function cachePlayerBundle(): void
    {
        // Output the player bundle
        $bundlePath = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'assets/bundle.min.js';
        $bundleMd5CachePath = $bundlePath . '.md5';

        copy(PROJECT_ROOT . '/modules/bundle.min.js', $bundlePath);
        file_put_contents($bundleMd5CachePath, md5_file($bundlePath));

        $this->appendRunMessage(__('Player bundle cached'));
    }

    /**
     * Once per day we cycle the XMR CMS key
     *  the old key should remain valid in XMR for up to 1 hour further to allow for cross over
     * @return void
     */
    private function cycleXmrKey(): void
    {
        $this->log->debug('cycleXmrKey: adding new key');
        try {
            $key = Random::generateString(20, 'xmr_');

            $this->getConfig()->changeSetting('XMR_CMS_KEY', $key);
            // XMR is by-design on the local network — see the note on
            // PlayerActionService::processQueue. Use the internal-services
            // SafeClient variant.
            $client = SafeClient::getSafeClientForInternal($this->config->getGuzzleProxy([
                'base_uri' => $this->getConfig()->getSetting('XMR_ADDRESS'),
            ]));

            $client->post('/', [
                'json' => [
                    'id' => constant('SECRET_KEY'),
                    'type' => 'keys',
                    'key' => $key,
                ],
            ]);
            $this->log->debug('cycleXmrKey: added new key');
        } catch (GuzzleException | \Exception $e) {
            $this->log->error('cycleXmrKey: failed. E = ' . $e->getMessage());
        }
    }
}
