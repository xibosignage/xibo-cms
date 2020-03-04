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

class AuditLogTest extends \Xibo\Tests\LocalWebTestCase
{
    public function testSearch()
    {
        $response = $this->sendRequest('GET','/audit');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());

        $object = json_decode($response->getBody());

        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertObjectHasAttribute('data', $object->data, $response->getBody());
        $this->assertObjectHasAttribute('draw', $object->data, $response->getBody());
        $this->assertObjectHasAttribute('recordsTotal', $object->data, $response->getBody());
        $this->assertObjectHasAttribute('recordsFiltered', $object->data, $response->getBody());

        // Make sure the recordsTotal is not greater than 10 (the default paging)
        $this->assertLessThanOrEqual(10, count($object->data->data));
    }

    public function testExportForm()
    {
        $response = $this->sendRequest('GET','/audit/form/export');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response);
    }

    /**
     * // TODO outouts the response to browser/system output.
     * @group broken
     */
    public function testExport()
    {
        $response = $this->sendRequest('GET','/audit/export', [
            'filterFromDt' => date('Y-m-d H:i:s', time() - 86400),
            'filterToDt' => date('Y-m-d H:i:s', time())
        ]);
        $this->assertSame(200, $response->getStatusCode());
    }
}
