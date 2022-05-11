<?php
/**
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
namespace Xibo\Tests\Helper;

use Xibo\Service\PlayerActionServiceInterface;

/**
 * Class MockPlayerActionService
 * @package Helper
 */
class MockPlayerActionService implements PlayerActionServiceInterface
{
    /** @var \Xibo\Service\LogServiceInterface */
    private $log;

    private $displays = [];

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
        $this->log->debug('MockPlayerActionService: sendAction');

        if (!is_array($displays)) {
            $displays = [$displays];
        }

        foreach ($displays as $display) {
            $this->displays[] = $display->displayId;
        }
    }

    /**
     * @inheritdoc
     */
    public function getQueue(): array
    {
        $this->log->debug('MockPlayerActionService: getQueue');
        return $this->displays;
    }

    /**
     * @inheritdoc
     */
    public function processQueue()
    {
        $this->log->debug('MockPlayerActionService: processQueue');
    }
}