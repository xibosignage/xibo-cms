<?php
/**
 * Copyright (C) 2022 Xibo Signage Ltd
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

use Xibo\Entity\Display;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\LocalWebTestCase;

class FontTest extends LocalWebTestCase
{
    use DisplayHelperTrait;

    private $testFileName = 'PHPUNIT FONT TEST';
    private $testFilePath = PROJECT_ROOT . '/tests/resources/UglyTypist.ttf';
    protected $startFonts;
    // TODO create api wrapper for fonts :)

    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();
        $this->startFonts = $this->getEntityProvider()->get('/fonts', ['start' => 0, 'length' => 10000]);
    }
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all media files that weren't there initially
        $finalFonts = $this->getEntityProvider()->get('/fonts', ['start' => 0, 'length' => 10000]);
        # Loop over any remaining font files and nuke them
        foreach ($finalFonts as $font) {
            $flag = true;
            foreach ($this->startFonts as $startFont) {
                if ($startFont['id'] == $font['id']) {
                    $flag = false;
                }
            }
            if ($flag) {
                try {
                    $this->getEntityProvider()->delete('/fonts/'.$font['id'].'/delete');
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete font ' . $font['id'] . '. E:' . $e->getMessage());
                }
            }
        }
        parent::tearDown();
    }

    /**
     * List all file in library
     */
    public function testListAll()
    {
        # Get all library items
        $response = $this->sendRequest('GET', '/fonts');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        // we expect fonts distributed with CMS to be there.
        $this->assertNotEmpty($object->data->data);
    }

    public function testUpload()
    {
        $uploadResponse = $this->uploadFontFile();
        $uploadedFileObject = $uploadResponse['files'][0];
        $this->assertNotEmpty($uploadedFileObject);
        $this->assertSame(filesize($this->testFilePath), $uploadedFileObject['size']);
        $this->assertSame(basename($this->testFilePath), $uploadedFileObject['fileName']);

        $this->getLogger()->debug(
            'Uploaded font ' . $uploadedFileObject['name'] .
            ' with ID ' . $uploadedFileObject['id'] .
            ' Stored as ' . $uploadedFileObject['fileName']
        );

        $fontRecord = $this->getEntityProvider()->get('/fonts', ['name' => $this->testFileName])[0];
        $this->assertNotEmpty($fontRecord);
        $this->assertSame(filesize($this->testFilePath), $fontRecord['size']);
        $this->assertSame(basename($this->testFilePath), $fontRecord['fileName']);
    }

    public function testDelete()
    {
        $upload = $this->uploadFontFile();

        $response = $this->sendRequest('DELETE', '/fonts/' . $upload['files'][0]['id']. '/delete');

        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
    }

    public function testFontDependencies()
    {
        $upload = $this->uploadFontFile();
        $size = $upload['files'][0]['size'];
        $md5 = $upload['files'][0]['md5'];

        // Create a Display
        $display = $this->createDisplay();
        $this->displaySetStatus($display, Display::$STATUS_DONE);
        $this->displaySetLicensed($display);

        // Call Required Files
        $rf = $this->getXmdsWrapper()->RequiredFiles($display->license);

        $this->assertContains('file download="http" size="'.$size.'" md5="'.$md5.'" saveAs="'.basename($this->testFilePath).'" type="dependency" fileType="font" ', $rf, 'Font not in Required Files');

        // Delete the Display
        $this->deleteDisplay($display);
    }

    public function testFontCss()
    {
        $fontCssPath = PROJECT_ROOT . '/library/fonts/fonts.css';

        // upload file, this should also update fonts.css file
        $this->uploadFontFile();
        // read css file
        $fontsCss = file_get_contents($fontCssPath);

        // get the record
        $fontRecord = $this->getEntityProvider()->get('/fonts', ['name' => $this->testFileName])[0];

        // check if the uploaded font was added to player fonts.css file.
        $this->assertContains('font-family: \''.$fontRecord['familyName'].'\';', $fontsCss, 'Font not in fonts.css');
        $this->assertContains('src: url(\''.basename($this->testFilePath).'\');', $fontsCss, 'Font not in fonts.css');
    }

    private function uploadFontFile()
    {
        $payload = [
            [
                'name' => 'name',
                'contents' => $this->testFileName
            ],
            [
                'name' => 'files',
                'contents' => fopen($this->testFilePath, 'r')
            ]
        ];

        return $this->getEntityProvider()->post('/fonts', ['multipart' => $payload]);
    }
}
