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
use Xibo\Controller\Notification;
use Xibo\Helper\AttachmentUploadHandler;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Exception\GeneralException;

/**
 * A finished attachment upload is staged in storage from the temp folder when a stage callback is given.
 */
class AttachmentUploadHandlerTest extends TestCase
{
    private string $uploadDir;

    /** @var array<array{0: string, 1: string}> stage callback calls: [temp path, name] */
    private array $staged = [];

    protected function setUp(): void
    {
        $this->uploadDir = sys_get_temp_dir() . '/attachment-upload-test-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->uploadDir, 0777, true);
        $_FILES = [];
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['HTTP_CONTENT_DISPOSITION']);
        exec('rm -rf ' . escapeshellarg($this->uploadDir));
    }

    private function handler(?\Closure $stage): AttachmentUploadHandler
    {
        $controller = $this->createMock(Notification::class);
        $controller->method('getLog')->willReturn($this->createMock(LogServiceInterface::class));

        return new AttachmentUploadHandler($this->uploadDir, new NullLogger(), [
            'controller' => $controller,
            'accept_file_types' => '/\.(jpg|jpeg|png|bmp|gif|zip|pdf)$/i',
            'stage_attachment' => $stage,
        ], false);
    }

    private function recordStage(): \Closure
    {
        return function (string $tempPath, string $name) {
            $this->staged[] = [$tempPath, $name];
        };
    }

    /**
     * Calls handleFormData() for an upload already written to the temp folder as $name.
     */
    private function finish(AttachmentUploadHandler $handler, string $name): \stdClass
    {
        file_put_contents($this->uploadDir . $name, 'pdf contents');
        $file = (object)['name' => $name, 'size' => 12, 'type' => 'application/pdf'];
        (new \ReflectionMethod($handler, 'handleFormData'))->invoke($handler, $file, null);
        return $file;
    }

    public function testTheUploadIsStagedFromTheTempFolderUnderItsName()
    {
        $file = $this->finish($this->handler($this->recordStage()), 'a.pdf');

        $this->assertSame([[$this->uploadDir . 'a.pdf', 'a.pdf']], $this->staged);
        $this->assertObjectNotHasProperty('error', $file);
    }

    public function testWithoutAStageCallbackTheUploadStaysInTheTempFolder()
    {
        $file = $this->finish($this->handler(null), 'a.pdf');

        $this->assertFileExists($this->uploadDir . 'a.pdf');
        $this->assertObjectNotHasProperty('error', $file);
    }

    public function testAFailedStageRemovesTheTempFileAndReportsAnError()
    {
        $file = $this->finish($this->handler(function () {
            throw new GeneralException('storage unavailable');
        }), 'a.pdf');

        $this->assertFileDoesNotExist($this->uploadDir . 'a.pdf');
        $this->assertSame('Problem moving uploaded file into the Attachment Folder', $file->error);
    }

    #[RunInSeparateProcess]
    public function testAPostStagesTheUploadUnderItsCleanedName()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_CONTENT_DISPOSITION'] = 'attachment; filename="../my doc.pdf"';
        $_FILES = ['files' => ['tmp_name' => null, 'name' => 'x.pdf', 'size' => 0, 'type' => 'application/pdf',
            'error' => null]];

        ob_start();
        $this->handler($this->recordStage())->post();
        $output = json_decode(ob_get_clean(), true);

        $this->assertSame([[$this->uploadDir . 'my doc.pdf', 'my doc.pdf']], $this->staged);
        $this->assertSame('my doc.pdf', $output['files'][0]['name']);
    }

    #[RunInSeparateProcess]
    public function testAPostOfARefusedFileTypeIsNotStaged()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_FILES = ['files' => ['tmp_name' => null, 'name' => 'run.php', 'size' => 0, 'type' => 'text/plain',
            'error' => null]];

        ob_start();
        $this->handler($this->recordStage())->post();
        $output = json_decode(ob_get_clean(), true);

        $this->assertSame([], $this->staged);
        $this->assertSame('Filetype not allowed', $output['files'][0]['error']);
        $this->assertFileDoesNotExist($this->uploadDir . 'run.php');
    }
}
