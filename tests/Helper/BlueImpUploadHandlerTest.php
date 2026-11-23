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

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Xibo\Helper\BlueImpUploadHandler;

/**
 * Chunked uploads are refused without a local library, as their parts could reach web servers that don't share a temp
 * folder.
 */
class BlueImpUploadHandlerTest extends TestCase
{
    private string $uploadDir;

    protected function setUp(): void
    {
        $this->uploadDir = sys_get_temp_dir() . '/blueimp-test-' . bin2hex(random_bytes(4)) . '/';
        $_SERVER['HTTP_CONTENT_RANGE'] = 'bytes 0-3/8';
        $_SERVER['HTTP_CONTENT_DISPOSITION'] = 'attachment; filename="a.pdf"';
        $_FILES = [];
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_CONTENT_RANGE'], $_SERVER['HTTP_CONTENT_DISPOSITION']);
        @array_map('unlink', glob($this->uploadDir . '*') ?: []);
        @rmdir($this->uploadDir);
    }

    #[RunInSeparateProcess]
    public function testAChunkIsRefusedAndNothingIsWritten()
    {
        $handler = new BlueImpUploadHandler($this->uploadDir, new NullLogger(), [], false);

        ob_start();
        $handler->post();
        $output = json_decode(ob_get_clean(), true);

        $this->assertSame('Chunked uploads are not supported', $output['files'][0]['error']);
        $this->assertDirectoryDoesNotExist($this->uploadDir);
    }

    #[RunInSeparateProcess]
    public function testAChunkIsAcceptedWithALocalLibrary()
    {
        $handler = new BlueImpUploadHandler($this->uploadDir, new NullLogger(), ['accept_chunks' => true], false);

        ob_start();
        $handler->post();
        $output = json_decode(ob_get_clean(), true);

        $this->assertNotSame('Chunked uploads are not supported', $output['files'][0]['error'] ?? null);
    }
}
