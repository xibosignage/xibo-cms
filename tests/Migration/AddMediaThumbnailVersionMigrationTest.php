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

namespace Xibo\Tests\Migration;

use Phinx\Db\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class AddMediaThumbnailVersionMigrationTest extends TestCase
{
    private string $library;

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../db/migrations/20261014130000_add_media_thumbnail_version_migration.php';
    }

    protected function setUp(): void
    {
        $this->library = sys_get_temp_dir() . '/' . uniqid('library_', true) . '/';
        mkdir($this->library);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->library . '*'));
        rmdir($this->library);
    }

    public function testAnOldVideoThumbnailIsKeptUnderItsCoverVersion()
    {
        // 1 has a cover and an old thumbnail, 2 a cover only, 3 neither.
        file_put_contents($this->library . '1_videocover.png', 'cover 1');
        file_put_contents($this->library . 'tn_1_videocover.png', 'thumbnail 1');
        file_put_contents($this->library . '2_videocover.png', 'cover 2');

        $executed = $this->recordVideoCovers([1, 2, 3]);

        $this->assertSame([
            'UPDATE `media` SET `thumbnailVersion` = \'' . md5('cover 1') . '\' WHERE `mediaId` = 1',
            'UPDATE `media` SET `thumbnailVersion` = \'' . md5('cover 2') . '\' WHERE `mediaId` = 2',
        ], $executed);
        $this->assertSame('thumbnail 1', file_get_contents($this->library . 'tn_1_videocover.png_' . md5('cover 1')));
        $this->assertFileExists($this->library . 'tn_1_videocover.png', 'nothing is deleted');
        $this->assertSame([], glob($this->library . 'tn_2_*'), 'no thumbnail is made');
        $this->assertSame([], glob($this->library . 'tn_3_*'));
    }

    public function testALibraryWithNoneOfTheVideosStopsTheMigration()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('set XIBO_MIGRATION_SKIP_LIBRARY_CHECK=1 to continue');
        $this->checkLibraryHolds([['mediaId' => 1, 'storedAs' => '1.mp4'], ['mediaId' => 2, 'storedAs' => '2.mp4']]);
    }

    public function testTheLibraryCheckCanBeSkipped()
    {
        $output = $this->checkLibraryHolds([['mediaId' => 1, 'storedAs' => '1.mp4']], true);

        $this->assertStringContainsString('Continuing without the old video and image thumbnails.', $output);
    }

    public function testAnImageFileIsEnough()
    {
        file_put_contents($this->library . '3.png', 'image 3');

        $this->checkLibraryHolds([['mediaId' => 1, 'storedAs' => '1.mp4'], ['mediaId' => 3, 'storedAs' => '3.png']]);
        $this->addToAssertionCount(1);
    }

    public function testAnOldImageThumbnailOfTheCurrentSizeIsKeptUnderTheImageMd5()
    {
        // 1 has a 320 thumbnail, 2 an old smaller one, 3 none, 4 a bad md5.
        $md5 = md5('image 1');
        $this->png($this->library . 'tn_1.png', 320, 180);
        $this->png($this->library . 'tn_2.png', 120, 80);
        $this->png($this->library . 'tn_4.png', 320, 320);

        $executed = $this->recordImageThumbnails([
            ['mediaId' => 1, 'storedAs' => '1.png', 'md5' => $md5],
            ['mediaId' => 2, 'storedAs' => '2.png', 'md5' => md5('image 2')],
            ['mediaId' => 3, 'storedAs' => '3.png', 'md5' => md5('image 3')],
            ['mediaId' => 4, 'storedAs' => '4.png', 'md5' => 'not an md5'],
        ]);

        $this->assertSame(['UPDATE `media` SET `thumbnailVersion` = \'' . $md5 . '\' WHERE `mediaId` = 1'], $executed);
        $this->assertFileEquals($this->library . 'tn_1.png', $this->library . 'tn_1.png_' . $md5);
        $this->assertFileExists($this->library . 'tn_1.png', 'nothing is deleted');
        $this->assertSame([], glob($this->library . 'tn_2.png_*'), 'a thumbnail of another size is remade later');
        $this->assertSame([], glob($this->library . 'tn_4.png_*'));
    }

    public function testAVideoWithoutACoverIsEnough()
    {
        file_put_contents($this->library . '2.mp4', 'video 2');

        $this->checkLibraryHolds([['mediaId' => 1, 'storedAs' => '1.mp4'], ['mediaId' => 2, 'storedAs' => '2.mp4']]);
        $this->addToAssertionCount(1);
    }

    public function testNoVideosNeedNoLibrary()
    {
        $this->checkLibraryHolds([]);
        $this->addToAssertionCount(1);
    }

    private function checkLibraryHolds(array $media, bool $skip = false): string
    {
        $output = new BufferedOutput();
        $migration = new \AddMediaThumbnailVersionMigration('test', 20261014130000, null, $output);
        (new \ReflectionMethod($migration, 'checkLibraryHolds'))->invoke($migration, $this->library, $media, $skip);
        return $output->fetch();
    }

    private function png(string $path, int $width, int $height): void
    {
        imagepng(imagecreatetruecolor($width, $height), $path);
    }

    /**
     * @return string[] the SQL executed
     */
    private function recordImageThumbnails(array $images): array
    {
        $executed = [];
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('execute')->willReturnCallback(function (string $sql) use (&$executed) {
            $executed[] = $sql;
            return 1;
        });

        $migration = new \AddMediaThumbnailVersionMigration('test', 20261014130000, null, new BufferedOutput());
        $migration->setAdapter($adapter);
        (new \ReflectionMethod($migration, 'recordImageThumbnails'))->invoke($migration, $this->library, $images);

        return $executed;
    }

    /**
     * @param int[] $mediaIds the videos
     * @return string[] the SQL executed
     */
    private function recordVideoCovers(array $mediaIds): array
    {
        $executed = [];
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('fetchAll')->willReturn(array_map(fn ($id) => ['mediaId' => $id], $mediaIds));
        $adapter->method('execute')->willReturnCallback(function (string $sql) use (&$executed) {
            $executed[] = $sql;
            return 1;
        });

        $migration = new \AddMediaThumbnailVersionMigration('test', 20261014130000, null, new BufferedOutput());
        $migration->setAdapter($adapter);
        (new \ReflectionMethod($migration, 'recordVideoCovers'))->invoke($migration, $this->library);

        return $executed;
    }
}
