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
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;


/**
 * Class Transition
 * @package Xibo\Entity
 */
#[OA\Schema]
class Transition
{
    use EntityTrait;

    /**
     * @var int
     */
    #[OA\Property(description: 'The transition ID')]
    public $transitionId;

    /**
     * @var string
     */
    #[OA\Property(description: 'The transition name')]
    public $transition;

    /**
     * @var string
     */
    #[OA\Property(description: 'Code for transition')]
    public $code;

    /**
     * @var int
     */
    #[OA\Property(description: 'Flag indicating whether this is a directional transition')]
    public $hasDirection;

    /**
     * @var int
     */
    #[OA\Property(description: 'Flag indicating whether this transition has a duration option')]
    public $hasDuration;

    /**
     * @var int
     */
    #[OA\Property(description: 'Flag indicating whether this transition should be available for IN assignments')]
    public $availableAsIn;

    /**
     * @var int
     */
    #[OA\Property(description: 'Flag indicating whether this transition should be available for OUT assignments')]
    public $availableAsOut;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct($store, $log, $dispatcher)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    public function getId()
    {
        return $this->transitionId;
    }

    public function getOwnerId()
    {
        return 1;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function save()
    {
        if ($this->transitionId == null || $this->transitionId == 0) {
            throw new InvalidArgumentException();
        }

        $this->getStore()->update('
            UPDATE `transition` SET AvailableAsIn = :availableAsIn, AvailableAsOut = :availableAsOut WHERE transitionID = :transitionId
        ', [
            'availableAsIn' => $this->availableAsIn,
            'availableAsOut' => $this->availableAsOut,
            'transitionId' => $this->transitionId
        ]);
    }
}