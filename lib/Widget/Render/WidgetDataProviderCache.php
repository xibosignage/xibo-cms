<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
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

namespace Xibo\Widget\Render;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stash\Interfaces\PoolInterface;
use Stash\Invalidation;
use Stash\Item;
use Xibo\Entity\Module;
use Xibo\Entity\Widget;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Exception\GeneralException;
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
     * @param \Xibo\Entity\Module $module
     * @param \Xibo\Entity\Widget $widget
     * @param \Xibo\Widget\Provider\DataProviderInterface $dataProvider
     * @return bool
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function decorateWithCache(
        Module $module,
        Widget $widget,
        DataProviderInterface $dataProvider
    ): bool {
        // TODO: what is the cache key?
        //  this needs to cater for cases where:
        //   - we want different data per display
        //   - we want to be kind to third parties (i.e. rss feeds)
        $this->cache = $this->pool->getItem('/widget/html/' . $widget->widgetId);
        $data = $this->cache->get();
        if ($this->cache->isMiss() || $data === null) {
            // Lock it up
            $this->concurrentRequestLock();
            return false;
        } else {
            $dataProvider->clearData();
            $dataProvider->addItems($data);
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
        $this->cache->set($dataProvider->getData());
        $this->cache->expiresAfter($dataProvider->getCacheTtl());

        // Save to the pool
        $this->pool->save($this->cache);

        // Release the cache
        $this->concurrentRequestRelease();
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
            $this->lock->expiresAfter(10); // Expire straight away (but give it time to save the thing)

            $this->pool->save($this->lock);
        }
    }

    // </editor-fold>
}
