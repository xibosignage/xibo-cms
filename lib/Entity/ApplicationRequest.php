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

namespace Xibo\Entity;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

class ApplicationRequest implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The request ID")
     * @var int
     */
    public $requestId;

    /**
     * @SWG\Property(description="The user ID")
     * @var int
     */
    public $userId;

    /**
     * @SWG\Property(description="The application ID")
     * @var string
     */
    public $applicationId;

    /**
     * @SWG\Property(description="The request route")
     * @var string
     */
    public $url;

    /**
     * @SWG\Property(description="The request method")
     * @var string
     */
    public $method;

    /**
     * @SWG\Property(description="The request start time")
     * @var string
     */
    public $startTime;

    /**
     * @SWG\Property(description="The request end time")
     * @var string
     */
    public $endTime;

    /**
     * @SWG\Property(description="The request duration")
     * @var int
     */
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
