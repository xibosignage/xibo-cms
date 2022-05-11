<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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
namespace Xibo\Tests\Helper;

use Xibo\Service\PlayerActionServiceInterface;

/**
 * Class NullPlayerActionService
 * @package Helper
 */
class NullPlayerActionService implements PlayerActionServiceInterface
{
    /** @var \Xibo\Service\LogServiceInterface */
    private $log;

    /**
     * @inheritdoc
     */
    public function __construct($config, $log, $triggerPlayerActions)
    {
        $this->log = $log;
    }

    /**
     * @inheritdoc
     */
    public function sendAction($displays, $action)
    {
        $this->log->debug('NullPlayerActionService: sendAction');
    }

    /**
     * @inheritdoc
     */
    public function getQueue(): array
    {
        $this->log->debug('NullPlayerActionService: getQueue');
        return [];
    }

    /**
     * @inheritdoc
     */
    public function processQueue()
    {
        $this->log->debug('NullPlayerActionService: processQueue');
    }
}
