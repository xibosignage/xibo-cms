<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\Tests\Helper;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Xibo\Helper\ConditionalRequest;
use Xibo\Helper\HttpCacheProvider;

class ConditionalRequestTest extends TestCase
{
    private const ETAG = '"abc"';
    private const MODIFIED = 1760000000;

    public function testARequestWithoutConditionsIsModified()
    {
        $this->assertFalse(ConditionalRequest::isNotModified(null, null, self::ETAG, self::MODIFIED));
    }

    public function testAMatchingETagIsNotModified()
    {
        $this->assertTrue(ConditionalRequest::isNotModified('"abc"', null, self::ETAG, self::MODIFIED));
        $this->assertTrue(ConditionalRequest::isNotModified('"x", W/"abc"', null, self::ETAG, self::MODIFIED));
        $this->assertTrue(ConditionalRequest::isNotModified('*', null, self::ETAG, self::MODIFIED));
    }

    public function testADifferentETagIsModifiedWhateverTheDate()
    {
        // If-None-Match takes precedence, so a current If-Modified-Since does not count.
        $this->assertFalse(ConditionalRequest::isNotModified(
            '"other"',
            gmdate('D, d M Y H:i:s', self::MODIFIED) . ' GMT',
            self::ETAG,
            self::MODIFIED
        ));
    }

    public function testWithoutAValidatorOnlyAnyETagMatches()
    {
        $at = gmdate('D, d M Y H:i:s', self::MODIFIED) . ' GMT';

        $this->assertFalse(ConditionalRequest::isNotModified('"abc", ', null, '', self::MODIFIED));
        $this->assertTrue(ConditionalRequest::isNotModified('*', null, '', self::MODIFIED));
        $this->assertFalse(ConditionalRequest::isNotModified(null, $at, self::ETAG, null));
    }

    public function testThePsr7WrapperReadsTheSameHeaders()
    {
        $request = new ServerRequest('GET', '/', ['If-None-Match' => 'W/"abc"']);

        $this->assertTrue(HttpCacheProvider::isNotModified($request, self::ETAG, self::MODIFIED));
        $this->assertFalse(HttpCacheProvider::isNotModified(
            $request->withHeader('If-None-Match', '"other"'),
            self::ETAG,
            self::MODIFIED
        ));
        $this->assertFalse(HttpCacheProvider::isNotModified($request->withoutHeader('If-None-Match'), self::ETAG, 1));
    }

    public function testADateAtOrAfterTheLastModifiedTimeIsNotModified()
    {
        $at = gmdate('D, d M Y H:i:s', self::MODIFIED) . ' GMT';
        $before = gmdate('D, d M Y H:i:s', self::MODIFIED - 1) . ' GMT';

        $this->assertTrue(ConditionalRequest::isNotModified(null, $at, self::ETAG, self::MODIFIED));
        $this->assertFalse(ConditionalRequest::isNotModified(null, $before, self::ETAG, self::MODIFIED));
        $this->assertFalse(ConditionalRequest::isNotModified(null, 'not a date', self::ETAG, self::MODIFIED));
    }
}
