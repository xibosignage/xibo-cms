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


namespace Xibo\Helper;

/**
 * Class UserLogProcessor
 * @package Xibo\Helper
 */
class UserLogProcessor
{
    /**
     * UserLogProcessor
     * @param int $userId
     * @param int|null $sessionHistoryId
     * @param int|null $requestId
     */
    public function __construct(
        private readonly int $userId,
        private readonly ?int $sessionHistoryId,
        private readonly ?int $requestId
    ) {
    }

    /**
     * @param array $record
     * @return array
     */
    public function __invoke(array $record): array
    {
        $record['extra']['userId'] = $this->userId;
        
        if ($this->sessionHistoryId != null) {
            $record['extra']['sessionHistoryId'] = $this->sessionHistoryId;
        }

        if ($this->requestId != null) {
            $record['extra']['requestId'] = $this->requestId;
        }

        return $record;
    }
}
