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

namespace Xibo\Widget\Render;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stash\Interfaces\PoolInterface;
use Stash\Invalidation;
use Stash\Item;
use Xibo\Entity\Display;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\LinkSigner;
use Xibo\Helper\ObjectVars;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Widget\Provider\DataProvider;
use Xibo\Widget\Provider\DataProviderInterface;
use Xibo\Xmds\Wsdl;

/**
 * Acts as a cache for the Widget data cache.
 */
class WidgetDataProviderCache
{
    /** @var LoggerInterface */
    private $logger;

    /** @var \Stash\Interfaces\PoolInterface */
    private $pool;

    /** @var Item */
    private $lock;

    /** @var Item */
    private $cache;

    /** @var string The cache key */
    private $key;

    /** @var bool Is the cache a miss or old */
    private $isMissOrOld = true;

    private $cachedMediaIds;

    /** @var int[] widgetIds which have already been notified about the currently cached data */
    private array $notifiedWidgetIds = [];

    /** @var \stdClass|null The raw cached payload (data/meta/media/notifiedWidgetIds), kept so that
     * notifiedWidgetIds can be updated and re-persisted without a second Stash read - a second `Item::get()`
     * call is not reliable here, since Stash's `isHit` flag is fixed by the first read/miss of this request. */
    private ?\stdClass $cachedObject = null;

    /** @var string|null The cacheDt this instance's notifiedWidgetIds/cachedObject snapshot was captured from -
     * used to detect a concurrent save producing a newer data version before we trust/persist that snapshot. */
    private ?string $cachedVersionDt = null;

