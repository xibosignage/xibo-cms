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
