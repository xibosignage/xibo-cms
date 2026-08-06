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

namespace Xibo\Tests\Xmds;

use GuzzleHttp\Exception\GuzzleException;
use Xibo\Tests\xmdsTestCase;

class SubmitStatsTest extends XmdsTestCase
{
    use XmdsHelperTrait;

    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * A stat row with an invalid (non-numeric) scheduleid, e.g. scheduleid="undefined", must be
     * skipped rather than causing the whole SubmitStats request to fail with a fatal SOAP error.
     * @return void
     * @throws GuzzleException
     */
    public function testSubmitStatsWithInvalidScheduleId()
    {
        $fromDt = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $toDt = date('Y-m-d H:i:s');

        $request = $this->sendRequest(
            'POST',
            $this->submitStats(
                '7',
                '<stats><stat fromdt="' . $fromDt . '" todt="' . $toDt
                    . '" scheduleid="undefined" layoutid="3336" type="layout"/></stats>'
            ),
            7
        );

        $this->assertStringContainsString(
            '<ns1:SubmitStatsResponse><success xsi:type="xsd:boolean">true</success>',
            $request->getBody()->getContents(),
            'Submit Stats with an invalid scheduleid did not return a success response'
        );
    }
}