    /**
     * @param \Stash\Interfaces\PoolInterface $pool
     */
    public function __construct(PoolInterface $pool)
    {
        $this->pool = $pool;
    }

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @return $this
     */
    public function useLogger(LoggerInterface $logger): WidgetDataProviderCache
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    private function getLog(): LoggerInterface
    {
        if ($this->logger === null) {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }

    /**
     * Decorate this data provider with cache
     * @param DataProvider $dataProvider
     * @param string $cacheKey
     * @param Carbon|null $dataModifiedDt The date any associated data was modified.
     * @param bool $isLockIfMiss Should the cache be locked if it's a miss? Defaults to true.
     * @return bool
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function decorateWithCache(
        DataProvider $dataProvider,
        string $cacheKey,
        ?Carbon $dataModifiedDt,
        bool $isLockIfMiss = true,
    ): bool {
        // Construct a key
        $this->key = '/widget/'
            . ($dataProvider->getDataType() ?: $dataProvider->getDataSource())
            . '/' . md5($cacheKey);

        $this->getLog()->debug('decorateWithCache: key is ' . $this->key);

        // Get the cache
        $this->cache = $this->pool->getItem($this->key);

        // Invalidation method old means that if this cache key is being regenerated concurrently to this request
        // we return the old data we have stored already.
        $this->cache->setInvalidationMethod(Invalidation::OLD);

        // Get the data (this might be OLD data)
        $data = $this->cache->get();
        $cacheCreationDt = $this->cache->getCreation();
        $hasData = $data !== null;

        // Does the cache have data?
        // we keep data 50% longer than we need to, so that it has a chance to be regenerated out of band
        if (!$hasData) {
            $this->getLog()->debug('decorateWithCache: miss, no data');
            $this->isMissOrOld = true;
        } else {
            // Determine whether this cache is a miss (i.e. expired and being regenerated, expired, out of date)
            // We use our own expireDt here because Stash will only return expired data with invalidation method
            // OLD if the data is currently being regenerated and another process has called lock() on it. Read
            // straight from the cached payload's own meta (rather than the data provider's) so this check never
            // depends on the data provider having been mutated first.
            $expireDt = $data->meta['expireDt'] ?? null;
            if ($expireDt !== null) {
                $expireDt = Carbon::createFromFormat('c', $expireDt);
            } else {
                $expireDt = $this->cache->getExpiration();
            }

            // Determine if the cache returned is a miss or older than the modified/expired dates
            $this->isMissOrOld = $this->cache->isMiss()
                || ($dataModifiedDt !== null && $cacheCreationDt !== false
                    && $dataModifiedDt->isAfter($cacheCreationDt))
                || $expireDt->isBefore(Carbon::now());

            $this->getLog()->debug('decorateWithCache: cache has data, is miss or old: '
                . var_export($this->isMissOrOld, true));

            $this->cachedObject = $data;
            $this->cachedVersionDt = $data->meta['cacheDt'] ?? null;

            // Clear the data provider and add the cached items back to it.
            $dataProvider->clearData();
            $dataProvider->clearMeta();
            $dataProvider->addItems($data->data ?? []);

            // Record any cached mediaIds
            $this->cachedMediaIds = $data->media ?? [];

            // Record which widgets have already been notified about this data version, so that widgets sharing
            // this cache key (e.g. identical weather locations) don't each need to be the one that triggered the
            // fetch in order to notify their own displays.
            $this->notifiedWidgetIds = $data->notifiedWidgetIds ?? [];

            // Update any meta
            foreach (($data->meta ?? []) as $key => $item) {
                $dataProvider->addOrUpdateMeta($key, $item);
            }
        }

        // If we do not have data/we're old/missed cache, and we have requested a lock, then we will be refreshing
        // the cache, so lock the record
        if ($isLockIfMiss && (!$hasData || $this->isMissOrOld)) {
            $this->concurrentRequestLock();
        }

        return $hasData;
    }

    /**
     * Is the cache a miss, or old data.
     * @return bool
     */
    public function isCacheMissOrOld(): bool
    {
        return $this->isMissOrOld;
    }

    /**
     * Get the cache date for this data provider and key
     * @param DataProvider $dataProvider
     * @param string $cacheKey
     * @return Carbon|null
     */
    public function getCacheDate(DataProvider $dataProvider, string $cacheKey): ?Carbon
    {
        // Construct a key
        $this->key = '/widget/'
            . ($dataProvider->getDataType() ?: $dataProvider->getDataSource())
            . '/' . md5($cacheKey);

        $this->getLog()->debug('getCacheDate: key is ' . $this->key);

        // Get the cache
        $this->cache = $this->pool->getItem($this->key);
        $cacheCreationDt = $this->cache->getCreation();

        return $cacheCreationDt ? Carbon::instance($cacheCreationDt) : null;
    }

    /**
     * @param DataProviderInterface $dataProvider
     * @param int|null $initiatingWidgetId The widget whose own check triggered this fresh save, if any - seeded
     *   directly into notifiedWidgetIds so that widget doesn't need a second read-modify-write via
     *   shouldNotifyForWidget() just to record that it already knows about the version it just wrote.
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function saveToCache(DataProviderInterface $dataProvider, ?int $initiatingWidgetId = null): void
    {
        if ($this->cache === null) {
            throw new GeneralException('No cache to save');
        }

        $object = $this->buildCacheObject($dataProvider);

        // This is a new data version. Only the initiating widget (if any) already knows about it.
        $this->notifiedWidgetIds = $initiatingWidgetId !== null ? [$initiatingWidgetId] : [];
        $object->notifiedWidgetIds = $this->notifiedWidgetIds;
        $this->cachedObject = $object;
        $this->cachedVersionDt = $object->meta['cacheDt'] ?? null;

        $this->persistItem($this->cache, $object, $dataProvider->getCacheTtl());

        $this->getLog()->debug('saveToCache: cached ' . $this->key
            . ' for ' . $dataProvider->getCacheTtl() . ' seconds');
    }

    /**
     * Build the common data/meta/media payload for a cache save, stamping the cache dates onto the data
     * provider's meta along the way.
     * @param DataProviderInterface $dataProvider
     * @return \stdClass
     */
    private function buildCacheObject(DataProviderInterface $dataProvider): \stdClass
    {
        // Set some cache dates so that we can track when this data provider was cached and when it should expire.
        $dataProvider->addOrUpdateMeta('cacheDt', Carbon::now()->format('c'));
        $dataProvider->addOrUpdateMeta(
            'expireDt',
            Carbon::now()->addSeconds($dataProvider->getCacheTtl())->format('c')
        );

        $object = new \stdClass();
        $object->data = $dataProvider->getData();
        $object->meta = $dataProvider->getMeta();
        $object->media = $dataProvider->getImageIds();

        // Available to a caller reading it back immediately after this save, in the same request.
        $this->cachedMediaIds = $object->media;

        return $object;
    }

    /**
     * Persist a cache payload into the given Stash item and throw on failure.
     * @param Item $item
     * @param \stdClass $object
     * @param int $cacheTtl
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function persistItem(Item $item, \stdClass $object, int $cacheTtl): void
    {
        $item->setInvalidationMethod(Invalidation::OLD);
        $item->set($object);

        // Keep the cache 50% longer than necessary
        // The expireDt must always be 15 minutes to allow plenty of time for the WidgetSyncTask to regenerate.
        $item->expiresAfter(ceil(max($cacheTtl * 1.5, 900)));

        // This is the call that actually persists the item and reports success/failure (Item::set() only
        // stages the value in memory and always returns the Item itself, never a bool).
        if (!$this->pool->save($item)) {
            throw new GeneralException('Cache failure');
        }
    }

    /**
     * Finalise the cache process
     */
    public function finaliseCache(): void
    {
        $this->concurrentRequestRelease();
    }

    /**
     * Return any cached mediaIds
     * @return array
     */
    public function getCachedMediaIds(): array
    {
        return $this->cachedMediaIds ?? [];
    }

    /**
     * Has the given widget already been notified about the data currently held in this cache entry?
     * Widgets which resolve to the same cache key (e.g. identical weather locations) share a single cache entry,
     * so whichever widget happens to trigger the fetch must not be the only one whose displays get notified -
     * every widget sharing the key needs to check in here at least once per data version.
     * @param int $widgetId
     * @return bool true if this widget has not yet been notified about the current data (and should be)
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function shouldNotifyForWidget(int $widgetId): bool
    {
        $this->refreshIfVersionChanged();

        if (in_array($widgetId, $this->notifiedWidgetIds, true)) {
            return false;
        }

        $this->notifiedWidgetIds[] = $widgetId;
        $this->persistNotifiedWidgetIds();

        return true;
    }

    /**
     * Re-read the shared cache entry if a concurrent save has produced a newer data version than the one this
     * instance's in-memory notifiedWidgetIds/cachedObject snapshot was captured from. Without this, a stale
     * snapshot (e.g. read while the cache was still valid, then overtaken by a concurrent regeneration before
     * shouldNotifyForWidget() runs) could wrongly report a widget as "already notified" for data that was, from
     * the cache's current point of view, just replaced by a genuinely new version.
     */
    private function refreshIfVersionChanged(): void
    {
        if ($this->key === null) {
            return;
        }

        // A fresh Item instance, not $this->cache - Stash's `isHit` flag is fixed by the first read/miss of a
        // given Item instance, so re-querying via a new instance is the reliable way to see the current state.
        $item = $this->pool->getItem($this->key);
        $item->setInvalidationMethod(Invalidation::OLD);
        $data = $item->get();

        if ($data === null) {
            return;
        }

        $currentVersionDt = $data->meta['cacheDt'] ?? null;
        if ($currentVersionDt !== $this->cachedVersionDt) {
            $this->cache = $item;
            $this->cachedObject = $data;
            $this->notifiedWidgetIds = $data->notifiedWidgetIds ?? [];
            $this->cachedVersionDt = $currentVersionDt;
        }
    }

    /**
     * Persist the current notifiedWidgetIds list back into the cache entry, preserving its existing expiry.
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function persistNotifiedWidgetIds(): void
    {
        if ($this->cache === null || $this->cachedObject === null) {
            // Nothing to update onto - there is no cached data for us to attach the notified list to.
            return;
        }

        // Capture the existing expiry before we mutate/save, otherwise Stash will persist this write without the
        // original TTL. We deliberately don't call $this->cache->get() here - Stash's `isHit` flag is fixed by
        // the first read/miss of this request, so a second get() can incorrectly return null even immediately
        // after a set(). $this->cachedObject is the reliable in-memory source of truth instead.
        $expiration = $this->cache->getExpiration();

        $this->cachedObject->notifiedWidgetIds = $this->notifiedWidgetIds;

        $this->cache->set($this->cachedObject);
        $this->cache->expiresAt($expiration);

        if (!$this->pool->save($this->cache)) {
            throw new GeneralException('Cache failure');
        }
    }

    /**
     * Decorate for a preview
     * @param array $data The data
     * @param callable $urlFor
     * @return array
     */
    public function decorateForPreview(array $data, callable $urlFor): array
    {
        foreach ($data as $row => $item) {
            // This is either an object or an array
            if (is_array($item)) {
                foreach ($item as $key => $value) {
                    if (is_string($value)) {
                        $data[$row][$key] = $this->decorateMediaForPreview($urlFor, $value);
                    }
                }
            } else if (is_object($item)) {
                foreach (ObjectVars::getObjectVars($item) as $key => $value) {
                    if (is_string($value)) {
                        $item->{$key} = $this->decorateMediaForPreview($urlFor, $value);
                    }
                }
            }
        }
        return $data;
    }

    /**
     * @param callable $urlFor
     * @param string|null $data
     * @return string|null
     */
    private function decorateMediaForPreview(callable $urlFor, ?string $data): ?string
    {
        if ($data === null) {
            return null;
        }
        $matches = [];
        preg_match_all('/\[\[(.*?)\]\]/', $data, $matches);
        foreach ($matches[1] as $match) {
            if (Str::startsWith($match, 'mediaId')) {
                $value = explode('=', $match);
                $data = str_replace(
                    '[[' . $match . ']]',
                    $urlFor('library.download', ['id' => $value[1], 'type' => 'image']),
                    $data
                );
            } else if (Str::startsWith($match, 'connector')) {
                $value = explode('=', $match);
                $data = str_replace(
                    '[[' . $match . ']]',
                    $urlFor('layout.preview.connector', [], ['token' => $value[1], 'isDebug' => 1]),
                    $data
                );
            }
        }
        return $data;
    }

    /**
     * Decorate for a player
     * @param \Xibo\Service\ConfigServiceInterface $configService
     * @param \Xibo\Entity\Display $display
     * @param string $encryptionKey
     * @param array $data The data
     * @param array $storedAs A keyed array of module files this widget has access to
     * @return array
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function decorateForPlayer(
        ConfigServiceInterface $configService,
        Display $display,
        string $encryptionKey,
        array $data,
        array $storedAs,
    ): array {
        $this->getLog()->debug('decorateForPlayer');

        $cdnUrl = $configService->getSetting('CDN_URL');

        foreach ($data as $row => $item) {
            // Each data item can be an array or an object
            if (is_array($item)) {
                foreach ($item as $key => $value) {
                    if (is_string($value)) {
                        $data[$row][$key] = $this->decorateMediaForPlayer(
                            $cdnUrl,
                            $display,
                            $encryptionKey,
                            $storedAs,
                            $value,
                        );
                    }
                }
            } else if (is_object($item)) {
                foreach (ObjectVars::getObjectVars($item) as $key => $value) {
                    if (is_string($value)) {
                        $item->{$key} = $this->decorateMediaForPlayer(
                            $cdnUrl,
                            $display,
                            $encryptionKey,
                            $storedAs,
                            $value
                        );
                    }
                }
            }
        }
        return $data;
    }

    /**
     * @param string|null $cdnUrl
     * @param \Xibo\Entity\Display $display
     * @param string $encryptionKey
     * @param array $storedAs
     * @param string|null $data
     * @return string|null
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    private function decorateMediaForPlayer(
        ?string $cdnUrl,
        Display $display,
        string $encryptionKey,
        array $storedAs,
        ?string $data,
    ): ?string {
        if ($data === null) {
            return null;
        }

        // Media substitutes
        $matches = [];
        preg_match_all('/\[\[(.*?)\]\]/', $data, $matches);
        foreach ($matches[1] as $match) {
            if (Str::startsWith($match, 'mediaId')) {
                $value = explode('=', $match);
                if (array_key_exists($value[1], $storedAs)) {
                    if ($display->isPwa()) {
                        $url = LinkSigner::generateSignedLink(
                            $display,
                            $encryptionKey,
                            $cdnUrl,
                            'M',
                            $value[1],
                            $storedAs[$value[1]],
                            null,
                            true,
                        );
                    } else {
                        $url = $storedAs[$value[1]];
                    }
                    $data = str_replace('[[' . $match . ']]', $url, $data);
                } else {
                    $data = str_replace('[[' . $match . ']]', '', $data);
                }
            } else if (Str::startsWith($match, 'connector')) {
                // We have WSDL here because this is only called from XMDS.
                $value = explode('=', $match);
                $data = str_replace(
                    '[[' . $match . ']]',
                    Wsdl::getRoot() . '?connector=true&token=' . $value[1],
                    $data
                );
            }
        }
        return $data;
    }

    // <editor-fold desc="Request locking">

    /**
     * Hold a lock on concurrent requests
     *  blocks if the request is locked
     * @param int $ttl seconds
     * @param int $wait seconds
     * @param int $tries
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function concurrentRequestLock(int $ttl = 300, int $wait = 2, int $tries = 5)
    {
        if ($this->cache === null) {
            throw new GeneralException('No cache to lock');
        }

        $this->lock = $this->pool->getItem('locks/concurrency/' . $this->cache->getKey());

        // Set the invalidation method to simply return the value (not that we use it, but it gets us a miss on expiry)
        // isMiss() returns false if the item is missing or expired, no exceptions.
        $this->lock->setInvalidationMethod(Invalidation::NONE);

        // Get the lock
        // other requests will wait here until we're done, or we've timed out
        $locked = $this->lock->get();

        // Did we get a lock?
        // if we're a miss, then we're not already locked
        if ($this->lock->isMiss() || $locked === false) {
            $this->getLog()->debug('Lock miss or false. Locking for ' . $ttl
                . ' seconds. $locked is '. var_export($locked, true)
                . ', key = ' . $this->cache->getKey());

            // so lock now
            $this->lock->set(true);
            $this->lock->expiresAfter($ttl);
            $this->lock->save();
        } else {
            // We are a hit - we must be locked
            $this->getLog()->debug('LOCK hit for ' . $this->cache->getKey() . ' expires '
                . $this->lock->getExpiration()->format(DateFormatHelper::getSystemFormat())
                . ', created ' . $this->lock->getCreation()->format(DateFormatHelper::getSystemFormat()));

            // Try again?
            $tries--;

            if ($tries <= 0) {
                // We've waited long enough
                throw new GeneralException('Concurrent record locked, time out.');
            } else {
                $this->getLog()->debug('Unable to get a lock, trying again. Remaining retries: ' . $tries);

                // Hang about waiting for the lock to be released.
                sleep($wait);

                // Recursive request (we've decremented the number of tries)
                $this->concurrentRequestLock($ttl, $wait, $tries);
            }
        }
    }

    /**
     * Release a lock on concurrent requests
     */
    private function concurrentRequestRelease()
    {
        if ($this->lock !== null) {
            $this->getLog()->debug('Releasing lock ' . $this->lock->getKey());

            // Release lock
            $this->lock->set(false);
            $this->lock->expiresAfter(10); // Expire straight away (but give time to save)

            $this->pool->save($this->lock);
        }
    }

    // </editor-fold>
}
