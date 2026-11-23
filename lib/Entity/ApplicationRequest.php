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

namespace Xibo\Entity;

use OpenApi\Attributes as OA;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Application Request
 */
#[OA\Schema]
class ApplicationRequest implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @var int
     */
    #[OA\Property(description: 'The request ID')]
    public $requestId;

    /**
     * @var int
     */
    #[OA\Property(description: 'The user ID')]
    public $userId;

    /**
     * @var string
     */
    #[OA\Property(description: 'The application ID')]
    public $applicationId;

    /**
     * @var string
     */
    #[OA\Property(description: 'The request route')]
    public $url;

    /**
     * @var string
     */
    #[OA\Property(description: 'The request method')]
    public $method;

    /**
     * @var string
     */
    #[OA\Property(description: 'The request start time')]
    public $startTime;

    /**
     * @var string
     */
    #[OA\Property(description: 'The request end time')]
    public $endTime;

    /**
     * @var int
     */
    #[OA\Property(description: 'The request duration')]
    public $duration;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(
        StorageServiceInterface $store,
        LogServiceInterface $log,
        EventDispatcherInterface $dispatcher
    ) {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }
}
