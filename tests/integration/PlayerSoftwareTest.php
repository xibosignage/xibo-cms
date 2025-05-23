<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
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
namespace Xibo\Tests\integration;

use Xibo\Entity\Display;
use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboDisplayProfile;
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class PlayerSoftwareTest
 * @package Xibo\Tests\integration
 */
class PlayerSoftwareTest extends LocalWebTestCase
{
    use DisplayHelperTrait;

    /** @var XiboLibrary */
    protected $media;

    /** @var XiboLibrary */
    protected $media2;

    /** @var XiboDisplay */
    protected $display;

    /** @var XiboDisplayProfile */
    protected $displayProfile;

    protected $version;
    protected $version2;

    // <editor-fold desc="Init">
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup test for  ' . get_class($this) . ' Test');

        // Upload version files
        $uploadVersion = $this->uploadVersionFile(Random::generateString(), PROJECT_ROOT . '/tests/resources/Xibo_for_Android_v1.7_R61.apk');
        $this->version = $uploadVersion['files'][0];
        $uploadVersion2 = $this->uploadVersionFile(Random::generateString(), PROJECT_ROOT . '/tests/resources/Xibo_for_Android_v1.8_R108.apk');
        $this->version2 = $uploadVersion2['files'][0];

        // Create a Display
        $this->display = $this->createDisplay(null, 'android');

        $this->displaySetStatus($this->display, Display::$STATUS_DONE);
        $this->displaySetLicensed($this->display);

        // Create a display profile
        $this->displayProfile = (new XiboDisplayProfile($this->getEntityProvider()))->create(Random::generateString(), 'android', 0);

        // Edit display profile to add the uploaded apk to the config
        $this->getEntityProvider()->put('/displayprofile/' . $this->displayProfile->displayProfileId, [
            'name' => $this->displayProfile->name,
            'type' => $this->displayProfile->type,
            'isDefault' => $this->displayProfile->isDefault,
            'versionMediaId' => $this->version['id']
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->getLogger()->debug('Finished Setup');
    }

    public function tearDown()
    {
        $this->getLogger()->debug('Tear Down');

        // Delete the version files we've been working with
        $this->getEntityProvider()->delete('/playersoftware/' . $this->version['id']);
        $this->getEntityProvider()->delete('/playersoftware/' . $this->version2['id']);
        // Delete the Display
        $this->deleteDisplay($this->display);
        // Delete the Display profile
        $this->displayProfile->delete();

        parent::tearDown();
    }
    // </editor-fold>

    public function testVersionFromProfile()
    {
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_DONE), 'Display Status isnt as expected');

        // Edit display, assign it to the created display profile
        $response = $this->sendRequest('PUT','/display/' . $this->display->displayId, [
            'display' => $this->display->display,
            'licensed' => $this->display->licensed,
            'license' => $this->display->license,
            'defaultLayoutId' => $this->display->defaultLayoutId,
            'displayProfileId' => $this->displayProfile->displayProfileId,
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'] );

        // Check response
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $this->assertNotEmpty($response->getBody());
        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertSame($this->displayProfile->displayProfileId, $object->data->displayProfileId, $response->getBody());

        // Ensure the Version Instructions are present on the Register Display call and that
        // Register our display
        $register = $this->getXmdsWrapper()->RegisterDisplay($this->display->license,
            $this->display->license,
            'android',
            null,
            null,
            null,
            '00:16:D9:C9:AL:69',
            $this->display->xmrChannel,
            $this->display->xmrPubKey
        );

        $this->getLogger()->debug($register);

        $this->assertContains($this->version['fileName'], $register, 'Version information not in Register');
        $this->assertContains('61', $register, 'Version information Code not in Register');
    }

    public function testVersionOverride()
    {
        $this->assertTrue($this->displayStatusEquals($this->display, Display::$STATUS_DONE), 'Display Status isnt as expected');

        // Edit display, set the versionMediaId
        $response = $this->sendRequest('PUT','/display/' . $this->display->displayId, [
            'display' => $this->display->display,
            'licensed' => $this->display->licensed,
            'license' => $this->display->license,
            'versionMediaId' => $this->version2['id'],
            'defaultLayoutId' => $this->display->defaultLayoutId,
            'displayProfileId' => $this->displayProfile->displayProfileId
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'] );

        // Check response
        $this->assertSame(200, $response->getStatusCode(), $response->getBody());
        $this->assertNotEmpty($response->getBody());

        $object = json_decode($response->getBody());
        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertSame($this->displayProfile->displayProfileId, $object->data->displayProfileId, $response->getBody());
        $this->assertNotEmpty($object->data->overrideConfig);

        foreach ($object->data->overrideConfig as $override) {
            if ($override->name === 'versionMediaId') {
                $this->assertSame($this->version2['id'], $override->value, json_encode($object->data->overrideConfig));
            }
        }

        // call register
        $register = $this->getXmdsWrapper()->RegisterDisplay($this->display->license,
            $this->display->license,
            'android',
            null,
            null,
            null,
            '00:16:D9:C9:AL:69',
            $this->display->xmrChannel,
            $this->display->xmrPubKey
        );
        // make sure the media ID set on the display itself is in the register
        $this->getLogger()->debug($register);
        $this->assertContains($this->version2['fileName'], $register, 'Version information not in Register');
        $this->assertContains('108', $register, 'Version information Code not in Register');
    }

    private function uploadVersionFile($fileName, $filePath)
    {
        $payload = [
            [
                'name' => 'name',
                'contents' => $fileName
            ],
            [
                'name' => 'files',
                'contents' => fopen($filePath, 'r')
            ]
        ];

        return $this->getEntityProvider()->post('/playersoftware', ['multipart' => $payload]);
    }
}
