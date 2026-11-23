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

namespace Xibo\Helper;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stash\Interfaces\ItemInterface;
use Stash\Interfaces\PoolInterface;
use Stash\Invalidation;

/**
 * A lock held in the cache by one process at a time, which only its holder can extend or release.
 * Best-effort: two processes can both take it in a race, so it only guards work that is safe to repeat.
 */
class StashLock
{
    private ?string $token = null;

    public function __construct(
        private readonly PoolInterface $pool,
        private readonly string $key,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Takes the lock, trying $tries times with $waitMs between tries.
     * @return bool false when another process still holds it
     */
    public function acquire(int $ttl, int $waitMs, int $tries): bool
    {
        while (true) {
            // A fresh item every time, because a reused one never sees the lock being released.
            $lock = $this->getItem();

            // A miss, or a released lock stored as false, means nobody holds it.
            $value = $lock->get();
            if ($lock->isMiss() || $value === false) {
                $this->token = bin2hex(random_bytes(8));
                $this->save($lock, $this->token, $ttl);
                return true;
            }

            $this->logger->debug('Lock ' . $this->key . ' is held elsewhere, expiring '
                . $lock->getExpiration()->format(DateFormatHelper::getSystemFormat()));

            $tries--;
            if ($tries <= 0) {
                return false;
            }

            usleep($waitMs * 1000);
        }
    }

    /**
     * Whether this process still holds the lock, read afresh in case it expired.
     */
    public function isOwner(): bool
    {
        if ($this->token === null) {
            return false;
        }

        $lock = $this->getItem();
        $value = $lock->get();

        return !$lock->isMiss() && $value === $this->token;
    }

    /**
     * Keeps the lock for another $ttl seconds, if this process still holds it.
     */
    public function extend(int $ttl): void
    {
        if ($this->isOwner()) {
            $this->save($this->getItem(), $this->token, $ttl);
        }
    }

    /**
     * Releases the lock, unless it expired and another process has taken it.
     */
    public function release(): void
    {
        if ($this->token === null) {
            return;
        }

        if ($this->isOwner()) {
            // Expires straight away, with time to save.
            $this->save($this->getItem(), false, 10);
        } else {
            $this->logger->info('Lock ' . $this->key . ' expired and was taken by another process, not releasing it');
        }

        $this->token = null;
    }

    private function getItem(): ItemInterface
    {
        $lock = $this->pool->getItem($this->key);
        $lock->setInvalidationMethod(Invalidation::NONE);
        return $lock;
    }

    private function save(ItemInterface $lock, string|false $value, int $ttl): void
    {
        $lock->set($value);
        $lock->expiresAfter($ttl);
        $this->pool->save($lock);
    }
}
