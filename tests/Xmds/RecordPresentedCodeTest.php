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

namespace Xibo\Tests\Xmds;

use PHPUnit\Framework\TestCase;
use Stash\Driver\Ephemeral;
use Stash\Pool;
use Xibo\Entity\Display;
use Xibo\Xmds\Soap;

/**
 * A known display only claims an Add Display code when the code it presents changes, so an old code
 * it resends on every collect can't take over the same code issued again later.
 */
class RecordPresentedCodeTest extends TestCase
{
    private Soap $soap;
    private Display $display;

    protected function setUp(): void
    {
        $pool = new Pool(new Ephemeral());

        // Only the cache is needed, so the rest of the XMDS service is left unbuilt.
        $this->soap = new class ($pool) extends Soap {
            public function __construct($pool)
            {
                $this->pool = $pool;
            }

            public function record(Display $display, ?string $authCode): bool
            {
                return $this->recordPresentedCode($display, $authCode);
            }
        };

        $this->display = (new \ReflectionClass(Display::class))->newInstanceWithoutConstructor();
        $this->display->displayId = 5;
    }

    public function testDisplayWithNoRecordDoesNotClaim()
    {
        $this->assertFalse($this->soap->record($this->display, 'K7QX'));
    }

    public function testSameCodeSentAgainDoesNotClaim()
    {
        $this->soap->record($this->display, 'K7QX');

        $this->assertFalse($this->soap->record($this->display, 'K7QX'));
        $this->assertFalse($this->soap->record($this->display, 'k7qx'));
    }

    public function testDifferentCodeClaims()
    {
        $this->soap->record($this->display, 'K7QX');

        $this->assertTrue($this->soap->record($this->display, 'AB12'));
        // Recorded, so resending it on the next collect doesn't claim again.
        $this->assertFalse($this->soap->record($this->display, 'AB12'));
    }

    public function testChangingToEmptyCodeDoesNotClaim()
    {
        $this->soap->record($this->display, 'K7QX');

        $this->assertFalse($this->soap->record($this->display, ''));
        $this->assertFalse($this->soap->record($this->display, null));
        // The empty code was recorded, so a code presented after it counts as new.
        $this->assertTrue($this->soap->record($this->display, 'AB12'));
    }
}
