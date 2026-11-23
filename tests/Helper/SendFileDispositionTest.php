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

use PHPUnit\Framework\TestCase;
use Xibo\Helper\SendFile;

/**
 * Attachment headers carry any file name safely, with an ASCII fallback and an RFC 5987 UTF-8 name.
 */
class SendFileDispositionTest extends TestCase
{
    public function testAnAsciiName()
    {
        $this->assertSame(
            'attachment; filename="report 1.pdf"; filename*=UTF-8\'\'report%201.pdf',
            SendFile::attachmentDisposition('report 1.pdf')
        );
    }

    public function testQuotesBackslashesAndControlCharactersCannotBreakTheHeader()
    {
        $this->assertSame(
            'attachment; filename="a_b_c__d.pdf"; filename*=UTF-8\'\'a%22b%5Cc%0D%0Ad.pdf',
            SendFile::attachmentDisposition("a\"b\\c\r\nd.pdf")
        );
    }

    public function testAUtf8NameHasAnAsciiFallback()
    {
        $this->assertSame(
            'attachment; filename="caf_.pdf"; filename*=UTF-8\'\'caf%C3%A9.pdf',
            SendFile::attachmentDisposition('café.pdf')
        );
    }
}
