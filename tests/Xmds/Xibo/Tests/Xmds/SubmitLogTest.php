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

namespace Xibo\Tests\Xmds;

use GuzzleHttp\Exception\GuzzleException;
use Xibo\Tests\xmdsTestCase;

class SubmitLogTest extends XmdsTestCase
{
    use XmdsHelperTrait;

    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Submit log with category event
     * @return void
     * @throws GuzzleException
     */
    public function testSubmitEventLog()
    {
        $request = $this->sendRequest(
            'POST',
            $this->submitEventLog('7'),
            7
        );

        $this->assertStringContainsString(
            '<ns1:SubmitLogResponse><success xsi:type="xsd:boolean">true</success>',
            $request->getBody()->getContents(),
            'Submit Log received incorrect response'
        );
    }
}
