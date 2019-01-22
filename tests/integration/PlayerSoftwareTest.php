<?php
/**
* Copyright (C) 2018 Xibo Signage Ltd
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
namespace Xibo\Tests\integration;

use Xibo\Entity\Display;
use Xibo\Entity\PlayerVersion;
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

    /** @var PlayerVersion */
    protected $version;

    protected $versionId;
    protected $versionId2;

    // <editor-fold desc="Init">
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup test for  ' . get_class() . ' Test');

        // Add a media items
        $this->media = (new XiboLibrary($this->getEntityProvider()))
            ->create(Random::generateString(), PROJECT_ROOT . '/tests/resources/Xibo_for_Android_v1.7_R61.apk');

        // upload second version
        $this->media2 = (new XiboLibrary($this->getEntityProvider()))
            ->create(Random::generateString(), PROJECT_ROOT . '/tests/resources/Xibo_for_Android_v1.8_R108.apk');

        // Get the versions
        $version = $this->getEntityProvider()->get('/playersoftware', ['mediaId' => $this->media->mediaId]);

        foreach ($version as $actualVersion) {
            $this->versionId = $actualVersion['versionId'];
        }

        $version2 = $this->getEntityProvider()->get('/playersoftware', ['mediaId' => $this->media2->mediaId]);

        foreach ($version2 as $actualVersion) {
            $this->versionId2 = $actualVersion['versionId'];
        }

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
            'isDefault' => $this->displayProfile->type,
            'versionMediaId' => $this->media->mediaId
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->getLogger()->debug('Finished Setup');
    }

    public function tearDown()
    {
        $this->getLogger()->debug('Tear Down');

        parent::tearDown();

        // Delete the media we've been working with
        $this->getEntityProvider()->delete('/playersoftware/' . $this->versionId);
        $this->getEntityProvider()->delete('/playersoftware/' . $this->versionId2);
        // Delete the Display
        $this->deleteDisplay($this->display);
        // Delete the Display profile
        $this->displayProfile->delete();
    }
    // </editor-fold>

    public function testVersionFromProfile()
    {
        // Edit display, assign it to the created display profile
        $this->client->put('/display/' . $this->display->displayId, [
            'display' => $this->display->display,
            'licensed' => $this->display->licensed,
            'license' => $this->display->license,
            'defaultLayoutId' => $this->display->defaultLayoutId,
            'displayProfileId' => $this->displayProfile->displayProfileId,
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'] );

        // Check response
        $this->assertSame(200, $this->client->response->status(), $this->client->response->getBody());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $this->assertSame($this->displayProfile->displayProfileId, $object->data->displayProfileId, $this->client->response->getBody());

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

        $this->assertContains($this->media->storedAs, $register, 'Version information not in Register');
        $this->assertContains('61', $register, 'Version information Code not in Register');
        $this->getLogger()->debug($register);
    }

    public function testVersionOverride()
    {
        // Edit display, set the versionMediaId
        $this->client->put('/display/' . $this->display->displayId, [
            'display' => $this->display->display,
            'licensed' => $this->display->licensed,
            'license' => $this->display->license,
            'versionMediaId' => $this->media2->mediaId,
            'defaultLayoutId' => $this->display->defaultLayoutId,
            'displayProfileId' => $this->displayProfile->displayProfileId
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'] );

        // Check response
        $this->assertSame(200, $this->client->response->status(), $this->client->response->getBody());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        $this->assertSame($this->displayProfile->displayProfileId, $object->data->displayProfileId, $this->client->response->getBody());
        $this->assertNotEmpty($object->data->overrideConfig);
        foreach ($object->data->overrideConfig as $override) {
            if ($override->name === 'versionMediaId')
                $this->assertSame($this->media2->mediaId, $override->value, json_encode($object->data->overrideConfig));
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
        $this->assertContains($this->media2->storedAs, $register, 'Version information not in Register');
        $this->assertContains('108', $register, 'Version information Code not in Register');
        $this->getLogger()->debug($register);
    }
}
