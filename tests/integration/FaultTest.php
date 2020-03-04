<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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

namespace Xibo\Tests\Integration;

/**
 * Class FaultTest
 * @package Xibo\Tests\Integration
 */
class FaultTest extends \Xibo\Tests\LocalWebTestCase
{
    /**
     * Collect data
     * This test modifies headers and we therefore need to run in a separate process
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCollect()
    {
        $response = $this->sendRequest('GET','/fault/collect', ['outputLog' => 'on']);
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * test turning debug on
     */
    public function testDebugOn()
    {
        $response = $this->sendRequest('PUT','/fault/debug/on');
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * test turning debug off
     */
    public function testDebugOff()
    {
        $response = $this->sendRequest('PUT','/fault/debug/off');
        $this->assertSame(200, $response->getStatusCode());
    }
}
