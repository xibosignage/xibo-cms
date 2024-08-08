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

namespace Xibo\XMR;

/**
 * Class ScheduleCriteriaUpdateAction
 * @package Xibo\XMR
 */
class ScheduleCriteriaUpdateAction extends PlayerAction
{
    /**
     * @var array
     */
    public $criteriaUpdates = [];

    public function __construct()
    {
        $this->setQos(10);
    }

    /**
     * Set criteria updates
     * @param array $criteriaUpdates an array of criteria updates
     * @return $this
     */
    public function setCriteriaUpdates(array $criteriaUpdates)
    {
        $this->criteriaUpdates = $criteriaUpdates;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getMessage(): string
    {
        $this->action = 'criteriaUpdate';

        // Ensure criteriaUpdates array is not empty
        if (empty($this->criteriaUpdates)) {
            // Throw an exception if criteriaUpdates is not provided
            throw new PlayerActionException(__('Criteria updates not provided.'));
        }

        return $this->serializeToJson(['criteriaUpdates']);
    }
}
