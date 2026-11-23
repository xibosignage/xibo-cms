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
use Xibo\Helper\Environment;

class EnvironmentTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'xibo-version-');
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testReadsStampedVersion()
    {
        // As written by the Dockerfile: echo adds a trailing newline.
        file_put_contents($this->path, "2026.09.01\n");

        $this->assertSame('2026.09.01', Environment::readVersionFile($this->path));
    }

    public function testMissingFileFallsBackToVersionName()
    {
        unlink($this->path);

        $this->assertSame(Environment::$WEBSITE_VERSION_NAME, Environment::readVersionFile($this->path));
    }

    public function testEmptyFileFallsBackToVersionName()
    {
        file_put_contents($this->path, "  \n");

        $this->assertSame(Environment::$WEBSITE_VERSION_NAME, Environment::readVersionFile($this->path));
    }

    public function testDirectoryFallsBackToVersionName()
    {
        $this->assertSame(Environment::$WEBSITE_VERSION_NAME, Environment::readVersionFile(sys_get_temp_dir()));
    }
}
