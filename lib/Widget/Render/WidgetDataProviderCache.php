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

namespace Xibo\Widget\Render;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stash\Interfaces\PoolInterface;
use Stash\Invalidation;
use Stash\Item;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\ObjectVars;
use Xibo\Support\Exception\GeneralException;
use Xibo\Widget\Provider\DataProvider;
use Xibo\Widget\Provider\DataProviderInterface;

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

    private $cachedMediaIds;

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
        bool $isLockIfMiss = true
    ): bool {
        // Construct a key
        $this->key = '/widget/' . $dataProvider->getDataType() . '/' . md5($cacheKey);

        $this->getLog()->debug('decorateWithCache: key is ' . $this->key);

        $this->cache = $this->pool->getItem($this->key);
        $data = $this->cache->get();

        // Hit or miss?
        if ($this->cache->isMiss()
            || $data === null
            || ($dataModifiedDt !== null && $dataModifiedDt->isAfter($this->cache->getCreation()))
        ) {
            $this->getLog()->debug('decorateWithCache: miss.');

            // Lock it up
            if ($isLockIfMiss) {
                $this->concurrentRequestLock();
            }
            return false;
        } else {
            $this->getLog()->debug('decorateWithCache: hit.');

            // Clear the data provider and add the cached items back to it.
            $dataProvider->clearData();
            $dataProvider->clearMeta();
            $dataProvider->addItems($data['data'] ?? []);

            foreach (($data['meta'] ?? []) as $key => $item) {
                $dataProvider->addOrUpdateMeta($key, $item);
            }
            $dataProvider->addOrUpdateMeta('cacheDt', $this->cache->getCreation()->format('c'));
            $dataProvider->addOrUpdateMeta('expireDt', $this->cache->getExpiration()->format('c'));

            // Record any cached mediaIds
            $this->cachedMediaIds = $data['media'] ?? [];
            return true;
        }
    }

    /**
     * @param DataProviderInterface $dataProvider
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function saveToCache(DataProviderInterface $dataProvider): void
    {
        if ($this->cache === null) {
            throw new GeneralException('No cache to save');
        }

        // Set our cache from the data provider.
        $dataProvider->addOrUpdateMeta('cacheDt', Carbon::now()->format('c'));
        $dataProvider->addOrUpdateMeta(
            'expireDt',
            Carbon::now()->addSeconds($dataProvider->getCacheTtl())->format('c')
        );

        $cached = $this->cache->set([
            'data' => $dataProvider->getData(),
            'meta' => $dataProvider->getMeta(),
            'media' => $dataProvider->getImageIds()
        ]);

        if (!$cached) {
            throw new GeneralException('Cache failure');
        }
        $this->cache->expiresAfter($dataProvider->getCacheTtl());

        // Save to the pool
        $this->pool->save($this->cache);

        $this->getLog()->debug('saveToCache: cached ' . $this->key
            . ' for ' . $dataProvider->getCacheTtl() . ' seconds');
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
     * @param array $data The data
     * @param array $storedAs A keyed array of module files this widget has access to
     * @return array
     */
    public function decorateForPlayer(
        array $data,
        array $storedAs
    ): array {
        foreach ($data as $row => $item) {
            // Each data item can be an array or an object
            if (is_array($item)) {
                foreach ($item as $key => $value) {
                    if (is_string($value)) {
                        $data[$row][$key] = $this->decorateMediaForPlayer($storedAs, $value);
                    }
                }
            } else if (is_object($item)) {
                foreach (ObjectVars::getObjectVars($item) as $key => $value) {
                    if (is_string($value)) {
                        $item->{$key} = $this->decorateMediaForPlayer($storedAs, $value);
                    }
                }
            }
        }
        return $data;
    }

    /**
     * @param array $storedAs
     * @param string|null $data
     * @return string|null
     */
    private function decorateMediaForPlayer(array $storedAs, ?string $data): ?string
    {
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
                    $data = str_replace('[[' . $match . ']]', $storedAs[$value[1]], $data);
                } else {
                    $data = str_replace('[[' . $match . ']]', '', $data);
                }
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
